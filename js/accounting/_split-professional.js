/**
 * EN: Implements frontend interaction behavior in `js/accounting/_split-professional.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/_split-professional.js`.
 */
/**
 * One-time extraction - parses professional.js and outputs feature files.
 * Run: node _split-professional.js
 * Delete this file after use.
 */
const fs = require('fs');
const path = require('path');

const srcPath = path.join(__dirname, 'professional.js');
const lines = fs.readFileSync(srcPath, 'utf8').split('\n');

const methodStarts = [];
const methodRegex = /^\s{4}([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*\{/;
for (let i = 0; i < lines.length; i++) {
    const m = lines[i].match(methodRegex);
    if (m) methodStarts.push({ name: m[1], index: i });
}

function getMethodEnd(startIndex) {
    for (let i = 0; i < methodStarts.length; i++) {
        if (methodStarts[i].index === startIndex) {
            if (i + 1 < methodStarts.length) return methodStarts[i + 1].index - 1;
            for (let j = startIndex + 1; j < lines.length; j++) {
                if (lines[j].trim() === '}' && lines[j].search(/\S/) === 4) return j;
            }
            return lines.length - 1;
        }
    }
    return startIndex;
}

function extractMethod(name) {
    const entry = methodStarts.find(m => m.name === name);
    if (!entry) return null;
    const end = getMethodEnd(entry.index);
    return lines.slice(entry.index, end + 1).join('\n');
}

function toObjLiteral(methodSrc) {
    return methodSrc.split('\n').map(l => '        ' + l).join('\n');
}

function writeMixin(filename, header, methodNames) {
    const parts = [];
    const missing = [];
    for (const n of methodNames) {
        const src = extractMethod(n);
        if (src) parts.push(toObjLiteral(src));
        else missing.push(n);
    }
    if (missing.length) console.warn(filename + ' missing:', missing.join(', '));
    const body = parts.join(',\n\n');
    const out = `/**
 * Professional Accounting - ${header}
 * Prototype mixin - attaches methods to ProfessionalAccounting
 */
(function() {
    'use strict';
    const methods = {
${body}
    };
    if (typeof window.__professionalAccountingAttach === 'function') {
        window.__professionalAccountingAttach(methods);
    }
})();
`;
    fs.writeFileSync(path.join(__dirname, filename), out);
    console.log('Wrote', filename);
}

const UTILITIES = ['formatDate', 'formatDateForInput', 'formatDateForAPI', 'formatCurrency', 'escapeHtml', 'showToast', 'showConfirmDialog', 'showPrompt', 'hasFormChanges', 'markFormAsChanged', 'markFormAsSaved', 'getDefaultCurrencySync', 'getDefaultCurrency', 'initDefaultCurrency', 'createPaginationHTML', 'getCachedReport', 'cacheReport', 'clearReportCache', 'isElementMeasurable'];

const DASHBOARD = ['updateOverviewCards', 'ensureQuickActionsVisible', 'updateRecentTransactionsPagination', 'setupRecentTransactionsPaginationControls', 'renderRecentTransactionsPageButtons', 'getChartStyles', 'renderRevenueExpenseNetChart', 'renderCashBalanceChart', 'renderReceivablePayableChart', 'renderExpenseBreakdownChart', 'renderInvoiceAgingChart', 'renderFinancialOverviewChart', 'filterAndSortBankAccounts', 'updateBankingPaginationControls', 'updateBankingStatusCards', 'setupBankAccountActions', 'clearLedgerFilters', 'loadDashboard', 'loadFinancialOverview', 'refreshDashboardCards', 'loadCashFlowSummary', 'loadFinancialSummary', 'initializeDates', 'initializeEnglishDatePickers', 'checkTablesExist', 'ensureTabButtonsClickable', 'attachReportCardListeners'];

const REPORTS = ['showReportLoading', 'saveReportAsFavorite', 'getReportFavorites', 'removeReportFavorite', 'loadReportFavorite', 'displayReportInPopupSmooth', 'displayReportInPopup', 'displayReportPlaceholderInPopup', 'setupReportHandlers', 'setupKeyboardShortcuts', 'setupReportComparison', 'refreshCurrentReport', 'saveCurrentReportAsFavorite', 'openReportComparison', 'displayReportComparison', 'getReportHTMLForComparison', 'setupDateValidation', 'isValidDate', 'showDateError', 'hideDateError', 'getQuickDatePresets', 'applyQuickDatePreset', 'setupColumnSorting', 'sortTableColumn', 'setupReportPagination', 'refreshReportDisplay', 'updateReportContent', 'getReportName', 'getReportDateFiltersHTML', 'getReportAccountFilterHTML', 'applyReportFilters', 'clearReportFilters', 'updateReportPagination', 'getReportTotalCount', 'displayReportPlaceholderInPopup', 'displayReportPlaceholder', 'displayReport', 'formatTrialBalance', 'formatIncomeStatement', 'formatBalanceSheet', 'formatCashFlow', 'formatAgedReceivables', 'formatAgedPayables', 'formatCashBook', 'formatBankBook', 'getReportStatusCards', 'getGeneralLedgerStatusCards', 'getTrialBalanceStatusCards', 'getIncomeStatementStatusCards', 'getBalanceSheetStatusCards', 'getCashFlowStatusCards', 'getAgedReceivablesStatusCards', 'getAgedPayablesStatusCards', 'getCashBookStatusCards', 'getBankBookStatusCards', 'getAccountStatementStatusCards', 'getExpenseStatementStatusCards', 'getChartOfAccountsStatusCards', 'getValueAddedStatusCards', 'getFixedAssetsStatusCards', 'getEntriesByYearStatusCards', 'getCustomerDebitsStatusCards', 'getStatisticalPositionStatusCards', 'getChangesInEquityStatusCards', 'getFinancialPerformanceStatusCards', 'getComparativeReportStatusCards', 'createStatCard', 'formatGeneralLedgerReport', 'formatExpenseStatement', 'formatAccountStatement', 'formatValueAdded', 'formatFixedAssets', 'formatEntriesByYear', 'formatCustomerDebits', 'formatStatisticalPosition', 'formatChangesInEquity', 'formatFinancialPerformance', 'formatComparativeReport', 'formatGenericReport', 'formatChartOfAccounts', 'generateReport', 'restoreReportsGrid'];

const MODALS = ['openAccountModal', 'updateModalArPagination', 'updateModalApPagination', 'updateModalBankPagination', 'updateModalEntityPagination', 'updateModalLedgerPagination', 'updateBulkActions', 'getSelectedIds', 'bulkExportAccounts', 'exportAccountsToCSV', 'getJournalEntryModalContent', 'getInvoiceModalContent', 'getBillModalContent', 'getBankAccountModalContent', 'openChartOfAccountsModal', 'setupChartOfAccountsFilters', 'updateCoaSortIndicators', 'updateCoaSelectAll', 'updateCoaPagination', 'updateCoaBulkActions', 'scrollToCoaTable', 'getChartOfAccountsModalContent', 'openQuickEntryModal', 'getQuickEntryModalContent', 'getReceivePaymentModalContent', 'getMakePaymentModalContent', 'getFinancialPeriodsModalContent', 'getTaxSettingsModalContent', 'openGeneralLedgerModal', 'openReceivablesModal', 'openPayablesModal', 'openBankingModal', 'openEntitiesModal', 'openReportsModal', 'openSettingsModal', 'openTransactionsModal', 'closeTransactionsModal', 'ensureModalsExist', 'createFollowupModal', 'createMessagesModal', 'openFollowupModal', 'closeFollowupModal', 'openMessagesModal', 'closeMessagesModal', 'showEditFollowupForm', 'closeEditFollowupForm', 'updateModalTransactionsPagination', 'loadModalInvoices', 'loadModalBills', 'loadModalBankAccounts', 'loadModalLedgerAccounts', 'loadModalJournalEntries', 'loadModalTransactions', 'loadEntitiesForSelect', 'showModal', 'closeModal', 'openQuickEntry', 'openJournalEntryModal', 'loadInvoices', 'loadBills', 'loadVouchers', 'loadReceiptVouchers', 'loadBankTransactions', 'loadBankingCashModal', 'openVouchersModal', 'openReceiptVouchersModal', 'attachEntityTransactionsHandlers', 'updateEntityStatusCards', 'renderEntityTransactionsTable', 'updateEntityTransactionsPagination', 'updateBulkActionsBar', 'setupReportsFilters', 'filterReports', 'setupSettingsFilters', 'filterSettings', 'resetSettings', 'exportSettings', 'setupSettingsHandlers', 'updateSettingsSummary', 'loadEntities', 'loadTopEntities', 'setupCustomerFields', 'setupEntityCascadingDropdowns', 'normalizeEntityTypeForModal', 'openCostCentersModal', 'openBankGuaranteeModal', 'openEntryApprovalModal', 'createJournalEntryLineRow', 'setupCostCentersEventHandlers', 'setupBankGuaranteesEventHandlers', 'setupEntryApprovalEventHandlers', 'openEntryApprovalForm', 'toggleDeleteButton', 'openCostCenterForm', 'openBankGuaranteeForm', 'loadAccountsForModalSelect', 'openReceiptVoucherModal', 'openVouchersModal', 'setupVouchersHandlers', '_receiptBankSelectValue', '_receiptCollectedSelectValue', '_setReceiptSelectValueAndTrigger', 'getReceiptVoucherModalContent', 'applyReceiptDataToEditForm', 'updateReceiptVouchersPagination', 'exportReceiptVouchers', 'setupBankingCashHandlers', 'setupBankingBulkActions', 'updateBankingBulkActionsBar', 'updateSelectAllCheckbox', 'clearBankingFilters', 'exportSelectedBankAccounts', 'printSelectedBankAccounts', 'renderBankTransactionsTable', 'setupBankTransactionHandlers', 'renderBankReconciliationsTable', 'setupBankReconciliationHandlers', 'renderPeriodsTable', 'setupPeriodsHandlers', 'renderTaxSettingsTable', 'setupTaxSettingsHandlers', 'openPeriodForm', 'openTaxSettingForm', 'showNewFollowupForm', 'closeNewFollowupForm', 'showNewMessageForm', 'closeNewMessageForm', 'setupFollowupMessages', 'updateBulkActionsFollowups', 'updateBulkActionsMessages', 'exportTransactions', 'exportCurrentReport', 'exportReportToCSV', 'exportReportToExcel', 'showExportMenu', 'setupCostCentersHandlers', 'setupBankGuaranteeHandlers', 'setupEntryApprovalHandlers'];

const MODALS_UNIQ = [...new Set(MODALS)];

const MANAGEMENT = ['loadCostCenters', 'loadBankGuarantees', 'loadEntryApprovalData', 'renderCostCentersTable', 'renderBankGuaranteeTable', 'renderEntryApprovalTable', 'updateCostCentersPagination', 'updateBankGuaranteePagination', 'updateEntryApprovalPagination', 'loadFollowups', 'loadMessages', 'createFollowup', 'createMessage', 'saveFollowup', 'saveMessage', 'deleteFollowup', 'deleteMessage', 'editFollowup', 'refreshAllModules', 'checkAndGenerateAlerts'];

writeMixin('professional.utilities.js', 'Utilities (formatDate, formatCurrency, escapeHtml, toast/dialog, caching)', UTILITIES);
writeMixin('professional.dashboard.js', 'Dashboard & Charts', DASHBOARD);
writeMixin('professional.reports.js', 'Reports (formatting, filters, comparison)', REPORTS);
writeMixin('professional.modals.js', 'Modals (ledger, invoices, bills, bank, quick entry, etc.)', MODALS_UNIQ);
writeMixin('professional.management.js', 'Management (cost centers, bank guarantees, entry approval, follow-ups)', MANAGEMENT);

console.log('Done. Now create professional.core.js manually (class + setupEventListeners + switchTab + cleanupStrayOverlays + saveReportsOriginalContent).');
