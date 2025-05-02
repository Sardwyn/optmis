<?php

namespace Optmis_Yacht_Manager\PostTypes;

defined('ABSPATH') || exit;

class Yacht {
    public static function register() {
        error_log('✅ Yacht CPT is being registered');

        register_post_type('yacht', [
            'labels' => [
                'name' => __('Yachts', 'optmis-yacht-manager'),
                'singular_name' => __('Yacht', 'optmis-yacht-manager'),
                'add_new_item' => __('Add New Yacht', 'optmis-yacht-manager'),
                'edit_item' => __('Edit Yacht', 'optmis-yacht-manager'),
                'new_item' => __('New Yacht', 'optmis-yacht-manager'),
                'view_item' => __('View Yacht', 'optmis-yacht-manager'),
                'search_items' => __('Search Yachts', 'optmis-yacht-manager'),
                'not_found' => __('No yachts found', 'optmis-yacht-manager'),
                'not_found_in_trash' => __('No yachts found in Trash', 'optmis-yacht-manager'),
            ],
            'public' => true,
            'has_archive' => true,
            'show_in_menu' => true,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-admin-site',
            'supports' => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'yacht'],
        ]);
    }

    public static function add_meta_boxes() {
        add_meta_box('yacht_details', __('Yacht Details', 'optmis-yacht-manager'), [self::class, 'render_meta_box'], 'yacht', 'normal', 'high');
    }

    public static function render_meta_box($post) {
        $model = get_post_meta($post->ID, '_yacht_model', true);
        $length = get_post_meta($post->ID, '_yacht_length', true);
        $year = get_post_meta($post->ID, '_yacht_year', true);
        $manufacturer = get_post_meta($post->ID, '_yacht_manufacturer', true);

        echo '<p><label>Model: <input type="text" name="yacht_model" value="' . esc_attr($model) . '" /></label></p>';
        echo '<p><label>Length (ft): <input type="number" name="yacht_length" value="' . esc_attr($length) . '" /></label></p>';
        echo '<p><label>Year: <input type="number" name="yacht_year" value="' . esc_attr($year) . '" /></label></p>';
        echo '<p><label>Manufacturer: <input type="text" name="yacht_manufacturer" value="' . esc_attr($manufacturer) . '" /></label></p>';
    }

    public static function save_meta_boxes($post_id) {
        if (array_key_exists('yacht_model', $_POST)) {
            update_post_meta($post_id, '_yacht_model', sanitize_text_field($_POST['yacht_model']));
        }
        if (array_key_exists('yacht_length', $_POST)) {
            update_post_meta($post_id, '_yacht_length', floatval($_POST['yacht_length']));
        }
        if (array_key_exists('yacht_year', $_POST)) {
            update_post_meta($post_id, '_yacht_year', intval($_POST['yacht_year']));
        }
        if (array_key_exists('yacht_manufacturer', $_POST)) {
            update_post_meta($post_id, '_yacht_manufacturer', sanitize_text_field($_POST['yacht_manufacturer']));
        }
    }
}
