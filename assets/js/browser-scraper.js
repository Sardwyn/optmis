jQuery(document).ready(function ($) {
  const supplierOptions = pssScraperData.suppliers || [];

  function getSavedCategories() {
    return JSON.parse(localStorage.getItem('pss_saved_categories') || '{}');
  }

  function saveCategory(supplier, name, url) {
    const data = getSavedCategories();
    data[supplier] = data[supplier] || [];
    data[supplier].push({ name, url });
    localStorage.setItem('pss_saved_categories', JSON.stringify(data));
  }

  function renderSavedCategories(supplier) {
    const $container = $('#pss-saved-categories').empty();
    const categories = getSavedCategories()[supplier] || [];

    if (!categories.length) {
      $container.append('<p style="color:#888;">No saved categories.</p>');
      return;
    }

    categories.forEach(cat => {
      const $row = $(`
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
          <div><strong>${cat.name}</strong> - ${cat.url}</div>
          <div><button class="button pss-scrape-saved" data-url="${cat.url}">Scrape</button></div>
        </div>
      `);
      $container.append($row);
    });
  }

  function showToast(message, success = true) {
    const $toast = $(`<div style="position:fixed;top:20px;right:20px;background:${success ? '#28a745' : '#dc3545'};color:white;padding:10px 20px;border-radius:5px;z-index:9999;font-weight:bold;">${message}</div>`);
    $('body').append($toast);
    setTimeout(() => $toast.fadeOut(400, () => $toast.remove()), 3000);
  }

  // Insert supplier/category controls if not already present
  if (!$('#pss-supplier-url-wrapper').length) {
    const $supplierRow = $(`
      <div id="pss-supplier-url-wrapper" style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
        <label for="pss-supplier-select">Supplier:</label>
        <select id="pss-supplier-select" style="max-width:200px;"></select>
        <input type="text" id="pss-url-input" style="flex:1; max-width:400px;" placeholder="https://example.com/category" />
      </div>
    `);
    supplierOptions.forEach(option => {
      $supplierRow.find('#pss-supplier-select').append(`<option value="${option.slug}">${option.name}</option>`);
    });
    $('#pss-page-count').closest('p').before($supplierRow);

    const $categoryManager = $(`
      <div style="margin-top:10px;">
        <input type="text" id="pss-category-name" placeholder="Category name" style="max-width:200px; margin-right:5px;" />
        <button class="button" id="pss-save-category">Save Category</button>
        <div id="pss-saved-categories" style="margin-top:10px;"></div>
      </div>
    `);
    $('#pss-supplier-url-wrapper').after($categoryManager);

    renderSavedCategories($('#pss-supplier-select').val());
  }

  // Save a category to local storage
  $(document).on('click', '#pss-save-category', function () {
    const supplier = $('#pss-supplier-select').val();
    const name     = $('#pss-category-name').val().trim();
    const url      = $('#pss-url-input').val().trim();
    if (!name || !url) return alert('Enter both a category name and URL.');
    saveCategory(supplier, name, url);
    $('#pss-category-name').val('');
    renderSavedCategories(supplier);
  });

  // Quick-scrape a saved category
  $(document).on('click', '.pss-scrape-saved', function () {
    const url = $(this).data('url');
    $('#pss-url-input').val(url);
    $('#pss-run-browser-scraper').trigger('click');
  });

  // Update displayed saved categories on supplier change
  $(document).on('change', '#pss-supplier-select', function () {
    renderSavedCategories($(this).val());
  });

  // Main scraping action
  $(document).on('click', '#pss-run-browser-scraper', async function () {
    const supplierSlug = $('#pss-supplier-select').val();
    const baseUrl      = $('#pss-url-input').val().trim();
    const maxPages     = parseInt($('#pss-page-count').val().trim()) || 1;
    const $log         = $('#pss-log-console');
    const $output      = $('#pss-scraped-output');
    $('#pss-failed-imports').hide().empty();
    $log.text('Fetching pages...');
    $output.empty();

    if (!baseUrl) {
      $log.append('\n❌ Please enter a URL.');
      return;
    }

    try {
      const engine = await import(`${pssScraperData.engineBaseUrl}${supplierSlug}.js`);
      if (engine && typeof engine.parse === 'function') {
        const allProducts = [];

        for (let page = 1; page <= maxPages; page++) {
          const pageUrl = baseUrl.includes('?') ? `${baseUrl}&p=${page}` : `${baseUrl}?p=${page}`;
          $log.append(`\n🔄 Fetching page ${page}...`);
          try {
            const pageRes  = await fetch(`${pssScraperData.ajaxUrl}?action=pss_proxy_scrape&url=${encodeURIComponent(pageUrl)}&security=${pssScraperData.security}`);
            const pageHtml = await pageRes.text();
            const pageDoc  = new DOMParser().parseFromString(pageHtml, 'text/html');
            const products = await engine.parse(pageDoc, baseUrl, async (url) => {
              const res  = await fetch(`${pssScraperData.ajaxUrl}?action=pss_proxy_scrape&url=${encodeURIComponent(url)}&security=${pssScraperData.security}`);
              const html = await res.text();
              return new DOMParser().parseFromString(html, 'text/html');
            });
            allProducts.push(...products);
          } catch (err) {
            $log.append(`\n❌ Failed to fetch page ${page}.`);
          }
        }

        $log.append(`\n✅ Found ${allProducts.length} total products.`);
        showToast(`Scrape complete: ${allProducts.length} products found!`, true);

        if (allProducts.length) {
          const table = $(`
            <table class="pss-product-table wp-list-table widefat fixed striped" style="width:100%; margin-top:10px;">
              <thead>
                <tr>
                  <th>Import?</th><th>Image</th><th>Title</th><th>Price</th><th>Status</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          `);

          allProducts.forEach(p => {
            const hasTitle = !!p.title?.trim();
            const hasDesc  = !!p.description?.trim();
            const hasBrand = !!p.brand?.trim();
            const hasSKU   = !!p.sku?.trim();
            let statusClass = 'pss-status-green';
            if (!hasTitle || !hasDesc || !hasBrand) {
              statusClass = (!hasTitle && !hasDesc && !hasBrand) ? 'pss-status-red' : 'pss-status-amber';
            }
            const tooltip = [
              !hasTitle ? 'Missing title' : '',
              !hasDesc  ? 'Missing description' : '',
              !hasBrand ? 'Missing brand' : '',
              !hasSKU   ? 'Missing SKU' : ''
            ].filter(Boolean).join(', ') || 'All good';

            const row = $('<tr></tr>');
            row.append(`<td><input type="checkbox" class="pss-import-checkbox" data-product='${JSON.stringify(p)}' checked></td>`);
            row.append(`<td><img src="${p.image}" style="width:60px;height:60px;object-fit:contain;background:#eee;border:1px solid #ddd;"></td>`);
            row.append(`<td><a href="${p.href}" target="_blank">${p.title || '(No Title)'}</a></td>`);
            row.append(`<td>${p.price || '–'}</td>`);
            row.append(`<td><div class="pss-product-status ${statusClass}" title="${tooltip}"></div></td>`);
            table.find('tbody').append(row);
          });

          $output.append(table);
          $('#pss-import-selected').show();
        }
      }
    } catch (err) {
      console.error(err);
      $log.append('\n❌ Failed to load supplier engine.');
    }
  });

  // Import selected products
  $(document).on('click', '#pss-import-selected', async function () {
    const selectedProducts = [];
    $('.pss-import-checkbox:checked').each(function () {
      const p = $(this).data('product');
      if (p) selectedProducts.push(p);
    });

    if (!selectedProducts.length) {
      alert('No products selected for import.');
      return;
    }

    $('#pss-log-console').append('\n🚀 Importing products...');
    $('#pss-failed-imports').hide().empty();

    try {
      const res = await fetch(pssScraperData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action:   'pss_import_products',
          security: pssScraperData.security,
          products: JSON.stringify(selectedProducts)
        })
      });
      const result = await res.json();

      if (result.success) {
        showToast('Import complete!', true);
        $('#pss-log-console').append(`\n✅ ${result.data.message}`);

        if (result.data.failed && result.data.failed.length) {
          const $failedBox = $('<div style="background:#fff3cd; border:1px solid #ffeeba; padding:15px; margin-top:15px; border-radius:4px;"></div>');
          $failedBox.append('<h3 style="margin-top:0;">⚠️ Failed to import these products:</h3>');
          const $ul = $('<ul style="margin-left:20px;"></ul>');

          result.data.failed.forEach(item => {
            const title = item.title || 'Unnamed Product';
            const href  = item.href  || '#';
            $ul.append(`<li><a href="${href}" target="_blank" style="color:#d9534f;">${title}</a></li>`);
          });

          $failedBox.append($ul);

          const $downloadBtn = $('<button class="button" style="margin-top:10px;">Download Failed List</button>');
          $downloadBtn.on('click', function () {
            const csvContent = "data:text/csv;charset=utf-8," + result.data.failed.map(i => `"${i.title.replace(/"/g, '""')}"`).join("\n");
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "failed_imports.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
          });

          $failedBox.append($('<div style="margin-top:10px;"></div>').append($downloadBtn));
          $('#pss-failed-imports').html($failedBox).fadeIn(300);
        }
      } else {
        showToast('Import failed!', false);
        $('#pss-log-console').append('\n❌ Import failed.');
      }
    } catch (err) {
      console.error(err);
      showToast('Import AJAX error!', false);
      $('#pss-log-console').append('\n❌ Import AJAX error.');
    }
  });
});
