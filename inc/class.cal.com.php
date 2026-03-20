<?php

namespace CalCom;

defined('ABSPATH') || exit;

class Cal
{
    private static $instance;

    private function includes()
    {
        include_once CALCOM_DIR_PATH . 'inc/class.embed.php';
        include_once CALCOM_DIR_PATH . 'inc/admin/class.customizer.php';
        include_once CALCOM_DIR_PATH . 'inc/class.custom-embed.php';
    }

    private function __construct()
    {
        $this->includes();
        $this->hooks();

        (new Embed())->hooks();
        (new Customizer())->hooks();
        (new CustomEmbed())->hooks();
    }

    private function hooks()
    {
        add_action('admin_enqueue_scripts', [$this, 'register_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'register_scripts']);
    }

    public function register_scripts()
    {
        $ver = file_exists(CALCOM_ASSETS_PATH . 'js/embed.js')
            ? filemtime(CALCOM_ASSETS_PATH . 'js/embed.js')
            : false;

        wp_register_script(
            'calcom-loader',
            CALCOM_ASSETS_URL . 'js/cal-loader.js',
            [],
            $ver,
            true
        );

        wp_register_script(
            'calcom-embed-js',
            CALCOM_ASSETS_URL . 'js/embed.js',
            ['calcom-loader'],
            $ver,
            true
        );

        wp_register_script(
            'calcom-custom-embed-js',
            CALCOM_ASSETS_URL . 'js/custom-embed.js',
            ['calcom-embed-js'],
            $ver,
            true
        );

        wp_register_script(
            'calcom-customizer-js',
            CALCOM_ASSETS_URL . 'js/admin-customizer.js',
            ['calcom-custom-embed-js'],
            $ver,
            true
        );

        wp_register_style(
            'calcom-customizer-css',
            CALCOM_ASSETS_URL . 'css/admin-customizer.css',
            [],
            $ver
        );

        wp_register_style(
            'calcom-embed-css',
            CALCOM_ASSETS_URL . 'css/style.css',
            [],
            $ver
        );
    }

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
