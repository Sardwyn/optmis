<?php

namespace Optmis_Yacht_Manager\Core;

defined('ABSPATH') || exit;

class Init {
    public static function run() {
        require_once plugin_dir_path(__DIR__) . '/Admin/oym-admin-status-widget.php';
        \Optmis_Yacht_Manager\Hooks\SignatureAutoSave::register();
        \Optmis_Yacht_Manager\Admin\MatchTestAdminPage::register();
        
        add_filter('template_include', [self::class, 'maybe_load_template'], 99);

        // Force load if autoloader fails
        if (!class_exists('\Optmis_Yacht_Manager\PostTypes\Yacht')) {
            error_log('⚠️ Forcing Yacht class load manually');
            require_once plugin_dir_path(__DIR__) . 'PostTypes/Yacht.php';
        }

        // Hooks
        add_action('init', ['\Optmis_Yacht_Manager\PostTypes\Yacht', 'register']);
        add_action('add_meta_boxes', ['\Optmis_Yacht_Manager\PostTypes\Yacht', 'add_meta_boxes']);
        add_action('save_post', ['\Optmis_Yacht_Manager\PostTypes\Yacht', 'save_meta_boxes']);
        add_action('init', ['\\Optmis_Yacht_Manager\\Admin\\YachtImporter', 'register']);
        add_action('admin_post_oym_import_yachts_csv', [self::class, 'handle_csv_upload']);
        add_action('init', ['\\Optmis_Yacht_Manager\\Admin\\YachtImporter', 'register']);


        add_filter('rank_math/frontend/redirect/ignore_redirect', function ($ignore, $url) {
            if (is_singular('yacht')) {
                return true;
            }
            return $ignore;
        }, 10, 2);
        
    }

    public static function maybe_load_template($template) {
        global $post;
    
        if ($post instanceof \WP_Post && $post->post_type === 'yacht') {
            error_log('✅ Using plugin single-yacht.php');
            $custom = plugin_dir_path(__DIR__) . '/../templates/single-yacht.php';
            if (file_exists($custom)) return $custom;
        }
    
        if (is_post_type_archive('yacht')) {
            error_log('✅ Using plugin archive-yacht.php');
            $custom = plugin_dir_path(__DIR__) . '/../templates/archive-yacht.php';
            if (file_exists($custom)) return $custom;
        }
    
        return $template;
    }
    
}
