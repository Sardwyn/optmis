<?php
/**
 * Plugin Name: Supplier Scraper
 * Description: Scrapes product data from supplier websites and imports into WooCommerce manually.
 * Version:     0.9.6
 * Author:      Bradley Templeton
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! defined( 'PSS_PLUGIN_DIR' ) )  define( 'PSS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
if ( ! defined( 'PSS_PLUGIN_URL' ) )  define( 'PSS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
require_once PSS_PLUGIN_DIR . 'admin/csv-sync-settings.php';

/**
 * Render main admin UI
 */
function pss_render_admin_page() {
    
    echo '<div class="wrap">
        <h1>Supplier Product Scraper</h1>
        <div id="pss-ui-root"></div>
        <div id="pss-scraped-output"></div>
        <div id="pss-failed-imports" style="display:none;margin-top:20px;"></div>
        <div id="pss-admin-preview-wrapper" style="position:fixed;top:80px;right:0;width:900px;height:800px;z-index:9999;background:#fff;border-left:2px solid #ccc;box-shadow:-4px 0 20px rgba(0,0,0,0.15);display:none;">
          <div style="background:#f1f1f1;padding:8px;border-bottom:1px solid #ddd;text-align:right;">
            <button id="pss-preview-close" class="button">Close Preview</button>
          </div>
          <iframe id="pss-admin-preview-frame" style="width:100%;height:calc(100% - 40px);border:0;"></iframe>
        </div>
    </div>';
}

/**
 * Render settings page
 */
function pss_render_settings_page() {
    echo '<div class="wrap">';
    echo '<h1>Supplier Scraper Settings</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields( 'pss_settings_group' );
    do_settings_sections( 'pss-settings' );
    submit_button();
    echo '</form>';
    echo '</div>';
}

/**
 * Download + attach a remote image.
 * Returns the attachment ID, or 0 on failure.
 */
/**
 * Try to sideload one remote URL and return an attachment ID, or WP_Error.
 */
/**
 * Try to sideload one remote URL and return an attachment ID, or WP_Error.
 */
function pss_sideload_image( string $url, int $post_id ) {
    if ( empty( $url ) ) {
        error_log("🛑 pss_sideload_image(): empty URL" );
        return new WP_Error('empty_url','Empty image URL');
    }
    error_log("🔄 pss_sideload_image(): fetching {$url}" );

    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    // already imported?
    $existing = attachment_url_to_postid( $url );
    if ( $existing ) {
        error_log("✅ pss_sideload_image(): already in library, ID={$existing}" );
        return $existing;
    }

    $sideloaded = media_sideload_image( $url, $post_id, null, 'id' );
    if ( is_wp_error( $sideloaded ) ) {
        error_log("❌ pss_sideload_image(): WP_Error for {$url} — " . $sideloaded->get_error_message() );
        return $sideloaded;
    }
    if ( ! $sideloaded ) {
        error_log("❌ pss_sideload_image(): no ID returned for {$url}" );
        return new WP_Error('no_id','No attachment ID returned');
    }

    error_log("✅ pss_sideload_image(): sideloaded {$url} as attachment ID {$sideloaded}" );
    return $sideloaded;
}


add_action('wp_ajax_pss_delete_scraper_category', function() {
  check_ajax_referer('pss_scraper_nonce', 'security');
  if ( ! current_user_can('manage_woocommerce') ) {
    wp_send_json_error();
  }

  $supplier = sanitize_text_field($_POST['supplier'] ?? '');
  $url = esc_url_raw($_POST['url'] ?? '');
  if (empty($supplier) || empty($url)) {
    wp_send_json_error();
  }

  $categories = get_option('pss_saved_scraper_categories', []);
  if (!isset($categories[$supplier])) {
    wp_send_json_error();
  }

  $categories[$supplier] = array_values(array_filter($categories[$supplier], function($cat) use ($url) {
    return $cat['url'] !== $url;
  }));

  update_option('pss_saved_scraper_categories', $categories);
  wp_send_json_success();
});


