jQuery(document).ready(function ($) {
  console.warn('✅ browser-importer.js initialized');

  // 🧼 Cleanup: prevent duplicate consoles or UI remnants
  $('#pss-log-console').remove();
  $('#pss-ui-root').empty();

 $('#pss-ui-root').html(`
  <div style="margin-top:20px;">
    <label for="pss-supplier-select">Supplier:</label>
    <select id="pss-supplier-select" style="min-width:200px; margin-left:10px;"></select>

    <label for="pss-price-modifier" style="margin-left:15px;">Modifier (%):</label>
    <input type="number" id="pss-price-modifier" style="width:80px; margin-left:5px;" value="0" step="0.1">
    <button class="button" id="pss-save-modifier" style="margin-left:10px;">Save Modifier</button>

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
    <button id="pss-import-selected" class="button button-secondary" style="margin-left:15px; display:none;">Import Selected Products</button>
  </div>

  <pre id="pss-log-console" style="margin-top:20px; max-height:200px; overflow:auto; background:#f8f9fa; padding:10px; border:1px solid #ccc;"></pre>
  <div id="pss-scraped-output" style="margin-top:20px;"></div>
  <div id="pss-failed-imports" style="margin-top:20px; display:none;"></div>
`);


  //Supplier Markup Button Functions
  function getSupplierMarkupMap() {
  return JSON.parse(localStorage.getItem('pss_supplier_markup') || '{}');
}

function saveSupplierMarkup(supplier, markup) {
  const map = getSupplierMarkupMap();
  map[supplier] = markup;
  localStorage.setItem('pss_supplier_markup', JSON.stringify(map));
}
$(document).on('click', '.pss-delete-category', function () {
  const supplier = $(this).data('supplier');
  const url = $(this).data('url');
  const categories = JSON.parse(localStorage.getItem('pss_saved_categories') || '{}');

  if (!categories[supplier]) return;

  categories[supplier] = categories[supplier].filter(cat => cat.url !== url);

  localStorage.setItem('pss_saved_categories', JSON.stringify(categories));
  showToast('🗑️ Category deleted');
  renderSavedCategories(supplier);
});



$('#pss-price-modifier').on('input', function () {
  const supplier = $('#pss-supplier-select').val();
  const val = parseFloat(this.value) || 0;
  saveSupplierMarkup(supplier, val);
});
$('#pss-save-modifier').on('click', function () {
  const supplier = $('#pss-supplier-select').val();
  const rawModifier = parseFloat($('#pss-price-modifier').val()) || 0;

  const markupMap = JSON.parse(localStorage.getItem('pss_supplier_markup') || '{}');
  markupMap[supplier] = rawModifier;
  localStorage.setItem('pss_supplier_markup', JSON.stringify(markupMap));

  showToast(`✅ Saved ${rawModifier}% modifier for ${supplier}`, true);
});

  // Show toast notifications
  function showToast(message, success = true) {
    const $toast = $(
      `<div style="position:fixed;top:20px;right:20px;background:${success ? '#28a745' : '#dc3545'};color:white;padding:10px 20px;border-radius:5px;z-index:9999;font-weight:bold;">${message}</div>`
    );
    $('body').append($toast);
    setTimeout(() => $toast.fadeOut(400, () => $toast.remove()), 3000);
  }

  // Populate supplier dropdown
  const supplierOptions = window.pssScraperData.suppliers || [];
  supplierOptions.forEach(option => {
    $('#pss-supplier-select').append(`<option value="${option.slug}">${option.name}</option>`);
  });

  $('#pss-supplier-select').on('change', function () {
  const supplier = this.value;
  renderSavedCategories(supplier);

  const markupMap = getSupplierMarkupMap(); // your preferred store
  const savedModifier = markupMap[supplier];
  $('#pss-price-modifier').val(typeof savedModifier !== 'undefined' ? savedModifier : 0);
});



  setTimeout(() => {
    const supplier = $('#pss-supplier-select').val();
    if (supplier) renderSavedCategories(supplier);
  }, 250);

setTimeout(() => {
  const supplier = $('#pss-supplier-select').val();
  if (supplier) {
    const markupMap = JSON.parse(localStorage.getItem('pss_supplier_markup') || '{}');
    const savedModifier = markupMap[supplier];
    if (typeof savedModifier !== 'undefined') {
      $('#pss-price-modifier').val(savedModifier);
    }
    renderSavedCategories(supplier);
  }
}, 250);



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
      security: pssScraperData.security,
    }),
  })
    .then(res => res.json())
    .then(result => {
      if (!result.success || !Array.isArray(result.data)) {
        throw new Error('Invalid Woo category response');
      }
      const wooCats = result.data;

      categories.forEach(cat => {
        const categoryKey = new URL(cat.url).pathname;
        const savedWooId = wooCategoryBindings[categoryKey] || '';
        const $select = $('<select style="margin-left:10px;"></select>');

        wooCats.forEach(wc => {
          const selected = wc.id == savedWooId ? 'selected' : '';
          $select.append(`<option value="${wc.id}" ${selected}>${wc.name}</option>`);
        });

        const $saveBtn = $('<button class="button" style="margin-left:10px;">Save Mapping</button>');
        $saveBtn.on('click', () => {
          const id = parseInt($select.val(), 10);
          wooCategoryBindings[categoryKey] = id;
          localStorage.setItem('pss_category_bindings', JSON.stringify(wooCategoryBindings));
          showToast(`✅ Bound "${cat.name}" to Woo category ID ${id}`, true);
        });

        const $scrapeBtn = $('<button class="button" style="margin-left:10px;">Scrape</button>');
        $scrapeBtn
          .addClass('pss-scrape-saved')
          .attr('data-url', cat.url)
          .attr('data-category', categoryKey)
          .attr('data-woo-id', savedWooId);

        const $deleteBtn = $(`
          <button class="button button-link-delete pss-delete-category" data-supplier="${supplier}" data-url="${cat.url}" title="Delete Category" style="margin-left:10px;">
            <span class="dashicons dashicons-trash"></span>
          </button>
        `);

        const $row = $('<div style="display:flex; align-items:center; margin-bottom:10px;"></div>');
        $row.append(`
          <div style="flex:1;">
            <strong>${cat.name}</strong> -
            <a href="${cat.url}" target="_blank" style="text-decoration:underline;">${cat.url}</a>
          </div>
        `);
        $row.append($select, $saveBtn, $scrapeBtn, $deleteBtn);
        $container.append($row);
      });
    })
    .catch(err => {
      console.error('❌ Failed to load Woo categories:', err);
      $container.append('<p style="color:#d00;">Could not load WooCommerce categories.</p>');
    });
}



  $('#pss-save-category').on('click', function () {
    const supplier = $('#pss-supplier-select').val();
    const name = $('#pss-category-name').val().trim();
    const url = $('#pss-url-input').val().trim();
    if (!name || !url) return alert('Enter both a category name and URL.');
    saveCategory(supplier, name, url);
    $('#pss-category-name').val('');
    renderSavedCategories(supplier);
  });

  $('#pss-price-modifier').on('input', function () {
  const supplier = $('#pss-supplier-select').val();
  const stored = JSON.parse(localStorage.getItem('pss_price_modifiers') || '{}');
  stored[supplier] = parseFloat(this.value) || 0;
  localStorage.setItem('pss_price_modifiers', JSON.stringify(stored));
});


  $(document).on('click', '.pss-scrape-saved', function () {
    const url = $(this).data('url');
    const catSlug = $(this).data('category');
    const catBindings = JSON.parse(localStorage.getItem('pss_category_bindings') || '{}');
    const boundWooId = catBindings[catSlug] || '';

    $('#pss-url-input').val(url);
    $('#pss-url-input').data('woo-category-id', boundWooId);
    $('#pss-run-browser-scraper').trigger('click');
  });

  // --- Scraper Click Handler with Adapters ---------------------------------
  $('#pss-run-browser-scraper').on('click', async function () {
    const wooCategoryId = $('#pss-url-input').data('woo-category-id');
    if (!wooCategoryId) {
      alert('❌ Please select and map a WooCommerce category before scraping.');
      return;
    }

    const supplierSlug = $('#pss-supplier-select').val();
    const maxPages = parseInt($('#pss-page-count').val().trim()) || 1;
    const $log = $('#pss-log-console');
    const $output = $('#pss-scraped-output');

    // capture the base URL before paging
    const baseUrl = $('#pss-url-input').val().trim();

    $('#pss-failed-imports').hide().empty();
    $log.text('Fetching pages...');
    $output.empty();

// --- 1) Define per-supplier adapters --------------------------------
const adapters = {
  aquafax: products => products.map(p => ({
    id:           p.sku,
    title:        p.title,
    url:          p.url,
    price:        p.price,
    sku:          p.sku,
    stock:        p.stock,
    image:        p.image,
    gallery:      p.gallery || [],
    description:  p.description || '',
    tags:         p.tags || [],
    category:     p.category,        // ← now matches what PHP expects
    categoryPath: p.categoryPath,
    isVariable:   p.isVariable,
    variants:     []
  })),
  lewmar: products => products.map(p => ({
    id:          p.sku,
    title:       p.title,
    url:         p.url,
    price:       p.price,
    sku:         p.sku,
    image:       p.image,
    gallery:     [],
    description: p.description,
    tags:        [],
    category:    p.category,        // ← renamed
    isVariable:  p.configurable,
    variants:    (p.variants||[]).map(v => ({
      id:    v.sku,
      title: v.title,
      url:   v.url,
      price: v.price,
      sku:   v.sku,
      image: v.image
    }))
  })),
  seago: products => products.map(p => ({
    id:       p.sku,
    title:    p.title,
    url:      p.url,
    price:    p.price,
    sku:      p.sku,
    image:    p.image,
    gallery:  [],
    category: p.category        // ← renamed
  })),
  timage: products => products.map(p => ({
    id:       p.sku,
    title:    p.title,
    url:      p.url,
    price:    p.price,
    sku:      p.sku,
    image:    p.image,
    gallery:  [],
    category: p.category        // ← renamed
  }))
};
// ----------------------------------------------------------------------


    try {
      const module = await import(
        /* @vite-ignore */ `${pssScraperData.engineBaseUrl}${supplierSlug}.js`
      );
      if (!module.parse || typeof module.parse !== 'function') {
        $log.append('\n❌ No parse function exported from engine.');
        return;
      }

      const allProducts = [];
      for (let page = 1; page <= maxPages; page++) {
        const pagedUrl = baseUrl.includes('?')
          ? `${baseUrl}&p=${page}`
          : `${baseUrl}?p=${page}`;
        $log.append(`\n🔄 Fetching page ${page}...`);

        try {
          const res = await fetch(
            `${pssScraperData.ajaxUrl}?action=pss_proxy_scrape&url=${encodeURIComponent(pagedUrl)}&security=${pssScraperData.security}`
          );
          const html = await res.text();
          const doc = new DOMParser().parseFromString(html, 'text/html');

          const fetchPage = async (innerUrl) => {
            const innerRes = await fetch(
              `${pssScraperData.ajaxUrl}?action=pss_proxy_scrape&url=${encodeURIComponent(innerUrl)}&security=${pssScraperData.security}`
            );
            const innerHtml = await innerRes.text();
            return new DOMParser().parseFromString(innerHtml, 'text/html');
          };

          const parsed = await module.parse({ url: pagedUrl, category: wooCategoryId }, fetchPage);
          if (Array.isArray(parsed)) {
            // cast category
            parsed.forEach(p => p.category = parseInt(wooCategoryId));
            // normalize shape
            const normalize = adapters[supplierSlug];
            const batch     = normalize ? normalize(parsed) : parsed;
            // accumulate
            allProducts.push(...batch);
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

        // render table
        if (module.renderScrapedProducts && typeof module.renderScrapedProducts === 'function') {
          module.renderScrapedProducts(allProducts);
        } else {
          $log.append('\n⚠️ No renderScrapedProducts function exported from engine.');
        }

        // persist optional (but show Import button regardless)
        fetch(pssScraperData.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action:   'pss_save_scraped_products',
            security: pssScraperData.security,
            products: JSON.stringify(allProducts)
          })
        })
        .finally(() => {
          // show it even if the save fails
          $('#pss-import-selected').show();
        });
      }
    } catch (err) {
      console.error(err);
      $log.append('\n❌ Failed to load supplier engine.');
    }
  });

