<?php

class PSS_CSV_Enricher {

    protected $supplier;
    protected $delegate;

    public function __construct($supplier) {
        $this->supplier = ucfirst(strtolower($supplier));

        $enricher_file = PSS_PLUGIN_DIR . "enrichment/csv-modules/{$this->supplier}.php";
        error_log("🔍 Attempting to load enricher file: {$enricher_file}");

        if (file_exists($enricher_file)) {
            require_once $enricher_file;
            $class_name = $this->supplier . "_Enricher";
            error_log("🔍 Looking for class: {$class_name}");

            if (class_exists($class_name)) {
                $this->delegate = new $class_name();
                error_log("✅ Enricher instantiated: {$class_name}");

                if (property_exists($this->delegate, 'products') && is_array($this->delegate->products)) {
                    $sample_keys = array_slice(array_keys($this->delegate->products), 0, 20);
                    error_log("🧪 Sample SKUs in delegate: " . implode(', ', $sample_keys));
                }
            } else {
                error_log("❌ [PSS Enricher] Class {$class_name} not found.");
            }
        } else {
            error_log("❌ [PSS Enricher] File {$enricher_file} not found.");
        }

        if (!$this->delegate) {
            error_log("⚠️ Delegate not instantiated; enrichment disabled.");
        }
    }

    public function enrich($row) {
        $sku = strtolower(trim($row['sku'] ?? ''));
        error_log("📥 [PSS_CSV_Enricher] Incoming SKU: {$sku}");

        if (empty($sku)) {
            error_log("❌ [PSS_CSV_Enricher] Missing SKU in row.");
            return $row;
        }

        if ($this->delegate && method_exists($this->delegate, 'enrich')) {
            error_log("🔧 [PSS_CSV_Enricher] Delegating enrichment to supplier enricher.");
            $enriched = $this->delegate->enrich($row);
            error_log("📤 [PSS_CSV_Enricher] Enriched result: " . print_r($enriched, true));
            return $enriched;
        }

        error_log("⚠️ [PSS_CSV_Enricher] No delegate or enrich method found. Skipping.");
        return $row;
    }

    public function sync() {
    $upload_dir = wp_upload_dir();

    if ($this->supplier === 'Seago') {
        try {
            error_log("🌐 [Seago] Starting XLSX sync…");

            $url = 'https://seagoyachtingltd.sharepoint.com/:x:/s/BrochuresPricelists/EQ3C_ZPCdTtOqcsIQqgJof8BQDlHlj1Bp0PUgslshwRl7A?download=1';
            $local_path = $upload_dir['basedir'] . '/seago-sync.xlsx';

            $response = wp_remote_get($url);

            if (is_wp_error($response)) {
                error_log("❌ [Seago] wp_remote_get failed: " . $response->get_error_message());
                return false;
            }

            $body = wp_remote_retrieve_body($response);

            if (empty($body)) {
                error_log("❌ [Seago] Retrieved body was empty.");
                return false;
            }

            file_put_contents($local_path, $body);
            update_option('pss_seago_xlsx_path', $local_path);

            error_log("✅ [Seago] File stored at {$local_path}");
            return true;
        } catch (Throwable $e) {
            error_log("🔥 [Seago] Fatal sync error: " . $e->getMessage());
            return false;
        }
    }

    if ($this->supplier === 'Aquafax') {
        $url = 'https://files.channable.com/Vwpjj7Daj_1AEVeCZKB_8g==.csv';
        $local_path = $upload_dir['basedir'] . '/aquafax-sync.csv';

        error_log("🌐 [Aquafax] Fetching CSV from: {$url}");

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            error_log("❌ [Aquafax] Failed to fetch CSV: " . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (!$body) {
            error_log("❌ [Aquafax] CSV file was empty or unreadable.");
            return false;
        }

        file_put_contents($local_path, $body);
        update_option('pss_aquafax_csv_path', $local_path);
        error_log("✅ [Aquafax] Synced CSV to: {$local_path}");

        return true;
    }

    error_log("⚠️ No sync handler implemented for supplier: {$this->supplier}");
    return false;
}

    
}