add_action( 'admin_init', function() {

    if (!empty($_POST['mapping']) && current_user_can('manage_options')) {
        $map_path = WP_CONTENT_DIR . '/uploads/pss-maps/aquafax.json';
        $input = array_map(function($entry) {
            return [
                'index'  => sanitize_text_field($entry['index'] ?? 'production_aquafax'),
                'filter' => sanitize_text_field($entry['filter'] ?? '')
            ];
        }, $_POST['mapping']);

        if (!file_exists(dirname($map_path))) {
            mkdir(dirname($map_path), 0755, true);
        }

        file_put_contents($map_path, json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Category mappings saved.</p></div>';
        });
    }
    register_setting( 'pss_settings_group', 'pss_openai_api_key',    ['sanitize_callback'=>'sanitize_text_field'] );
    register_setting( 'pss_settings_group', 'pss_debug_mode',       ['sanitize_callback'=>'sanitize_text_field'] );
    register_setting( 'pss_settings_group', 'pss_wc_consumer_key',  ['sanitize_callback'=>'sanitize_text_field'] );
    register_setting( 'pss_settings_group', 'pss_wc_consumer_secret',['sanitize_callback'=>'sanitize_text_field'] );
    register_setting( 'pss_settings_group', 'pss_category_map',     ['sanitize_callback'=>'pss_sanitize_category_map'] );
    register_setting( 'pss_settings_group', 'pss_enable_ai_descriptions', ['sanitize_callback' => 'sanitize_text_field'] );

    // Enable AI Descriptions
    add_settings_field( 'pss_enable_ai_descriptions', 'Enable AI Descriptions', function() {
    $val = get_option('pss_enable_ai_descriptions', 'no');
    echo '<input type="checkbox" name="pss_enable_ai_descriptions" value="yes" ' . checked($val, 'yes', false) . '> Enable GPT-4 generated descriptions';
}, 'pss-settings', 'pss_settings_section' );

    // AI Integration
    add_settings_section( 'pss_settings_section', 'AI Integration', '__return_null', 'pss-settings' );
    add_settings_field( 'pss_openai_api_key', 'OpenAI API Key', function(){
        $key = esc_attr( get_option('pss_openai_api_key') );
        echo "<input type='text' name='pss_openai_api_key' value='$key' class='regular-text' />";
        echo "<p class='description'>Used for AI-powered product description generation (GPT-4).</p>";
    }, 'pss-settings', 'pss_settings_section' );

    // Debug
    add_settings_section( 'pss_debug_section', 'Debug & Diagnostics', '__return_null', 'pss-settings' );
    add_settings_field( 'pss_debug_mode', 'Enable Debug Logging', function(){
        $v = get_option('pss_debug_mode','no');
        echo '<select name="pss_debug_mode">'
           . '<option value="no" '.selected($v,'no',false).'>No</option>'
           . '<option value="yes" '.selected($v,'yes',false).'>Yes</option>'
           . '</select>';
        echo "<p class='description'>Logs WooCommerce import payloads and REST responses to PHP error log.</p>";
    }, 'pss-settings', 'pss_debug_section' );

    // Woo Credentials
    add_settings_section( 'pss_wc_api_section', 'WooCommerce API Credentials', '__return_null', 'pss-settings' );
    add_settings_field( 'pss_wc_consumer_key', 'Consumer Key', function(){
        $k = esc_attr( get_option('pss_wc_consumer_key') );
        echo "<input type='text' name='pss_wc_consumer_key' value='$k' class='regular-text' />";
    }, 'pss-settings', 'pss_wc_api_section' );
    add_settings_field( 'pss_wc_consumer_secret', 'Consumer Secret', function(){
        $s = esc_attr( get_option('pss_wc_consumer_secret') );
        echo "<input type='password' name='pss_wc_consumer_secret' value='$s' class='regular-text' />";
    }, 'pss-settings', 'pss_wc_api_section' );
});

// Sanitize category map 
function pss_sanitize_category_map($in){
    return is_array($in) ? array_map('sanitize_text_field',$in) : [];
}

// Global Toggle for AI use - Will extend in future for per supplier toggles
function pss_ai_enabled_for(): bool {
    return (bool) get_option('pss_enable_ai_descriptions', false);
}


// Menu
add_action('admin_menu', function() {
    add_menu_page(
        'Supplier Tools',
        'Supplier Tools',
        'manage_woocommerce',
        'product-scraper',
        'pss_render_admin_page',
        'dashicons-admin-tools',
        56
    );

    add_submenu_page(
        'product-scraper',
        'Scraper',
        'Scraper',
        'manage_woocommerce',
        'product-scraper',
        'pss_render_admin_page'
    );

    add_submenu_page(
        'product-scraper',
        'Settings',
        'Settings',
        'manage_woocommerce',
        'pss-settings',
        'pss_render_settings_page'
    );

    add_submenu_page(
        'product-scraper',
        'CSV Sync',
        'CSV Sync',
        'manage_woocommerce',
        'csv-sync',
        'pss_csv_sync_settings_page'
    );

    add_submenu_page(
        'product-scraper',                  // Parent slug
        'Supplier Category Mapper',         // Page title
        'Category Mapper',                  // Menu title
        'manage_options',                   // Capability
        'pss-category-map',                 // Menu slug
        'pss_render_category_mapper_page'   // Callback
    );
});

