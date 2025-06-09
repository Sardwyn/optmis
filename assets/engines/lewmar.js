import { runParsers } from './strategy-runner.js';

export async function parse({ url, category }, fetchPage) {
  console.warn(`🔥 Lewmar engine parsing ${url}`);
  window.fetchPage = fetchPage;

  const doc = await fetchPage(url);
  const products = await runParsers([
    doc => tryJsonLd(doc, category),
    doc => tryDomFallback(doc, category)
  ], doc);

  console.log('🧪 Parsed products from engine:', products);

  const missingTitles = products.filter(p => !p.title || !p.title.trim());
  if (missingTitles.length) {
    console.warn(`❗ ${missingTitles.length} product(s) missing title:`, missingTitles);
  }

  // ✅ Ensure category is preserved for import pipeline
  products.forEach(p => {
    p.category = category;
  });

  return products;
}


async function tryJsonLd(doc, category) {
  const script = doc.querySelector('script[type="application/ld+json"]');
  if (!script) return [];

  try {
    const parsed = JSON.parse(script.textContent);
    const product = parsed?.['@type'] === 'Product' ? parsed : parsed?.['@graph']?.find(p => p['@type'] === 'Product');
    if (!product) return [];

    return [{
      title: product.name,
      url: product.url || '',
      image: product.image,
      price: product.offers?.price,
      sku: product.sku?.replace('-configurable', ''),
      description: product.description,
      configurable: product.sku?.includes('-configurable') || false,
      variants: [],
      category
    }];
  } catch (e) {
    console.warn('Lewmar JSON-LD parse error:', e);
    return [];
  }
}

async function tryDomFallback(doc, category) {
  //const items = [...doc.querySelectorAll('[data-sku]')];
  const items = Array.from( doc.querySelectorAll('[data-sku]') );
  console.warn('🧱 Lewmar DOM product count:', items.length);

  const results = await Promise.all(items.map(async item => {
    const rawSku = item.getAttribute('data-sku') || '';
    const isConfigurable = rawSku.endsWith('-configurable');
    const sku = rawSku.replace('-configurable', '');

    return {
      title: item.querySelector('.product-item-link')?.textContent.trim() || '',
      url: item.querySelector('.product-item-link')?.href || '',
      image: item.querySelector('img')?.src || '',
      price: item.querySelector('.price')?.textContent.trim() || '',
      sku,
      description: item.querySelector('.product-item-description')?.textContent.trim() || '',
      //configurable: isConfigurable,
      configurable: rawSku.endsWith('-configurable'),
      variants: [],
      category
    };
  }));

  const missingTitles = results.filter(p => !p.title || !p.title.trim());
  if (missingTitles.length) {
    console.warn(`❗ ${missingTitles.length} DOM-parsed product(s) missing title:`, missingTitles);
  }

  return results;
}

async function fetchVariantsFromProductPage(url) {
  console.warn(`🧬 Fetching variants from detail page: ${url}`);
  const doc = await fetchPage(url);
  const scripts = [...doc.querySelectorAll('script')];

  const configScript = scripts.find(s =>
    s.textContent.includes('initConfigurableDropdownOptions_') &&
    s.textContent.includes('initConfigurableOptions')
  );

  if (!configScript) {
    console.warn('❌ No initConfigurableOptions() script block found');
    return [];
  }

  const match = configScript.textContent.match(/initConfigurableOptions\([^,]+,\s*(\{.*\})\s*\)/s);
  if (!match) {
    console.warn('❌ Failed to extract JSON from script block');
    return [];
  }

  try {
    const parsed = JSON.parse(match[1]);
    const options = parsed.attributes?.[277]?.options || [];
    const skuMap = parsed.sku || {};

    const variants = options.map(opt => {
      const productId = opt.products?.[0];
      return {
        sku: skuMap[productId] || productId,
        label: opt.label,
        price: '', // price not exposed per variant
      };
    });

    console.warn('✅ Parsed variant config from inline JS:', variants);
    return variants;
  } catch (e) {
    console.warn('❌ JSON parsing failed:', e);
    return [];
  }
}

export function renderScrapedProducts(products) {
  window.lastScrapedProducts = products;

  const output = document.getElementById('pss-scraped-output');
  if (!products.length) {
    output.innerHTML = '<p>No products scraped.</p>';
    return;
  }

  const rows = products.map(p => {
    const variantBtn = p.configurable
      ? `<button class="button button-small" data-variants-url="${p.url}" data-product-id="${p.sku}" onclick="loadVariants(this)">Variants</button>`
      : '';

    return `
      <tr>
        <td class="pss-checkbox-cell"><input type="checkbox" /></td>
        <td><img src="${p.image}" style="width:60px;" onerror="this.src='${window.pssScraperData.pluginUrl}assets/img/placeholder.png'"></td>
        <td><a href="${p.url}" target="_blank" style="color:${!p.title ? 'red' : 'inherit'};">
          ${p.title || '(Missing Title)'}
        </a></td>
        <td>${p.price || '–'}</td>
        <td>${p.sku || ''}</td>
        <td>${p.description?.substring(0, 80) || ''}</td>
        <td>${variantBtn}</td>
      </tr>`;
  }).join('');

  output.innerHTML = `
    <table class="wp-list-table widefat fixed striped pss-product-table" style="margin-top:10px;">
      <thead>
        <tr>
          <th class="pss-checkbox-cell"><input type="checkbox" id="pss-select-all" /></th>
          <th>Image</th>
          <th>Title</th>
          <th>Price</th>
          <th>SKU</th>
          <th>Description</th>
          <th>Variants</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>`;
}

window.loadVariants = async function (button) {
  const productId = button.getAttribute('data-product-id');
  const url = button.getAttribute('data-variants-url');

  if (document.querySelector(`#variants-${productId}`)) return;

  button.disabled = true;
  button.textContent = 'Loading...';

  const variants = await fetchVariantsFromProductPage(url);

  const row = button.closest('tr');
  const variantHtml = variants.map(v => `
    <tr class="variant-row" id="variants-${productId}">
      <td colspan="6" style="padding-left: 40px;">
        <strong>Variant:</strong> ${v.label} – <strong>SKU:</strong> ${v.sku} – <strong>Price:</strong> ${v.price}
      </td>
    </tr>`).join('');

  row.insertAdjacentHTML('afterend', variantHtml || `<tr id="variants-${productId}"><td colspan="6"><em>No variants found</em></td></tr>`);
  button.textContent = 'Variants';
  button.disabled = false;
};
