<?php
/**
 * Ticket Duplicator Plugin - Main Class
 *
 * @author  ChesnoTech
 * @version 1.0.0
 */

require_once 'config.php';

class TicketDuplicatorPlugin extends Plugin {
    var $config_class = 'TicketDuplicatorConfig';

    static private $bootstrapped = false;

    function bootstrap() {
        self::bootstrapStatic();
    }

    static function bootstrapStatic() {
        if (self::$bootstrapped)
            return;
        self::$bootstrapped = true;

        if (!defined('STAFFINC_DIR'))
            return;

        Signal::connect('ajax.scp', array('TicketDuplicatorPlugin', 'registerAjaxRoutes'));
        ob_start(array('TicketDuplicatorPlugin', 'injectAssets'));
    }

    /**
     * pre_upgrade — called by osTicket when the installed version differs
     * from the manifest version. Return false to abort the upgrade.
     */
    function pre_upgrade(&$errors) {
        // Back up DB config before osTicket applies the version bump
        require_once dirname(__FILE__) . '/class.TicketDuplicatorUpdater.php';
        $backup = TicketDuplicatorUpdater::backupDatabase();
        if (!$backup['success']) {
            $errors[] = 'Ticket Duplicator: DB backup failed — '
                      . ($backup['error'] ?? 'unknown error');
            return false;
        }
        return true;
    }

    static function registerAjaxRoutes($dispatcher) {
        $dir = INCLUDE_DIR . 'plugins/ticket-duplicator/';
        $dispatcher->append(
            url('^/ticket-duplicator/', patterns(
                $dir . 'class.TicketDuplicatorAjax.php:TicketDuplicatorAjax',
                url_get('^check-access$', 'checkAccess'),
                url_get('^config$', 'getConfig'),
                url_post('^duplicate$', 'duplicate'),
                url_post('^log-source-note$', 'logSourceNote'),
                url_get('^assets/js$', 'serveJs'),
                url_get('^assets/css$', 'serveCss'),
                url_get('^update/check$', 'checkUpdate'),
                url_post('^update/install$', 'installUpdate'),
                url_get('^assets/updater-js$', 'serveUpdaterJs')
            ))
        );
    }

    static function injectAssets($buffer) {
        if (!empty($_SERVER['HTTP_X_PJAX']))
            return $buffer;

        if (strpos($buffer, '</head>') === false
                || strpos($buffer, '</body>') === false)
            return $buffer;

        $base = ROOT_PATH . 'scp/ajax.php/ticket-duplicator/assets';
        $dir = dirname(__FILE__) . '/assets/';
        $v = max(
            @filemtime($dir . 'ticket-duplicator.js'),
            @filemtime($dir . 'ticket-duplicator.css')
        ) ?: time();

        $css = sprintf(
            '<link rel="stylesheet" type="text/css" href="%s/css?v=%s">',
            $base, $v);
        $js = sprintf(
            '<script type="text/javascript" src="%s/js?v=%s"></script>',
            $base, $v);

        $i18n = array(
            'modal_title'    => /* trans */ 'Duplicate Ticket #%s',
            'label_total'    => /* trans */ 'Total copies (incl. original):',
            'label_manual'   => /* trans */ 'Enter field values manually',
            'btn_cancel'     => /* trans */ 'Cancel',
            'btn_create'     => /* trans */ 'Create %d duplicate(s)',
            'err_failed'     => /* trans */ 'Failed to duplicate ticket.',
            'err_partial'    => /* trans */ 'Successfully created %d of %d before the error.',
            'notice_created' => /* trans */ '%d duplicate ticket(s) created! (#%s \u2014 #%s)',
        );
        $i18nScript = '<script>window.TDi18n=' . json_encode($i18n, JSON_UNESCAPED_UNICODE) . ';</script>';

        $buffer = str_replace('</head>', $css . "\n" . $i18nScript . "\n</head>", $buffer);
        $buffer = str_replace('</body>', $js . "\n</body>", $buffer);

        // Inject auto-updater assets only on the plugins management page
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        if (strpos($scriptName, 'plugins.php') !== false) {
            $updaterJs = sprintf(
                '<script type="text/javascript" src="%s/updater-js?v=%s"></script>',
                $base, $v);
            $buffer = str_replace('</body>', $updaterJs . "\n</body>", $buffer);
        }

        return $buffer;
    }
}

// Static bootstrap: ensures AJAX routes + assets load even with 0 instances.
if (defined('STAFFINC_DIR'))
    TicketDuplicatorPlugin::bootstrapStatic();
