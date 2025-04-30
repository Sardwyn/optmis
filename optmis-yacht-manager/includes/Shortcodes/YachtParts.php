<?php

namespace Optmis_Yacht_Manager\Shortcodes;

defined('ABSPATH') || exit;

class YachtParts {
    public static function register() {
        add_shortcode('yacht_parts', [self::class, 'render']);
    }

    public static function render($atts) {
        $atts = shortcode_atts([
            'yacht_id' => 0,
            'category' => '',
        ], $atts);

        if (empty($atts['yacht_id'])) return '<p>No yacht specified.</p>';

        $meta_query = [
            [
                'key' => '_compatible_yachts',
                'value' => (int) $atts['yacht_id'],
                'compare' => 'LIKE',
            ]
        ];

        $tax_query = [];
        if (!empty($atts['category'])) {
            $tax_query[] = [
                'taxonomy' => 'part_category',
                'field' => 'slug',
                'terms' => $atts['category']
            ];
        }

        $query = new \WP_Query([
            'post_type' => 'part',
            'posts_per_page' => -1,
            'meta_query' => $meta_query,
            'tax_query' => $tax_query
        ]);

        if (!$query->have_posts()) return '<p>No compatible parts found.</p>';

        ob_start();
        echo '<div class="yacht-parts">';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<div class="yacht-part">';
            if (has_post_thumbnail()) {
                the_post_thumbnail('thumbnail');
            }
            echo '<h3>' . get_the_title() . '</h3>';
            echo '<p>' . get_the_excerpt() . '</p>';
            echo '</div>';
        }
        echo '</div>';
        wp_reset_postdata();
        return ob_get_clean();
    }
}
