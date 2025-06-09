
<?php

// Register the submenu item for CSV Supplier Sync
add_action('admin_menu', function () {
    add_submenu_page(
        'product-scraper',
        'CSV Supplier Sync',
        'CSV Supplier Sync',
        'manage_options',
        'csv-supplier-sync',
        'pss_csv_sync_settings_page'
    );
});

// Define the callback function that renders the settings page
function pss_csv_sync_settings_page() {
    ?>
    <div class="wrap">
        <h1>CSV Supplier Sync</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('csv_sync_group');
            do_settings_sections('csv-supplier-sync');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register the setting
add_action('admin_init', function () {
    register_setting('csv_sync_group', 'csv_sync_option');

    add_settings_section(
        'csv_sync_section',
        'CSV File Sync Settings',
        function () {
            echo '<p>Set the remote CSV source URL:</p>';
        },
        'csv-supplier-sync'
    );

    add_settings_field(
        'csv_sync_option',
        'CSV URL',
        function () {
            $value = esc_attr(get_option('csv_sync_option', ''));
            echo "<input type='text' name='csv_sync_option' value='{$value}' class='regular-text' />";
        },
        'csv-supplier-sync',
        'csv_sync_section'
    );
});
