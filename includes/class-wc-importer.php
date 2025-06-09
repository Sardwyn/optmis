<?php

class PSS_WC_Importer {

    public function import_product($product) {
        // Create the WooCommerce product post
        $post_id = wp_insert_post([
            'post_type'    => 'product',
            'post_status'  => 'publish',
            'post_title'   => $product['title'] ?? 'Untitled Product',
            'post_content' => $product['description'] ?? '',
            'post_excerpt' => $product['short_description'] ?? '',
        ]);
    
        if (is_wp_error($post_id)) {
            error_log('❌ Failed to insert product: ' . $product['title']);
            return;
        }
    
        // Set featured image if available
        if (!empty($product['image'])) {
            $this->attach_image_to_product($post_id, $product['image']);
        }
    
        // Set product price (placeholder logic)
        if (!empty($product['price']) && is_numeric($product['price'])) {
            update_post_meta($post_id, '_regular_price', $product['price']);
            update_post_meta($post_id, '_price', $product['price']);
        }
    
        // Set SKU
        if (!empty($product['sku'])) {
            update_post_meta($post_id, '_sku', sanitize_text_field($product['sku']));
        }
    
        // Save brand and supplier
        if (!empty($product['brand'])) {
            update_post_meta($post_id, '_brand', sanitize_text_field($product['brand']));
        }
        if (!empty($product['supplier'])) {
            update_post_meta($post_id, '_supplier', sanitize_text_field($product['supplier']));
        }
    
        // Save _oym_signature for matching
        if (!empty($product['_oym_signature']) && is_array($product['_oym_signature'])) {
            update_post_meta($post_id, '_oym_signature', $product['_oym_signature']);
            error_log("✅ Saved _oym_signature for product ID $post_id: " . json_encode($product['_oym_signature']));
        }
    
        // Assign category if set
        if (!empty($product['category_id'])) {
            wp_set_post_terms($post_id, [(int) $product['category_id']], 'product_cat');
        }
    }
    
}