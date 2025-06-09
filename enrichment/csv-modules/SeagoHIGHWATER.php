<?php
require_once PSS_PLUGIN_DIR . 'enrichment/pss-load-phpspreadsheet.php';

class Seago_Enricher {
    public $products = [];

    public function __construct() {
        $path = get_option('pss_seago_xlsx_path');
        error_log("🧭 [Seago_Enricher] XLSX path resolved to: {$path}");

        if (!$path || !file_exists($path)) {
            error_log("❌ [Seago_Enricher] File not found at {$path}");
            return;
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            foreach ($rows as $i => $row) {
                if ($i === 0 || empty($row[3]) || empty($row[4])) continue;

                $title = trim($row[3]);     // Column D = Product Name
                $raw_sku = trim($row[4]);   // Column E = MP (SKU)
                $sku_key = $this->normalize_sku($raw_sku);

                $this->products[$sku_key] = [
                    'title'   => $title,
                    'sku'     => $raw_sku,
                    'price'   => $row[11] ?? '',
                    'option1' => $row[6] ?? '',
                    'option2' => $row[7] ?? '',
                    'option3' => $row[8] ?? '',
                ];

                error_log("🔑 [Seago_Enricher] Registered SKU key: {$sku_key}");
            }

            error_log("✅ [Seago_Enricher] Loaded " . count($this->products) . " products.");
        } catch (Throwable $e) {
            error_log("❌ [Seago_Enricher] Error reading XLSX: " . $e->getMessage());
        }
    }

    public function enrich($row) {
        $incoming_title = $row['title'] ?? '';
        $normalized_title_key = $this->normalize_sku($incoming_title);
        error_log("🧪 [Seago_Enricher] enrich() called with normalized title: {$normalized_title_key}");

        // Try direct match with normalized product title
        if (isset($this->products[$normalized_title_key])) {
            $data = $this->products[$normalized_title_key];
            error_log("✅ [Seago_Enricher] Direct title match found for: {$normalized_title_key}");
        }
        // Try reordered numeric-prefix version
        else {
            $reordered_key = $this->reorder_title_digits($incoming_title);
            $normalized_reordered_key = $this->normalize_sku($reordered_key);
            error_log("🔁 [Seago_Enricher] Trying reordered key: {$normalized_reordered_key}");

            if (isset($this->products[$normalized_reordered_key])) {
                $data = $this->products[$normalized_reordered_key];
                error_log("✅ [Seago_Enricher] Matched on reordered key: {$normalized_reordered_key}");
            }
        }

        if (!isset($data)) {
            error_log("❌ [Seago_Enricher] No match found for: {$normalized_title_key}");
            return $row;
        }

        // Apply enrichment
        $row['price']   = $data['price'];
        $row['option1'] = $data['option1'];
        $row['option2'] = $data['option2'];
        $row['option3'] = $data['option3'];

        return $row;
    }

    private function normalize_sku($value) {
        $value = strtolower(trim($value));
        $value = str_replace(['&', '+', '_'], '-', $value);
        $value = preg_replace('/[^a-z0-9]/i', '-', $value);
        $value = preg_replace('/-+/', '-', $value);
        return trim($value, '-');
    }

    private function reorder_title_digits($title) {
        // Match trailing digits
        if (preg_match('/^(.*?)(\d+)\s*$/', $title, $matches)) {
            $prefix = trim($matches[1]);
            $digits = trim($matches[2]);
            return "{$digits} {$prefix}";
        }

        return $title;
    }
}
