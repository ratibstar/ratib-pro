/**
 * RATEB Offline SDK bootstrap (Phase 14.2 + 15B + 16B + 17B CRM).
 * Flag merge is additive — later bootstraps update flags without a second full boot.
 */
(function (root) {
    'use strict';

    var booted = false;
    var flags = {
        'offline.enabled': false,
        'offline.pos.complete': true,
        'offline.inventory.movements': false,
        'offline.hr.attendance': false,
        'offline.procurement': false,
        'offline.procurement.goods_receipt': false,
        'offline.recruitment': false,
        'offline.recruitment.candidates': false,
        'offline.recruitment.workflow': false,
        'offline.recruitment.assignment': false,
        'offline.accounting': false,
        'offline.accounting.journals': false,
        'offline.accounting.workflow': false,
        'offline.accounting.masterdata': false,
        'offline.crm': false,
        'offline.crm.leads': false,
        'offline.crm.workflow': false,
        'offline.crm.activities': false,
        'offline.crm.masterdata': false,
        'offline.projects': false,
        'offline.projects.tasks': false,
        'offline.projects.workflow': false,
        'offline.projects.timesheets': false,
        'offline.projects.masterdata': false,
        'offline.assets': false,
        'offline.assets.maintenance': false,
        'offline.assets.workflow': false,
        'offline.assets.inspections': false,
        'offline.assets.masterdata': false,
        'offline.approval': false,
        'offline.approval.requests': false,
        'offline.approval.workflow': false,
        'offline.approval.masterdata': false,
        'offline.procurement_enterprise': false,
        'offline.procurement_enterprise.suppliers': false,
        'offline.procurement_enterprise.tenders': false,
        'offline.procurement_enterprise.contracts': false,
        'offline.procurement_enterprise.workflow': false,
        'offline.procurement_enterprise.masterdata': false,
        'offline.manufacturing': false,
        'offline.manufacturing.production': false,
        'offline.manufacturing.workflow': false,
        'offline.manufacturing.quality': false,
        'offline.manufacturing.masterdata': false,
        'offline.read_cache': false,
        'offline.auth.unlock': false,
        'offline.rbac.cache': false,
        'offline.master_data': false,
        'offline.pilot.ops_pages': false
    };

    function mergeFlags(incoming) {
        if (!incoming || typeof incoming !== 'object') {
            return flags;
        }
        Object.keys(incoming).forEach(function (k) {
            flags[k] = !!incoming[k];
        });
        return flags;
    }

    function statusPayload() {
        return {
            enabled: !!flags['offline.enabled'],
            inventory: !!flags['offline.inventory.movements'],
            hr: !!flags['offline.hr.attendance'],
            procurement: !!flags['offline.procurement'],
            procurement_goods_receipt: !!flags['offline.procurement.goods_receipt'],
            recruitment: !!flags['offline.recruitment'],
            recruitment_candidates: !!flags['offline.recruitment.candidates'],
            recruitment_workflow: !!flags['offline.recruitment.workflow'],
            recruitment_assignment: !!flags['offline.recruitment.assignment'],
            accounting: !!flags['offline.accounting'],
            accounting_journals: !!flags['offline.accounting.journals'],
            accounting_workflow: !!flags['offline.accounting.workflow'],
            accounting_masterdata: !!flags['offline.accounting.masterdata'],
            crm: !!flags['offline.crm'],
            crm_leads: !!flags['offline.crm.leads'],
            crm_workflow: !!flags['offline.crm.workflow'],
            crm_activities: !!flags['offline.crm.activities'],
            crm_masterdata: !!flags['offline.crm.masterdata'],
            projects: !!flags['offline.projects'],
            projects_tasks: !!flags['offline.projects.tasks'],
            projects_workflow: !!flags['offline.projects.workflow'],
            projects_timesheets: !!flags['offline.projects.timesheets'],
            projects_masterdata: !!flags['offline.projects.masterdata'],
            assets: !!flags['offline.assets'],
            assets_maintenance: !!flags['offline.assets.maintenance'],
            assets_workflow: !!flags['offline.assets.workflow'],
            assets_inspections: !!flags['offline.assets.inspections'],
            assets_masterdata: !!flags['offline.assets.masterdata'],
            approval: !!flags['offline.approval'],
            approval_requests: !!flags['offline.approval.requests'],
            approval_workflow: !!flags['offline.approval.workflow'],
            approval_masterdata: !!flags['offline.approval.masterdata'],
            procurement_enterprise: !!flags['offline.procurement_enterprise'],
            procurement_enterprise_suppliers: !!flags['offline.procurement_enterprise.suppliers'],
            procurement_enterprise_tenders: !!flags['offline.procurement_enterprise.tenders'],
            procurement_enterprise_contracts: !!flags['offline.procurement_enterprise.contracts'],
            procurement_enterprise_workflow: !!flags['offline.procurement_enterprise.workflow'],
            procurement_enterprise_masterdata: !!flags['offline.procurement_enterprise.masterdata'],
            manufacturing: !!flags['offline.manufacturing'],
            manufacturing_production: !!flags['offline.manufacturing.production'],
            manufacturing_workflow: !!flags['offline.manufacturing.workflow'],
            manufacturing_quality: !!flags['offline.manufacturing.quality'],
            manufacturing_masterdata: !!flags['offline.manufacturing.masterdata'],
            read_cache: !!flags['offline.read_cache'],
            auth_unlock: !!flags['offline.auth.unlock'],
            rbac_cache: !!flags['offline.rbac.cache'],
            master_data: !!flags['offline.master_data'],
            pilot_ops_pages: !!flags['offline.pilot.ops_pages'],
            version: '14.2.0'
        };
    }

    function init(options) {
        options = options || {};
        if (options.flags && typeof options.flags === 'object') {
            mergeFlags(options.flags);
        }
        // Already booted: merge flags only (Phase 13.1 — no freeze).
        if (booted) {
            if (root.RatebOfflineEvents) {
                root.RatebOfflineEvents.emit('sdk:flags', statusPayload());
            }
            return statusPayload();
        }
        var enabled = !!flags['offline.enabled'];
        if (root.RatebOfflineQueue) {
            root.RatebOfflineQueue.configure({
                enabled: enabled,
                apiBase: options.apiBase || '',
                clientQueueMax: typeof options.clientQueueMax === 'number'
                    ? options.clientQueueMax
                    : 500
            });
        }
        if (root.RatebOfflineTransport) {
            root.RatebOfflineTransport.configure({ enabled: enabled });
        }
        if (root.RatebOfflineConnectivity) {
            root.RatebOfflineConnectivity.configure({
                probeUrl: options.probeUrl || (options.apiBase ? String(options.apiBase).replace(/\/$/, '') + '/status' : null)
            });
            if (enabled && options.startConnectivity !== false) {
                root.RatebOfflineConnectivity.start();
            }
        }
        if (enabled && root.RatebOfflineReplayScheduler && options.startScheduler !== false) {
            root.RatebOfflineReplayScheduler.start(options.schedulerIntervalMs || 15000);
        }
        booted = true;
        if (root.RatebOfflineEvents) {
            root.RatebOfflineEvents.emit('sdk:ready', statusPayload());
        }
        return statusPayload();
    }

    root.RatebOffline = {
        version: '14.2.0',
        init: init,
        mergeFlags: mergeFlags,
        isBooted: function () { return booted; },
        isEnabled: function () { return !!flags['offline.enabled']; },
        isInventoryEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.inventory.movements']);
        },
        isHrEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.hr.attendance']);
        },
        isProcurementEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.procurement']);
        },
        isProcurementGoodsReceiptEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.procurement']
                && flags['offline.procurement.goods_receipt']);
        },
        isRecruitmentEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.recruitment']);
        },
        isRecruitmentCandidatesEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.recruitment']
                && flags['offline.recruitment.candidates']);
        },
        isRecruitmentWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.recruitment']
                && flags['offline.recruitment.workflow']);
        },
        isRecruitmentAssignmentEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.recruitment']
                && flags['offline.recruitment.assignment']);
        },
        isAccountingEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.accounting']);
        },
        isAccountingJournalsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.accounting']
                && flags['offline.accounting.journals']);
        },
        isAccountingWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.accounting']
                && flags['offline.accounting.workflow']);
        },
        isAccountingMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.accounting']
                && flags['offline.accounting.masterdata']);
        },
        isCrmEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.crm']);
        },
        isCrmLeadsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.crm']
                && flags['offline.crm.leads']);
        },
        isCrmWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.crm']
                && flags['offline.crm.workflow']);
        },
        isCrmActivitiesEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.crm']
                && flags['offline.crm.activities']);
        },
        isCrmMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.crm']
                && flags['offline.crm.masterdata']);
        },
        isProjectsEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.projects']);
        },
        isProjectsTasksEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.projects']
                && flags['offline.projects.tasks']);
        },
        isProjectsWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.projects']
                && flags['offline.projects.workflow']);
        },
        isProjectsTimesheetsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.projects']
                && flags['offline.projects.timesheets']);
        },
        isProjectsMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.projects']
                && flags['offline.projects.masterdata']);
        },
        isAssetsEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.assets']);
        },
        isAssetsMaintenanceEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.assets']
                && flags['offline.assets.maintenance']);
        },
        isAssetsWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.assets']
                && flags['offline.assets.workflow']);
        },
        isAssetsInspectionsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.assets']
                && flags['offline.assets.inspections']);
        },
        isAssetsMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.assets']
                && flags['offline.assets.masterdata']);
        },
        isApprovalEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.approval']);
        },
        isApprovalRequestsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.approval']
                && flags['offline.approval.requests']);
        },
        isApprovalWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.approval']
                && flags['offline.approval.workflow']);
        },
        isApprovalMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.approval']
                && flags['offline.approval.masterdata']);
        },
        isProcurementEnterpriseEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.procurement_enterprise']);
        },
        isProcurementEnterpriseSuppliersEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.procurement_enterprise']
                && flags['offline.procurement_enterprise.suppliers']);
        },
        isProcurementEnterpriseTendersEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.procurement_enterprise']
                && flags['offline.procurement_enterprise.tenders']);
        },
        isProcurementEnterpriseContractsEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.procurement_enterprise']
                && flags['offline.procurement_enterprise.contracts']);
        },
        isProcurementEnterpriseWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.procurement_enterprise']
                && flags['offline.procurement_enterprise.workflow']);
        },
        isProcurementEnterpriseMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.procurement_enterprise']
                && flags['offline.procurement_enterprise.masterdata']);
        },
        isManufacturingEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.manufacturing']);
        },
        isManufacturingProductionEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.manufacturing']
                && flags['offline.manufacturing.production']);
        },
        isManufacturingWorkflowEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.manufacturing']
                && flags['offline.manufacturing.workflow']);
        },
        isManufacturingQualityEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.manufacturing']
                && flags['offline.manufacturing.quality']);
        },
        isManufacturingMasterDataEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.manufacturing']
                && flags['offline.manufacturing.masterdata']);
        },
        isReadCacheEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.read_cache']);
        },
        isAuthUnlockEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.read_cache'] && flags['offline.auth.unlock']);
        },
        isRbacCacheEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.read_cache']
                && flags['offline.auth.unlock']
                && flags['offline.rbac.cache']);
        },
        isMasterDataEnabled: function () {
            return !!(flags['offline.enabled'] && flags['offline.master_data']);
        },
        isPilotOpsPagesEnabled: function () {
            return !!(flags['offline.enabled']
                && flags['offline.read_cache']
                && flags['offline.pilot.ops_pages']);
        },
        flags: function () { return Object.assign({}, flags); },
        queue: function () { return root.RatebOfflineQueue || null; },
        transport: function () { return root.RatebOfflineTransport || null; },
        connectivity: function () { return root.RatebOfflineConnectivity || null; },
        pos: function () { return root.RatebOfflinePosAdapter || null; },
        inventory: function () { return root.RatebOfflineInventoryAdapter || null; },
        hr: function () { return root.RatebOfflineHrAdapter || null; },
        procurement: function () { return root.RatebOfflineProcurementAdapter || null; },
        recruitment: function () { return root.RatebOfflineRecruitmentAdapter || null; },
        accounting: function () { return root.RatebOfflineAccountingAdapter || null; },
        crm: function () { return root.RatebOfflineCrmAdapter || null; },
        projects: function () { return root.RatebOfflineProjectsAdapter || null; },
        assets: function () { return root.RatebOfflineAssetsAdapter || null; },
        approvals: function () { return root.RatebOfflineApprovalAdapter || null; },
        procurementEnterprise: function () { return root.RatebOfflineProcurementEnterpriseAdapter || null; },
        manufacturing: function () { return root.RatebOfflineManufacturingAdapter || null; },
        opsForms: function () { return root.RatebOfflineOpsForms || null; },
        shell: function () { return root.RatebOfflineShellAdapter || null; },
        auth: function () { return root.RatebOfflineAuthLock || null; },
        rbac: function () { return root.RatebOfflineRbacCache || null; },
        masterData: function () { return root.RatebOfflineMasterData || null; },
        schema: function () { return root.RatebOfflineSchema || null; },
        deltaPull: function () { return root.RatebOfflineDeltaPull || null; }
    };
})(typeof window !== 'undefined' ? window : globalThis);
