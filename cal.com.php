<?php

/**
 * Plugin Name: Cal.com
 * Description: Simplest and easiest way to embed Cal.com in WordPress.
 * Author: Cal.com, Simpma
 * Author URI: https://cal.com/
 * Version: 3.0.0
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 * Text Domain: cal-com
 * Requires at least: 4.6
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

defined('CALCOM_DIR_PATH')          || define('CALCOM_DIR_PATH', plugin_dir_path(__FILE__));
defined('CALCOM_DIR_URL')           || define('CALCOM_DIR_URL', plugin_dir_url(__FILE__));
defined('CALCOM_ASSETS_URL')        || define('CALCOM_ASSETS_URL', CALCOM_DIR_URL . 'assets/');
defined('CALCOM_ASSETS_PATH')       || define('CALCOM_ASSETS_PATH', CALCOM_DIR_PATH . 'assets/');

require_once CALCOM_DIR_PATH . 'inc/Autoloader.php';

CalCom\Autoloader::register();

CalCom\Cal::get_instance();
