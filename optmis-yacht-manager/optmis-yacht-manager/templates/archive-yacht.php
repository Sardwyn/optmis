<?php
get_header();

echo '<div class="wrap">';
echo '<h1>' . post_type_archive_title('', false) . '</h1>';

if (have_posts()) {
    echo '<ul class="yacht-archive-list">';
    while (have_posts()) {
        the_post();
        echo '<li>';
        echo '<a href="' . get_permalink() . '">' . get_the_title() . '</a>';
        echo '</li>';
    }
    echo '</ul>';

    the_posts_pagination();
} else {
    echo '<p>No yachts found.</p>';
}

echo '</div>';
get_footer();
