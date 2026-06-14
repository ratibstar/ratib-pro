(function () {
    var form = document.querySelector('[data-inventory-form]');
    if (!form) return;

    var warehouseSelect = form.querySelector('[name="warehouse_id"]');
    var existingSelect = form.querySelector('[data-inventory-existing]');
    var itemNameWrap = form.querySelector('[data-inventory-new-item]');
    var itemNameInput = form.querySelector('[name="item_name"]');
    var skuInput = form.querySelector('[name="sku"]');
    var qtyInput = form.querySelector('[data-inventory-quantity]');
    var unitCostInput = form.querySelector('[name="unit_cost"]');
    var reorderInput = form.querySelector('[name="reorder_level"]');
    var maxStockInput = form.querySelector('[name="max_stock"]');
    var unitSelect = form.querySelector('[name="unit"]');
    var categorySelect = form.querySelector('[name="category_id"]');
    var movementSelect = form.querySelector('[data-inventory-movement-type]');
    var lineTotal = form.querySelector('[data-inventory-line-total]');
    var reorderAlert = form.querySelector('[data-inventory-reorder-alert]');
    var maxAlert = form.querySelector('[data-inventory-max-alert]');
    var qtyLabel = form.querySelector('[data-inventory-qty-label]');
    var itemsUrl = form.getAttribute('data-warehouse-items-url') || '';
    var isEdit = form.getAttribute('data-is-edit') === '1';
    var currentQty = parseFloat(form.getAttribute('data-current-qty') || '0') || 0;
    var itemsCache = {};

    var labels = {
        quantity: qtyLabel ? qtyLabel.textContent : 'Quantity',
        movementQty: qtyLabel ? (qtyLabel.getAttribute('data-movement-label') || qtyLabel.textContent) : 'Quantity'
    };

    function num(el) {
        if (!el) return 0;
        var v = parseFloat(String(el.value || '').replace(',', '.'));
        return isNaN(v) ? 0 : v;
    }

    function formatMoney(n) {
        return (Math.round(n * 100) / 100).toFixed(2);
    }

    function effectiveQty() {
        var qty = num(qtyInput);
        var movement = movementSelect ? movementSelect.value : '';
        if (isEdit && movement) {
            if (movement === 'in') return currentQty + qty;
            if (movement === 'out') return Math.max(0, currentQty - qty);
            if (movement === 'adjustment') return qty;
        }
        return qty;
    }

    function updateLineTotal() {
        if (!lineTotal) return;
        var total = effectiveQty() * num(unitCostInput);
        lineTotal.value = formatMoney(total);
    }

    function updateAlerts() {
        var eff = effectiveQty();
        var reorder = num(reorderInput);
        var maxStock = num(maxStockInput);
        if (reorderAlert) {
            reorderAlert.classList.toggle('d-none', !(reorder > 0 && eff <= reorder));
        }
        if (maxAlert) {
            maxAlert.classList.toggle('d-none', !(maxStock > 0 && eff > maxStock));
        }
    }

    function updateQtyLabel() {
        if (!qtyLabel || !movementSelect) return;
        var movementLabel = qtyLabel.getAttribute('data-movement-label') || labels.movementQty;
        if (isEdit && movementSelect.value) {
            qtyLabel.textContent = movementLabel;
        } else {
            qtyLabel.textContent = labels.quantity;
        }
    }

    function toggleNewItemFields() {
        var existingId = existingSelect ? existingSelect.value : '';
        var isExisting = existingId !== '';
        if (itemNameWrap) itemNameWrap.classList.toggle('d-none', isExisting);
        if (itemNameInput) {
            itemNameInput.required = !isExisting;
            if (isExisting) itemNameInput.value = '';
        }
        if (movementSelect && !isEdit) {
            movementSelect.querySelectorAll('option').forEach(function (opt) {
                if (opt.value === 'out') {
                    opt.disabled = !isExisting;
                    if (!isExisting && opt.selected) {
                        movementSelect.value = 'in';
                    }
                }
            });
        }
    }

    function fillFromItem(row) {
        if (!row) return;
        if (itemNameInput) itemNameInput.value = row.item_name || '';
        if (skuInput) skuInput.value = row.sku || '';
        if (unitCostInput) unitCostInput.value = row.unit_cost || '';
        if (reorderInput) reorderInput.value = row.reorder_level || '';
        if (maxStockInput) maxStockInput.value = row.max_stock || '';
        if (unitSelect && row.unit) unitSelect.value = row.unit;
        if (categorySelect && row.category_id) categorySelect.value = String(row.category_id);
        updateLineTotal();
        updateAlerts();
    }

    function loadWarehouseItems(warehouseId) {
        if (!existingSelect || !itemsUrl || warehouseId < 1) {
            if (existingSelect) {
                existingSelect.innerHTML = '<option value="">' + (existingSelect.options[0] ? existingSelect.options[0].textContent : '') + '</option>';
            }
            return;
        }
        if (itemsCache[warehouseId]) {
            renderItems(itemsCache[warehouseId]);
            return;
        }
        fetch(itemsUrl + '?warehouse_id=' + encodeURIComponent(String(warehouseId)), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                itemsCache[warehouseId] = data.items || [];
                renderItems(itemsCache[warehouseId]);
            })
            .catch(function () {
                itemsCache[warehouseId] = [];
                renderItems([]);
            });
    }

    function renderItems(items) {
        if (!existingSelect) return;
        var firstLabel = existingSelect.querySelector('option[value=""]');
        var label = firstLabel ? firstLabel.textContent : '';
        existingSelect.innerHTML = '<option value="">' + label + '</option>';
        items.forEach(function (row) {
            var opt = document.createElement('option');
            opt.value = String(row.id);
            var sku = (row.sku || '').trim();
            opt.textContent = sku ? (sku + ' — ' + (row.item_name || '')) : (row.item_name || '');
            opt.setAttribute('data-item', JSON.stringify(row));
            existingSelect.appendChild(opt);
        });
        toggleNewItemFields();
    }

    if (warehouseSelect) {
        warehouseSelect.addEventListener('change', function () {
            loadWarehouseItems(parseInt(warehouseSelect.value, 10) || 0);
            toggleNewItemFields();
        });
        var whId = parseInt(warehouseSelect.value, 10) || 0;
        if (whId > 0) loadWarehouseItems(whId);
    }

    if (existingSelect) {
        existingSelect.addEventListener('change', function () {
            var opt = existingSelect.options[existingSelect.selectedIndex];
            var raw = opt ? opt.getAttribute('data-item') : '';
            toggleNewItemFields();
            if (raw) {
                try { fillFromItem(JSON.parse(raw)); } catch (e) { /* ignore */ }
            }
        });
    }

    [qtyInput, unitCostInput, reorderInput, maxStockInput].forEach(function (el) {
        if (el) el.addEventListener('input', function () {
            updateLineTotal();
            updateAlerts();
        });
    });

    if (movementSelect) {
        movementSelect.addEventListener('change', function () {
            updateQtyLabel();
            if (isEdit && movementSelect.value && qtyInput) {
                qtyInput.value = '';
            }
            updateLineTotal();
            updateAlerts();
        });
    }

    toggleNewItemFields();
    updateQtyLabel();
    updateLineTotal();
    updateAlerts();
})();
