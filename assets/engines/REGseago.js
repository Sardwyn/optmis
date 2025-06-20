export async function parse({ url }, fetchPage) {
  console.warn(`🔥 seago.js module loaded at ${Date.now()}`);
  const doc = await fetchPage(url);

  console.warn('📄 HTML Preview:', doc.documentElement.innerHTML.slice(0, 1000));

  const products = [];
  const cards = doc.querySelectorAll('.category-products-cell.w-dyn-item');

  console.warn('🔍 Found product blocks:', cards.length);

  for (const card of cards) {
    const a = card.querySelector('a.hover-image');
    const img = card.querySelector('img.image-100');
    const title = card.querySelector('.category-headings')?.textContent?.trim() || '';
    const href = a?.getAttribute('href');
    const image = img?.getAttribute('src');

    if (href && title) {
      products.push({
        title,
        price: '',
        image: image || '',
        url: new URL(href, url).href,
      });
    }
  }

  console.warn(`🧩 Parsed ${products.length} products from ${url}`);
  return products;
}

export async function enrichWithSeago(product, options = {}, fetchPage) {
  const url = product?.url;
  if (!url) throw new Error("Missing product URL for enrichment");

  console.warn("🧭 Enriching from Seago product page:", url);

  let doc;
  try {
    doc = await fetchPage(url);
  } catch (err) {
    console.warn("❌ Failed to fetch Seago product page:", url, err);
    return null;
  }

  // Try main image first
  let image = doc.querySelector('.hover-image img, .image-100, img')?.getAttribute('src') || '';
  if (image?.includes('52cloudacute')) {
    image = product.image || '';
  }

  const desc =
    doc.querySelector('meta[name="description"]')?.content?.trim() ||
    doc.querySelector('.product-title, h1')?.textContent?.trim() ||
    '';

  const sku =
    doc.querySelector('.sku, [itemprop="sku"]')?.textContent?.trim() || '';

  // Extract specs
  const specBlock = doc.querySelector('[data-w-tab="Tab 2"] .w-richtext');
  let specs = {};

  if (specBlock) {
    const lines = Array.from(specBlock.querySelectorAll('p')).map(el => el.textContent.trim());
    for (const line of lines) {
      const match = line.match(/^(.+?)\s*[:–—]\s*(.+)$/);
      if (match) {
        const [_, key, value] = match;
        specs[key.trim()] = value.trim();
      }
    }
    console.warn("🔧 Extracted specs:", specs);
  }

  return {
    image,
    url,
    sku,
    description: desc,
    specs, // This will be available for oym_signature
  };
}

export function renderScrapedProducts(products) {
  const output = document.getElementById('pss-scraped-output');
  if (!products.length) {
    output.innerHTML = '<p>No products scraped.</p>';
    return;
  }

  const rows = products.map((p) => {
    const imageTag = `<img src="${p.image}" style="width:60px;" onerror="this.src='${window.pssScraperData.pluginUrl}assets/img/placeholder.png'">`;
    const truncatedTitle = p.title.length > 70 ? p.title.slice(0, 70) + '…' : p.title;

    return `
      <tr>
        <td>${imageTag}</td>
        <td><a href="${p.url}" target="_blank">${truncatedTitle}</a></td>
        <td>${p.price || '–'}</td>
        <td>${p.sku || ''}</td>
        <td>${p.description?.substring(0, 80) || ''}...</td>
        <td>${
          p.specs
            ? `<pre style="white-space:pre-wrap; font-size:11px;">${JSON.stringify(p.specs, null, 2)}</pre>`
            : ''
        }</td>
      </tr>`;
  }).join('');

  output.innerHTML = `
    <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
      <thead>
        <tr>
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


