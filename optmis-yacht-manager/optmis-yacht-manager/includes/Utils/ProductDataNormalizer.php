<?php
namespace Optmis_Yacht_Manager\Utils;

defined('ABSPATH') || exit;

class ProductDataNormalizer
{
    /**
     * Main dispatcher: attempts to extract raw spec from the best available source.
     *
     * @param int \$product_id
     * @return array|null associative array of normalized fields
     */
    public static function extract_raw_spec(int \$product_id): ?array
    {
        // Return cached normalized spec if available
        \$cached = get_post_meta(\$product_id, '_oym_spec_signature', true);
        if (\$cached && is_string(\$cached)) {
            \$decoded = json_decode(\$cached, true);
            if (is_array(\$decoded)) return \$decoded;
        }

        // Priority: ExternalImporter
        if (metadata_exists('post', \$product_id, '_ei_product')) {
            \$spec = self::extract_from_external_importer(\$product_id);
            if (!empty(\$spec)) return \$spec;
        }

        // Future: Check for known CSV-based keys
        if (metadata_exists('post', \$product_id, '_ei_import_source')) {
            \$spec = self::extract_from_csv_import(\$product_id);
            if (!empty(\$spec)) return \$spec;
        }

        // Future: Check for PSS imports
        if (metadata_exists('post', \$product_id, 'pss_scraper_source')) {
            \$spec = self::extract_from_scraper(\$product_id);
            if (!empty(\$spec)) return \$spec;
        }

        // Fallback: manual Woo fields or ACF
        \$spec = self::extract_from_manual(\$product_id);
        return !empty(\$spec) ? \$spec : null;
    }

    /**
     * Extract structured data from ExternalImporter object stored in `_ei_product`
     */
    public static function extract_from_external_importer(int \$product_id): ?array
    {
        \$raw = get_post_meta(\$product_id, '_ei_product', true);
        if (!is_string(\$raw) || strpos(\$raw, 'ExternalImporter') === false) return null;

        \$spec = [];
        try {
            \$obj = @unserialize(\$raw);
            if (!is_object(\$obj)) return null;

            // Map fields to internal structure
            if (!empty(\$obj->description)) {
                \$spec['description'] = wp_strip_all_tags(\$obj->description);
            }
            if (!empty(\$obj->price)) {
                \$spec['price'] = \$obj->price;
            }
            if (!empty(\$obj->sku)) {
                \$spec['sku'] = \$obj->sku;
            }
            if (!empty(\$obj->title)) {
                \$spec['title'] = \$obj->title;
            }
            if (!empty(\$obj->manufacturer)) {
                \$spec['brand'] = \$obj->manufacturer;
            }
            // Add other mapped fields as needed

        } catch (\Throwable \$e) {
            error_log("[OYM] Failed to unserialize _ei_product: " . \$e->getMessage());
            return null;
        }

        return !empty(\$spec) ? \$spec : null;
    }

    /**
     * Placeholder for CSV import logic
     */
    public static function extract_from_csv_import(int \$product_id): ?array
    {
        return null; // TODO: implement
    }

    /**
     * Placeholder for scraper-based (PSS) extraction
     */
    public static function extract_from_scraper(int \$product_id): ?array
    {
        return null; // TODO: implement
    }

    /**
     * Placeholder for fallback/manual fields or ACF
     */
    public static function extract_from_manual(int \$product_id): ?array
    {
        return null; // TODO: implement
    }
} 
