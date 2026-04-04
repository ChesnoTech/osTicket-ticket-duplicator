<?php
/**
 * Ticket Duplicator Plugin - Auto-Updater
 *
 * Handles version checking against GitHub, file/DB backup, and
 * downloading + installing the latest release.
 *
 * @author  ChesnoTech
 * @version 1.0.0
 */

class TicketDuplicatorUpdater {

    const GITHUB_USER   = 'ChesnoTech';
    const GITHUB_REPO   = 'osTicket-ticket-duplicator';
    const GITHUB_BRANCH = 'master';

    // ── Version helpers ──────────────────────────────────────────────────────

    static function getLocalVersion() {
        // Read via file_get_contents + regex (not include) to avoid
        // PHP opcode cache returning stale data after a live update.
        $file = dirname(__FILE__) . '/plugin.php';
        $content = @file_get_contents($file);
        if ($content && preg_match("/'version'\s*=>\s*'([^']+)'/", $content, $m))
            return $m[1];
        return '0.0.0';
    }

    static function getRemoteVersion() {
        // Use GitHub API (no CDN caching) to get plugin.php contents
        $url = 'https://api.github.com/repos/'
             . self::GITHUB_USER . '/' . self::GITHUB_REPO
             . '/contents/plugin.php?ref=' . self::GITHUB_BRANCH;
        $json = self::curlGet($url);
        if (!$json) return false;

        $data = @json_decode($json, true);
        if (!$data || empty($data['content'])) return false;

        $content = @base64_decode($data['content']);
        if (!$content) return false;

        if (preg_match("/'version'\s*=>\s*'([^']+)'/", $content, $m))
            return $m[1];
        return false;
    }

    /**
     * Returns array:
     *   available (bool), local (string), remote (string|false), error (string|null)
     */
    static function checkUpdate() {
        $local  = self::getLocalVersion();
        $remote = self::getRemoteVersion();

        if ($remote === false) {
            return array(
                'available' => false,
                'local'     => $local,
                'remote'    => false,
                'error'     => /* trans */ 'Could not reach GitHub to check for updates',
            );
        }

        return array(
            'available' => version_compare($remote, $local, '>'),
            'local'     => $local,
            'remote'    => $remote,
            'error'     => null,
        );
    }

    // ── Backup ───────────────────────────────────────────────────────────────

    /**
     * Backup plugin files to a timestamped directory.
     * Returns array: success (bool), path (string), error (string)
     */
    static function backupFiles() {
        $src     = dirname(__FILE__);
        $baseDir = self::getBackupBaseDir();
        $dest    = $baseDir . '/files-' . date('Ymd-His');

        if (!self::copyDir($src, $dest))
            return array('success' => false, 'error' => /* trans */ 'Could not copy plugin directory to backup location');

        return array('success' => true, 'path' => $dest);
    }

    /**
     * Backup plugin DB config rows to a SQL file.
     * Returns array: success (bool), path (string), error (string)
     */
    static function backupDatabase() {
        $baseDir = self::getBackupBaseDir();
        $file    = $baseDir . '/db-' . date('Ymd-His') . '.sql';

        $lines = array(
            '-- Ticket Duplicator database backup',
            '-- Generated: ' . date('Y-m-d H:i:s'),
            '-- Restore: mysql -u USER -p DATABASE < this_file.sql',
            '',
        );

        // Capture all plugin config (all instances across all plugins — small table)
        $res = db_query("SELECT * FROM " . TABLE_PREFIX . "config WHERE namespace LIKE 'plugin.%'");
        if ($res) {
            while ($row = db_fetch_array($res)) {
                $ns  = addslashes($row['namespace']);
                $key = addslashes($row['key']);
                $val = addslashes($row['value']);
                $lines[] = "REPLACE INTO `" . TABLE_PREFIX . "config`"
                         . " (`namespace`,`key`,`value`)"
                         . " VALUES ('$ns','$key','$val');";
            }
        }

        if (!file_put_contents($file, implode("\n", $lines)))
            return array('success' => false, 'error' => /* trans */ 'Cannot write database backup file');

        return array('success' => true, 'path' => $file);
    }

    // ── Install ──────────────────────────────────────────────────────────────