//Category Mapper Page
function pss_render_category_mapper_page() {
    echo '<div class="wrap"><h1>Supplier Category Mapper</h1>';

    $all = get_option('pss_saved_scraper_categories', []);
    $categories = isset($all['aquafax']) ? ['aquafax' => $all['aquafax']] : [];

    $map_path = WP_CONTENT_DIR . '/uploads/pss-maps/aquafax.json';
    $savedMap = file_exists($map_path) ? json_decode(file_get_contents($map_path), true) : [];


    echo '<form method="post">';
    echo '<table class="widefat fixed" style="width:100%; max-width:1000px">';
    echo '<thead><tr><th>Supplier</th><th>Category Path</th><th>Algolia Index</th><th>Filter String</th></tr></thead><tbody>';

    foreach ($categories as $supplier => $entries) {
        foreach ($entries as $entry) {
            $url = rtrim(parse_url($entry['url'], PHP_URL_PATH), '/');
            if (!$url) continue;

            $key = $url;
            $index = esc_attr($savedMap[$key]['index'] ?? 'production_aquafax');
            $filter = esc_attr($savedMap[$key]['filter'] ?? '');

            echo '<tr>';
            echo '<td>' . esc_html($supplier) . '</td>';
            echo '<td><code>' . esc_html($url) . '</code></td>';
            echo '<td><input type="text" name="mapping[' . esc_attr($key) . '][index]" value="' . $index . '" style="width:100%"></td>';
            echo '<td><input type="text" name="mapping[' . esc_attr($key) . '][filter]" value="' . $filter . '" style="width:100%"></td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    submit_button('Save Mappings');
    echo '</form></div>';
}

// Decription Rating
function pss_maybe_rewrite_description($description, $product_data, $openai_api_key) {
    if (empty($description)) {
        // Nothing to evaluate—generate from scratch
        return pss_generate_ai_description($product_data, $openai_api_key);
    }

    // Build prompt to rate existing description
    $rating_prompt = "Rate the following WooCommerce product description on a scale of 1–10 for clarity, usefulness, and originality. "
                   . "Respond with a single number only. No explanation.\n\n"
                   . "Description:\n\"{$description}\"";

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $openai_api_key,
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a product copy editor.'],
                ['role' => 'user', 'content' => $rating_prompt]
            ],
            'max_tokens' => 5,
            'temperature' => 0.0,
        ])
    ]);

    $rating = 10; // Default to 10 in case of failure
    if (!is_wp_error($response) && isset($response['body'])) {
        $json = json_decode($response['body'], true);
        $raw = trim($json['choices'][0]['message']['content'] ?? '');
        $rating = (int) filter_var($raw, FILTER_SANITIZE_NUMBER_INT);
    }

    // If rating is 6 or lower, rewrite it
    if ($rating <= 6) {
        return pss_generate_ai_description($product_data, $openai_api_key);
    }

    return $description;
}



function pss_generate_ai_description($p, $api) {
    if (empty($api)) {
        error_log('❌ No OpenAI API key provided.');
        return '';
    }

    $title    = $p['title']    ?? '';
    $cat      = $p['category'] ?? '';
    $brand    = $p['brand']    ?? '';
    $sku      = $p['sku']      ?? '';
    $price    = $p['price']    ?? '';
    $stock    = $p['stock']    ?? '';
    $variants = '';

    if (!empty($p['variants']) && is_array($p['variants'])) {
        $labels = array_column($p['variants'], 'label');
        $variants = implode(', ', $labels);
    }

    $specs = '';
    if (is_array($p['specs'] ?? null)) {
        $specs = implode(', ', array_filter($p['specs']));
    } elseif (is_string($p['specs'] ?? null)) {
        $specs = $p['specs'];
    }

    // Build clean, AI-friendly prompt
    $prompt = "You're writing a WooCommerce product description for a marine equipment store.\n\n"
            . "Write a concise 2–3 sentence paragraph for the following product. Focus on key specifications, use case, and practical benefits.\n"
            . "Avoid generic phrases, vague adjectives (like 'innovative', 'premium', 'high-quality'), or any marketing fluff. Be specific, helpful, and grounded in the provided data.\n\n"
            . "Focus on use-case scenarios and beneficial features that tie into those use cases, specifically as they apply to marine applications.\n"
            . "Product Title: {$title}\n"
            . (!is_numeric($cat) && $cat ? "Category: {$cat}\n" : '')
            . ($brand    ? "Brand: {$brand}\n" : '')
            . "SKU: {$sku}\n"
            . ($specs    ? "Key Specs: {$specs}\n" : '')
            . ($variants ? "Available in: {$variants}\n" : '')
            . ($price    ? "Price: £{$price}\n" : '')
            . ($stock !== '' ? "Stock: {$stock} units\n" : '');

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api,
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode([
            'model'    => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You write concise product descriptions for an ecommerce site selling marine equipment.'],
                ['role' => 'user',   'content' => $prompt]
            ],
            'max_tokens'  => 150,
            'temperature' => 0.7
        ])
    ]);

    if (is_wp_error($response)) {
        error_log('❌ AI request failed: ' . $response->get_error_message());
        return '';
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($body['choices'][0]['message']['content'])) {
        error_log('⚠️ Unexpected AI response structure');
        return '';
    }

    $result = trim($body['choices'][0]['message']['content']);
    error_log('✅ AI description generated: ' . mb_substr($result, 0, 100) . '...');
    return $result;
}



