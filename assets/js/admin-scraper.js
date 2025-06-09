jQuery(document).ready(function ($) {
  $('#pss-run-scraper').on('click', function (e) {
    e.preventDefault();

    const $console  = $('#pss-log-console');

    $console.text('Running scraper...\n');

    $.ajax({
      url: pssScraperAjax.ajaxUrl,
      method: 'POST',
      data: {
        action      : 'pss_run_scraper_ajax',
        security    : pssScraperAjax.nonce,
      },
      xhrFields: {
        onprogress: function (e) {
          const responseText = e.currentTarget.response;
          const lines        = responseText.split(/\r?\n/);
          $console.text(lines.join('\n'));
          $console.scrollTop($console[0].scrollHeight);
        }
      },
      success: function () {
        $console.append('\n✅ Done!');
      },
      error: function () {
        $console.append('\n❌ An error occurred.');
      }
    });
  });
});
