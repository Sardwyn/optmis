jQuery(document).ready(function ($) {
    const suppliers = pssMappingData.suppliers || [];
  
    // Populate supplier dropdown
    const $select = $('#pss-mapping-supplier-select');
    suppliers.forEach(s => {
      $select.append(`<option value="${s.slug}">${s.name}</option>`);
    });
  
    // Mock category data (for development/testing)
    
  
    // Render table rows for the selected supplier
    function renderMappings(supplier) {
      const $tbody = $('#pss-mapping-results');
      $tbody.empty();
  
      $.get(pssMappingData.ajaxUrl, {
        action: 'pss_get_category_mappings',
        nonce: pssMappingData.nonce,
        supplier
      }, function (res) {
        if (!res.success || !Array.isArray(res.data)) res.data = [];
  
        let rows = res.data;
        if (!rows.length && pssMappingData.savedCategories[supplier]) {
          rows = pssMappingData.savedCategories[supplier].map(r => ({ ...r, woo_category_id: null }));
        }
        if (!rows.length) {
          $tbody.append('<tr><td colspan="4"><em>No categories found for this supplier.</em></td></tr>');
          return;
        }
  
        rows.forEach(row => {
          const $row = $('<tr></tr>');
          $row.append(`<td>${row.name}</td>`);
          $row.append(`<td><a href="${row.url}" target="_blank">${row.url}</a></td>`);
  
          const $select = $('<select class="woocommerce-category-select"></select>');
          pssMappingData.categories.forEach(cat => {
            const selected = cat.id == row.woo_category_id ? 'selected' : '';
            $select.append(`<option value="${cat.id}" ${selected}>${cat.name}</option>`);
          });
  
          const $selectCell = $('<td></td>').append($select);
          $row.append($selectCell);
  
          const $deleteBtn = $('<button class="button-link-delete" style="color:#a00;">Delete</button>');
          const $actionCell = $('<td></td>').append($deleteBtn);
          $row.append($actionCell);
  
          $deleteBtn.on('click', function () {
            $row.remove();
          });
  
          $tbody.append($row);
        });
        
      });
    }
  
      
  
    // On supplier selection
    $select.on('change', function () {
      const supplier = $(this).val();
      renderMappings(supplier);
    });
  
    // Initial render for first supplier if available
    if (suppliers.length) {
      $select.val(suppliers[0].slug).trigger('change');
    }
  
    // Save mappings
    $(document).on('click', '#pss-save-mappings', function () {
      const supplier = $('#pss-mapping-supplier-select').val();
      const mappings = [];
  
      $('#pss-mapping-results tr').each(function () {
        const $row = $(this);
        const name = $row.find('td').eq(0).text().trim();
        const url  = $row.find('td').eq(1).text().trim();
        const catId = $row.find('select').val();
        if (name && url && catId) {
          mappings.push({ name, url, woo_category_id: catId });
        }
      });
  
      $.post(pssMappingData.ajaxUrl, {
        action: 'pss_save_category_mappings',
        nonce: pssMappingData.nonce,
        supplier,
        mappings
      }, function (res) {
        if (res.success) {
          alert('Mappings saved successfully.');
        } else {
          alert('Failed to save mappings.');
        }
      });
    });
  });
  