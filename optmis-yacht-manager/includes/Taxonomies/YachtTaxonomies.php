<?php
namespace Optmis_Yacht_Manager\Taxonomies;

defined('ABSPATH') || exit;

class YachtTaxonomies {
    public static function register() {
        // Yacht Type Taxonomy
        register_taxonomy('yacht_type', 'yacht', [
            'labels' => [
                'name' => __('Yacht Types', 'optmis-yacht-manager'),
                'singular_name' => __('Yacht Type', 'optmis-yacht-manager'),
            ],
            'public' => true,
            'hierarchical' => true,
            'show_in_rest' => true,
        ]);

        // Manufacturer Taxonomy
        register_taxonomy('manufacturer', 'yacht', [
            'labels' => [
                'name' => __('Manufacturers', 'optmis-yacht-manager'),
                'singular_name' => __('Manufacturer', 'optmis-yacht-manager'),
            ],
            'public' => true,
            'hierarchical' => false,
            'show_in_rest' => true,
        ]);
    }
}

