<?php

abstract class PSS_Supplier_Scraper_Base {

    abstract public function run();

    protected function get_dom_from_url( $url ) {
        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $html = wp_remote_retrieve_body( $response );

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        return $dom;
    }

    protected function extract_text( DOMXPath $xpath, $query ) {
        $nodes = $xpath->query($query);
        return ($nodes->length > 0) ? trim($nodes->item(0)->textContent) : null;
    }

    protected function log( $message ) {
        echo '<p>' . esc_html($message) . '</p>';
    }
}
