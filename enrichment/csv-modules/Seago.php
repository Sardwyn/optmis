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

                $title    = trim($row[3]);   // Column D = Product Name
                $raw_sku  = trim($row[4]);   // Column E = MP
                $sku_key  = $this->normalize_sku($raw_sku);

                $product = [
                    'title'   => $title,
                    'sku'     => $raw_sku,
                    'mp'      => $raw_sku,
                    'price'   => $row[11] ?? '',
                    'option1' => $row[6] ?? '',
                    'option2' => $row[7] ?? '',
                    'option3' => $row[8] ?? '',
                ];

                $this->products[$sku_key] = $product;

                // Add compact + mp variations
                $this->products[$this->compact_key($raw_sku)] = $product;

                error_log("🔑 [Seago_Enricher] Registered SKU key: {$sku_key}");
            }

            error_log("✅ [Seago_Enricher] Loaded " . count($this->products) . " products.");
        } catch (Throwable $e) {
            error_log("❌ [Seago_Enricher] Error reading XLSX: " . $e->getMessage());
        }
    }

    public function enrich($row) {
        $incoming_title = $row['title'] ?? '';
        $normalized = $this->normalize_sku($incoming_title);
        error_log("🧪 [Seago_Enricher] enrich() called with normalized title: {$normalized}");

        $matched = $this->match_product($incoming_title, $normalized);
        if (!$matched) {
            error_log("❌ [Seago_Enricher] No match found for: {$normalized}");
            return $row;
        }

        // Apply enrichment
        $row['price']   = $matched['price'];
        $row['option1'] = $matched['option1'];
        $row['option2'] = $matched['option2'];
        $row['option3'] = $matched['option3'];

        return $row;
    }

    private function match_product($title, $normalized) {
        $compact = $this->compact_key($normalized);

        // 1. Direct match
        if (isset($this->products[$normalized])) {
            error_log("✅ [match_product] Direct match: {$normalized}");
            return $this->products[$normalized];
        }

        // 2. Reordered digits (e.g. "Go Lite 230" -> "230 Go Lite")
        $reordered = $this->normalize_sku($this->reorder_title_digits($title));
        if (isset($this->products[$reordered])) {
            error_log("✅ [match_product] Reordered match: {$reordered}");
            return $this->products[$reordered];
        }

        // 3. Compact fallback (strip dashes/spaces)
        if (isset($this->products[$compact])) {
            error_log("✅ [match_product] Compact match: {$compact}");
            return $this->products[$compact];
        }

        // 4. MP heuristic scan
        foreach ($this->products as $key => $product) {
            if (!empty($product['mp'])) {
                $mp_norm    = $this->normalize_sku($product['mp']);
                $mp_compact = $this->compact_key($product['mp']);

                if (in_array($normalized, [$mp_norm, $mp_compact, $compact], true)) {
                    error_log("✅ [match_product] MP match: {$key}");
                    return $product;
                }

                // Reverse-match forms (e.g. SL230, 230SL)
                if (preg_match('/^(\d+)[-_ ]?([a-z]+)/i', $normalized, $m)) {
                    $flip1 = strtolower($m[1] . $m[2]);
                    $flip2 = strtolower($m[2] . $m[1]);

                    if (in_array($mp_compact, [$flip1, $flip2], true)) {
                        error_log("✅ [match_product] Heuristic flip match: {$mp_compact}");
                        return $product;
                    }
                }
            }
        }

        return null;
    }

    private function normalize_sku($value) {
        $value = strtolower(trim($value));
        $value = str_replace(['&', '+', '_'], '-', $value);
        $value = preg_replace('/[^a-z0-9]/i', '-', $value);
        $value = preg_replace('/-+/', '-', $value);
        return trim($value, '-');
    }

    private function compact_key($value) {
        return str_replace('-', '', $this->normalize_sku($value));
    }

    private function reorder_title_digits($title) {
        if (preg_match('/^(.*?)(\d+)\s*$/', $title, $m)) {
            $prefix = trim($m[1]);
            $digits = trim($m[2]);
            return "{$digits} {$prefix}";
        }
        return $title;
    }
}
