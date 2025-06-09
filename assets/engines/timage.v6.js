import { runParsers } from './strategy-runner.js';

export async function parse({ url, category }, fetchPage) {
  console.log('🚀 parse() from timage.js has started running');

  const doc = await fetchPage(url);
  const products = [];
  const items = doc.querySelectorAll('.product-item-info');
  console.log(`📦 Found ${items.length} product-item-info blocks`);

  for (const el of items) {
    try {
      console.log('🔁 Looping over product card');

      const title = el.querySelector('.product-item-link')?.textContent.trim() || '';
      const href = el.querySelector('.product-item-link')?.href || '';

      if (!title || !href) {
        console.warn('❌ Skipping due to missing title or href', el.outerHTML);
        continue;
      }

      const imageEl = el.querySelector('.product-image-photo');
      console.log('🖼️  Raw imageEl:', imageEl?.outerHTML);
      const image = imageEl?.getAttribute('data-src') || imageEl?.getAttribute('src') || '';
      console.log('🖼️ Extracted image URL:', image);

      let rawSku = (el.querySelector('small')?.textContent.match(/SKU:\s*(.+)/)?.[1] || '').trim();
      console.log('🔍 Raw SKU string:', rawSku);
      const isVariable = rawSku.endsWith('*');
      const sku = rawSku.replace(/\*$/, '');
      console.log('🔢 Final SKU:', sku);

      if (!sku) {
        console.warn('❌ Skipping due to missing SKU', el.outerHTML);
        continue;
      }

      const price = el.querySelector('.price')?.textContent.trim() || '';
      const desc = el.querySelector('.product-item-description')?.textContent.trim() || '';

      const baseProduct = {
        title,
        url: href,
        image,
        price,
        sku,
        description: desc,
        category,
        type: isVariable ? 'variable' : 'simple',
      };

      if (isVariable) {
        const pdp = await fetchPage(href);
        const variants = [];

        // Swatches
        const swatches = pdp.querySelectorAll('.swatch-option');
        swatches.forEach(opt => {
          const label = opt.getAttribute('data-option-label')?.trim();
          if (label) {
            variants.push({
              label,
              price,
              image,
              sku: `${sku}-${label.toLowerCase().replace(/\s+/g, '-')}`,
            });
          }
        });

        // Dropdowns
        const selects = pdp.querySelectorAll('select.super-attribute-select');
        selects.forEach(select => {
          const options = [...select.querySelectorAll('option')];
          options.forEach(opt => {
            const val = opt.value.trim();
            const text = opt.textContent.trim();
            if (val && val !== '') {
              variants.push({
                label: text,
                price: (text.match(/£([\d.]+)/) || [])[1] || price,
                image,
                sku: `${sku}-${val}`,
              });
            }
          });
        });

        if (variants.length) {
          baseProduct.variants = variants;
        } else {
          baseProduct.type = 'simple';
        }
      }

      console.log('✅ Final baseProduct object:', baseProduct);
      products.push(baseProduct);
    } catch (err) {
      console.error('❌ Error in product iteration:', err);
    }
  }

  console.log(`🧩 Parsed ${products.length} products from Timage`);
  return products;
}

export function renderScrapedProducts(products) {
  window.lastScrapedProducts = products;
  const output = document.getElementById('pss-scraped-output');

  if (!products.length) {
    output.innerHTML = '<p>No products scraped.</p>';
    return;
  }

  const rows = products.map((p) => {
    const imageTag = `<img src="${p.image}" style="width:60px;" onerror="this.src='${window.pssScraperData.pluginUrl}assets/img/placeholder.png'" />`;
    const truncatedTitle = p.title.length > 70 ? `${p.title.slice(0, 70)}…` : p.title;

    let variantsHtml = '';
    if (Array.isArray(p.variants)) {
      variantsHtml = `<ul style="margin: 0; padding-left: 16px;">` +
        p.variants.map(v =>
          `<li>${v.label} | £${v.price} | ${v.sku}</li>`
        ).join('') +
        `</ul>`;
    }

    return `
      <tr>
        <td class="pss-checkbox-cell"><input type="checkbox" /></td>
        <td>${imageTag}</td>
        <td><a href="${p.url}" target="_blank">${truncatedTitle}</a></td>
        <td>${p.price || '–'}</td>
        <td>${p.sku || ''}</td>
        <td>${p.description?.substring(0, 80) || ''}</td>
        <td>${variantsHtml}</td>
      </tr>`;
  }).join('');

  output.innerHTML = `
    <table class="wp-list-table widefat fixed striped pss-product-table" style="margin-top: 10px;">
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
