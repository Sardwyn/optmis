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
    console.log('🧩 Parsed', products.length, 'products from Timage');
  
    const output = document.getElementById('pss-scraped-output');
    if (!products.length) {
      output.innerHTML = '<p>No products scraped.</p>';
      return;
    }
  
    // Group products by base SKU
    const grouped = {};
    for (const p of products) {
      const base = p.sku.replace(/[-._]?\d+[^-._]*$/, '').split('-')[0]; // Basic base SKU heuristic
      if (!grouped[base]) grouped[base] = [];
      grouped[base].push(p);
    }
  
    // Render HTML table
    let tableHTML = `
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
        <tbody>`;
  
    Object.entries(grouped).forEach(([base, group], i) => {
      const groupId = `variant-group-${i}`;
      const baseProduct = group[0];
  
      const imageTag = `<img src="${baseProduct.image}" style="width:60px;" onerror="this.src='${window.pssScraperData.pluginUrl}assets/img/placeholder.png'">`;
      const truncatedTitle = baseProduct.title.length > 70 ? baseProduct.title.slice(0, 70) + '…' : baseProduct.title;
  
      // Base product row
      tableHTML += `
        <tr>
          <td class="pss-checkbox-cell"><input type="checkbox" /></td>
          <td>${imageTag}</td>
          <td>
            <a href="${baseProduct.url}" target="_blank">${truncatedTitle}</a>
            ${group.length > 1 ? `<br><button data-toggle="${groupId}" class="pss-toggle-variants" style="margin-top:5px;">Show ${group.length - 1} variants</button>` : ''}
          </td>
          <td>${baseProduct.price || '–'}</td>
          <td>${baseProduct.sku || ''}</td>
          <td>${baseProduct.description?.substring(0, 80) || ''}</td>
          <td>${
            baseProduct.specs
              ? `<pre style="white-space:pre-wrap; font-size:11px;">${JSON.stringify(baseProduct.specs, null, 2)}</pre>`
              : ''
          }</td>
        </tr>`;
  
      // Variant rows
      if (group.length > 1) {
        group.slice(1).forEach(v => {
          tableHTML += `
            <tr class="variant-row" data-group="${groupId}" style="display:none;">
              <td class="pss-checkbox-cell"><input type="checkbox" /></td>
              <td><img src="${v.image}" style="width:60px;" onerror="this.src='${window.pssScraperData.pluginUrl}assets/img/placeholder.png'"></td>
              <td><a href="${v.url}" target="_blank">${v.title}</a></td>
              <td>${v.price || '–'}</td>
              <td>${v.sku || ''}</td>
              <td>${v.description?.substring(0, 80) || ''}</td>
              <td>${
                v.specs
                  ? `<pre style="white-space:pre-wrap; font-size:11px;">${JSON.stringify(v.specs, null, 2)}</pre>`
                  : ''
              }</td>
            </tr>`;
        });
      }
    });
  
    tableHTML += `</tbody></table>`;
    output.innerHTML = tableHTML;
  
    // Attach toggle logic
    document.querySelectorAll('.pss-toggle-variants').forEach(btn => {
      btn.addEventListener('click', () => {
        const group = btn.getAttribute('data-toggle');
        const rows = document.querySelectorAll(`tr[data-group="${group}"]`);
        const isOpen = rows[0]?.style.display === 'table-row';
        rows.forEach(r => r.style.display = isOpen ? 'none' : 'table-row');
        btn.textContent = isOpen ? `Show ${rows.length} variants` : `Hide ${rows.length} variants`;
      });
    });
  }
  