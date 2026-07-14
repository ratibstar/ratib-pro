/*! RATEB Offline module offline-files.js (Phase OA — sourced from offline/client). */

/* ---- documents-adapter.js ---- */
/**
 * RATEB Offline — Enterprise Documents (DMS) adapter (Phase 26B / Tier 1 drafts).
 * Queues repository / folder / document / version / checkout / share / permission drafts.
 * Activated only when offline.enabled + offline.documents (sub-flags gate children).
 * Does NOT enqueue delete, upload, attachments, binary, notifications, email/SMS,
 * payments, signature, publish, approve, or download.
 */
(function (root) {
    'use strict';

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return {};
    }

    function isActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.documents']);
    }

    function isRepositoriesActive() {
        var f = flags();
        return !!(isActive() && f['offline.documents.repositories']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.documents.workflow']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.documents.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'dms') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('documents_offline_disabled'));
        }
        if ((action === 'repository.create' || action === 'repository.update'
            || action === 'folder.create' || action === 'folder.update') && !isRepositoriesActive()) {
            return Promise.reject(new Error('documents_repositories_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('documents_workflow_offline_disabled'));
        }
        if (action === 'delete' || action === 'attachment.create' || action === 'upload'
            || action === 'payment.create' || action === 'accounting.post'
            || action === 'signature.create' || action === 'publish' || action === 'approve'
            || action === 'download' || action === 'email.send' || action === 'sms.send') {
            return Promise.reject(new Error('documents_action_rejected'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'documents',
            action: action,
            payload: payload || {},
            version: options.version || 1,
            occurred_at: options.occurred_at || new Date().toISOString()
        });
    }

    function pullDirectory(entity, options) {
        options = options || {};
        if (!isMasterDataActive()) {
            return Promise.resolve({ items: [], stub: true, disabled: true });
        }
        var pull = root.RatebOfflineDeltaPull;
        if (!pull || typeof pull.pull !== 'function') {
            return Promise.reject(new Error('delta_pull_unavailable'));
        }
        return pull.pull(entity, options).then(function (res) {
            return (res && res.delta) ? res.delta : (res || { items: [] });
        });
    }

    root.RatebOfflineDocumentsAdapter = {
        isActive: isActive,
        isRepositoriesActive: isRepositoriesActive,
        isWorkflowActive: isWorkflowActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueRepositoryCreate: function (payload, options) {
            return enqueue('repository.create', payload || {}, options);
        },
        enqueueRepositoryUpdate: function (payload, options) {
            return enqueue('repository.update', payload || {}, options);
        },
        enqueueFolderCreate: function (payload, options) {
            return enqueue('folder.create', payload || {}, options);
        },
        enqueueFolderUpdate: function (payload, options) {
            return enqueue('folder.update', payload || {}, options);
        },
        enqueueDocumentCreate: function (payload, options) {
            return enqueue('document.create', payload || {}, options);
        },
        enqueueDocumentUpdate: function (payload, options) {
            return enqueue('document.update', payload || {}, options);
        },
        enqueueVersionCreate: function (payload, options) {
            return enqueue('version.create', payload || {}, options);
        },
        enqueueCheckoutCreate: function (payload, options) {
            return enqueue('checkout.create', payload || {}, options);
        },
        enqueueShareCreate: function (payload, options) {
            return enqueue('share.create', payload || {}, options);
        },
        enqueuePermissionCreate: function (payload, options) {
            return enqueue('permission.create', payload || {}, options);
        },
        enqueueCommentCreate: function (payload, options) {
            return enqueue('comment.create', payload || {}, options);
        },
        enqueueNoteCreate: function (payload, options) {
            return enqueue('note.create', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        draft: function (action, payload, options) {
            return enqueue(action, payload || {}, options);
        },
        retry: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.retryFailed !== 'function') {
                return Promise.reject(new Error('offline_queue_unavailable'));
            }
            return q.retryFailed({ module: 'documents' });
        },
        status: function () {
            var q = root.RatebOfflineQueue;
            if (!q || typeof q.status !== 'function') {
                return Promise.resolve({ pending: 0, failed: 0, module: 'documents' });
            }
            return q.status({ module: 'documents' });
        },
        sync: function () {
            var transport = root.RatebOfflineTransport;
            if (!transport || typeof transport.flush !== 'function') {
                return Promise.reject(new Error('offline_transport_unavailable'));
            }
            return transport.flush({ module: 'documents' });
        },
        pullRepositories: function (options) {
            return pullDirectory('documents_repository_directory', options);
        },
        pullCategories: function (options) {
            return pullDirectory('documents_category_directory', options);
        },
        pullWorkflowStatuses: function (options) {
            return pullDirectory('documents_workflow_status_directory', options);
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);

