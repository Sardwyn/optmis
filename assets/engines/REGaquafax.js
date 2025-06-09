export async function parse({ url }, fetchPage) {
    const products = [];
  
    const categoryMap = {
      "/boat-control/engine-controls": "Boat Control > Engine Controls",
      "/boat-control/steering-systems": "Boat Control > Steering Systems",
      "/electrical/batteries": "Electrical > Batteries",
      // ➕ Add more mappings as needed
    };
  
    const urlPath = new URL(url).pathname;
    const matchedKey = Object.keys(categoryMap).find(path => urlPath.startsWith(path));
    const category = matchedKey ? categoryMap[matchedKey] : null;
  
    if (!category) {
      console.warn("❌ Aquafax: No matching category for", urlPath);
      return [];
    }
  
    const algoliaUrl =
      'https://emuayxes9y-dsn.algolia.net/1/indexes/*/queries?x-algolia-agent=Algolia%20for%20JavaScript%3B%20Browser%3B%20instantsearch.js&x-algolia-api-key=ea2e5abdc754dad79f56972bb523e081&x-algolia-application-id=EMUAYXES9Y';
  
    let page = 0;
    const maxPages = 10; // 🔁 Use a sane limit
    const hitsPerPage = 48;
  
    try {
      while (page < maxPages) {
        const payload = {
          requests: [
            {
              indexName: 'production_aquafax',
              params: `clickAnalytics=true&facetFilters=%5B%5D&filters=(categories.lvl1%3A%22${encodeURIComponent(
                category
              )}%22)&hitsPerPage=${hitsPerPage}&page=${page}`
            }
          ]
        };
  
        const res = await fetch(algoliaUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
  
        const json = await res.json();
        const hits = json?.results?.[0]?.hits || [];
        if (!hits.length) break;
  
        for (const hit of hits) {
          const title = hit.name || 'Untitled';
          const image = hit.image?.filename
            ? `https://d1xmkx19apgl7d.cloudfront.net/images/presets/category_page_thumbnail/${hit.image.filename}`
            : '';
          const productUrl = `https://www.aquafax.co.uk/products/${hit.slug}`;
  
          products.push({
            title,
            image,
            url: productUrl,
            price: 'Login to view',
          });
        }
  
        page++;
      }
  
      console.warn(`🧩 Parsed ${products.length} Aquafax products from ${url}`);
      return products;
    } catch (err) {
      console.error('❌ Aquafax parse failed:', err);
      return [];
    }
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
          <td>
            ${
              p.specs
                ? `<pre style="white-space:pre-wrap; font-size:11px;">${JSON.stringify(p.specs, null, 2)}</pre>`
                : ''
            }
          </td>
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
  
  