<?php

namespace CalCom;

defined('ABSPATH') || exit;

/**
 * PSR-4 autoloader: CalCom\ -> inc/
 */
class Autoloader
{
    const PREFIX = 'CalCom\\';

    public static function register()
    {
        spl_autoload_register(array(self::class, 'load'));
    }

    private static function load($class)
    {
        if (strpos($class, self::PREFIX) !== 0) {
            return;
        }

        $relative = substr($class, strlen(self::PREFIX));
        $file = CALCOM_DIR_PATH . 'inc/' . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $relative) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
