<?php
if (!interface_exists('\Psr\SimpleCache\CacheInterface')) {
    require_once PSS_PLUGIN_DIR . 'libs/psr/simple-cache/src/CacheInterface.php';
}
if (!class_exists('\Composer\Pcre\Preg')) {
    require_once PSS_PLUGIN_DIR . 'libs/composer/pcre/Preg.php';
}


// Core
require_once PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/Spreadsheet.php';
require_once PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/IOFactory.php';

// Reader hierarchy
require_once PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/Reader/IReader.php';
require_once PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/Reader/BaseReader.php';
require_once PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/Reader/Xlsx.php';

// Shared utilities
require_once PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/Cell/Coordinate.php';
require_once PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/Shared/StringHelper.php';
require_once PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/Shared/File.php';



// File: enrichment/pss-load-phpspreadsheet.php
if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    require_once PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/Spreadsheet.php';
    require_once PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/Reader/BaseReader.php';
    require_once PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/Reader/Xlsx.php';
    require_once PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/IOFactory.php';
    require_once PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/Cell/Coordinate.php';
    require_once PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/Shared/StringHelper.php';
}

spl_autoload_register(function ($class) {
    $prefix = 'PhpOffice\\PhpSpreadsheet\\';
    $base_dir = PSS_PLUGIN_DIR . 'libs/PhpSpreadsheet/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return; // Not our namespace
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

