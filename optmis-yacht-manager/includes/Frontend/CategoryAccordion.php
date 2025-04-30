<?php

error_log('🚨 CategoryAccordion.php was loaded');

namespace Optmis_Yacht_Manager\Frontend;
error_log('🚨 CategoryAccordion.php was loaded');

defined('ABSPATH') || exit;

class CategoryAccordion {
    public static function register() {
        error_log('✅ CategoryAccordion::register() was called.');
        add_shortcode('yacht_part_menu', [self::class, 'render']);
    }

    public static function render($atts) {
        error_log('✅ render() called with atts: ' . print_r($atts, true));

        $atts = shortcode_atts([
            'yacht_id' => 0,
        ], $atts);

        $yacht_id = (int) $atts['yacht_id'];
        if (!$yacht_id) {
            error_log('❌ yacht_id is missing or 0');
            return '<p>No yacht selected.</p>';
        }

        error_log('✅ yacht_id = ' . $yacht_id);

        $terms = get_terms([
            'taxonomy' => 'part_category',
            'hide_empty' => false,
            'parent' => 0,
        ]);

        if (empty($terms)) {
            error_log('❌ No part_category terms found');
            return '<p>No categories found.</p>';
        }

        error_log('✅ Terms found: ' . count($terms));

        ob_start();
        echo '<div class="oym-accordion" data-yacht-id="' . esc_attr($yacht_id) . '">';

        foreach ($terms as $parent) {
            self::render_term($parent, $yacht_id);
        }

        echo '</div>';
        return ob_get_clean();
    }

    private static function render_term($term, $yacht_id) {
        // your existing render_term() logic here
    }

    private static function count_parts($yacht_id, $category_id) {
        // your existing count_parts() logic here
    }
}