// Save the array of scraped products to a transient
add_action('wp_ajax_pss_save_scraped_products', function(){
    check_ajax_referer('pss_scraper_nonce','security');

    if ( ! current_user_can('manage_woocommerce') ) {
        wp_send_json_error(['message'=>'Permission denied'], 403);
    }

    // 1) Try form-encoded first
    $raw = stripslashes( $_POST['products'] ?? '' );

    // 2) If empty, grab the raw JSON payload
    if ( empty( $raw ) ) {
        $input = file_get_contents('php://input');
        $body  = json_decode( $input, true );
        if ( isset( $body['products'] ) ) {
            // re-encode just the products array so it matches your existing logic
            $raw = json_encode( $body['products'] );
        }
    }

    // Decode and validate
    $data = json_decode( $raw, true );
    if ( ! is_array( $data ) ) {
        wp_send_json_error([ 'message' => 'Invalid products payload' ], 400 );
        
    }

    // Persist for later use
    set_transient( 'pss_last_scraped_products', $data, HOUR_IN_SECONDS );

    wp_send_json_success([ 'saved_count' => count( $data ) ]);
    
});



// Retrieve that transient when needed
add_action('wp_ajax_pss_get_last_scraped_products', function(){
    check_ajax_referer('pss_scraper_nonce','security');
    if(!current_user_can('manage_woocommerce')) {
        wp_send_json_error();
    }
    $data = get_transient('pss_last_scraped_products') ?: [];
    wp_send_json_success($data);
});

/**
 * AJAX: cache scraped products on the server
 */
add_action( 'wp_ajax_pss_save_scraped_products', function() {
    // Security & permissions
    check_ajax_referer( 'pss_scraper_nonce', 'security' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Permission denied' );
    }

    // Read the raw JSON body
    $body = file_get_contents( 'php://input' );
    $data = json_decode( $body, true );

    if ( empty( $data['products'] ) || ! is_array( $data['products'] ) ) {
        wp_send_json_error( 'No products to save' );
    }

    // Store them in a transient for 10 minutes keyed to the current user
    $user_id = get_current_user_id();
    set_transient( "pss_scraped_products_{$user_id}", $data['products'], 10 * MINUTE_IN_SECONDS );

    wp_send_json_success();
} );



