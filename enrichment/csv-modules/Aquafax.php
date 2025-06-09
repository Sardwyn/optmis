<?php
class Aquafax_Enricher {

    public $products = [];

    public function __construct() {
        $csv_path = get_option('pss_aquafax_csv_path');

        error_log("👋 [Aquafax_Enricher] File loaded from: " . __FILE__);
        error_log("🧭 [Aquafax_Enricher] CSV path resolved to: {$csv_path}");

        if (!$csv_path || !file_exists($csv_path)) {
            error_log('❌ [Aquafax_Enricher] Missing or invalid CSV path.');
            return;
        }

        $row_count = 0;
        $skipped = 0;

        if (($handle = fopen($csv_path, "r")) !== false) {
            $header = fgetcsv($handle);
            $header = array_map('strtolower', $header);

            error_log("🧾 [Aquafax_Enricher] CSV header: " . implode(', ', $header));

            while (($row = fgetcsv($handle)) !== false) {
                $row_count++;
                if (count($row) !== count($header)) {
                    $skipped++;
                    continue;
                }

                $item = array_combine($header, $row);
                $sku = strtolower(trim($item['sku'] ?? ''));

                if ($sku) {
                    $this->products[$sku] = $item;
                }
            }

            fclose($handle);
            error_log("✅ [Aquafax_Enricher] Loaded {$row_count} rows, skipped {$skipped}");
        } else {
            error_log('❌ [Aquafax_Enricher] Failed to open CSV.');
        }
    }

    public function enrich($row) {
    $sku = strtolower(trim($row['sku'] ?? ''));
    error_log("🧪 [Aquafax_Enricher] enrich() called with SKU: {$sku}");

    if (isset($this->products[$sku])) {
        $data = $this->products[$sku];

        $row['matched_name']   = $data['title'] ?? '';
        $row['matched_price']  = $data['net_price'] ?? '';
        $row['matched_stock']  = $data['group_stock'] ?? '';
        $row['matched_status'] = 'Matched (Aquafax)';

        if (!empty($row['matched_price'])) {
            $row['price'] = $row['matched_price'];
        }
        if (!empty($row['matched_stock'])) {
            $row['stock'] = $row['matched_stock'];
        }

        error_log("✅ [Aquafax_Enricher] Enriched SKU: {$sku} with price={$row['matched_price']}, stock={$row['matched_stock']}");
    } else {
        error_log("❌ [Aquafax_Enricher] No match found for SKU: {$sku}");
    }

    // ✅ Default to AI description unless explicitly disabled
    $row['use_ai_description'] = ! isset($row['use_ai_description']) || filter_var($row['use_ai_description'], FILTER_VALIDATE_BOOLEAN);

    return $row;
}

}
