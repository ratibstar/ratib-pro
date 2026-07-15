(function () {
  'use strict';

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function postForm(url, data) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) {
      var v = data[k];
      if (v === null || v === undefined) return;
      if (typeof v === 'object' && !(v instanceof Blob)) {
        fd.append(k, typeof v === 'string' ? v : JSON.stringify(v));
      } else {
        fd.append(k, v);
      }
    });
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function boot() {
    var root = qs('#websiteBuilderRoot');
    if (!root) return;
    var csrf = root.getAttribute('data-csrf') || '';
    var pageId = root.getAttribute('data-page-id') || '';
    var pageSlug = root.getAttribute('data-page-slug') || '';
    var selectedType = null;
    var dragBlock = null;
    var dragSection = null;

    function collectOrder() {
      var sections = [];
      var blocks = {};
      qsa('.wb-section-card', qs('#wbCanvas')).forEach(function (sec) {
        var sid = sec.getAttribute('data-section-id');
        sections.push(sid);
        blocks[sid] = qsa('.wb-block-card', sec).map(function (b) { return b.getAttribute('data-block-id'); });
      });
      return { sections: sections, blocks: blocks };
    }

    function saveOrder() {
      var order = collectOrder();
      var fd = new FormData();
      fd.append('_csrf', csrf);
      order.sections.forEach(function (id) { fd.append('sections[]', id); });
      Object.keys(order.blocks).forEach(function (sid) {
        order.blocks[sid].forEach(function (bid) { fd.append('blocks[' + sid + '][]', bid); });
      });
      return fetch(root.getAttribute('data-reorder-url'), { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); });
    }

    qsa('.wb-palette-item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        qsa('.wb-palette-item').forEach(function (b) { b.classList.remove('is-selected'); });
        btn.classList.add('is-selected');
        selectedType = btn.getAttribute('data-block-type');
      });
      btn.addEventListener('dragstart', function (e) {
        selectedType = btn.getAttribute('data-block-type');
        e.dataTransfer.setData('text/plain', 'new:' + selectedType);
      });
      btn.setAttribute('draggable', 'true');
    });

    qs('#wbAddSection') && qs('#wbAddSection').addEventListener('click', function () {
      postForm(root.getAttribute('data-add-section-url'), {
        _csrf: csrf,
        page_slug: pageSlug,
        section_key: 'section_' + Date.now(),
        title_en: 'New section'
      }).then(function () { location.reload(); });
    });

    qsa('.wb-drop-hint').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!selectedType) return;
        postForm(root.getAttribute('data-add-block-url'), {
          _csrf: csrf,
          section_id: btn.getAttribute('data-section-id'),
          block_type: selectedType
        }).then(function () { location.reload(); });
      });
    });

    qsa('.wb-block-list').forEach(function (list) {
      list.addEventListener('dragover', function (e) {
        e.preventDefault();
        list.parentElement && list.parentElement.parentElement && list.parentElement.parentElement.classList.add('is-drag-over');
      });
      list.addEventListener('dragleave', function () {
        list.parentElement && list.parentElement.parentElement && list.parentElement.parentElement.classList.remove('is-drag-over');
      });
      list.addEventListener('drop', function (e) {
        e.preventDefault();
        list.parentElement && list.parentElement.parentElement && list.parentElement.parentElement.classList.remove('is-drag-over');
        var raw = e.dataTransfer.getData('text/plain') || '';
        if (raw.indexOf('new:') === 0) {
          postForm(root.getAttribute('data-add-block-url'), {
            _csrf: csrf,
            section_id: list.getAttribute('data-section-id'),
            block_type: raw.slice(4)
          }).then(function () { location.reload(); });
          return;
        }
        if (dragBlock) {
          list.appendChild(dragBlock);
          dragBlock.classList.remove('is-dragging');
          dragBlock = null;
          saveOrder();
        }
      });
    });

    qsa('.wb-block-card').forEach(function (card) {
      card.addEventListener('dragstart', function () {
        dragBlock = card;
        card.classList.add('is-dragging');
      });
      card.addEventListener('dragend', function () {
        card.classList.remove('is-dragging');
        dragBlock = null;
      });
    });

    var canvas = qs('#wbCanvas');
    qsa('.wb-section-card').forEach(function (sec) {
      sec.addEventListener('dragstart', function (e) {
        if (e.target.closest && e.target.closest('.wb-block-card')) return;
        dragSection = sec;
        sec.classList.add('is-dragging');
      });
      sec.addEventListener('dragend', function () {
        sec.classList.remove('is-dragging');
        dragSection = null;
        saveOrder();
      });
      sec.addEventListener('dragover', function (e) {
        if (!dragSection || dragSection === sec) return;
        e.preventDefault();
        var rect = sec.getBoundingClientRect();
        if (e.clientY < rect.top + rect.height / 2) {
          canvas.insertBefore(dragSection, sec);
        } else {
          canvas.insertBefore(dragSection, sec.nextSibling);
        }
      });
    });

    qsa('.wb-save-block').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-block-id');
        var card = btn.closest('.wb-block-card');
        var title = qs('.wb-block-title', card);
        postForm(root.getAttribute('data-update-block-url'), {
          _csrf: csrf,
          id: id,
          title_en: title ? title.value : ''
        });
      });
    });

    qsa('.wb-delete-block').forEach(function (btn) {
      btn.addEventListener('click', function () {
        postForm(root.getAttribute('data-delete-block-url'), { _csrf: csrf, id: btn.getAttribute('data-block-id') })
          .then(function () { location.reload(); });
      });
    });

    qsa('.wb-delete-section').forEach(function (btn) {
      btn.addEventListener('click', function () {
        postForm(root.getAttribute('data-delete-section-url'), { _csrf: csrf, id: btn.getAttribute('data-section-id') })
          .then(function () { location.reload(); });
      });
    });

    function action(url, extra) {
      var data = Object.assign({ _csrf: csrf, page_id: pageId }, extra || {});
      return postForm(url, data);
    }

    qs('#wbBtnDraft') && qs('#wbBtnDraft').addEventListener('click', function () {
      action(root.getAttribute('data-draft-url'), { label: 'Manual draft' }).then(function () { location.reload(); });
    });
    qs('#wbBtnPublish') && qs('#wbBtnPublish').addEventListener('click', function () {
      action(root.getAttribute('data-publish-url')).then(function () { alert('Published'); location.reload(); });
    });
    qs('#wbBtnPreview') && qs('#wbBtnPreview').addEventListener('click', function () {
      action(root.getAttribute('data-preview-url')).then(function (res) {
        if (res && res.url) window.open(res.url, '_blank', 'noopener');
      });
    });
    qs('#wbBtnSchedule') && qs('#wbBtnSchedule').addEventListener('click', function () {
      var at = qs('#wbScheduleAt');
      action(root.getAttribute('data-schedule-url'), { scheduled_at: at ? at.value : '' }).then(function () { location.reload(); });
    });
    qsa('.wb-rollback').forEach(function (btn) {
      btn.addEventListener('click', function () {
        action(root.getAttribute('data-rollback-url'), { version_id: btn.getAttribute('data-version-id') })
          .then(function () { location.reload(); });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
