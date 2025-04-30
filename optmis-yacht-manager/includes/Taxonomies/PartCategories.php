<?php

namespace Optmis_Yacht_Manager\Taxonomies;

defined('ABSPATH') || exit;

class PartCategories {
    public static function register() {
        register_taxonomy('part_category', 'part', [
            'labels' => [
                'name' => __('Part Categories', 'optmis-yacht-manager'),
                'singular_name' => __('Part Category', 'optmis-yacht-manager'),
            ],
            'public' => true,
            'hierarchical' => true,
            'show_in_rest' => true,
        ]);
    }
}
