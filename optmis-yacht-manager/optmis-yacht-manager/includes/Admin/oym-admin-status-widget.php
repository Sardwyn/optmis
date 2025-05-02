<?php
defined('ABSPATH') || exit;

add_action('wp_dashboard_setup', function () {
    wp_add_dashboard_widget(
        'oym_project_status_widget',
        'Optmis Platform Project Status',
        'oym_render_project_status_widget'
    );
});

function oym_render_project_status_widget() {
    echo '<div style="max-height:160px; overflow:hidden;">';
    echo '<canvas id="oym-status-chart" style="height:140px;"></canvas>';
    echo '</div>';
}

add_action('admin_footer', 'oym_output_status_widget_script');
function oym_output_status_widget_script() {
    $screen = get_current_screen();
    if ($screen && $screen->base !== 'dashboard') return;

    $project_data = [
        'Supplier Scraper Plugin' => 70,
        'Woo Product Data Management' => 60,
        'Optmis Yacht Manager Plugin' => 55,
        'Matching Layer & Schema System' => 40,
        'Overall Completion' => 56
    ];

    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        const canvas = document.getElementById("oym-status-chart");
        if (!canvas) return;

        const ctx = canvas.getContext("2d");
        new Chart(ctx, {
          type: "bar",
          data: {
            labels: <?php echo json_encode(array_keys($project_data)); ?>,
            datasets: [{
              label: "Completion %",
              data: <?php echo json_encode(array_values($project_data)); ?>,
              backgroundColor: "#4F8EF7"
            }]
          },
          options: {
            indexAxis: "y",
            scales: {
              x: { beginAtZero: true, max: 100 }
            },
            plugins: {
              legend: { display: false }
            },
            layout: {
              padding: 5
            },
            responsive: true,
            maintainAspectRatio: false
          }
        });
      });
    </script>
    <?php
}
