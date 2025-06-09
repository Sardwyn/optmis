<?php

// File: class-scraper-timage.php

class PSS_Scraper_Timage extends PSS_Supplier_Scraper_Base {

    public function run() {
        $save_to_db = isset($_POST['save_to_db']) && $_POST['save_to_db'] === '1';
        $selected_category = sanitize_text_field($_POST['category'] ?? 'Timage');
        $importer = new PSS_WC_Importer();

        echo "<p>Starting Timage scrape...</p>";
        @ob_flush(); flush();

        $category_urls = [
            'https://timage.co.uk/lighting',
            'https://timage.co.uk/hardware',
            // Add more categories as needed
        ];

        $product_urls = [];

        foreach ( $category_urls as $cat_url ) {
            echo "<p>Crawling category: $cat_url</p>";
            @ob_flush(); flush();

            $urls = $this->crawl_product_urls( $cat_url );
            echo "<p>Found " . count($urls) . " products.</p>";
            @ob_flush(); flush();

            $product_urls = array_merge( $product_urls, $urls );
        }

        $products = [];

        echo "<p>Save to DB: " . ($save_to_db ? 'Yes' : 'No') . " | Category: $selected_category</p>";
        @ob_flush(); flush();

        foreach ( $product_urls as $product_url ) {
            echo "<p>Processing: $product_url</p>";
            @ob_flush(); flush();

            $product = $this->parse_product_data( $product_url );
            if ( $product ) {
                $product['category'] = $selected_category;
                $products[] = $product;

                if ( $save_to_db ) {
                    $result = $importer->import_product( $product );
                    if ( is_wp_error($result) ) {
                        echo "<p style='color:red;'>Error importing: " . esc_html($result->get_error_message()) . "</p>";
                    } else {
                        echo "<p style='color:green;'>Imported product ID: $result</p>";
                    }
                } else {
                    echo "<p style='color:orange;'>Previewed: " . esc_html($product['title']) . "</p>";
                }

                @ob_flush(); flush();
            }
        }

        echo "<p>Finished importing " . count($products) . " products.</p>";
        @ob_flush(); flush();

        return $products;
    }

    private function crawl_product_urls( $category_url ) {
        $urls = [];
        $dom = $this->get_dom_from_url( $category_url );

        if ( ! $dom ) return $urls;

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//a');

        foreach ( $nodes as $node ) {
            $url = $node->getAttribute('href');
            if ( $url && ! in_array($url, $urls) ) {
                $urls[] = esc_url_raw($url);
            }
        }

        return $urls;
    }

    private function parse_product_data( $url ) {
        $dom = $this->get_dom_from_url( $url );
        if ( ! $dom ) return null;

        $xpath = new DOMXPath($dom);

        $title = $this->extract_text( $xpath, '//h1' );
        $price = $this->extract_text( $xpath, '//span[contains(@class, "price")]' );
        $sku = $this->extract_text( $xpath, '//div[contains(@class, "product-sku")]/span' );
        $stock = $this->extract_text( $xpath, '//div[contains(@class, "stock")]' );
        $description = $this->extract_text( $xpath, '//div[contains(@class, "product attribute description")]/div' );

        // Placeholder for image and category logic
        $images = []; // Scrape image URLs from gallery
        $category = 'Timage';

        return compact('title', 'price', 'sku', 'stock', 'description', 'images', 'category', 'url');
    }
}
