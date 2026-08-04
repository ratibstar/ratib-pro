(function (document, api, ui) {
  'use strict';

  var productUuid = '';

  document.addEventListener('DOMContentLoaded', function () {
    ui.bindForm(document.getElementById('seoLoadForm'), async function (data) {
      productUuid = data.product_uuid;
      try {
        var res = await api.get('/catalog/products/' + encodeURIComponent(productUuid) + '/seo');
        var item = res.data || {};
        document.getElementById('seoSaveForm').hidden = false;
        document.getElementById('seoMetaTitle').value = item.meta_title || '';
        document.getElementById('seoSlug').value = item.slug || '';
        document.getElementById('seoMetaDescription').value = item.meta_description || '';
        document.getElementById('seoCanonical').value = item.canonical_url || '';
        document.getElementById('seoDetail').innerHTML = ui.entityDetail(item, [
          { key: 'meta_title', label: ui.t('field_meta_title', 'Meta title') },
          { key: 'slug', label: ui.t('field_slug', 'Slug') },
          { key: 'meta_description', label: ui.t('field_meta_description', 'Meta description') },
          { key: 'canonical_url', label: ui.t('field_canonical', 'Canonical URL') }
        ]);
      } catch (error) {
        ui.handleError(error);
      }
    });

    ui.bindForm(document.getElementById('seoSaveForm'), async function (data) {
      try {
        await api.put('/catalog/products/' + encodeURIComponent(productUuid) + '/seo', {
          meta_title: data.meta_title,
          slug: data.slug,
          meta_description: data.meta_description,
          canonical_url: data.canonical_url
        });
        ui.flash(ui.t('success', 'Success'), 'success');
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
