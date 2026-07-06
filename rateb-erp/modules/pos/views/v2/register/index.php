<?php
declare(strict_types=1);

use Rateb\App\Pos\Support\PosView;

/** @var array<string, mixed> $v2Config */
$i18n = is_array($v2Config['i18n'] ?? null) ? $v2Config['i18n'] : [];
$t = static fn (string $key, string $fallback = ''): string => (string) ($i18n[$key] ?? $fallback);
?>
<div class="pos-v2-register d-flex flex-column min-vh-100" data-pos-register>
    <header class="pos-v2-header border-bottom px-3 py-2 d-flex align-items-center gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
            <span class="pos-v2-badge badge text-bg-primary">V2</span>
            <h1 class="h5 mb-0 text-truncate"><?php echo PosView::escape($t('register_title', __('pos_register'))); ?></h1>
            <span class="text-muted small d-none d-md-inline" data-pos-register-name></span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo PosView::escape($t('theme', 'Theme')); ?>">
                <button type="button" class="btn btn-outline-secondary" data-theme-choice="light" aria-pressed="false"><?php echo PosView::escape($t('theme_light', 'Light')); ?></button>
                <button type="button" class="btn btn-outline-secondary" data-theme-choice="dark" aria-pressed="false"><?php echo PosView::escape($t('theme_dark', 'Dark')); ?></button>
            </div>
            <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo PosView::escape($t('language', 'Language')); ?>">
                <button type="button" class="btn btn-outline-secondary" data-pos-locale="en" aria-pressed="false">EN</button>
                <button type="button" class="btn btn-outline-secondary" data-pos-locale="ar" aria-pressed="false">AR</button>
            </div>
            <a href="<?php echo PosView::escape(rateb_app_url('pos/register')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo PosView::escape($t('v1_link', 'V1 Register')); ?></a>
        </div>
    </header>

    <div class="pos-v2-alert-zone px-3 pt-2" data-pos-alerts aria-live="polite"></div>

    <div class="pos-v2-main flex-grow-1 d-flex flex-column flex-lg-row overflow-hidden">
        <aside class="pos-v2-sidebar border-end d-flex flex-column" data-pos-sidebar>
            <div class="p-3 border-bottom">
                <label class="form-label small text-muted mb-1" for="pos-v2-search"><?php echo PosView::escape($t('search', 'Search products')); ?></label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true">⌕</i></span>
                    <input type="search" id="pos-v2-search" class="form-control" data-pos-search autocomplete="off"
                           placeholder="<?php echo PosView::escape($t('search_placeholder', 'Name or SKU…')); ?>">
                    <button type="button" class="btn btn-outline-secondary" data-pos-search-clear hidden><?php echo PosView::escape($t('clear', 'Clear')); ?></button>
                </div>
            </div>
            <nav class="pos-v2-categories flex-grow-1 overflow-auto p-2" data-pos-categories aria-label="<?php echo PosView::escape($t('categories', 'Categories')); ?>">
                <div class="text-center text-muted py-4 small" data-pos-categories-loading><?php echo PosView::escape($t('loading', 'Loading…')); ?></div>
            </nav>
        </aside>

        <section class="pos-v2-catalog flex-grow-1 d-flex flex-column min-w-0" data-pos-catalog>
            <div class="pos-v2-catalog-toolbar px-3 py-2 border-bottom d-flex align-items-center justify-content-between gap-2">
                <span class="small text-muted" data-pos-catalog-meta></span>
                <span class="spinner-border spinner-border-sm text-secondary d-none" data-pos-catalog-spinner role="status" aria-hidden="true"></span>
            </div>
            <div class="pos-v2-catalog-scroll flex-grow-1 overflow-auto p-3" data-pos-catalog-scroll>
                <div class="pos-v2-product-grid row g-3" data-pos-product-grid></div>
                <div class="text-center py-5 d-none" data-pos-catalog-empty>
                    <p class="text-muted mb-0"><?php echo PosView::escape($t('catalog_empty', 'No products found.')); ?></p>
                </div>
                <div class="text-center py-4 d-none" data-pos-catalog-error>
                    <p class="text-danger mb-2" data-pos-catalog-error-msg></p>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-pos-catalog-retry><?php echo PosView::escape($t('retry', 'Retry')); ?></button>
                </div>
                <div class="text-center py-3 d-none" data-pos-catalog-load-more>
                    <span class="spinner-border spinner-border-sm text-secondary" role="status" aria-hidden="true"></span>
                    <span class="visually-hidden"><?php echo PosView::escape($t('loading_more', 'Loading more…')); ?></span>
                </div>
            </div>
        </section>

        <aside class="pos-v2-cart-panel border-start d-flex flex-column" data-pos-cart-panel>
            <ul class="nav nav-tabs nav-fill px-2 pt-2" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pos-tab-cart" data-bs-toggle="tab" data-bs-target="#pos-pane-cart" type="button" role="tab" aria-controls="pos-pane-cart" aria-selected="true">
                        <?php echo PosView::escape($t('cart', 'Cart')); ?>
                        <span class="badge rounded-pill text-bg-secondary ms-1" data-pos-cart-count>0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pos-tab-customer" data-bs-toggle="tab" data-bs-target="#pos-pane-customer" type="button" role="tab" aria-controls="pos-pane-customer" aria-selected="false">
                        <?php echo PosView::escape($t('customer', 'Customer')); ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pos-tab-discount" data-bs-toggle="tab" data-bs-target="#pos-pane-discount" type="button" role="tab" aria-controls="pos-pane-discount" aria-selected="false">
                        <?php echo PosView::escape($t('discount', 'Discount')); ?>
                    </button>
                </li>
            </ul>
            <div class="tab-content flex-grow-1 overflow-hidden d-flex flex-column">
                <div class="tab-pane fade show active h-100 d-flex flex-column" id="pos-pane-cart" role="tabpanel" aria-labelledby="pos-tab-cart" tabindex="0" data-pos-cart-tab>
                    <div class="flex-grow-1 overflow-auto p-2" data-pos-cart-lines>
                        <div class="text-center text-muted py-5 small" data-pos-cart-empty><?php echo PosView::escape($t('cart_empty', 'Cart is empty.')); ?></div>
                        <div class="text-center py-5 d-none" data-pos-cart-loading>
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        </div>
                    </div>
                    <footer class="pos-v2-cart-footer border-top p-3" data-pos-cart-footer>
                        <dl class="row small mb-2 g-1">
                            <dt class="col-6 text-muted"><?php echo PosView::escape($t('subtotal', 'Subtotal')); ?></dt>
                            <dd class="col-6 text-end mb-0" data-pos-total-subtotal>—</dd>
                            <dt class="col-6 text-muted"><?php echo PosView::escape($t('discount_total', 'Discount')); ?></dt>
                            <dd class="col-6 text-end mb-0" data-pos-total-discount>—</dd>
                            <dt class="col-6 fw-semibold"><?php echo PosView::escape($t('total', 'Total')); ?></dt>
                            <dd class="col-6 text-end fw-semibold mb-0" data-pos-total-grand>—</dd>
                        </dl>
                    </footer>
                </div>
                <div class="tab-pane fade h-100 overflow-auto p-3" id="pos-pane-customer" role="tabpanel" aria-labelledby="pos-tab-customer" tabindex="0" data-pos-customer-panel>
                    <div data-pos-customer>
                        <p class="text-muted small"><?php echo PosView::escape($t('customer_hint', 'Attach a customer by ID.')); ?></p>
                        <form class="row g-2 align-items-end mb-3" data-pos-customer-form>
                            <div class="col-8">
                                <label class="form-label small" for="pos-v2-customer-id"><?php echo PosView::escape($t('customer_id', 'Customer ID')); ?></label>
                                <input type="number" min="1" step="1" class="form-control form-control-sm" id="pos-v2-customer-id" name="customer_id" data-pos-customer-id required>
                            </div>
                            <div class="col-4">
                                <button type="submit" class="btn btn-sm btn-primary w-100" data-pos-customer-attach><?php echo PosView::escape($t('attach', 'Attach')); ?></button>
                            </div>
                        </form>
                        <div class="card d-none" data-pos-customer-card>
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-truncate" data-pos-customer-name></div>
                                        <div class="small text-muted" data-pos-customer-meta></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger flex-shrink-0" data-pos-customer-remove><?php echo PosView::escape($t('remove', 'Remove')); ?></button>
                                </div>
                            </div>
                        </div>
                        <div class="text-center py-3 d-none" data-pos-customer-loading>
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade h-100 overflow-auto p-3" id="pos-pane-discount" role="tabpanel" aria-labelledby="pos-tab-discount" tabindex="0" data-pos-discount-panel>
                    <div data-pos-discount>
                        <p class="text-muted small mb-3"><?php echo PosView::escape($t('discount_hint', 'Apply percent or fixed discounts.')); ?></p>
                        <form class="mb-4" data-pos-discount-cart-form>
                            <h2 class="h6"><?php echo PosView::escape($t('cart_discount', 'Cart discount')); ?></h2>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small" for="pos-v2-cart-discount-type"><?php echo PosView::escape($t('type', 'Type')); ?></label>
                                    <select class="form-select form-select-sm" id="pos-v2-cart-discount-type" name="type" data-pos-discount-type>
                                        <option value="percent"><?php echo PosView::escape($t('percent', 'Percent')); ?></option>
                                        <option value="fixed"><?php echo PosView::escape($t('fixed', 'Fixed')); ?></option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small" for="pos-v2-cart-discount-value"><?php echo PosView::escape($t('value', 'Value')); ?></label>
                                    <input type="number" min="0" step="any" class="form-control form-control-sm" id="pos-v2-cart-discount-value" name="value" data-pos-discount-value required>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small" for="pos-v2-cart-discount-reason"><?php echo PosView::escape($t('reason', 'Reason (optional)')); ?></label>
                                <input type="text" class="form-control form-control-sm" id="pos-v2-cart-discount-reason" name="reason" data-pos-discount-reason maxlength="200">
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary" data-pos-discount-cart-apply><?php echo PosView::escape($t('apply_cart_discount', 'Apply to cart')); ?></button>
                        </form>
                        <form data-pos-discount-line-form>
                            <h2 class="h6"><?php echo PosView::escape($t('line_discount', 'Line discount')); ?></h2>
                            <div class="mb-2">
                                <label class="form-label small" for="pos-v2-line-discount-line"><?php echo PosView::escape($t('line', 'Cart line')); ?></label>
                                <select class="form-select form-select-sm" id="pos-v2-line-discount-line" name="line_id" data-pos-discount-line-select required>
                                    <option value=""><?php echo PosView::escape($t('select_line', 'Select a line…')); ?></option>
                                </select>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small" for="pos-v2-line-discount-type"><?php echo PosView::escape($t('type', 'Type')); ?></label>
                                    <select class="form-select form-select-sm" id="pos-v2-line-discount-type" name="type">
                                        <option value="percent"><?php echo PosView::escape($t('percent', 'Percent')); ?></option>
                                        <option value="fixed"><?php echo PosView::escape($t('fixed', 'Fixed')); ?></option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small" for="pos-v2-line-discount-value"><?php echo PosView::escape($t('value', 'Value')); ?></label>
                                    <input type="number" min="0" step="any" class="form-control form-control-sm" id="pos-v2-line-discount-value" name="value" required>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small" for="pos-v2-line-discount-reason"><?php echo PosView::escape($t('reason', 'Reason (optional)')); ?></label>
                                <input type="text" class="form-control form-control-sm" id="pos-v2-line-discount-reason" name="reason" maxlength="200">
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary" data-pos-discount-line-apply><?php echo PosView::escape($t('apply_line_discount', 'Apply to line')); ?></button>
                        </form>
                        <div class="text-center py-3 d-none" data-pos-discount-loading>
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>
