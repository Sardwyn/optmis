import { runParsers } from './strategy-runner.js';

export async function parse({ url, category }, fetchPage) {
  console.warn("🧪 Using Fallback Map Version 2!");

  const fallbackCategoryMap = {
    "/boat-control/engine-controls/control-boxes": "Boat Control > Engine Controls > Control Boxes",
    "/boat-control/engine-controls/control-cables": "boat Control > Engine Control > Control Cables",
    "/deck-hardware/anchors": "Deck hardware > Anchors",
    "/deck-hardware/windlasses": "Deck hardware > Windlasses",
    "/navigation/compasses": "Navigation > Compasses",
    "/electrical/lights/navigation-lights": "Electrical > Lights > Navigation Lights",
    "/engine/spares/fuel-filters": "Engine > Spares > Fuel Filters",
    "/plumbing/pumps/bilge-pumps": "Plumbing > Pumps > Bilge Pumps"
  };

  const products = [];

  const categoryMap = fallbackCategoryMap;
  console.log("🧪 categoryMap source:", window.pssScraperData?.categoryMap ? "window.pssScraperData" : "fallback");

  const urlPath = new URL(url).pathname;

  console.log("🔬 Matching URL path:", urlPath);
  console.log("🧩 Final categoryMap keys:", Object.keys(categoryMap));

  const matchedKey = Object.keys(categoryMap).find(key => urlPath.includes(key));
  const algoliaCategory = matchedKey ? categoryMap[matchedKey] : null;

  if (!algoliaCategory) {
    console.warn(`❌ No matching Algolia category for path: "${urlPath}"`);
    console.warn(`🧭 Available keys: ${Object.keys(categoryMap).join(', ')}`);
    return [];
  }

  console.log(`✅ Matched "${matchedKey}" to Algolia category "${algoliaCategory}"`);


  const algoliaUrl =
    'https://emuayxes9y-dsn.algolia.net/1/indexes/*/queries?x-algolia-agent=Algolia%20for%20JavaScript%3B%20Browser%3B%20instantsearch.js&x-algolia-api-key=ea2e5abdc754dad79f56972bb523e081&x-algolia-application-id=EMUAYXES9Y';

  let page = 0;
  const maxPages = 10;
  const hitsPerPage = 48;

  try {
    while (page < maxPages) {
      const payload = {
        requests: [
          {
            indexName: 'production_aquafax',
            params: `facetFilters=["categories.lvl2:${encodeURIComponent(algoliaCategory)}"]&hitsPerPage=${hitsPerPage}&page=${page}`

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
        const sku = hit.sku || '';
        const brand = hit.attributes?.['228'] || '';
        const stock = hit.stockLevel ?? null;
        const rawDescription = hit.attributes?.['48'] || '';
        const keywords = rawDescription.split(',').map(tag => tag.trim()).filter(Boolean);
        const isVariable = hit.isConfigurable === true;
        const categoryPath = hit.categories?.lvl2?.[0] || '';
        const image = hit.image?.filename
          ? `https://d1xmkx19apgl7d.cloudfront.net/images/presets/category_page_thumbnail/${hit.image.filename}`
          : '';
        const gallery = (hit.media || [])
          .filter(m => m.type === 'image')
          .map(m => `https://d1xmkx19apgl7d.cloudfront.net/images/presets/category_page_thumbnail/${m.filename}`);
        const productUrl = `https://www.aquafax.co.uk/products/${hit.slug}`;
      
        console.log(`🧾 Product: ${title} | SKU: ${sku} | Stock: ${stock} | Brand: ${brand}`);
      
        products.push({
          title,
          sku,
          brand,
          stock,
          url: productUrl,
          price: "0.00",
          image,
          gallery,
          description: "", // force AI description fallback
          tags: keywords,  // parsed from attributes[48]
          isVariable,
          category: category,
          categoryPath
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


// ✅ Scraped product renderer
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
