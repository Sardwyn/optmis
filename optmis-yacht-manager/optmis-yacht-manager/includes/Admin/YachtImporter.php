<?php

namespace Optmis_Yacht_Manager\Admin;

defined('ABSPATH') || exit;

class YachtImporter {
    public static function register() {
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_post_oym_import_yachts_csv', [self::class, 'handle_csv_upload']);
    }

    public static function add_menu() {
        add_submenu_page(
            'tools.php',
            'Import Yachts',
            'Import Yachts',
            'manage_options',
            'oym-import-yachts',
            [self::class, 'render_page']
        );
    }

    public static function render_page() {
        ?>
        <div class="wrap">
            <h1>Import Sample Yachts</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="oym_import_yachts">
                <?php submit_button('Generate Sample Yachts'); ?>
            </form>

            <h2>Upload Yacht CSV</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="oym_import_yachts_csv">
                <input type="file" name="yacht_csv" accept=".csv" required>
                <?php submit_button('Import Yachts from CSV'); ?>
            </form>
        </div>
        <?php
    }

    public static function handle_csv_upload() {
        if (!current_user_can('manage_options') || empty($_FILES['yacht_csv']['tmp_name'])) {
            wp_die('Unauthorized or no file uploaded.');
        }

        $file = fopen($_FILES['yacht_csv']['tmp_name'], 'r');
        $header = fgetcsv($file);

        while ($row = fgetcsv($file)) {
            $data = array_combine($header, $row);

            $post_id = wp_insert_post([
                'post_title' => $data['name'] ?? '',
                'post_type' => 'yacht',
                'post_status' => 'publish',
            ]);

            if ($post_id) {
                update_post_meta($post_id, '_yacht_model', $data['model'] ?? '');
                update_post_meta($post_id, '_yacht_length', $data['length'] ?? '');
                update_post_meta($post_id, '_yacht_year', $data['year'] ?? '');
                update_post_meta($post_id, '_yacht_manufacturer', $data['manufacturer'] ?? '');
            }
        }

        fclose($file);
        wp_redirect(admin_url('tools.php?page=oym-import-yachts&imported=csv'));
        exit;
    }
}
