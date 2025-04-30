<?php

namespace Optmis_Yacht_Manager\PostTypes;

defined('ABSPATH') || exit;

class Part {
    public static function register() {
        register_post_type('part', [
            'labels' => [
                'name' => __('Parts', 'optmis-yacht-manager'),
                'singular_name' => __('Part', 'optmis-yacht-manager'),
                'add_new_item' => __('Add New Part', 'optmis-yacht-manager'),
                'edit_item' => __('Edit Part', 'optmis-yacht-manager'),
                'new_item' => __('New Part', 'optmis-yacht-manager'),
                'view_item' => __('View Part', 'optmis-yacht-manager'),
                'search_items' => __('Search Parts', 'optmis-yacht-manager'),
                'not_found' => __('No parts found', 'optmis-yacht-manager'),
                'not_found_in_trash' => __('No parts found in Trash', 'optmis-yacht-manager'),
            ],
            'public' => true,
            'has_archive' => true,
            'show_in_menu' => true,
            'menu_position' => 26,
            'menu_icon' => 'dashicons-admin-generic',
            'supports' => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'part'],
        ]);
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'compatible_yachts',
            __('Compatible Yachts', 'optmis-yacht-manager'),
            [self::class, 'render_compatible_yachts_box'],
            'part',
            'side',
            'default'
        );
    }

    public static function render_compatible_yachts_box($post) {
        $selected = get_post_meta($post->ID, '_compatible_yachts', true) ?: [];
        $yachts = get_posts(['post_type' => 'yacht', 'numberposts' => -1]);

        echo '<select name="compatible_yachts[]" multiple style="width:100%;height:150px;">';
        foreach ($yachts as $yacht) {
            $selected_attr = in_array($yacht->ID, $selected) ? 'selected' : '';
            echo "<option value='{$yacht->ID}' $selected_attr>{$yacht->post_title}</option>";
        }
        echo '</select>';
    }

    public static function save_meta_boxes($post_id) {
        if (isset($_POST['compatible_yachts']) && is_array($_POST['compatible_yachts'])) {
            update_post_meta($post_id, '_compatible_yachts', array_map('intval', $_POST['compatible_yachts']));
        } else {
            delete_post_meta($post_id, '_compatible_yachts');
        }
    }
}
