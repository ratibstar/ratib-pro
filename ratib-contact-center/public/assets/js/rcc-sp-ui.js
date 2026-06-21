/**
 * RATIB Softphone UI bindings (external JS — no inline scripts in views).
 */
(function (global) {
    'use strict';

    function pad(n) { return n < 10 ? '0' + n : String(n); }

    function formatTime(sec) {
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        return pad(m) + ':' + pad(s);
    }

    function RccSoftphoneUi() {}

    RccSoftphoneUi.mount = function (opts) {
        var panel = document.getElementById('rcc-softphone-panel');
        if (!panel || typeof global.RccSoftphone !== 'function') { return; }

        var els = {
            statusDot: document.getElementById('rcc-sp-status-dot'),
            statusLabel: document.getElementById('rcc-sp-status-label'),
            number: document.getElementById('rcc-sp-number'),
            direction: document.getElementById('rcc-sp-direction'),
            timer: document.getElementById('rcc-sp-timer'),
            erp: document.getElementById('rcc-sp-erp'),
            queue: document.getElementById('rcc-sp-queue-name'),
            popup: document.getElementById('rcc-sp-incoming-popup'),
            popupNumber: document.getElementById('rcc-sp-popup-number'),
            answer: document.getElementById('rcc-sp-answer'),
            hangup: document.getElementById('rcc-sp-hangup'),
            hold: document.getElementById('rcc-sp-hold'),
            mute: document.getElementById('rcc-sp-mute'),
            transfer: document.getElementById('rcc-sp-transfer'),
            dial: document.getElementById('rcc-sp-dial'),
            popupAnswer: document.getElementById('rcc-sp-popup-answer'),
            audio: document.getElementById('rcc-sp-remote-audio')
        };

        var timerTick = null;
        var muted = false;

        function setAgentUi(state) {
            var map = { ready: 'ready', busy: 'busy', wrapup: 'wrapup', offline: 'offline' };
            var key = map[state] || 'offline';
            if (els.statusDot) {
                els.statusDot.className = 'rcc-softphone__status-dot rcc-softphone__status-dot--' + key;
            }
            if (els.statusLabel) { els.statusLabel.textContent = state; }
        }

        function showIncoming(payload) {
            var num = payload.remote_number || payload.caller_number || '—';
            if (els.popupNumber) { els.popupNumber.textContent = num; }
            if (els.number) { els.number.textContent = num; }
            if (els.popup) {
                els.popup.classList.remove('d-none', 'hidden');
            }
            if (els.answer) { els.answer.disabled = false; }
        }

        function hideIncoming() {
            if (els.popup) {
                els.popup.classList.add('d-none');
                els.popup.classList.remove('hidden');
            }
        }

        function startUiTimer(phone) {
            if (timerTick) { clearInterval(timerTick); }
            timerTick = setInterval(function () {
                if (els.timer && phone) {
                    els.timer.textContent = formatTime(phone.getDuration());
                }
            }, 1000);
        }

        function stopUiTimer() {
            if (timerTick) { clearInterval(timerTick); timerTick = null; }
            if (els.timer) { els.timer.textContent = '00:00'; }
        }

        function setInCallUi(active) {
            if (els.hangup) { els.hangup.disabled = !active; }
            if (els.hold) { els.hold.disabled = !active; }
            if (els.mute) { els.mute.disabled = !active; }
            if (els.transfer) { els.transfer.disabled = !active; }
            if (els.answer) { els.answer.disabled = active; }
            if (els.dial) { els.dial.disabled = active; }
        }

        var phone = new global.RccSoftphone({
            apiBase: opts.apiBase,
            wsUrl: opts.wsUrl,
            tenantId: opts.tenantId,
            agentId: opts.agentId,
            userId: opts.userId,
            remoteAudio: els.audio,
            onIncoming: function (payload) { showIncoming(payload); },
            onConnected: function (call) {
                hideIncoming();
                setInCallUi(true);
                setAgentUi('busy');
                if (els.direction) { els.direction.textContent = call.direction || 'connected'; }
                startUiTimer(phone);
            },
            onEnded: function () {
                hideIncoming();
                setInCallUi(false);
                setAgentUi('wrapup');
                stopUiTimer();
                if (els.number) { els.number.textContent = '—'; }
                if (els.erp) { els.erp.classList.add('rcc-softphone__hidden'); }
            },
            onHold: function () { if (els.direction) { els.direction.textContent = 'held'; } },
            onResume: function () { if (els.direction) { els.direction.textContent = 'connected'; } },
            onAgentState: function (state) { setAgentUi(state); },
            onErpProfile: function (profile) {
                if (!els.erp || !profile || !profile.contact) { return; }
                els.erp.classList.remove('rcc-softphone__hidden');
                var name = profile.contact.full_name || profile.contact.name || '—';
                var company = profile.company ? profile.company.name : '';
                var sla = profile.sla_status || 'unknown';
                els.erp.innerHTML = '<div class="rcc-softphone__erp-title">' + name + '</div>'
                    + (company ? '<div>' + company + '</div>' : '')
                    + '<div>SLA: ' + sla + '</div>';
            },
            onStatus: function (status) {
                if (status === 'registered') { setAgentUi('ready'); if (els.dial) { els.dial.disabled = false; } }
            },
            onError: function (err) { console.error('[RCC Softphone]', err); }
        });

        if (els.answer) {
            els.answer.addEventListener('click', function () { phone.answer(); });
        }
        if (els.popupAnswer) {
            els.popupAnswer.addEventListener('click', function () { phone.answer(); });
        }
        if (els.hangup) {
            els.hangup.addEventListener('click', function () { phone.hangup(); });
        }
        if (els.hold) {
            els.hold.addEventListener('click', function () {
                if (els.direction && els.direction.textContent === 'held') {
                    phone.resume();
                } else {
                    phone.hold();
                }
            });
        }
        if (els.mute) {
            els.mute.addEventListener('click', function () {
                muted = !muted;
                phone.mute(muted);
                els.mute.textContent = muted ? 'Unmute' : 'Mute';
            });
        }
        if (els.transfer) {
            els.transfer.addEventListener('click', function () {
                var ext = prompt('Transfer to extension:');
                if (ext) { phone.transferBlind(ext); }
            });
        }
        if (els.dial) {
            els.dial.addEventListener('click', function () {
                var num = prompt('Dial number:');
                if (num) { phone.dial(num); }
            });
        }

        phone.init().catch(function (e) { console.error(e); });
        global.__rccSoftphone = phone;
    };

    global.RccSoftphoneUi = RccSoftphoneUi;

    document.addEventListener('DOMContentLoaded', function () {
        var panel = document.getElementById('rcc-softphone-panel');
        if (!panel) { return; }
        RccSoftphoneUi.mount({
            tenantId: parseInt(panel.getAttribute('data-tenant') || '0', 10),
            agentId: parseInt(panel.getAttribute('data-agent') || '0', 10),
            userId: panel.getAttribute('data-user') ? parseInt(panel.getAttribute('data-user'), 10) : null,
            apiBase: panel.getAttribute('data-api') || '/ratib-contact-center/public/api/v1/softphone.php',
            wsUrl: panel.getAttribute('data-ws') || 'ws://127.0.0.1:9702'
        });
    });
})(typeof window !== 'undefined' ? window : globalThis);
