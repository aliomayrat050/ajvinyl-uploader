<?php

/**
 * Plugin Name: AJ Uploader for Woo
 * Description: let your Customers Upload files after checkout and in Account Page
 * Version: 1.0.0
 * Author: AJ Creative Agency
 * Text Domain: aj-uploader-for-woo
 */


// Sicherheitshalber sicherstellen, dass WordPress geladen ist
if (!defined('ABSPATH')) {
    exit;
}

function AJ_UPLOADER_auto_loader($class_name)
{
    // Not loading a class from our plugin.
    if (!is_int(strpos($class_name, 'AJ_UPLOADER')))
        return;
    // Remove root namespace as we don't have that as a folder.
    $class_name = str_replace('AJ_UPLOADER\\', '', $class_name);
    $class_name = str_replace('\\', '/', strtolower($class_name)) . '.php';
    // Get only the file name.
    $pos =  strrpos($class_name, '/');
    $file_name = is_int($pos) ? substr($class_name, $pos + 1) : $class_name;
    // Get only the path.
    $path = str_replace($file_name, '', $class_name);
    // Append 'class-' to the file name and replace _ with -
    $new_file_name = 'class-' . str_replace('_', '-', $file_name);
    // Construct file path.
    $file_path = plugin_dir_path(__FILE__)  . str_replace('\\', DIRECTORY_SEPARATOR, $path . strtolower($new_file_name));

    if (file_exists($file_path))
        require_once($file_path);
}

spl_autoload_register('AJ_UPLOADER_auto_loader');



function ajuploader()
{

    // version
    $version = '1.0.0';

    // globals
    global $ajuploader;

    // initialize
    if (!isset($ajuploader)) {
        $ajuploader = new \AJ_UPLOADER\init();
        $ajuploader->initialize($version, __FILE__);
    }

    return $ajuploader;
}

// initialize

ajuploader();

function svg_autoloader($class) {
    $prefix = 'enshrined\\svgSanitize\\'; // Der Namespace der Bibliothek
    $base_dir =  plugin_dir_path(__FILE__) . 'vendor/svg-sanitizer/'; // Der Pfad zum Quellcode


    // Überprüfen, ob der Klassennamen mit dem Prefix übereinstimmt
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return; // Wenn der Namespace nicht übereinstimmt, abbrechen
    }

  

    // Den relativen Klassennamen herausziehen und in den entsprechenden Pfad umwandeln
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Überprüfen, ob die Datei existiert und einbinden
    if (file_exists($file)) {
        require_once $file;
    }
}

spl_autoload_register('svg_autoloader');



// Declare HPOS compatibility.
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
