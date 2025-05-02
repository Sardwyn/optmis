<?php
namespace Optmis_Yacht_Manager\PostTypes;

defined('ABSPATH') || exit;

class WooPartMeta {
    public static function add_meta_boxes() {
        add_meta_box(
            'oym_compatibility',
            __('Yacht Compatibility', 'optmis-yacht-manager'),
            [self::class, 'render_compatibility_box'],
            'product',
            'normal',
            'default'
        );
    }

    public static function render_compatibility_box($post) {
        $value = get_post_meta($post->ID, '_compatible_yachts', true);
        echo '<label for="oym_compatible_yachts">Enter compatible Yacht IDs (comma-separated):</label>';
        echo '<input type="text" name="oym_compatible_yachts" id="oym_compatible_yachts" class="widefat" value="' . esc_attr($value) . '">';
    }

    public static function save_meta_boxes($post_id) {
        if (isset($_POST['oym_compatible_yachts'])) {
            $cleaned = sanitize_text_field($_POST['oym_compatible_yachts']);
            update_post_meta($post_id, '_compatible_yachts', $cleaned);
        }
    }
}
