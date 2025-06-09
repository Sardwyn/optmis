// browser-importer.js — full version with enhanced logging
jQuery(document).ready(function ($) {
  console.warn('✅ browser-importer.js initialized');

  $('#pss-ui-root').html(`
    <div style="margin-top:20px;">
      <label for="pss-supplier-select">Supplier:</label>
      <select id="pss-supplier-select" style="min-width:200px; margin-left:10px;"></select>
      <input type="text" id="pss-url-input" style="width:400px; margin-left:10px;" placeholder="Enter category URL">
    </div>
  
    <div style="margin-top:10px;">
  <input type="text" id="pss-category-name" placeholder="Category name" style="width:200px; margin-right:10px;">
  <button class="button" id="pss-save-category">Save Category</button>
</div>

  
    <div id="pss-saved-categories" style="margin-top:15px;"></div>
  
    <div style="margin-top:15px;">
      <label for="pss-page-count">Max pages to scan:</label>
      <input type="number" id="pss-page-count" value="1" min="1" style="width:60px; margin-left:10px;">
    </div>
  
    <div style="margin-top:15px;">
      <button id="pss-run-browser-scraper" class="button button-primary">Scrape Products</button>
      <button id="pss-import-selected" class="button button-secondary" style="margin-left:15px;display:none;">Import Selected Products</button>
    </div>
  `);
  

  const supplierOptions = window.pssScraperData.suppliers || [];
  supplierOptions.forEach(option => {
    $('#pss-supplier-select').append(`<option value="${option.slug}">${option.name}</option>`);
  });


  async function loadWooCategories() {
    try {
      const res = await fetch(`${pssScraperData.ajaxUrl}?action=pss_get_wc_categories&security=${pssScraperData.security}`);
      const result = await res.json();
  
      if (!result.success || !Array.isArray(result.data)) {
        throw new Error('Unexpected category response');
      }
  
      const categories = result.data;
      //const $dropdown = $('#pss-woo-category');
      $dropdown.empty();
      //$dropdown.append('<option value="">-- Select Woo Category --</option>');
  
      //categories.forEach(cat => {
        //$dropdown.append(`<option value="${cat.id}">${cat.name}</option>`);
      //});
    } catch (err) {
      console.error('❌ Failed to load WooCommerce categories:', err);
      alert('Could not load WooCommerce categories. Check your API keys or site config.');
    }
  }
  

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
    const wooCategoryBindings = JSON.parse(localStorage.getItem('pss_category_bindings') || '{}');
  
    if (!categories.length) {
      $container.append('<p style="color:#888;">No saved categories.</p>');
      return;
    }
  
    fetch(pssScraperData.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'pss_get_wc_categories',
        security: pssScraperData.security
      })
    })
    .then(res => res.json())
    .then(result => {
      if (!result.success || !Array.isArray(result.data)) {
        throw new Error('Unexpected category response');
      }
  
      const wooCats = result.data;
  
      categories.forEach((cat, index) => {
        const categoryKey = new URL(cat.url).pathname;
        const savedWooId = wooCategoryBindings[categoryKey] || '';
        const $select = $('<select style="margin-left:10px;"></select>');
  
        wooCats.forEach(wc => {
          const selected = wc.id == savedWooId ? 'selected' : '';
          $select.append(`<option value="${wc.id}" ${selected}>${wc.name}</option>`);
        });
  
        const $saveBtn = $('<button class="button" style="margin-left:10px;">Save Mapping</button>');
        $saveBtn.on('click', function () {
          const id = parseInt($select.val(), 10);
          wooCategoryBindings[categoryKey] = id;
          localStorage.setItem('pss_category_bindings', JSON.stringify(wooCategoryBindings));
  
          fetch(pssScraperData.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              action: 'pss_save_category_map',
              security: pssScraperData.security,
              key: categoryKey,
              label: $select.find('option:selected').text()
            })
          });
  
          showToast(`✅ Bound "${cat.name}" to Woo category ID ${id}`, true);
        });
  
        const $scrapeBtn = $('<button class="button" style="margin-left:10px;">Scrape</button>');
        $scrapeBtn
          .addClass('pss-scrape-saved')
          .attr('data-url', cat.url)
          .attr('data-category', categoryKey);
  
        const $deleteBtn = $('<button class="button" style="margin-left:10px;color:#d9534f;">Delete</button>');
        $deleteBtn.on('click', () => {
          if (!confirm(`Delete category "${cat.name}"?`)) return;
          categories.splice(index, 1);
          localStorage.setItem('pss_saved_categories', JSON.stringify({ [supplier]: categories }));
          renderSavedCategories(supplier);
          showToast(`🗑️ Deleted "${cat.name}"`, true);
        });
  
        const $row = $('<div style="display:flex; align-items:center; margin-bottom:10px;"></div>');
        $row.append(`<div style="flex:1;"><strong>${cat.name}</strong> - ${cat.url}</div>`);
        $row.append($select, $saveBtn, $scrapeBtn, $deleteBtn);
        $container.append($row);
      });
    })
    .catch(err => {
      console.error('❌ Failed to load Woo categories:', err);
      $container.append('<p style="color:#d00;">Could not load WooCommerce categories.</p>');
    });
  }
  
  

  function showToast(message, success = true) {
    const $toast = $(`<div style="position:fixed;top:20px;right:20px;background:${success ? '#28a745' : '#dc3545'};color:white;padding:10px 20px;border-radius:5px;z-index:9999;font-weight:bold;">${message}</div>`);
    $('body').append($toast);
    setTimeout(() => $toast.fadeOut(400, () => $toast.remove()), 3000);
  }

  $('#pss-supplier-select').on('change', function () {
    renderSavedCategories(this.value);
  });

  setTimeout(() => {
    const supplier = $('#pss-supplier-select').val();
    if (supplier) renderSavedCategories(supplier);
  }, 200);
  

  $('#pss-save-category').on('click', function () {
    const supplier = $('#pss-supplier-select').val();
    const name = $('#pss-category-name').val().trim();
    const url = $('#pss-url-input').val().trim();
    if (!name || !url) return alert('Enter both a category name and URL.');
    saveCategory(supplier, name, url);
    $('#pss-category-name').val('');
    renderSavedCategories(supplier);
  });

  $('#pss-preview-close').on('click', () => {
    $('#pss-admin-preview-wrapper').hide();
  });

  $(document).on('click', '.pss-scrape-saved', function () {
    const url = $(this).data('url');
    const catSlug = $(this).data('category')?.toLowerCase();
    const catBindings = JSON.parse(localStorage.getItem('pss_category_bindings') || '{}');
    const boundWooId = catBindings[catSlug] || '';
  
    $('#pss-url-input').val(url);
    if (boundWooId) {
      $('#pss-woo-category').val(boundWooId);
    }
    $('#pss-run-browser-scraper').trigger('click');
  });
  

  $('#pss-run-browser-scraper').on('click', async function () {
    const supplier = $('#pss-supplier-select').val();
    const baseUrl = $('#pss-url-input').val().trim();
    const savedCategories = getSavedCategories()[supplier] || [];
    const matched = savedCategories.find(cat => cat.url === baseUrl);
    const categoryKey = matched?.name?.toLowerCase?.() || '';
    const wooBindings = JSON.parse(localStorage.getItem('pss_category_bindings') || '{}');
    const wooCategoryId = wooBindings[categoryKey];
  
    const $log = $('#pss-log-console');
    const $output = $('#pss-scraped-output');
  
    $('#pss-failed-imports').hide().empty();
    $log.text('Fetching pages...');
    $output.empty();
  
    if (!baseUrl) {
      $log.append('\n❌ Please enter a URL.');
      return;
    }
  
    if (!wooCategoryId) {
      alert('❌ Please select and map a WooCommerce category for this saved URL before scraping.');
      return;
    }
  
    const maxPages = parseInt($('#pss-page-count').val().trim()) || 1;
  
    try {
      const engineUrl = `${pssScraperData.engineBaseUrl}${supplier}.js?v=${Date.now()}`;
      const module = await import(/* @vite-ignore */ engineUrl);
  
      if (!module.parse || typeof module.parse !== 'function') {
        $log.append('\n❌ No parse function exported from engine.');
        return;
      }
  
      const allProducts = [];
      for (let page = 1; page <= maxPages; page++) {
        const pagedUrl = baseUrl.includes('?') ? `${baseUrl}&p=${page}` : `${baseUrl}?p=${page}`;
        $log.append(`\n🔄 Fetching page ${page}...`);
  
        try {
          const res = await fetch(`${pssScraperData.ajaxUrl}?action=pss_proxy_scrape&url=${encodeURIComponent(pagedUrl)}&security=${pssScraperData.security}`);
          const html = await res.text();
          const doc = new DOMParser().parseFromString(html, 'text/html');
  
          const fetchPage = async (innerUrl) => {
            const innerRes = await fetch(`${pssScraperData.ajaxUrl}?action=pss_proxy_scrape&url=${encodeURIComponent(innerUrl)}&security=${pssScraperData.security}`);
            const innerHtml = await innerRes.text();
            return new DOMParser().parseFromString(innerHtml, 'text/html');
          };
  
          const parsed = await module.parse({ url: pagedUrl, category: wooCategoryId }, fetchPage);
          if (Array.isArray(parsed)) {
            parsed.forEach(p => p.category = parseInt(wooCategoryId));
            allProducts.push(...parsed);
          }
  
        } catch (err) {
          console.error(`❌ Page ${page} failed`, err);
          $log.append(`\n❌ Failed to fetch page ${page}.`);
        }
      }
  
      $log.append(`\n✅ Found ${allProducts.length} total products.`);
      showToast(`Scrape complete: ${allProducts.length} products found!`, true);
  
      if (allProducts.length) {
        window.lastScrapedProducts = allProducts;
        if (module.renderScrapedProducts && typeof module.renderScrapedProducts === 'function') {
          module.renderScrapedProducts(allProducts);
        } else {
          $log.append('\n⚠️ No renderScrapedProducts function exported from engine.');
        }
        $('#pss-import-selected').show();
      }
  
    } catch (err) {
      console.error(err);
      $log.append('\n❌ Failed to load supplier engine.');
    }
  });
  

  $('#pss-import-selected').on('click', async function () {
    const selectedProducts = [];
    $('.pss-product-table input[type="checkbox"]:checked').each(function () {
      const index = $(this).closest('tr').index();
      const product = window.lastScrapedProducts?.[index];
      if (product) {
        selectedProducts.push(product);
      } else {
        console.warn('⚠️ No product found for row index:', index);
      }
    });

    if (!selectedProducts.length) {
      alert('No products selected for import.');
      return;
    }

    $('#pss-log-console').append('\n🚀 Importing products...');

    try {
      const res = await fetch(pssScraperData.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'pss_import_products',
          security: pssScraperData.security,
          products: JSON.stringify(selectedProducts)
        })
      });

      const result = await res.json();

      if (result.success) {
        showToast('Import complete!', true);
        $('#pss-log-console').append(`\n✅ ${result.data.message}`);

        if (result.data.failed?.length) {
          const $failedBox = $('<div style="background:#fff3cd; border:1px solid #ffeeba; padding:15px; margin-top:15px; border-radius:4px;"></div>');
          $failedBox.append('<h3 style="margin-top:0;">⚠️ Failed to import these products:</h3>');
          const $ul = $('<ul style="margin-left:20px;"></ul>');

          result.data.failed.forEach(item => {
            const title = item.title || 'Unnamed';
            const href = item.href || '#';
            $ul.append(`<li><a href="${href}" target="_blank" style="color:#d9534f;">${title}</a></li>`);
          });

          $failedBox.append($ul);

          const $downloadBtn = $('<button class="button" style="margin-top:10px;">Download Failed List</button>');
          $downloadBtn.on('click', function () {
            const csv = "data:text/csv;charset=utf-8," + result.data.failed.map(i => `"${i.title.replace(/"/g, '""')}"`).join("\n");
            const encodedUri = encodeURI(csv);
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

  window.groupAndPreviewWoo = function () {
    const products = window.lastScrapedProducts || [];
    const grouped = window.wooGrouping?.groupVariantsForWoo?.(products) || [];

    const $preview = $('#woo-preview');
    $preview.text(
      grouped.length
        ? JSON.stringify(grouped, null, 2)
        : '⚠️ No configurable products with variants found.'
    );
  };

  $('#pss-group-woo').on('click', window.groupAndPreviewWoo);
});
