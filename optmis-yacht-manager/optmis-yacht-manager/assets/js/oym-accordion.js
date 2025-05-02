document.addEventListener('DOMContentLoaded', function () {
    const accordion = document.querySelector('.oym-accordion');
    if (!accordion) return;

    accordion.addEventListener('click', function (e) {
        if (e.target.matches('li[data-term-id]')) {
            const termId = e.target.dataset.termId;
            const yachtId = accordion.dataset.yachtId;

            e.target.classList.add('loading');

            fetch(oymAjax.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'oym_get_parts',
                    term_id: termId,
                    yacht_id: yachtId,
                })
            })
            .then(res => res.text())
            .then(html => {
                const results = document.getElementById('oym-results');
                if (results) results.innerHTML = html;
                e.target.classList.remove('loading');
            });
        }
    });
});
