<?php

namespace Optmis_Yacht_Manager\Core;

defined('ABSPATH') || exit;

class Init {
    public static function run() {
        error_log('✅ Init::run() called');

        // Register filters
        add_filter('template_include', [self::class, 'maybe_load_template']);

        // Register Yacht CPT
        add_action('init', ['\\Optmis_Yacht_Manager\\PostTypes\\Yacht', 'register']);
        add_action('add_meta_boxes', ['\\Optmis_Yacht_Manager\\PostTypes\\Yacht', 'add_meta_boxes']);
        add_action('save_post', ['\\Optmis_Yacht_Manager\\PostTypes\\Yacht', 'save_meta_boxes']);

        // Register Part CPT
        add_action('init', ['\\Optmis_Yacht_Manager\\PostTypes\\Part', 'register']);
        add_action('add_meta_boxes', ['\\Optmis_Yacht_Manager\\PostTypes\\Part', 'add_meta_boxes']);
        add_action('save_post', ['\\Optmis_Yacht_Manager\\PostTypes\\Part', 'save_meta_boxes']);

        // Register Taxonomy
        add_action('init', ['\\Optmis_Yacht_Manager\\Taxonomies\\PartCategories', 'register']);

        // Register Shortcodes
        add_action('init', ['\\Optmis_Yacht_Manager\\Shortcodes\\YachtParts', 'register']);

        // Frontend Menu Accordion
        add_action('init', ['\\Optmis_Yacht_Manager\\Frontend\\CategoryAccordion', 'register']);

        // AJAX Handlers
        add_action('init', ['\\Optmis_Yacht_Manager\\Ajax\\PartsAjax', 'register']);
    }

    public static function maybe_load_template($template) {
        global $post;

        if (is_singular('yacht') && is_object($post) && isset($post->post_type) && $post->post_type === 'yacht') {
            error_log('✅ Loading single-yacht.php template override');
            $custom = plugin_dir_path(__DIR__) . '/../templates/single-yacht.php';
            if (file_exists($custom)) {
                return $custom;
            } else {
                error_log('❌ single-yacht.php not found at: ' . $custom);
            }
        }

        if (is_post_type_archive('yacht')) {
            error_log('✅ Loading archive-yacht.php template override');
            $custom = plugin_dir_path(__DIR__) . '/../templates/archive-yacht.php';
            if (file_exists($custom)) {
                return $custom;
            } else {
                error_log('❌ archive-yacht.php not found at: ' . $custom);
            }
        }

        return $template;
    }
}
