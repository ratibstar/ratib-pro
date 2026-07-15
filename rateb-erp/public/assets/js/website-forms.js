(function () {
  'use strict';
  function boot() {
    var root = document.getElementById('websiteFormBuilder');
    if (!root) return;
    var list = document.getElementById('wbFieldsList');
    var hidden = document.getElementById('wbFieldsJson');
    var fields = [];
    try { fields = JSON.parse(root.getAttribute('data-fields') || '[]'); } catch (e) { fields = []; }
    if (!Array.isArray(fields)) fields = [];

    function render() {
      list.innerHTML = '';
      fields.forEach(function (f, i) {
        var row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-end';
        row.innerHTML =
          '<div class="col-md-2"><label class="form-label">Key</label><input class="form-control form-control-sm" data-i="' + i + '" data-k="field_key" value="' + (f.field_key || '') + '"></div>' +
          '<div class="col-md-2"><label class="form-label">Type</label><select class="form-select form-select-sm" data-i="' + i + '" data-k="field_type">' +
          ['text','email','tel','textarea','select','number'].map(function (t) {
            return '<option value="' + t + '"' + ((f.field_type || 'text') === t ? ' selected' : '') + '>' + t + '</option>';
          }).join('') + '</select></div>' +
          '<div class="col-md-3"><label class="form-label">Label EN</label><input class="form-control form-control-sm" data-i="' + i + '" data-k="label_en" value="' + (f.label_en || '') + '"></div>' +
          '<div class="col-md-3"><label class="form-label">Label AR</label><input class="form-control form-control-sm" data-i="' + i + '" data-k="label_ar" value="' + (f.label_ar || '') + '"></div>' +
          '<div class="col-md-1 form-check mt-4"><input class="form-check-input" type="checkbox" data-i="' + i + '" data-k="is_required"' + (f.is_required ? ' checked' : '') + '><label class="form-check-label">Req</label></div>' +
          '<div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" data-remove="' + i + '">×</button></div>';
        list.appendChild(row);
      });
    }

    list.addEventListener('input', function (e) {
      var t = e.target;
      var i = parseInt(t.getAttribute('data-i'), 10);
      var k = t.getAttribute('data-k');
      if (isNaN(i) || !k || !fields[i]) return;
      if (k === 'is_required') fields[i][k] = t.checked ? 1 : 0;
      else fields[i][k] = t.value;
    });
    list.addEventListener('change', function (e) {
      var t = e.target;
      var i = parseInt(t.getAttribute('data-i'), 10);
      var k = t.getAttribute('data-k');
      if (isNaN(i) || !k || !fields[i]) return;
      if (k === 'is_required') fields[i][k] = t.checked ? 1 : 0;
      else fields[i][k] = t.value;
    });
    list.addEventListener('click', function (e) {
      var rm = e.target.getAttribute('data-remove');
      if (rm === null) return;
      fields.splice(parseInt(rm, 10), 1);
      render();
    });

    document.getElementById('wbAddField').addEventListener('click', function () {
      fields.push({ field_key: 'field_' + (fields.length + 1), field_type: 'text', label_en: 'Field', label_ar: '', is_required: 0 });
      render();
    });

    document.getElementById('wbFormEditor').addEventListener('submit', function () {
      hidden.value = JSON.stringify(fields);
    });

    render();
  }
  document.addEventListener('DOMContentLoaded', boot);
})();
