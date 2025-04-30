<?php
// 🚨 Confirm the template was loaded
error_log('🚨 single-yacht.php loaded from plugin');
echo '<!-- 🧪 Template: single-yacht.php loaded -->';

get_header();

// 🚨 Check the global $post object
global $post;
if (!$post) {
    error_log('❌ $post is null in single-yacht.php');
} else {
    error_log('✅ $post ID: ' . $post->ID . ' | Type: ' . get_post_type($post));
}

if (have_posts()) {
    error_log('✅ have_posts() returned true');

    while (have_posts()) {
        the_post();
        $post_id = get_the_ID();
        error_log('✅ the_post() called: ID ' . $post_id);

        echo '<div class="single-yacht">';
        echo '<h1>' . get_the_title() . '</h1>';

        echo '<div class="yacht-meta">';
        echo '<p><strong>Model:</strong> ' . esc_html(get_post_meta($post_id, '_yacht_model', true)) . '</p>';
        echo '<p><strong>Length:</strong> ' . esc_html(get_post_meta($post_id, '_yacht_length', true)) . ' ft</p>';
        echo '<p><strong>Year:</strong> ' . esc_html(get_post_meta($post_id, '_yacht_year', true)) . '</p>';
        echo '<p><strong>Manufacturer:</strong> ' . esc_html(get_post_meta($post_id, '_yacht_manufacturer', true)) . '</p>';
        echo '</div>';

        echo '<div class="yacht-description">' . get_the_content() . '</div>';

        // 🧪 Shortcode section
        $shortcode = '[yacht_part_menu yacht_id="' . $post_id . '"]';
        error_log('🧪 Rendering shortcode: ' . $shortcode);
        $shortcode_output = do_shortcode($shortcode);
        error_log('🧪 Shortcode rendered output: ' . strip_tags($shortcode_output));

        echo '<div class="oym-accordion-wrapper">';
        echo $shortcode_output;
        echo '</div>';

        echo '<div id="oym-results">Select a category to see matching parts.</div>';

        echo '</div>'; // end .single-yacht
    }
} else {
    error_log('❌ have_posts() returned false');
    echo '<p>Yacht not found.</p>';
}

get_footer();