add_action( 'wp_ajax_pss_import_products', function() {
    error_log('🚀 Reached pss_import_products');

    check_ajax_referer( 'pss_scraper_nonce', 'security' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( [ 'message' => 'No permission' ] );
    }

    $raw      = stripslashes( $_POST['products'] ?? '[]' );
    $products = json_decode( $raw, true );

    if ( ! is_array( $products ) ) {
        wp_send_json_error( [ 'message' => 'Invalid JSON' ] );
    }

    $enableAI    = get_option('pss_enable_ai_descriptions') === 'yes';
    $apiKey      = get_option('pss_openai_api_key');
    $markup = isset($_POST['markup']) ? floatval($_POST['markup']) : 0.0;
    $supplierSlug = ! empty( $products[0]['supplier'] ) ? sanitize_key( $products[0]['supplier'] ) : '';

    $imported   = [];
    $failed     = [];
    $reportRows = [];

    if ( $supplierSlug ) {
        require_once PSS_PLUGIN_DIR . 'enrichment/class-pss-csv-enricher.php';
        $enricher = new PSS_CSV_Enricher( $supplierSlug );

        foreach ( $products as $p ) {
            try {
                // 🔁 Enrichment
                $p = $enricher->enrich( $p );

                if ( isset( $p['matched_price'] ) ) {
                    $p['price'] = $p['matched_price'];
                }
                if ( isset( $p['matched_stock'] ) ) {
                    $p['stock'] = $p['matched_stock'];
                }

                // 🧠 AI rewrite fallback
                if ( empty($p['description']) && $enableAI && $apiKey ) {
                    $aiDescription = pss_generate_ai_description($p, $apiKey);
                    if ( ! empty($aiDescription) ) {
                        $p['description'] = $aiDescription;
                    }
                }

                // 📄 Log to report
                $reportRows[] = [
                    'sku'         => $p['sku'] ?? '',
                    'title'       => $p['title'] ?? '',
                    'matched'     => $p['matched_status'] ?? 'Unmatched',
                    'price'       => $p['price'] ?? '',
                    'stock'       => $p['stock'] ?? '',
                    'used_ai'     => isset($p['_originalDescription']) ? 'Yes' : 'No',
                    'ai_summary'  => isset($p['_ai_report']) ? json_encode($p['_ai_report']) : '',
                ];

                // 🛒 Create WooCommerce product
                $existing_id = wc_get_product_id_by_sku( $p['sku'] );
                if ( $existing_id ) {
                    $product = wc_get_product( $existing_id );
                } elseif ( ! empty( $p['variants'] ) ) {
                    $product = new WC_Product_Variable();
                    $product->set_sku( $p['sku'] );
                } else {
                    $product = new WC_Product_Simple();
                    $product->set_sku( $p['sku'] );
                }

                if ( $supplierSlug ) {
                    $product->update_meta_data( '_pss_supplier', $supplierSlug );
                }

                $product->set_name( $p['title'] );
                if ( isset( $p['price'] ) ) {
                    $basePrice = floatval( $p['price'] );
                    $retailPrice = round( $basePrice * ( 1 + $markup / 100 ), 2 );
                    $product->set_regular_price( $retailPrice );

                    //Store Original Cost Price
                    $product->update_meta_data( '_pss_base_price', $basePrice );
                }

                
                if ( isset( $p['stock'] ) ) {
                    $product->set_manage_stock( true );
                    $product->set_stock_quantity( $p['stock'] );
                }
                if ( ! empty( $p['description'] ) ) {
                    $product->set_description( $p['description'] );
                }

                $cat = $p['categoryId'] ?? $p['category'] ?? '';
                if ( $cat ) {
                    $product->set_category_ids( [ (int) $cat ] );
                }

                $featured_id = null;
                if ( ! empty( $p['image'] ) ) {
                    error_log("📦 Preparing to sideload image: " . $p['image']);
                    $featured_id = pss_sideload_image($p['image'], 0);
                    error_log("📷 Image ID returned for SKU {$p['sku']}: " . print_r($featured_id, true));
                }

                $gallery_ids = [];
                if ( ! empty( $p['gallery'] ) && is_array( $p['gallery'] ) ) {
                    foreach ( $p['gallery'] as $url ) {
                        $gid = pss_sideload_image($url, 0);
                        if ( ! is_wp_error($gid) ) {
                            $gallery_ids[] = $gid;
                        }
                    }
                }

                $product_id = $product->save();

                if ( $featured_id && ! is_wp_error($featured_id) ) {
                    set_post_thumbnail($product_id, $featured_id);
                }
                if ( $gallery_ids ) {
                    update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
                }

                if ( ! empty($p['supplier']) ) {
                    $supplierSlug = sanitize_title($p['supplier']);
                    if ( taxonomy_exists('supplier') ) {
                        $term = term_exists($supplierSlug, 'supplier');
                        if ( ! is_array($term) ) {
                            $term = wp_insert_term($supplierSlug, 'supplier');
                        }
                        if ( ! is_wp_error($term) && ! empty($term['term_id']) ) {
                            wp_set_object_terms($product->get_id(), (int) $term['term_id'], 'supplier', false);
                        }
                    }
                }

                if ( ! empty( $p['variants'] ) && $product instanceof WC_Product_Variable ) {
                    $parent_id = $product->save();
                    foreach ( $p['variants'] as $v ) {
                        $variation = new WC_Product_Variation();
                        $variation->set_parent_id( $parent_id );
                        $variation->set_sku( $v['sku'] );
                        $base = floatval( $v['price'] ?? 0 );
                        $retail = round( $base * ( 1 + $markup / 100 ), 2 );
                        $variation->set_regular_price( $retail );

                        $variation->save();
                    }
                }

                $imported[] = [
                    'id'    => $product->get_id(),
                    'sku'   => $p['sku'],
                    'title' => $p['title'],
                ];

            } catch ( \Exception $e ) {
                $failed[] = [
                    'title'  => $p['title'] ?? '(no title)',
                    'reason' => $e->getMessage(),
                ];
            }
        }
    } else {
        error_log('⚠️ Supplier slug missing — enrichment skipped');
    }

    if ( $failed ) {
        error_log( "🛑 Import failures: " . print_r( $failed, true ) );
    }

    if ( ! empty($reportRows) ) {
        $reportFile = PSS_PLUGIN_DIR . 'logs/ai_enrichment_report_' . time() . '.csv';
        if ( ! file_exists(dirname($reportFile)) ) {
            mkdir(dirname($reportFile), 0755, true);
        }
        $fp = fopen($reportFile, 'w');
        fputcsv($fp, array_keys($reportRows[0]));
        foreach ( $reportRows as $row ) {
            fputcsv($fp, $row);
        }
        fclose($fp);
        error_log("📝 AI enrichment report written to $reportFile");
    }

    wp_send_json_success( [
        'imported' => $imported,
        'failed'   => $failed,
    ] );
});

