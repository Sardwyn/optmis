export async function parse({ url, category }, fetchPage) {
  const doc = await fetchPage(url);
  const products = [];

  const blocks = doc.querySelectorAll('.product-card');
  blocks.forEach(el => {
    const title = el.querySelector('.title')?.textContent.trim() || '';
    const image = el.querySelector('img')?.getAttribute('src') || '';
    const href = el.querySelector('a')?.href || '';

    if (title && href) {
      products.push({
        title,
        price: '',
        image,
        url: new URL(href, url).href,
        category
      });
    }
  });

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
    const imageTag = `<img src="${p.image}" style="width:60px;" onerror="this.src='${window.pssScraperData.pluginUrl}assets/img/placeholder.png'">`;
    const truncatedTitle = p.title.length > 70 ? p.title.slice(0, 70) + '…' : p.title;

    return `
      <tr>
        <td class="pss-checkbox-cell"><input type="checkbox" /></td>
        <td>${imageTag}</td>
        <td><a href="${p.url}" target="_blank">${truncatedTitle}</a></td>
        <td>${p.price || '–'}</td>
        <td>${p.sku || ''}</td>
        <td>${p.description?.substring(0, 80) || ''}</td>
        <td>${
          p.specs
            ? `<pre style="white-space:pre-wrap; font-size:11px;">${JSON.stringify(p.specs, null, 2)}</pre>`
            : ''
        }</td>
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

