(function () {
    'use strict';

    function isOffline() {
        return window.RatebPosConnectivity
            ? !window.RatebPosConnectivity.isOnline()
            : !navigator.onLine;
    }

    function csrf() {
        var meta = document.querySelector('meta[name="rateb-csrf"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function notify(msg, isError) {
        if (window.RatebPosNotify) {
            window.RatebPosNotify(msg, isError);
            return;
        }
        if (isError) {
            console.warn(msg);
        }
    }

    function queue(action, payload) {
        if (!window.RatebPosOffline || !window.RatebPosOffline.push) {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        return window.RatebPosOffline.push({
            client_id: window.RatebPosOffline.newClientId
                ? window.RatebPosOffline.newClientId(action)
                : (action + '-' + Date.now()),
            action: action,
            payload: payload,
            version: 1
        });
    }

    function bindOpenForm() {
        var form = document.querySelector('form[action*="pos/shifts/open"]');
        if (!form || form.getAttribute('data-pos-shift-form') !== null) {
            // Register gate is handled in pos-register-tiles.js
            return;
        }
        form.addEventListener('submit', function (e) {
            if (!isOffline()) {
                return;
            }
            e.preventDefault();
            var terminal = form.querySelector('[name="terminal_id"]');
            var opening = form.querySelector('[name="opening_float"]');
            var terminalId = terminal ? Number(terminal.value || 0) : 0;
            if (terminalId < 1) {
                notify('Select a terminal', true);
                return;
            }
            queue('shift_open', {
                terminal_id: terminalId,
                opening_float: opening ? Number(opening.value || 0) : 0,
                scope: window.RatebPosOffline.buildScope ? window.RatebPosOffline.buildScope() : {}
            }).then(function () {
                notify('Shift open queued for sync');
            }).catch(function (err) {
                notify(err.message || 'Failed', true);
            });
        });
    }

    function bindCloseForm() {
        var form = document.querySelector('form[action*="/close"]');
        if (!form) {
            return;
        }
        var action = form.getAttribute('action') || '';
        var match = action.match(/pos\/shifts\/(\d+)\/close/);
        if (!match) {
            return;
        }
        form.addEventListener('submit', function (e) {
            if (!isOffline()) {
                return;
            }
            e.preventDefault();
            var closing = form.querySelector('[name="closing_float"]');
            var notes = form.querySelector('[name="notes"]');
            queue('shift_close', {
                shift_id: Number(match[1]),
                closing_float: closing ? Number(closing.value || 0) : 0,
                notes: notes ? String(notes.value || '') : '',
                scope: window.RatebPosOffline.buildScope ? window.RatebPosOffline.buildScope() : {}
            }).then(function () {
                notify('Shift close queued for sync');
            }).catch(function (err) {
                notify(err.message || 'Failed', true);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindOpenForm();
        bindCloseForm();
    });
})();
