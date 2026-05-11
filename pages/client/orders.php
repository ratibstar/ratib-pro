<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'orders';
$RCP_HEADING = 'Orders center';
$RCP_SUBHEADING = 'Enterprise table with filters, paging, and bulk bridge hooks.';
$RCP_EXTRA_JS = [ratib_client_dashboard_asset_url('js/client-dashboard-orders.js')];
require __DIR__ . '/_common-start.inc.php';
?>
            <div id="rcp-orders-page" class="ratib-cp-board" data-page="1">
                <div class="ratib-cp-table-wrap" role="region" aria-label="Orders table">
                    <div class="ratib-cp-toolbar" role="search">
                        <label>
                            Search
                            <input id="rcp-filter-q" type="search" placeholder="ID or product" autocomplete="off">
                        </label>
                        <label>
                            Status
                            <select id="rcp-filter-status" aria-label="Filter by order status">
                                <option value="">Any</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="failed">Failed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </label>
                        <label>
                            Payment
                            <select id="rcp-filter-pay" aria-label="Filter by payment status">
                                <option value="">Any</option>
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="failed">Failed</option>
                            </select>
                        </label>
                        <button type="button" id="rcp-run-filter" class="secondary">Apply</button>
                        <button type="button" id="rcp-reset-filter" class="secondary">Reset</button>
                        <button type="button" id="rcp-bulk-apply">Bulk suspend (selected)</button>
                    </div>
                    <div class="ratib-cp-table-scroll">
                        <table class="ratib-cp-table" role="table" aria-describedby="rcp-orders-caption">
                            <caption id="rcp-orders-caption" class="visually-hidden">Client orders</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Select</th>
                                    <th scope="col">Order ID</th>
                                    <th scope="col">Product</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Payment</th>
                                    <th scope="col">Created</th>
                                    <th scope="col">Renewal</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rcp-orders-tbody"></tbody>
                        </table>
                    </div>
                    <div class="ratib-cp-pagination" role="navigation" aria-label="Pagination">
                        <span id="rcp-pager-meta" class="rcp-muted-span me-auto"></span>
                        <button type="button" id="rcp-prev">Previous</button>
                        <button type="button" id="rcp-next">Next</button>
                    </div>
                </div>
            </div>
<?php
require __DIR__ . '/_common-end.inc.php';
