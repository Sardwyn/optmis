<?php

namespace Optmis_Yacht_Manager\PostTypes;

defined('ABSPATH') || exit;

class Yacht {

    public static function register() {
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
        add_meta_box(
            'yacht_details',
            __('Yacht Details', 'optmis-yacht-manager'),
            [self::class, 'render_meta_box'],
            'yacht',
            'normal',
            'default'
        );
    }

    public static function render_meta_box($post) {
        $fields = [
            'model' => __('Model', 'optmis-yacht-manager'),
            'length' => __('Length (ft)', 'optmis-yacht-manager'),
            'year' => __('Year', 'optmis-yacht-manager'),
            'manufacturer' => __('Manufacturer', 'optmis-yacht-manager'),
        ];

        foreach ($fields as $key => $label) {
            $value = get_post_meta($post->ID, "_yacht_$key", true);
            echo "<p><label for='yacht_$key'>$label</label><br />";
            echo "<input type='text' name='yacht_$key' id='yacht_$key' value='" . esc_attr($value) . "' class='widefat' /></p>";
        }

        wp_nonce_field('save_yacht_meta', 'yacht_meta_nonce');
    }

    public static function save_meta_boxes($post_id) {
        if (!isset($_POST['yacht_meta_nonce']) || !wp_verify_nonce($_POST['yacht_meta_nonce'], 'save_yacht_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = ['model', 'length', 'year', 'manufacturer'];

        foreach ($fields as $key) {
            if (isset($_POST["yacht_$key"])) {
                update_post_meta($post_id, "_yacht_$key", sanitize_text_field($_POST["yacht_$key"]));
            }
        }
    }
}
