<?php
namespace Optmis_Yacht_Manager\Utils;

defined('ABSPATH') || exit;

class SchemaMapper {
    public static function get_system_role_for_category($slug) {
        // TEMP: hardcoded test version
        return $slug === 'bridge-supplies' ? 'navigation_display' : null;
    }
}
