/**
 * RATIB Contact Center — WebRTC Softphone SDK
 * Requires: SIP.js (UserAgent), RccRealtimeClient (optional but recommended)
 * NO polling — EventBus WebSocket + SIP signaling only
 */
(function (global) {
    'use strict';

    var CALL_STATUS = {
        RINGING: 'ringing',
        CONNECTED: 'connected',
        HELD: 'held',
        TRANSFERRED: 'transferred',
        ENDED: 'ended'
    };

    function RccSoftphone(options) {
        this.apiBase = options.apiBase || '/ratib-contact-center/public/api/v1/softphone.php';
        this.wsUrl = options.wsUrl || 'ws://127.0.0.1:9702';
        this.tenantId = options.tenantId || 0;
        this.agentId = options.agentId || 0;
        this.userId = options.userId || null;
        this.autoAnswer = !!options.autoAnswer;
        this.remoteAudio = options.remoteAudio || null;
        this.onIncoming = options.onIncoming || function () {};
        this.onConnected = options.onConnected || function () {};
        this.onEnded = options.onEnded || function () {};
        this.onHold = options.onHold || function () {};
        this.onResume = options.onResume || function () {};
        this.onAgentState = options.onAgentState || function () {};
        this.onErpProfile = options.onErpProfile || function () {};
        this.onError = options.onError || function () {};
        this.onStatus = options.onStatus || function () {};

        this._userAgent = null;
        this._registerer = null;
        this._session = null;
        this._realtime = null;
        this._activeCall = null;
        this._timer = null;
        this._duration = 0;
        this._webrtcConfig = null;
        this._uiLocked = false;
        this._pendingQueueAssignment = null;
    }

    RccSoftphone.prototype.init = function () {
        var self = this;
        return self._api('register', {}).then(function (res) {
            self._webrtcConfig = res.webrtc;
            self.autoAnswer = !!res.auto_answer_queue_calls;
            self._connectRealtime(res.realtime_rooms || []);
            self.onStatus('api_ready', res);
            return self._sipRegister(res.webrtc).catch(function (sipErr) {
                self.onStatus('sip_unavailable', { detail: sipErr && sipErr.message ? sipErr.message : String(sipErr) });
                return { registered: false, sip: false };
            });
        });
    };

    RccSoftphone.prototype._connectRealtime = function (rooms) {
        var self = this;
        var ws = (self.wsUrl || '').trim();
        if (!ws || typeof global.RccRealtimeClient !== 'function') {
            return;
        }
        var list = rooms.slice();
        list.push('agent:' + self.agentId, 'tenant:' + self.tenantId);
        self._realtime = new global.RccRealtimeClient({
            url: self.wsUrl,
            tenantId: self.tenantId,
            rooms: list,
            onEvent: function (ev) { self._onRealtimeEvent(ev); }
        });
        self._realtime.connect();
    };

    RccSoftphone.prototype._onRealtimeEvent = function (ev) {
        var self = this;
        if (!ev || !ev.type) { return; }

        switch (ev.type) {
            case 'CALL_INCOMING':
                if (ev.agent_id && ev.agent_id !== self.agentId) { return; }
                self.onIncoming(ev.payload || {}, ev);
                break;
            case 'QUEUE_ASSIGNED':
                if (ev.agent_id !== self.agentId) { return; }
                self._pendingQueueAssignment = ev;
                if (self.autoAnswer) {
                    self.answer(null, ev);
                } else {
                    self.onIncoming(ev.payload || {}, ev);
                }
                break;
            case 'CALL_CONNECTED':
                if (ev.payload && ev.payload.erp_customer) {
                    self.onErpProfile(ev.payload.erp_customer, ev);
                }
                self._startTimer();
                self.onConnected(self._activeCall || ev.payload || {}, ev);
                break;
            case 'CALL_ENDED':
                self._cleanupSession();
                self.onEnded(ev.payload || {}, ev);
                break;
            case 'AGENT_BUSY':
                self._uiLocked = true;
                self.onAgentState('busy', ev);
                break;
            case 'AGENT_READY':
            case 'AGENT_WRAPUP':
                self._uiLocked = false;
                self.onAgentState(ev.type.replace('AGENT_', '').toLowerCase(), ev);
                break;
            case 'CALL_HOLD':
                self.onHold(self._activeCall, ev);
                break;
            case 'CALL_RESUME':
                self.onResume(self._activeCall, ev);
                break;
            case 'SOFTPHONE_STATE':
                if (ev.payload) { self._activeCall = ev.payload; }
                break;
        }
    };

    RccSoftphone.prototype._sipRegister = function (cfg) {
        var self = this;
        if (typeof global.SIP === 'undefined' || !global.SIP.UserAgent) {
            self.onStatus('sip_js_missing', 'Load SIP.js before rcc-softphone.js');
            return Promise.resolve({ registered: false });
        }

        var uri = global.SIP.UserAgent.makeURI(cfg.aor || cfg.uri);
        if (!uri) {
            return Promise.reject(new Error('Invalid SIP URI'));
        }

        self._userAgent = new global.SIP.UserAgent({
            uri: uri,
            transportOptions: { server: cfg.server },
            authorizationUsername: cfg.authorizationUsername,
            authorizationPassword: cfg.authorizationPassword,
            sessionDescriptionHandlerFactoryOptions: {
                iceGatheringTimeout: 5000,
                peerConnectionConfiguration: { iceServers: cfg.iceServers || [] }
            }
        });

        self._userAgent.delegate = {
            onInvite: function (invitation) { self._onSipInvite(invitation); }
        };

        return self._userAgent.start().then(function () {
            self._registerer = new global.SIP.Registerer(self._userAgent);
            return self._registerer.register();
        }).then(function () {
            self.onStatus('registered', cfg);
            self._startPing();
            return { registered: true };
        }).catch(function (err) {
            self.onError(err);
            throw err;
        });
    };

    RccSoftphone.prototype._onSipInvite = function (invitation) {
        var self = this;
        self._session = invitation;
        var remote = (invitation.remoteIdentity && invitation.remoteIdentity.uri && invitation.remoteIdentity.uri.user) || 'unknown';

        self._setupSessionHandlers(invitation);

        self.onIncoming({ remote_number: remote, direction: 'inbound' }, { type: 'SIP_INVITE' });

        if (self.autoAnswer || self._pendingQueueAssignment) {
            self.answer(invitation, self._pendingQueueAssignment);
            self._pendingQueueAssignment = null;
        }
    };

    RccSoftphone.prototype._setupSessionHandlers = function (session) {
        var self = this;
        session.stateChange.addListener(function (state) {
            if (state === global.SIP.SessionState.Terminated) {
                self._reportHangup();
                self._cleanupSession();
            }
        });
    };

    RccSoftphone.prototype.answer = function (invitation, queueEvent) {
        var self = this;
        if (self._uiLocked && !queueEvent) {
            return Promise.reject(new Error('Agent busy'));
        }
        var session = invitation || self._session;
        if (!session) {
            return Promise.reject(new Error('No incoming session'));
        }

        var remote = (session.remoteIdentity && session.remoteIdentity.uri && session.remoteIdentity.uri.user) || 'unknown';
        var queueId = queueEvent && queueEvent.queue_id ? queueEvent.queue_id : null;
        var callId = queueEvent && queueEvent.call_id ? queueEvent.call_id : null;

        return session.accept({
            sessionDescriptionHandlerOptions: { constraints: { audio: true, video: false } }
        }).then(function () {
            self._attachRemoteAudio(session);
            return self._api('accept', {
                call_id: callId || 0,
                remote_number: remote,
                queue_id: queueId,
                sip_call_id: session.id || null
            });
        }).then(function (callState) {
            self._activeCall = callState;
            self.onConnected(callState, { type: 'LOCAL_ANSWER' });
            return callState;
        }).catch(function (e) { self.onError(e); throw e; });
    };

    RccSoftphone.prototype.dial = function (number) {
        var self = this;
        return self._api('outbound', { destination: number }).then(function (res) {
            if (!self._userAgent || typeof global.SIP === 'undefined' || !global.SIP.UserAgent) {
                self._activeCall = res.call || res;
                self.onConnected({ remote_number: number, direction: 'outbound', api_only: true }, { type: 'API_OUTBOUND' });
                return res;
            }
            var target = global.SIP.UserAgent.makeURI('sip:' + number + '@' + (self._webrtcConfig.domain || 'pbx'));
            var inviter = new global.SIP.Inviter(self._userAgent, target, {
                sessionDescriptionHandlerOptions: { constraints: { audio: true, video: false } }
            });
            self._session = inviter;
            self._setupSessionHandlers(inviter);
            return inviter.invite().then(function () {
                self._attachRemoteAudio(inviter);
                self._activeCall = res.call || res;
                return self._api('connected', {
                    softphone_call_id: (res.call && res.call.id) || 0,
                    remote_number: number
                });
            });
        });
    };

    RccSoftphone.prototype.hangup = function () {
        var self = this;
        var p = Promise.resolve();
        if (self._session) {
            p = self._session.bye ? self._session.bye() : self._session.cancel();
        }
        return p.then(function () {
            return self._reportHangup();
        }).then(function () {
            self._cleanupSession();
        });
    };

    RccSoftphone.prototype.hold = function () {
        var self = this;
        if (!self._session || !self._session.sessionDescriptionHandler) {
            return Promise.reject(new Error('No active session'));
        }
        return self._session.sessionDescriptionHandler.holdModifier().then(function () {
            return self._api('hold', { softphone_call_id: (self._activeCall && self._activeCall.id) || 0 });
        }).then(function (s) {
            self._activeCall = s;
            self.onHold(s);
            return s;
        });
    };

    RccSoftphone.prototype.resume = function () {
        var self = this;
        if (!self._session || !self._session.sessionDescriptionHandler) {
            return Promise.reject(new Error('No active session'));
        }
        return self._session.sessionDescriptionHandler.unholdModifier().then(function () {
            return self._api('resume', { softphone_call_id: (self._activeCall && self._activeCall.id) || 0 });
        }).then(function (s) {
            self._activeCall = s;
            self.onResume(s);
            return s;
        });
    };

    RccSoftphone.prototype.mute = function (muted) {
        if (!this._session || !this._session.sessionDescriptionHandler) { return; }
        var pc = this._session.sessionDescriptionHandler.peerConnection;
        if (!pc) { return; }
        pc.getSenders().forEach(function (sender) {
            if (sender.track && sender.track.kind === 'audio') {
                sender.track.enabled = !muted;
            }
        });
    };

    RccSoftphone.prototype.transferBlind = function (extension) {
        return this._api('transfer_blind', {
            softphone_call_id: (this._activeCall && this._activeCall.id) || 0,
            target_extension: extension
        });
    };

    RccSoftphone.prototype.transferAttended = function (extension, complete) {
        return this._api('transfer_attended', {
            softphone_call_id: (this._activeCall && this._activeCall.id) || 0,
            target_extension: extension,
            complete: !!complete
        });
    };

    RccSoftphone.prototype._reportHangup = function () {
        if (!this._activeCall || !this._activeCall.id) {
            return Promise.resolve();
        }
        return this._api('hangup', { softphone_call_id: this._activeCall.id });
    };

    RccSoftphone.prototype._cleanupSession = function () {
        this._session = null;
        this._activeCall = null;
        this._stopTimer();
        this._duration = 0;
    };

    RccSoftphone.prototype._attachRemoteAudio = function (session) {
        var self = this;
        var el = self.remoteAudio;
        if (!el || !session.sessionDescriptionHandler) { return; }
        var pc = session.sessionDescriptionHandler.peerConnection;
        if (!pc) { return; }
        var remoteStream = new MediaStream();
        pc.getReceivers().forEach(function (r) {
            if (r.track) { remoteStream.addTrack(r.track); }
        });
        el.srcObject = remoteStream;
        el.play().catch(function () {});
    };

    RccSoftphone.prototype._startTimer = function () {
        var self = this;
        self._stopTimer();
        self._duration = 0;
        self._timer = setInterval(function () { self._duration += 1; }, 1000);
    };

    RccSoftphone.prototype._stopTimer = function () {
        if (this._timer) { clearInterval(this._timer); this._timer = null; }
    };

    RccSoftphone.prototype.getDuration = function () { return this._duration; };

    RccSoftphone.prototype._startPing = function () {
        var self = this;
        setInterval(function () {
            self._api('ping', {}).catch(function () {});
        }, 30000);
    };

    RccSoftphone.prototype._api = function (action, body) {
        var self = this;
        var payload = Object.assign({}, body, {
            tenant_id: self.tenantId,
            agent_id: self.agentId,
            user_id: self.userId
        });
        return fetch(self.apiBase + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (r) { return r.json(); }).then(function (json) {
            if (!json.ok) { throw new Error(json.error || 'API error'); }
            return json.data;
        });
    };

    RccSoftphone.prototype.destroy = function () {
        var self = this;
        if (self._realtime) { self._realtime.disconnect(); }
        if (self._registerer) { self._registerer.unregister().catch(function () {}); }
        if (self._userAgent) { self._userAgent.stop().catch(function () {}); }
        return self._api('unregister', {});
    };

    global.RccSoftphone = RccSoftphone;
    global.RccSoftphoneCallStatus = CALL_STATUS;
})(typeof window !== 'undefined' ? window : globalThis);
