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
            $rows = $sheet->toArray(); // Default numeric index

            foreach ($rows as $i => $row) {
                if ($i === 0 || empty($row[0]) || empty($row[1])) continue;

                $sku   = trim($row[0]); // Product Code
                $title = trim($row[1]); // Product Description
                $price = trim($row[3]); // Retail Price excl. VAT

                $key = $this->normalize($sku);
                $fallback_key = $this->normalize($title);

                $this->products[$key] = [
                    'sku'   => $sku,
                    'title' => $title,
                    'price' => $price,
                ];

                if (!isset($this->products[$fallback_key])) {
                    $this->products[$fallback_key] = [
                        'sku'   => $sku,
                        'title' => $title,
                        'price' => $price,
                    ];
                }

                error_log("🔑 [Timage_Enricher] Registered SKU: {$key}, fallback: {$fallback_key}");
            }

            error_log("✅ [Timage_Enricher] Loaded " . count($this->products) . " products.");
            error_log("📦 Final product keys: " . implode(', ', array_keys($this->products)));

        } catch (Throwable $e) {
            error_log("❌ [Timage_Enricher] Error reading XLSX: " . $e->getMessage());
        }
    }

    public function enrich($row) {
        $incoming_sku   = $this->normalize($row['sku'] ?? '');
        $incoming_title = $this->normalize($row['title'] ?? '');

        error_log("🧪 [Timage_Enricher] enrich() SKU raw: {$row['sku']}, normalized: {$incoming_sku}");
        error_log("🧪 [Timage_Enricher] enrich() title raw: {$row['title']}, normalized: {$incoming_title}");

        $data = $this->products[$incoming_sku]
            ?? $this->products[$incoming_title]
            ?? null;

        if (!$data) {
            error_log("❌ [Timage_Enricher] No match for SKU: {$incoming_sku} or title: {$incoming_title}");
            return $row;
        }

        error_log("✅ [Timage_Enricher] Matched on SKU or fallback title");

        $row['price'] = $data['price'];
        return $row;
    }

    private function normalize($v) {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $v)));
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
