export async function parse({ url }, fetchPage) {
  const doc = await fetchPage(url);
  const products = [];

  const items = doc.querySelectorAll('.product-item-info');

  items.forEach(el => {
    const title = el.querySelector('.product-item-link')?.textContent.trim() || '';
    const href = el.querySelector('.product-item-link')?.href || '';
    const image = el.querySelector('.product-image-photo')?.src || '';
    const price = el.querySelector('.price')?.textContent.trim() || '';
    const sku = el.querySelector('[data-sku]')?.getAttribute('data-sku') || '';
    const desc = el.querySelector('.product-item-description')?.textContent.trim() || '';

    if (title && href) {
      products.push({
        title,
        url: href,
        image,
        price,
        sku,
        description: desc,
      });
    }
  });

  console.warn(`🧩 Parsed ${products.length} products from Timage`);
  return products;
}



export function renderScrapedProducts(products) {
  const output = document.getElementById('pss-scraped-output');
  if (!products.length) {
    output.innerHTML = '<p>No products scraped.</p>';
    return;
  }

  const rows = products.map((p) => {
    const imageTag = `<img src="${p.image}" style="width:60px;" onerror="this.src='${window.pssScraperData.pluginUrl}assets/img/placeholder.png'">`;
    return `
      <tr>
        <td>${imageTag}</td>
        <td><a href="${p.url}" target="_blank">${p.title}</a></td>
        <td>${p.price || '–'}</td>
        <td>${p.sku || ''}</td>
        <td>${p.description?.substring(0, 80) || ''}</td>
        <td></td>
      </tr>`;
  }).join('');

  output.innerHTML = `
    <table class="wp-list-table widefat fixed striped">
      <thead>
        <tr><th>Image</th><th>Title</th><th>Price</th><th>SKU</th><th>Description</th><th>Specs</th></tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>`;
}