//Enqueue client scripts & expose data
 
add_action('admin_footer', function(){
    if( ! is_admin() ) return;

    // engines
    $engines = [];
    $dir = WP_CONTENT_DIR.'/uploads/supplier-engines/';
    if( file_exists($dir) ){
        foreach( glob($dir.'*.js') as $f ){
            $slug = basename($f,'.js');
            $engines[] = ['slug'=>$slug,'name'=>ucwords(str_replace('-',' ',$slug))];
        }
    }
    if( empty($engines) ){
        echo "<script>console.warn('⚠️ No supplier engines found');</script>";
    }

    // categories
    $cats = get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]);
    $opts = [];
    if( ! is_wp_error($cats) ){
        foreach($cats as $c){
            $opts[] = ['id'=>$c->term_id,'name'=>$c->name];
        }
    }

    $data = [
        'ajaxUrl'       => admin_url('admin-ajax.php'),
        'engineBaseUrl' => plugin_dir_url(__FILE__).'assets/engines/',
        'pluginUrl'     => plugin_dir_url(__FILE__),
        'security'      => wp_create_nonce('pss_scraper_nonce'),
        'suppliers'     => $engines,
        'categories'    => $opts,
        'savedCategories'=>get_option('pss_saved_scraper_categories',[]),
        'categoryMap'   => get_option('pss_category_map',[]),
    ];
    //echo '<script>window.pssScraperData='.json_encode($data).';</script>';
    //echo '<script type="module" src="'.plugin_dir_url(__FILE__).'assets/js/browser-importer.js?v='.time().'"></script>';
}, 99 );

/**
 * AJAX: fetch Woo categories
 */

 add_action( 'wp_ajax_pss_get_wc_categories', function(){
    check_ajax_referer( 'pss_scraper_nonce', 'security' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error();
    }

    $terms = get_terms( [
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ] );

    if ( is_wp_error( $terms ) ) {
        wp_send_json_error( [ 'message' => 'Could not fetch categories' ] );
    }

    $cats = [];
    foreach ( $terms as $term ) {
        // — use object properties, NOT array keys
        $cats[] = [
            'id'   => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        ];
    }

    wp_send_json_success( $cats );
    // wp_send_json_* already ends execution, no extra wp_die() needed here
} );


add_action('wp_ajax_pss_save_scraped_products', function() {
    check_ajax_referer('pss_scraper_nonce', 'security');

    if (! current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }

    // Read raw JSON body
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (! isset($data['products']) || ! is_array($data['products'])) {
        wp_send_json_error(['message' => 'Invalid payload']);
    }

    // Store in a transient for, say, 12 hours
    set_transient('pss_last_scraped_products', $data['products'], 12 * HOUR_IN_SECONDS);

    wp_send_json_success();
});


/**
 * AJAX: proxy scrape
 */
add_action('wp_ajax_pss_proxy_scrape', function(){
    $nonce = $_REQUEST['security'] ?? '';
    if( ! wp_verify_nonce($nonce,'pss_scraper_nonce') ){
        wp_send_json_error('Invalid security nonce');
    }
    $url = esc_url_raw($_REQUEST['url'] ?? '');
    if( ! $url ) wp_send_json_error('Missing URL');

    $host = wp_parse_url($url, PHP_URL_HOST);
    if( strpos($host,'aquafax.co.uk')!==false ){
        wp_send_json_error(['error'=>'Aquafax requires iframe scraping.']);
    }
    $headers = [];
    if( strpos($host,'lewmar.com')!==false ){
        $headers = [
            'User-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64)...',
            'Accept'=>'application/json,text/html;q=0.9,*/*;q=0.8'
        ];
    }
    $r = wp_remote_get($url,['timeout'=>20,'headers'=>$headers]);
    echo wp_remote_retrieve_body($r);
});

