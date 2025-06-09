// supplier-scraper/assets/engines/aquafax.js

export async function parse({ url, category }, fetchPage) {
  console.warn('🛰️ Aquafax engine active');

  const algoliaAppId = 'EMUAYXES9Y';
  const algoliaApiKey = '75421198637da8f63235d12933f3539f';

  // Step 1: look up the matching Algolia index/filter for the given URL
const map = window.pssScraperData.categoryMap || {};
const cleanUrl = url.split('?')[0]; // Needed if the URL might include query params
const urlPath = new URL(cleanUrl).pathname; // Extract just the path (e.g. "/boat-control/...")

console.log('🔍 Cleaned URL:', cleanUrl);
console.log('🧭 URL Path:', urlPath);
console.log('🗺️ Available keys in map:', Object.keys(map));

// Retrieve the markup from the global config
const markupMap = window.pssScraperData.markupMap || {};
const supplier = 'aquafax'; // hardcoded for this engine
const markup = parseFloat(markupMap[supplier]) || 0;

// Markup utility function
function applyMarkup(price, markup) {
  const num = parseFloat(price);
  return isNaN(num) ? price : (num * (1 + markup / 100)).toFixed(2);
}


const raw = map[urlPath];

if (!raw || typeof raw !== 'object' || !raw.index || !raw.filter) {
  throw new Error(`No Algolia mapping found for path: ${urlPath}`);
}

const indexName = raw.index;
const filters = raw.filter.replace(/\\"/g, '"'); // unescape quotes




  // Step 2: Query Algolia
  const response = await fetch(`https://${algoliaAppId}-dsn.algolia.net/1/indexes/*/queries`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Algolia-Application-Id': algoliaAppId,
      'X-Algolia-API-Key': algoliaApiKey,
    },
    body: JSON.stringify({
      requests: [
        {
          indexName,
          params: new URLSearchParams({
            hitsPerPage: '1000',
            filters
          }).toString()
        }
      ]
    })
  });

  const data = await response.json();
  const hits = data.results?.[0]?.hits || [];

  return hits.map(hit => {
  const rawPrice = hit.price?.value || hit.price || '';
  const markedUpPrice = applyMarkup(rawPrice, markup);
  const imageMedia = hit.media?.find(m => m.type === 'image');
  const imageUrl = imageMedia
    ? `https://d1blekj7w4kc3j.cloudfront.net/images/presets/product_page_small/${imageMedia.filename}`
    : '';

  return {
    title: hit.name || hit.title || 'Untitled',
    url: `https://www.aquafax.co.uk/${hit.slug || hit.url || hit.path || ''}`,
    price: markedUpPrice,
    sku: hit.sku || '',
    stock: hit.stockLevel ?? hit.stock ?? 'Unknown',
    image: imageUrl,
    description: hit.description || '',
    category,
  };
});



}



export function renderScrapedProducts(products) {
  const $output = jQuery('#pss-scraped-output').empty();

  const $table = jQuery(`
    <table class="pss-product-table" style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
      <thead>
        <tr style="background-color: #f8f8f8;">
          <th style="padding: 8px; text-align: center;"><input type="checkbox" id="select-all" /></th>
          <th style="padding: 8px;">Image</th>
          <th style="padding: 8px;">Title</th>
          <th style="padding: 8px;">Price</th>
          <th style="padding: 8px;">SKU</th>
          <th style="padding: 8px;">Stock</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  `);

  const $tbody = $table.find('tbody');

  products.forEach((p, i) => {
    const rowStyle = i % 2 === 0 ? 'background-color: #fff;' : 'background-color: #f1f1f1;';
    $tbody.append(`
      <tr style="${rowStyle} vertical-align: middle;">
        <td style="text-align: center;"><input type="checkbox" class="row-check" /></td>
        <td style="padding: 6px; text-align: center;"><img src="${p.image}" style="max-width: 60px; height: auto;"></td>
        <td style="padding: 6px;">${p.title}</td>
        <td style="padding: 6px;">${p.price}</td>
        <td style="padding: 6px;">${p.sku}</td>
        <td style="padding: 6px;">${p.stock}</td>
      </tr>
    `);
  });

  $output.append($table);

  // Select all checkbox logic
  jQuery('#select-all').on('change', function () {
    const checked = this.checked;
    jQuery('.row-check').prop('checked', checked);
  });
}

const Papa = window.Papa;
if (!Papa) {
  throw new Error('❌ PapaParse not available. Make sure it is loaded before this script.');
}



export async function enrich(products) {
  console.info('🔧 Enriching Aquafax products via CSV + markup');

  // Step 1: Fetch and parse the remote CSV
  const csvUrl = 'https://files.channable.com/Vwpjj7Daj_1AEVeCZKB_8g==.csv';
  const response = await fetch(csvUrl);
  if (!response.ok) throw new Error(`Failed to fetch Aquafax CSV: ${response.status}`);

  const text = await response.text();
  const parsed = Papa.parse(text, { header: true });
  const rows = parsed.data;

  // Step 2: Map CSV data by SKU (normalize keys just in case)
  const csvMap = {};
  for (const row of rows) {
    const sku = row['sku']?.trim();
    if (sku) {
      csvMap[sku] = {
        title: row['title'] || '',
        description: row['description'] || '',
        price: row['net_price'] || '',
        stock: row['group_stock'] || '',
        parent_part: row['parent_part'] || '',
      };
    }
  }

  // Step 3: Apply enrichment from CSV
  products.forEach(p => {
    const match = csvMap[p.sku];
    if (match) {
      if (match.price) p.price = match.price;
      if (match.stock) p.stock = match.stock;
      if (match.description) p.description = match.description;
      // Optionally: attach parent_part if needed in future
      p._source = 'csv'; // For debugging if needed
    }
  });

  return products;

  // Step 4: Apply markup - Legacy - We apply markup serverside now
  //const markupMap = window.pssScraperData.markupMap || {};
  //const markupPercent = parseFloat(markupMap.aquafax || '0');
  //console.log('🧮 Aquafax markup:', markupPercent);

  //return products.map(p => {
    //const originalPrice = parseFloat(p.price) || 0;
    //const markedUpPrice = (originalPrice * (1 + markupPercent / 100)).toFixed(2);
    //return {
      //...p,
      //price: markedUpPrice,
      //_originalPrice: originalPrice,
    //};
  //});
}



