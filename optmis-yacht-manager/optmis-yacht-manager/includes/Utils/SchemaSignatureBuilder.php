<?php
namespace Optmis_Yacht_Manager\Utils;

defined('ABSPATH') || exit;

use WC_Product;
use Optmis_Yacht_Manager\Utils\SchemaMapper;
use Optmis_Yacht_Manager\Utils\ProductDataNormalizer;

class SchemaSignatureBuilder {

    /**
     * Build a product's compatibility signature based on its schema role.
     *
     * @param int \$product_id WooCommerce product ID
     * @return array|null Normalized signature or null if category/role/schema missing
     */
    public static function build_signature_for_product(\$product_id): ?array
    {
        \$product = wc_get_product(\$product_id);
        if (!\$product instanceof WC_Product) return null;

        \$terms = wp_get_post_terms(\$product_id, 'product_cat', ['fields' => 'slugs']);
        if (empty(\$terms)) return null;

        // Use the first category slug to find system role
        \$role = SchemaMapper::get_system_role_for_category(\$terms[0]);
        if (!\$role) return null;

        \$schema_path = plugin_dir_path(__DIR__) . '/../schemas/' . \$role . '.schema.json';
        if (!file_exists(\$schema_path)) return null;

        \$schema_keys = json_decode(file_get_contents(\$schema_path), true);
        if (!is_array(\$schema_keys)) return null;

        // Normalize product data
        \$raw_spec = ProductDataNormalizer::extract_raw_spec(\$product_id);
        if (!is_array(\$raw_spec)) return null;

        // Match only fields in schema
        \$signature = [];
        foreach (\$schema_keys as \$field) {
            if (isset(\$raw_spec[\$field])) {
                \$signature[\$field] = \$raw_spec[\$field];
            }
        }

        return \$signature;
    }
} 
