export function groupVariantsForWoo(products) {
  const grouped = [];

  for (const p of products) {
    if (!p.configurable || !Array.isArray(p.variants) || p.variants.length === 0) continue;

    grouped.push({
      type: 'variable',
      parent: {
        title: p.title,
        sku: p.sku + '-configurable',
        description: p.description || '',
        image: p.image,
      },
      variants: p.variants.map(v => ({
        sku: v.sku,
        label: v.label,
        price: v.price || '',
      })),
    });
  }

  return grouped;
}

// ✅ Globally expose for UI binding
window.wooGrouping = { groupVariantsForWoo };
