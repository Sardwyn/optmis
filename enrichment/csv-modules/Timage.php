<?php
require_once PSS_PLUGIN_DIR . 'enrichment/pss-load-phpspreadsheet.php';

class Timage_Enricher {
    public $products = [];

    public function __construct() {
        $path = get_option('pss_timage_xlsx_path');
        error_log("🧭 [Timage_Enricher] XLSX path resolved to: {$path}");

        if (!$path || !file_exists($path)) {
            error_log("❌ [Timage_Enricher] File not found at {$path}");
            return;
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            foreach ($rows as $i => $row) {
                // Skip the first row (notes) and the second row (headers)
                if ($i <= 2 || empty($row['A']) || empty($row['B'])) continue;

                $sku   = trim((string) $row['A']); // Product Code
                $title = trim((string) $row['B']); // Product Description
                $price = trim((string) $row['D']); // Retail Price

                $sku_key   = $this->normalize($sku);
                $title_key = $this->normalize($title);

                $this->products[$sku_key] = [
                    'sku'   => $sku,
                    'title' => $title,
                    'price' => $price,
                ];

                if (!isset($this->products[$title_key])) {
                    $this->products[$title_key] = [
                        'sku'   => $sku,
                        'title' => $title,
                        'price' => $price,
                    ];
                }

                error_log("🔑 [Timage_Enricher] Registered SKU: {$sku_key}, fallback: {$title_key}");
            }

            error_log("✅ [Timage_Enricher] Loaded " . count($this->products) . " products.");
        } catch (Throwable $e) {
            error_log("❌ [Timage_Enricher] Error reading XLSX: " . $e->getMessage());
        }
    }

    public function enrich($row) {
        $raw_sku   = $row['sku']   ?? '';
        $raw_title = $row['title'] ?? '';

        $normalized_sku   = $this->normalize($raw_sku);
        $normalized_title = $this->normalize($raw_title);

        error_log("🧪 [Timage_Enricher] enrich() SKU raw: {$raw_sku}, normalized: {$normalized_sku}");
        error_log("🧪 [Timage_Enricher] enrich() title raw: {$raw_title}, normalized: {$normalized_title}");

        $data = $this->products[$normalized_sku]
              ?? $this->products[$normalized_title]
              ?? null;

        if (!$data) {
            error_log("❌ [Timage_Enricher] No match for SKU: {$normalized_sku} or title: {$normalized_title}");
            return $row;
        }

        error_log("✅ [Timage_Enricher] Match found for enrichment.");
        $row['price'] = $data['price'];
        return $row;
    }

    private function normalize($value) {
        $value = strtolower(trim($value));
        $value = str_replace(['&', '+', '.', '_'], '-', $value);
        $value = preg_replace('/[^a-z0-9]/i', '-', $value);
        return preg_replace('/-+/', '-', trim($value, '-'));
    }

    public function sync() {
        $upload_dir = wp_upload_dir();
        $dest = $upload_dir['basedir'] . '/timage-sync.xlsx';

        $remote = 'https://mcusercontent.com/93c3d8fa2d09551dc8d1b472e/files/fec7a21e-1fea-f4ad-e1fc-9fc852b7381c/Retail_Price_List_2025_FINAL.01.xlsx';

        error_log("🌐 [Timage] Downloading XLSX from: {$remote}");

        try {
            $response = wp_remote_get($remote);
            if (is_wp_error($response)) {
                error_log("❌ [Timage] Fetch failed: " . $response->get_error_message());
                return false;
            }

            $body = wp_remote_retrieve_body($response);
            if (!$body) {
                error_log("❌ [Timage] XLSX body empty");
                return false;
            }

            file_put_contents($dest, $body);
            update_option('pss_timage_xlsx_path', $dest);

            error_log("✅ [Timage] Synced XLSX to: {$dest}");
            return true;
        } catch (Throwable $e) {
            error_log("🔥 [Timage] Sync exception: " . $e->getMessage());
            return false;
        }
    }
}
