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

      const blockedPhrases = ['contact', 'ask', 'call'];
      const isPriceBlocked = blockedPhrases.some(p => price.toLowerCase().includes(p));
      const isNumericPrice = typeof price === 'string' && /\d/.test(price);
      if (!isNumericPrice || isPriceBlocked) continue;

      const baseProduct = {
        title,
        url: href,
        image,
        price,
        sku,
        description: desc,
        category
      };

      // Handle variants if variable SKU
      if (isVariable) {
        const pdp = await fetchPage(href);
        const variants = [];

        const swatches = pdp.querySelectorAll('.swatch-option');
        swatches.forEach(opt => {
          const label = opt.getAttribute('data-option-label')?.trim();
          if (label) {
            variants.push({
              label,
              price,
              image,
              sku: `${sku}-${label.toLowerCase().replace(/\s+/g, '-')}`
            });
          }
        });

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
                sku: `${sku}-${val}`
              });
            }
          });
        });

        baseProduct.type = variants.length ? 'variable' : 'simple';
        if (variants.length) baseProduct.variants = variants;
      } else {
        baseProduct.type = 'simple';
      }

      console.log('✅ Final baseProduct object:', baseProduct);
      products.push(baseProduct);

    } catch (e) {
      console.error('❌ Error inside product loop:', e, el.outerHTML);
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

  const rows = products.flatMap((product) => {
    const imageTag = `<img src="\${product.image}" style="width:60px;" onerror="this.src='\${window.pssScraperData.pluginUrl}assets/img/placeholder.png'">`;
    const truncatedTitle = product.title.length > 70 ? product.title.slice(0, 70) + '…' : product.title;

    // If variants exist, map them out
    if (product.variants?.length) {
      return product.variants.map((variant) => `
        <tr>
          <td class="pss-checkbox-cell"><input type="checkbox" /></td>
          <td>${imageTag}</td>
          <td><a href="${product.url}" target="_blank">${truncatedTitle} - ${variant.label}</a></td>
          <td>${variant.price || product.price || '–'}</td>
          <td>${variant.sku || product.sku || ''}</td>
          <td>${product.description?.substring(0, 80) || ''}</td>
          <td>
            ${product.specs ? `<pre style="white-space:pre-wrap; font-size:11px;">${JSON.stringify(product.specs, null, 2)}</pre>` : ''}
          </td>
        </tr>`);
    }

    // If no variants, render base product
    return `
      <tr>
        <td class="pss-checkbox-cell"><input type="checkbox" /></td>
        <td>${imageTag}</td>
        <td><a href="${product.url}" target="_blank">${truncatedTitle}</a></td>
        <td>${product.price || '–'}</td>
        <td>${product.sku || ''}</td>
        <td>${product.description?.substring(0, 80) || ''}</td>
        <td>
          ${product.specs ? `<pre style="white-space:pre-wrap; font-size:11px;">${JSON.stringify(product.specs, null, 2)}</pre>` : ''}
        </td>
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
          <th>Specs</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>`;
}
