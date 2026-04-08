<?php
/**
 * Ticket Duplicator Plugin - Auto-Updater
 *
 * Handles version checking against GitHub Releases API, file/DB backup,
 * and downloading + installing specific minor or major releases.
 *
 * @author  ChesnoTech
 * @version 2.0.0
 */

class TicketDuplicatorUpdater {

    const GITHUB_USER   = 'ChesnoTech';
    const GITHUB_REPO   = 'osTicket-ticket-duplicator';
    const GITHUB_BRANCH = 'master';
    const CHECK_CACHE_TTL = 900; // 15 minutes

    // ── Version helpers ──────────────────────────────────────────────────────

    static function getLocalVersion() {
        $file = dirname(__FILE__) . '/plugin.php';
        $content = @file_get_contents($file);
        if ($content && preg_match("/'version'\s*=>\s*'([^']+)'/", $content, $m))
            return $m[1];
        return '0.0.0';
    }

    /**
     * Parse a version string into [major, minor, patch].
     */
    private static function parseSemver($version) {
        $clean = ltrim($version, 'vV');
        $parts = explode('.', $clean);
        return array(
            (int)($parts[0] ?? 0),
            (int)($parts[1] ?? 0),
            (int)($parts[2] ?? 0),
        );
    }

    /**
     * Fetch all releases from the GitHub Releases API.
     * Returns array of {tag, version, name, body, prerelease} or false on failure.
     */
    private static function fetchReleases() {
        $url = 'https://api.github.com/repos/'
             . self::GITHUB_USER . '/' . self::GITHUB_REPO
             . '/releases?per_page=50';
        $json = self::curlGet($url);
        if (!$json) return false;

        $data = @json_decode($json, true);
        if (!is_array($data)) return false;

        $releases = array();
        foreach ($data as $r) {
            if (!empty($r['draft'])) continue;
            $tag = isset($r['tag_name']) ? $r['tag_name'] : '';
            $ver = ltrim($tag, 'vV');
            if (!preg_match('/^\d+\.\d+\.\d+/', $ver)) continue;

            $releases[] = array(
                'tag'        => $tag,
                'version'    => $ver,
                'name'       => isset($r['name']) ? $r['name'] : $tag,
                'body'       => isset($r['body']) ? $r['body'] : '',
                'prerelease' => !empty($r['prerelease']),
            );
        }

        // Sort descending by version
        usort($releases, function ($a, $b) {
            return version_compare($b['version'], $a['version']);
        });

        return $releases;
    }

    /**
     * Check for available updates, categorized as minor and major.
     *
     * Returns array:
     *   local    (string)  — current installed version
     *   minor    (array|null) — latest minor/patch update (same major version)
     *   major    (array|null) — latest major update (higher major version)
     *   error    (string|null)
     *   cached   (bool)
     *
     * Each update entry: {version, tag, name, body, type}
     */
    static function checkUpdate($force = false) {
        $local = self::getLocalVersion();
        list($localMajor, , ) = self::parseSemver($local);

        // ── Try cache first ──
        if (!$force) {
            $cached = self::readCache();
            if ($cached !== false) {
                // Recalculate against current local version (may have just updated)
                $cached['local'] = $local;
                $cached = self::recalcAvailability($cached, $local);
                $cached['cached'] = true;
                return $cached;
            }
        }

        // ── Fetch releases from GitHub ──
        $releases = self::fetchReleases();

        if ($releases === false) {
            return array(
                'local'  => $local,
                'minor'  => null,
                'major'  => null,
                'error'  => /* trans */ 'Could not reach GitHub to check for updates',
                'cached' => false,
            );
        }

        // ── Find latest minor and latest major ──
        $latestMinor = null;
        $latestMajor = null;

        foreach ($releases as $rel) {
            if ($rel['prerelease']) continue;
            if (!version_compare($rel['version'], $local, '>')) continue;

            list($relMajor, , ) = self::parseSemver($rel['version']);

            if ($relMajor === $localMajor && $latestMinor === null) {
                $latestMinor = array(
                    'version' => $rel['version'],
                    'tag'     => $rel['tag'],
                    'name'    => $rel['name'],
                    'body'    => $rel['body'],
                    'type'    => 'minor',
                );
            }

            if ($relMajor > $localMajor && $latestMajor === null) {
                $latestMajor = array(
                    'version' => $rel['version'],
                    'tag'     => $rel['tag'],
                    'name'    => $rel['name'],
                    'body'    => $rel['body'],
                    'type'    => 'major',
                );
            }

            // Found both — stop searching
            if ($latestMinor !== null && $latestMajor !== null) break;
        }

        $result = array(
            'local'  => $local,
            'minor'  => $latestMinor,
            'major'  => $latestMajor,
            'error'  => null,
            'cached' => false,
        );

        self::writeCache($result);
        return $result;
    }

