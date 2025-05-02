<?php
namespace Optmis_Yacht_Manager\Utils;

defined('ABSPATH') || exit;

class YachtSystemRequirement
{
    /**
     * Get requirements for a given yacht slug and system role.
     * Falls back to generic system default if yacht-specific is not found.
     *
     * @param string $yacht_slug e.g. 'azimut-68'
     * @param string $system_role e.g. 'navigation_display'
     * @return array|null
     */
    public static function get_for(string $yacht_slug, string $system_role): ?array
    {
        // Check yacht-specific requirements file
        $yacht_file = plugin_dir_path(__DIR__) . '/../data/yachts/' . $yacht_slug . '.json';
        if (file_exists($yacht_file)) {
            $data = json_decode(file_get_contents($yacht_file), true);
            if (isset($data[$system_role]) && is_array($data[$system_role])) {
                return $data[$system_role];
            }
        }

        // Fallback: system-wide default
        $fallback_file = plugin_dir_path(__DIR__) . '/../data/system_defaults/' . $system_role . '.json';
        if (file_exists($fallback_file)) {
            $fallback = json_decode(file_get_contents($fallback_file), true);
            if (is_array($fallback)) {
                return $fallback;
            }
        }

        return null;
    }
} 
