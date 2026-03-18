<?php

namespace CalCom;

defined('ABSPATH') || exit;

class Cal
{
    private static $instance;

    private function includes()
    {
        include_once CALCOM_DIR_PATH . 'inc/class.embed.php';
    }

    private function __construct()
    {
        $this->includes();
        $this->hooks();

        $embed = new \CalCom\Embed();
        $embed->hooks();
    }

    private function hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'register_scripts'));
    }

    /**
     * Register needed JS scripts
     * 
     * @return void
     */
    public function register_scripts()
    {
        $js_path = CALCOM_ASSETS_PATH . 'js/embed.js';
        $css_path = CALCOM_ASSETS_PATH . 'css/style.css';

        $js_version = file_exists($js_path) ? filemtime($js_path) : null;
        $css_version = file_exists($css_path) ? filemtime($css_path) : null;

        wp_register_script(
            'calcom-embed-js',
            CALCOM_ASSETS_URL . 'js/embed.js',
            array(),
            $js_version,
            true
        );

        wp_register_style(
            'calcom-embed-css',
            CALCOM_ASSETS_URL . 'css/style.css',
            array(),
            $css_version
        );
    }

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
