<?php
namespace Optmis_Yacht_Manager\Admin;

defined('ABSPATH') || exit;

use Optmis_Yacht_Manager\Utils\MatchingEngine;
use Optmis_Yacht_Manager\Utils\YachtSystemRequirement;

class MatchTestAdminPage {
    public static function register() {
        add_action('admin_menu', [self::class, 'add_debug_page']);
    }

    public static function add_debug_page() {
        add_menu_page(
            'Match Test',
            'Match Test',
            'manage_options',
            'oym-match-test',
            [self::class, 'render_debug_page']
        );
    }

    public static function render_debug_page() {
        $product_sig = [
            'voltage' => '12V',
            'screen_size' => '12”',
            'interface' => 'NMEA2000'
        ];

        // Try loading yacht requirement using fallback logic
        $yacht_req = YachtSystemRequirement::get_for('nonexistent-yacht', 'navigation_display');

        if (!$yacht_req) {
            echo '<div class="notice notice-error"><p><strong>No requirements found for navigation_display.</strong></p></div>';
            return;
        }

        $result = MatchingEngine::match($product_sig, $yacht_req);

        echo '<div class="wrap">';
        echo '<h1>Yacht-Part Match Test</h1>';
        echo '<h2>Using fallback requirements</h2>';
        echo '<pre>' . esc_html(print_r($result, true)) . '</pre>';
        echo '</div>';
    }
}