add_action('wp_ajax_pss_save_price_modifier', function() {
    check_ajax_referer('pss_scraper_nonce', 'security');

    $supplier = sanitize_key($_POST['supplier'] ?? '');
    $modifier = floatval($_POST['modifier'] ?? 0);

    $all = get_option('pss_supplier_markups', []);
    $all[$supplier] = $modifier;

    update_option('pss_supplier_markups', $all);

    wp_send_json_success(['message' => "Modifier for $supplier saved."]);
});


//Legacy Tools Debug
/*add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',                      // Parent menu
        'Supplier Sync Debug',           // Page title
        'Supplier Sync Debug',           // Menu title
        'manage_options',                // Capability
        'supplier-sync-debug',           // Slug
        'pss_render_sync_debug_page'     // Callback
    );
});
/*

function pss_render_sync_debug_page() {
    echo '<div class="wrap"><h1>Supplier Sync Debug</h1>';

    $suppliers = ['Seago', 'Timage']; // extend this list as needed

    // Determine which supplier is selected
    $selected = sanitize_text_field($_POST['supplier'] ?? 'Seago');

    // Ensure the CSV Enricher class is available
    $enricher_path = plugin_dir_path(__FILE__) . 'enrichment/class-pss-csv-enricher.php';
    if (!class_exists('PSS_CSV_Enricher')) {
        if (file_exists($enricher_path)) {
            require_once $enricher_path;
            error_log("✅ [Bootstrap] Loaded class-pss-csv-enricher.php");
        } else {
            error_log("❌ [Bootstrap] class-pss-csv-enricher.php missing at: {$enricher_path}");
            echo '<p>❌ Enricher file missing. Check logs.</p>';
            return;
        }
    }

    // If "Run Sync" was triggered
    if (isset($_POST['run_sync'])) {
        echo "<p>⏳ Running sync for <strong>{$selected}</strong>…</p>";

        $enricher = new PSS_CSV_Enricher($selected);
        $success = $enricher->sync();

        echo $success ? '<p>✅ Sync complete!</p>' : '<p>❌ Sync failed. Check error_log.</p>';
    }

    // Build form
    echo '<form method="POST">';
    echo '<label for="supplier">Select Supplier:</label> ';
    echo '<select name="supplier" id="supplier">';
    foreach ($suppliers as $s) {
        $sel = selected($selected, $s, false);
        echo "<option value=\"{$s}\" {$sel}>{$s}</option>";
    }
    echo '</select> ';
    submit_button('Run Sync', 'primary', 'run_sync');
    echo '</form>';

    // Display path info
    $opt_key = 'pss_' . strtolower($selected) . '_xlsx_path';
    $csv_path = get_option($opt_key);

    echo '<hr>';
    echo "<p><strong>{$selected} XLSX path:</strong> {$csv_path}</p>";
    echo "<p><strong>File exists:</strong> " . (file_exists($csv_path) ? '✅ Yes' : '❌ No') . "</p>";
    echo "<p><strong>Size:</strong> " . (file_exists($csv_path) ? filesize($csv_path) : 'n/a') . " bytes</p>";
    echo '</div>';
}

// Helper Utility for Algolia Category Mapping

add_action('admin_menu', function () {
  add_submenu_page(
    'tools.php',
    'Aquafax Category Mapper',
    'Aquafax Mapper',
    'manage_options',
    'aquafax-mapper',
    'pss_render_aquafax_mapper'
  );
});

function pss_render_aquafax_mapper() {
  echo '<div class="wrap"><h1>Aquafax Category Mapper</h1>';
  echo '<h2>Debug: Raw Saved Categories</h2>';
    echo '<pre>';
    print_r(get_option('pss_saved_scraper_categories'));
    echo '</pre>';


  if (isset($_POST['pss_save_map']) && check_admin_referer('pss_save_map')) {
    $map = array_filter(array_map('sanitize_text_field', $_POST['algolia_map'] ?? []));
    update_option('pss_category_map', $map);
    echo '<div class="notice notice-success"><p>Mapping saved.</p></div>';
  }

  $saved = get_option('pss_saved_scraper_categories', []);
$categories = [];




if (!empty($saved['aquafax']) && is_array($saved['aquafax'])) {
    foreach ($saved['aquafax'] as $entry) {
        if (!empty($entry['url'])) {
            $path = wp_parse_url($entry['url'], PHP_URL_PATH);
            if ($path && substr_count($path, '/') >= 1) {
                $categories[] = rtrim($path, '/');
            }
        }
    }
}

$categories = array_unique($categories); // Avoid duplicates

  $savedMap   = get_option('pss_category_map', []);

  echo '<form method="post">';
  wp_nonce_field('pss_save_map');
  echo '<table class="widefat"><thead><tr><th>Category Path</th><th>Algolia Category</th></tr></thead><tbody>';

  foreach ($categories as $path) {
    $value = esc_attr($savedMap[$path] ?? '');
    echo "<tr>
            <td><code>{$path}</code></td>
            <td><input type='text' name='algolia_map[{$path}]' value='{$value}' style='width:100%'></td>
          </tr>";
  }

  echo '</tbody></table>';
  echo '<p><input type="submit" class="button-primary" name="pss_save_map" value="Save Mapping"></p>';
  echo '</form></div>';
}

//Leagacy Sitemap Fetch
/*function pss_fetch_aquafax_sitemap_urls() {
  $html = wp_remote_retrieve_body(wp_remote_get('https://www.aquafax.co.uk/sitemap'));
  if (empty($html)) return [];

  libxml_use_internal_errors(true);
  $dom = new DOMDocument();
  @$dom->loadHTML($html);
  libxml_clear_errors();

  $xpath = new DOMXPath($dom);
  $nodes = $xpath->query("//a[contains(@href, 'aquafax.co.uk')]");

  $categories = [];
  foreach ($nodes as $node) {
    $href = $node->getAttribute('href');
    $path = wp_parse_url($href, PHP_URL_PATH);
    if ($path && substr_count($path, '/') > 1 && strpos($path, '/products/') === false) {
      $categories[] = rtrim($path, '/');
    }
  }

  return array_unique($categories);
}*/