// --- Import Selected Products --------------------------------------------
$('#pss-import-selected').on('click', async function () {
  const selectedProducts = [];
  $('.pss-product-table input[type="checkbox"]:checked').each(function () {
    const index = $(this).closest('tr').index();
    const product = window.lastScrapedProducts?.[index];
    if (product) {
      selectedProducts.push(product);
    }
  });

  const supplierSlug = $('#pss-supplier-select').val();
  const rawModifier = parseFloat($('#pss-price-modifier').val()) || 0;

  // Save the modifier persistently
  const markupMap = JSON.parse(localStorage.getItem('pss_supplier_markup') || '{}');
  markupMap[supplierSlug] = rawModifier;
  localStorage.setItem('pss_supplier_markup', JSON.stringify(markupMap));

  // Apply the modifier
  selectedProducts.forEach(p => {
    p.supplier = supplierSlug;

    if (p.price) {
      const numeric = parseFloat(p.price.toString().replace(/[^\d.]/g, ''));
      if (!isNaN(numeric)) {
        const adjusted = numeric * (1 + rawModifier / 100);
        p.price = adjusted.toFixed(2);
      }
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
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action:   'pss_import_products',
        security: pssScraperData.security,
        products: JSON.stringify(selectedProducts)
      })
    });
    const result = await res.json();

    if (result.success && result.data.imported?.length) {
      showToast('✅ Import complete!', true);
      $('#pss-log-console').append(`\n✅ ${result.data.imported.length} products imported.`);
    } else {
      showToast('⚠️ Import failed or no products were imported.', false);
      $('#pss-log-console').append('\n❌ No products were imported.');
    }

    if (result.data?.failed?.length) {
      const $failedBox = $('<div style="background:#fff3cd; border:1px solid #ffeeba; padding:15px; margin-top:15px; border-radius:4px;"></div>');
      $failedBox.append('<h3 style="margin-top:0;">⚠️ Failed to import these products:</h3>');
      const $ul = $('<ul style="margin-left:20px;"></ul>');
      result.data.failed.forEach(item => {
        const title = item.title || 'Unnamed';
        const href  = item.href  || '#';
        $ul.append(`<li><a href="${href}" target="_blank" style="color:#d9534f;">${title}</a></li>`);
      });
      $failedBox.append($ul);

      const $downloadBtn = $('<button class="button" style="margin-top:10px;">Download Failed List</button>');
      $downloadBtn.on('click', function () {
        const csv = "data:text/csv;charset=utf-8," +
          result.data.failed.map(i => `"${i.title.replace(/"/g, '""')}"`).join("\n");
        const link = document.createElement("a");
        link.setAttribute("href", encodeURI(csv));
        link.setAttribute("download", "failed_imports.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      });
      $failedBox.append($('<div style="margin-top:10px;"></div>').append($downloadBtn));

      $('#pss-failed-imports').html($failedBox).fadeIn(300);
    }
  } catch (err) {
    console.error(err);
    showToast('❌ Import AJAX error!', false);
    $('#pss-log-console').append('\n❌ Import AJAX error.');
  }
});

  
// after you build the rest of your UI…
// ─── grouping (guarded) ─────────────────────────────────────────────

/*const $groupBtn = $('#pss-group-woo');
if (
  window.wooGrouping &&
  typeof window.wooGrouping.groupVariantsForWoo === 'function'
) {
  console.log('✅ grouping enabled');
  $groupBtn.on('click', window.groupAndPreviewWoo);
} else {
  console.log('⚠️ grouping disabled – hiding button');
  $groupBtn.hide();
}


if (window.wooGrouping?.groupVariantsForWoo) {
  console.log('✅ grouping enabled');
  window.groupAndPreviewWoo = () => {
    const products = window.lastScrapedProducts || [];
    const grouped  = window.wooGrouping.groupVariantsForWoo(products);
    $('#woo-preview').text(
      grouped.length
        ? JSON.stringify(grouped, null, 2)
        : '⚠️ No variants found.'
    );
  };
  $groupBtn.on('click', window.groupAndPreviewWoo);
} else {
  console.log('⚠️ grouping disabled – removing button');
  $groupBtn.remove();
}

*/
});