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
        if (self::$bootstrapped)
            return;
        self::$bootstrapped = true;

        if (!defined('STAFFINC_DIR'))
            return;

        Signal::connect('ajax.scp', array('TicketDuplicatorPlugin', 'registerAjaxRoutes'));
        ob_start(array('TicketDuplicatorPlugin', 'injectAssets'));
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

    static function registerAjaxRoutes($dispatcher) {
        $dir = INCLUDE_DIR . 'plugins/ticket-duplicator/';
        $dispatcher->append(
            url('^/ticket-duplicator/', patterns(
                $dir . 'class.TicketDuplicatorAjax.php:TicketDuplicatorAjax',
                url_post('^duplicate$', 'duplicate'),
                url_get('^assets/js$', 'serveJs'),
                url_get('^assets/css$', 'serveCss')
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

        $buffer = str_replace('</head>', $css . "\n</head>", $buffer);
        $buffer = str_replace('</body>', $js . "\n</body>", $buffer);

        return $buffer;
    }
}

// Static bootstrap: ensures AJAX routes + assets load even with 0 instances.
if (defined('STAFFINC_DIR'))
    TicketDuplicatorPlugin::bootstrapStatic();
