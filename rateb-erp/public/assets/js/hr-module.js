(function () {
    'use strict';

    document.querySelectorAll('[data-hr-tree-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var group = btn.closest('[data-hr-tree-group]');
            if (!group) {
                return;
            }
            var open = group.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
})();
