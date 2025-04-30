<?php
/**
 * Plugin Name: Optmis Yacht Manager
 * Plugin URI:  https://optmis.yachts
 * Description: Extend WooCommerce customer profiles with yacht-specific information and parts matching.
 * Version:     0.1.0
 * Author:      Bradley Templeton
 * Author URI:  https://optmis.yachts
 * License:     Private, All rights reserved.
 * Text Domain: optmis-yacht-manager
 * Domain Path: /languages
 */

defined('ABSPATH') || exit; // No direct access

// Autoload Classes
spl_autoload_register(function ($class) {
    $prefix = 'Optmis_Yacht_Manager\\';
    $base_dir = __DIR__ . '/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
        error_log("✅ Autoloaded class: $class");
    } else {
        error_log("❌ Could not find file for class: $class");
    }
});

// Bootstrap the Plugin
function optmis_yacht_manager_init() {
    error_log('✅ optmis_yacht_manager_init() fired.');

    if (class_exists('Optmis_Yacht_Manager\\Core\\Init')) {
        error_log('✅ Optmis_Yacht_Manager\\Core\\Init class exists.');
        Optmis_Yacht_Manager\Core\Init::run();
    } else {
        error_log('❌ Optmis_Yacht_Manager\\Core\\Init class not found.');
    }
}

add_action('plugins_loaded', 'optmis_yacht_manager_init');