    /**
     * Recalculate minor/major availability against the current local version.
     * Used when reading from cache (local version may have changed since cache was written).
     */
    private static function recalcAvailability($result, $local) {
        if ($result['minor'] && !version_compare($result['minor']['version'], $local, '>'))
            $result['minor'] = null;
        if ($result['major'] && !version_compare($result['major']['version'], $local, '>'))
            $result['major'] = null;
        return $result;
    }

    /**
     * Invalidate the update-check cache (call after a successful install).
     */
    static function clearCache() {
        $file = self::getCacheFile();
        if (file_exists($file))
            @unlink($file);
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
     * Backup then download + install a specific tagged version from GitHub.
     *
     * @param string $tag  Git tag to install (e.g. "v1.3.0"). If empty, downloads master.
     * @return array  success (bool), backup_files, backup_db, error, rollback
     */
    static function downloadAndInstall($tag = '') {
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

        // 4. Build download URL — tag-specific or master fallback
        if ($tag) {
            $zipUrl = 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO
                    . '/archive/refs/tags/' . $tag . '.zip';
        } else {
            $zipUrl = 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO
                    . '/archive/refs/heads/' . self::GITHUB_BRANCH . '.zip';
        }

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

        if (!$result['success']) {
            // ── Auto-rollback: restore files from backup ──
            $rollback = self::rollbackFiles($fileBackup['path']);
            $result['rollback'] = $rollback['success']
                ? 'Files restored from backup'
                : 'Rollback failed: ' . ($rollback['error'] ?? 'unknown');
            return array_merge($result, array(
                'backup_files' => $fileBackup['path'],
                'backup_db'    => isset($dbBackup['path']) ? $dbBackup['path'] : null,
            ));
        }

        // Clear cached update-check so the panel reflects the new version
        self::clearCache();

        return array(
            'success'      => true,
            'backup_files' => $fileBackup['path'],
            'backup_db'    => isset($dbBackup['path']) ? $dbBackup['path'] : null,
        );
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Extract a GitHub archive ZIP into the plugin directory.
     * Auto-detects the root folder prefix inside the ZIP.
     */
    private static function extractAndOverwrite($zipPath) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true)
            return array('success' => false, 'error' => /* trans */ 'Cannot open downloaded ZIP file');

        $pluginDir = realpath(dirname(__FILE__));

        // Auto-detect the root prefix from the first entry (GitHub uses repo-tag/ or repo-branch/)
        $prefix = '';
        if ($zip->numFiles > 0) {
            $first = $zip->getNameIndex(0);
            if (substr($first, -1) === '/') {
                $prefix = $first;
            } else {
                $slashPos = strpos($first, '/');
                if ($slashPos !== false)
                    $prefix = substr($first, 0, $slashPos + 1);
            }
        }
        $prefixLen = strlen($prefix);

        if (!$prefix) {
            $zip->close();
            return array('success' => false, 'error' => /* trans */ 'Unexpected ZIP structure — no root folder found');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            // Skip the root folder entry itself and entries outside the prefix
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
            CURLOPT_USERAGENT      => 'osTicket-TicketDuplicator-Updater/2.0',
        ));

        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_errno($ch);
        curl_close($ch);

        if ($err || $code !== 200) return false;
        return $data;
    }

    /**
     * Restore plugin files from a backup directory.
     */
    private static function rollbackFiles($backupDir) {
        if (!$backupDir || !is_dir($backupDir))
            return array('success' => false, 'error' => 'Backup directory not found');

        $pluginDir = dirname(__FILE__);
        if (!self::copyDir($backupDir, $pluginDir))
            return array('success' => false,
                'error' => 'Could not copy backup files back to plugin directory');

        return array('success' => true);
    }

    private static function getCacheFile() {
        return sys_get_temp_dir() . '/td-update-cache-'
             . md5(realpath(dirname(__FILE__))) . '.json';
    }

    private static function readCache() {
        $file = self::getCacheFile();
        if (!file_exists($file))
            return false;

        if (filemtime($file) + self::CHECK_CACHE_TTL < time()) {
            @unlink($file);
            return false;
        }

        $data = @json_decode(@file_get_contents($file), true);
        return is_array($data) ? $data : false;
    }

    private static function writeCache($result) {
        $file = self::getCacheFile();
        @file_put_contents($file, json_encode($result), LOCK_EX);
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
