<?php

namespace Optmis_Yacht_Manager\Frontend;

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
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => 0,
        ]);

        if (empty($terms)) {
            error_log('❌ No product_cat terms found');
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
        echo '<div class="oym-category">';
        echo '<details>';
        echo '<summary>' . esc_html($term->name) . '</summary>';

        $children = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => $term->term_id,
        ]);

        if (!empty($children)) {
            echo '<ul>';
            foreach ($children as $child) {
                $count = self::count_products($yacht_id, $child->term_id);
                echo '<li data-term-id="' . esc_attr($child->term_id) . '">';
                echo esc_html($child->name);
                echo $count > 0 ? " <strong>({$count})</strong>" : " (0)";
                echo '</li>';
            }
            echo '</ul>';
        } else {
            $count = self::count_products($yacht_id, $term->term_id);
            echo '<p>' . ($count > 0 ? "{$count} product(s) found." : 'No matching products.') . '</p>';
        }

        echo '</details>';
        echo '</div>';
    }

    private static function count_products($yacht_id, $category_id) {
        $query = new \WP_Query([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => '_compatible_yachts',
                'value' => $yacht_id,
                'compare' => 'LIKE',
            ]],
            'tax_query' => [[
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $category_id,
            ]]
        ]);

        return $query->found_posts;
    }
}