    /**
     * Backup then download + install the latest version from GitHub.
     * Returns array: success (bool), backup_files (string), backup_db (string), error (string)
     */
    static function downloadAndInstall() {
        // 1. Backup files (required — abort if it fails)
        $fileBackup = self::backupFiles();
        if (!$fileBackup['success'])
            return array(
                'success' => false,
                'error'   => /* trans */ 'File backup failed: ' . $fileBackup['error'],
            );

        // 2. Backup database (non-fatal — continue even if it fails)
        $dbBackup = self::backupDatabase();

        // 3. Check ZipArchive is available
        if (!class_exists('ZipArchive'))
            return array(
                'success'      => false,
                'error'        => /* trans */ 'PHP ZipArchive extension is required but not available',
                'backup_files' => $fileBackup['path'],
            );

        // 4. Download ZIP from GitHub
        $zipUrl  = 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO
                 . '/archive/refs/heads/' . self::GITHUB_BRANCH . '.zip';
        $zipData = self::curlGet($zipUrl);
        if (!$zipData)
            return array(
                'success'      => false,
                'error'        => /* trans */ 'Failed to download update from GitHub',
                'backup_files' => $fileBackup['path'],
            );

        // 5. Write ZIP to temp file
        $tmpZip = sys_get_temp_dir() . '/td-update-' . time() . '.zip';
        if (!file_put_contents($tmpZip, $zipData))
            return array(
                'success'      => false,
                'error'        => /* trans */ 'Cannot write temporary ZIP file',
                'backup_files' => $fileBackup['path'],
            );

        // 6. Extract and overwrite
        $result = self::extractAndOverwrite($tmpZip);
        @unlink($tmpZip);

        if (!$result['success'])
            return array_merge($result, array(
                'backup_files' => $fileBackup['path'],
                'backup_db'    => isset($dbBackup['path']) ? $dbBackup['path'] : null,
            ));

        return array(
            'success'      => true,
            'backup_files' => $fileBackup['path'],
            'backup_db'    => isset($dbBackup['path']) ? $dbBackup['path'] : null,
        );
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private static function extractAndOverwrite($zipPath) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true)
            return array('success' => false, 'error' => /* trans */ 'Cannot open downloaded ZIP file');

        $pluginDir  = realpath(dirname(__FILE__));
        // GitHub names the top-level folder: repo-branch/
        $prefix     = self::GITHUB_REPO . '-' . self::GITHUB_BRANCH . '/';
        $prefixLen  = strlen($prefix);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            // Skip the root folder entry itself
            if ($name === $prefix || substr($name, 0, $prefixLen) !== $prefix)
                continue;

            $relative = substr($name, $prefixLen);
            if ($relative === '') continue;

            // Safety: ensure final path stays inside the plugin directory
            $outPath = $pluginDir . DIRECTORY_SEPARATOR
                     . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $realOut = realpath(dirname($outPath));
            if ($realOut === false || strpos($realOut, $pluginDir) !== 0)
                continue;

            if (substr($name, -1) === '/') {
                // Directory entry
                if (!is_dir($outPath)) @mkdir($outPath, 0755, true);
            } else {
                $dir = dirname($outPath);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                if (file_put_contents($outPath, $zip->getFromIndex($i)) === false) {
                    $zip->close();
                    return array('success' => false,
                        'error' => /* trans */ 'Cannot write file: ' . $relative
                            . ' — check directory permissions (owner must be www-data)');
                }
            }
        }

        $zip->close();
        return array('success' => true);
    }

    private static function getBackupBaseDir() {
        $dir = INCLUDE_DIR . 'plugins/td-backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
            // Prevent direct web access to backup files
            @file_put_contents($dir . '/.htaccess', "Deny from all\n");
        }
        return $dir;
    }

    private static function curlGet($url) {
        if (!function_exists('curl_init'))
            return false;

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'osTicket-TicketDuplicator-Updater/1.0',
        ));

        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_errno($ch);
        curl_close($ch);

        if ($err || $code !== 200) return false;
        return $data;
    }

    private static function copyDir($src, $dst) {
        if (!is_dir($dst) && !@mkdir($dst, 0755, true))
            return false;

        $handle = opendir($src);
        if (!$handle) return false;

        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') continue;
            $srcPath = $src . DIRECTORY_SEPARATOR . $entry;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($srcPath)) {
                if (!self::copyDir($srcPath, $dstPath)) {
                    closedir($handle);
                    return false;
                }
            } else {
                if (!@copy($srcPath, $dstPath)) {
                    closedir($handle);
                    return false;
                }
            }
        }
        closedir($handle);
        return true;
    }
}
