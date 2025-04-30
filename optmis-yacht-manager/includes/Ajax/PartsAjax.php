<?php

namespace Optmis_Yacht_Manager\Ajax;

defined('ABSPATH') || exit;

class PartsAjax {
    public static function register() {
        add_action('wp_ajax_oym_get_parts', [self::class, 'handle']);
        add_action('wp_ajax_nopriv_oym_get_parts', [self::class, 'handle']);
    }

    public static function handle() {
        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $yacht_id = isset($_POST['yacht_id']) ? absint($_POST['yacht_id']) : 0;

        if (!$term_id || !$yacht_id) {
            wp_send_json_error('Invalid request');
        }

        $query = new \WP_Query([
            'post_type' => 'part',
            'posts_per_page' => -1,
            'meta_query' => [[
                'key' => '_compatible_yachts',
                'value' => $yacht_id,
                'compare' => 'LIKE',
            ]],
            'tax_query' => [[
                'taxonomy' => 'part_category',
                'field' => 'term_id',
                'terms' => $term_id,
            ]]
        ]);

        if (!$query->have_posts()) {
            echo '<p>No matching parts found.</p>';
            wp_die();
        }

        echo '<ul class="oym-parts-list">';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<li>';
            echo '<strong>' . get_the_title() . '</strong>';
            echo '<br><small>' . get_the_excerpt() . '</small>';
            echo '</li>';
        }
        echo '</ul>';
        wp_reset_postdata();
        wp_die();
    }
}
