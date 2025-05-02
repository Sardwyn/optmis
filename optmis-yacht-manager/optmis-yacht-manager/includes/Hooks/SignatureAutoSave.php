<?php
namespace Optmis_Yacht_Manager\Hooks;

defined('ABSPATH') || exit;

use Optmis_Yacht_Manager\Utils\SchemaSignatureBuilder;

class SignatureAutoSave {
    public static function register() {
        add_action('save_post_product', [self::class, 'maybe_save_signature'], 10, 3);
    }

    public static function maybe_save_signature($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;

        $signature = SchemaSignatureBuilder::build_signature_for_product($post_id);
        if (is_array($signature)) {
            update_post_meta($post_id, '_oym_spec_signature', json_encode($signature));
            error_log("[OYM] Saved compatibility signature for product #$post_id");
        }
    }
} 