// 🔐 Safely enqueue + localize script with category map and nonce
add_action('admin_enqueue_scripts', function ($hook) {
  if (
    $hook !== 'tools_page_aquafax-mapper' &&
    strpos($hook, 'product-scraper') === false
  ) return;

  wp_enqueue_script(
    'papaparse',
    'https://cdn.jsdelivr.net/npm/papaparse@5.4.1/papaparse.min.js',
    [],
    '5.4.1',
    true
  );

  wp_enqueue_script(
    'pss-browser-importer',
    plugin_dir_url(__FILE__) . 'assets/js/browser-importer.js',
    ['jquery'],
    null,
    true
  );

  $engine_base = plugins_url('assets/engines/', __FILE__);

  $data = [
    'ajaxUrl'       => admin_url('admin-ajax.php'),
    'security'      => wp_create_nonce('pss_scraper_nonce'),
    'engineBaseUrl' => trailingslashit($engine_base),
    'suppliers'     => pss_get_supplier_list(),
    'categoryMap' => file_exists(WP_CONTENT_DIR . '/uploads/pss-maps/aquafax.json')
    ? json_decode(file_get_contents(WP_CONTENT_DIR . '/uploads/pss-maps/aquafax.json'), true)
    : []
  ];

  wp_localize_script('pss-browser-importer', 'pssScraperData', $data);
});


function pss_get_supplier_list() {
  return [
    [ 'slug' => 'aquafax', 'name' => 'Aquafax' ],
    [ 'slug' => 'lewmar',  'name' => 'Lewmar' ],
    [ 'slug' => 'seago',   'name' => 'Seago' ],
    [ 'slug' => 'timage',  'name' => 'Timage' ]
  ];
}

add_action('wp_ajax_pss_save_scraper_category', 'pss_save_scraper_category');
function pss_save_scraper_category() {
    check_ajax_referer('pss_scraper_nonce', 'security');

    $supplier = sanitize_text_field($_POST['supplier'] ?? '');
    $name     = sanitize_text_field($_POST['name'] ?? '');
    $url      = esc_url_raw($_POST['url'] ?? '');

    if (!$supplier || !$name || !$url) {
        wp_send_json_error('Missing data');
    }

    $existing = get_option('pss_saved_scraper_categories', []);
    $existing[$supplier] = $existing[$supplier] ?? [];

    // Avoid duplicates
    foreach ($existing[$supplier] as $entry) {
        if ($entry['url'] === $url) {
            wp_send_json_success(['saved' => $existing[$supplier]]);
        }
    }

    $existing[$supplier][] = ['name' => $name, 'url' => $url];
    update_option('pss_saved_scraper_categories', $existing);

    wp_send_json_success(['saved' => $existing[$supplier]]);
}











