/**
 * RATEB AI page — soft-nav safe (script[src] re-injected by erp-nav-instant).
 * Mirrors the inline window.ratebAi API from the AI view.
 */
(function (root, doc) {
    'use strict';

    function installVoiceFeatures(api) {
        if (!api) return;
        var rootNode = doc.getElementById('ratebAiRoot');
        var inputBtn = doc.getElementById('aiVoiceInputBtn');
        var stopBtn = doc.getElementById('aiVoiceStopBtn');
        var modeBtn = doc.getElementById('aiVoiceModeBtn');
        // Soft-nav replaces these nodes via main.innerHTML while window.ratebAi survives.
        // Only skip when already bound to the *current* button elements.
        if (api.__ratebVoiceBound
            && api.__ratebVoiceInputBtn === inputBtn
            && api.__ratebVoiceStopBtn === stopBtn
            && api.__ratebVoiceModeBtn === modeBtn
            && inputBtn
            && typeof api.toggleVoiceInput === 'function') {
            return;
        }

        var languageSelect = doc.getElementById('aiVoiceLanguage');
        var status = doc.getElementById('aiVoiceStatus');
        var messages = doc.getElementById('aiMessages');
        var formEl = doc.getElementById('aiInputForm');
        var recBar = doc.getElementById('aiVoiceRecBar');
        var recCancel = doc.getElementById('aiVoiceRecCancel');
        var recOk = doc.getElementById('aiVoiceRecOk');
        var recText = doc.getElementById('aiVoiceRecText');
        var Recognition = root.SpeechRecognition || root.webkitSpeechRecognition;
        var recognition = null;
        var listening = false;
        var speaking = false;
        var voiceMode = false;
        var waitingForVoiceReply = false;
        var pendingTranscript = '';
        var autoSendOnFinal = false; // capsule UI: user confirms with ✓
        var voiceSession = 0;
        var lastMicToggleAt = 0;
        var restartTimer = null;
        var openTimer = null;

        function isRecActive() {
            return !!(api.__voiceRecActive || (formEl && formEl.classList.contains('is-recording')));
        }

        function recArmedRecently() {
            var armed = Number(api.__voiceRecArmedAt || 0);
            return armed > 0 && (Date.now() - armed) < 700;
        }

        function clearRestartTimer() {
            if (restartTimer) {
                try { root.clearTimeout(restartTimer); } catch (eT) {}
                restartTimer = null;
            }
        }

        function clearOpenTimer() {
            if (openTimer) {
                try { root.clearTimeout(openTimer); } catch (eO) {}
                openTimer = null;
            }
        }

        function updateRecPreview(text) {
            recText = doc.getElementById('aiVoiceRecText') || recText;
            recBar = doc.getElementById('aiVoiceRecBar') || recBar;
            var value = String(text || '').trim();
            if (recText) recText.textContent = value;
            if (recBar) recBar.classList.toggle('has-text', !!value);
        }

        function storeTranscript(text) {
            var value = String(text || '').trim();
            pendingTranscript = value;
            api.__pendingVoiceTranscript = value;
            putTranscript(value);
            updateRecPreview(value);
        }

        function readTranscript() {
            var fromApi = String(api.__pendingVoiceTranscript || '').trim();
            if (fromApi) return fromApi;
            var fromPending = String(pendingTranscript || '').trim();
            if (fromPending) return fromPending;
            var input = doc.getElementById('aiInput');
            if (input) return String(input.value || '').trim();
            var preview = doc.getElementById('aiVoiceRecText');
            if (preview) return String(preview.textContent || '').trim();
            return '';
        }

        function showRecBar() {
            api.__voiceRecActive = true;
            if (!api.__voiceRecArmedAt) api.__voiceRecArmedAt = Date.now();
            // Re-query in case soft-nav swapped nodes but this install is still live.
            formEl = doc.getElementById('aiInputForm') || formEl;
            recBar = doc.getElementById('aiVoiceRecBar') || recBar;
            recCancel = doc.getElementById('aiVoiceRecCancel') || recCancel;
            recOk = doc.getElementById('aiVoiceRecOk') || recOk;
            recText = doc.getElementById('aiVoiceRecText') || recText;
            if (formEl) formEl.classList.add('is-recording');
            if (recBar) {
                recBar.hidden = false;
                recBar.removeAttribute('hidden');
                recBar.classList.add('is-arming');
                try { recBar.style.display = 'flex'; } catch (eDisp) {}
            }
            // Block ✕/✓ under the cursor for a short arming window (mic click must not hit cancel).
            root.setTimeout(function () {
                var bar = doc.getElementById('aiVoiceRecBar');
                if (bar) bar.classList.remove('is-arming');
            }, 700);
        }

        function hideRecBar() {
            api.__voiceRecActive = false;
            api.__voiceRecArmedAt = 0;
            clearRestartTimer();
            clearOpenTimer();
            formEl = doc.getElementById('aiInputForm') || formEl;
            recBar = doc.getElementById('aiVoiceRecBar') || recBar;
            if (formEl) formEl.classList.remove('is-recording');
            if (recBar) {
                recBar.hidden = true;
                recBar.setAttribute('hidden', 'hidden');
                recBar.classList.remove('is-arming');
                recBar.classList.remove('has-text');
                try { recBar.style.display = ''; } catch (eDisp2) {}
            }
            if (recText) recText.textContent = '';
        }

        if (!inputBtn || !modeBtn || !languageSelect || !status) {
            api.toggleVoiceInput = function () {
                try {
                    var s = doc.getElementById('aiVoiceStatus');
                    if (s) {
                        s.textContent = 'عناصر المايك غير جاهزة — حدّث الصفحة (Ctrl+F5)';
                        s.setAttribute('data-state', 'ready');
                    }
                } catch (eSt) {}
                return false;
            };
            api.toggleVoiceMode = api.toggleVoiceInput;
            api.stopVoice = function () { return false; };
            api.bindVoice = function () { installVoiceFeatures(api); };
            return;
        }
        if (!stopBtn) {
            stopBtn = doc.createElement('button');
            stopBtn.id = 'aiVoiceStopBtn';
            stopBtn.hidden = true;
            stopBtn.className = 'rateb-ai-voice-btn rateb-ai-voice-btn--stop is-hidden';
        }
        if (!messages) {
            messages = doc.getElementById('aiMessages') || doc.body;
        }

        function setStatus(state, detail) {
            var labels = {
                ready: 'Ready',
                listening: 'Listening / جاري الاستماع',
                processing: 'Processing / جاري المعالجة',
                speaking: 'Speaking / جاري الرد'
            };
            status.textContent = detail || labels[state] || state;
            status.setAttribute('data-state', state);
        }

        function setStopVisible(visible) {
            stopBtn.classList.toggle('is-hidden', !visible);
            if (visible) {
                stopBtn.removeAttribute('hidden');
                stopBtn.style.display = '';
            } else {
                stopBtn.setAttribute('hidden', 'hidden');
                stopBtn.style.display = 'none';
            }
        }

        function stopSpeaking() {
            if (root.speechSynthesis) root.speechSynthesis.cancel();
            speaking = false;
            if (!listening) setStatus('ready');
        }

        function speak(text, continueListening) {
            var content = String(text || '').trim();
            if (!content || !root.speechSynthesis || !root.SpeechSynthesisUtterance) return;
            stopSpeaking();
            var utterance = new root.SpeechSynthesisUtterance(content);
            utterance.lang = languageSelect.value || 'en-US';
            utterance.onstart = function () {
                speaking = true;
                setStatus('speaking');
                setStopVisible(true);
            };
            utterance.onend = function () {
                speaking = false;
                if (voiceMode && continueListening) {
                    startListening();
                } else {
                    setStatus('ready');
                    setStopVisible(false);
                }
            };
            utterance.onerror = function () {
                speaking = false;
                setStatus('ready', 'Voice playback unavailable');
                setStopVisible(false);
            };
            root.speechSynthesis.speak(utterance);
        }

        function decorateMessage(message) {
            if (!message || !message.classList.contains('assistant') || message.classList.contains('rateb-ai-typing-container') || message.querySelector('.rateb-ai-speak-btn')) return;
            var content = message.querySelector('.rateb-ai-message-content');
            if (!content || !String(content.textContent || '').trim()) return;
            var button = doc.createElement('button');
            button.type = 'button';
            button.className = 'rateb-ai-speak-btn';
            button.setAttribute('aria-label', 'Play AI response');
            button.title = 'Play AI response';
            button.innerHTML = '<i class="fa-solid fa-volume-high" aria-hidden="true"></i>';
            button.addEventListener('click', function () { speak(content.textContent, false); });
            message.appendChild(button);
        }

        function putTranscript(text) {
            var input = doc.getElementById('aiInput');
            var value = String(text || '').trim();
            if (!input || !value) return;
            input.value = value;
            try { input.dispatchEvent(new Event('input', { bubbles: true })); } catch (eIn) {}
            if (typeof api.syncSendBtn === 'function') api.syncSendBtn();
        }

        var micStream = null;

        function releaseMicStream() {
            if (!micStream) return;
            try {
                var tracks = micStream.getTracks ? micStream.getTracks() : [];
                for (var i = 0; i < tracks.length; i += 1) {
                    try { tracks[i].stop(); } catch (eTr) {}
                }
            } catch (eRel) {}
            micStream = null;
        }

        function ensureMicPermission(done) {
            var cb = typeof done === 'function' ? done : function () {};
            if (!root.navigator || !root.navigator.mediaDevices || !root.navigator.mediaDevices.getUserMedia) {
                cb(true);
                return;
            }
            // Prompt once to unlock Permissions-Policy / browser mic gate, then release
            // so SpeechRecognition can own the mic (holding the stream can block STT).
            root.navigator.mediaDevices.getUserMedia({ audio: true, video: false }).then(function (stream) {
                try {
                    var tracks = stream.getTracks ? stream.getTracks() : [];
                    for (var i = 0; i < tracks.length; i += 1) {
                        try { tracks[i].stop(); } catch (eTr) {}
                    }
                } catch (eStop) {}
                api.__micPermissionOk = true;
                cb(true);
            }).catch(function (err) {
                var name = err && err.name ? String(err.name) : '';
                if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || name === 'SecurityError') {
                    setStatus('ready', 'اسمح للمايك من إعدادات المتصفح (قفل العنوان ← الميكروفون)');
                    cb(false);
                    return;
                }
                cb(true);
            });
        }

        function startRecognitionEngine() {
            if (!Recognition) {
                setStatus('ready', 'الميكروفون غير مدعوم في هذا المتصفح. استخدم Chrome واسمح بالمايك.');
                return;
            }
            if (listening) return;
            if (api.loading) api.loading = false;
            stopSpeaking();
            clearRestartTimer();
            var mySession = ++voiceSession;
            var cycleBase = String(api.__pendingVoiceTranscript || pendingTranscript || '').trim();
            try { if (recognition) recognition.abort(); } catch (eAbortPrev) {}
            recognition = new Recognition();
            recognition.lang = (languageSelect && languageSelect.value) ? languageSelect.value : 'ar-SA';
            // Single-utterance cycles are more reliable for ar-SA; we restart while capsule is open.
            recognition.continuous = false;
            recognition.interimResults = true;
            recognition.maxAlternatives = 1;
            recognition.onstart = function () {
                if (mySession !== voiceSession) return;
                listening = true;
                inputBtn.classList.add('is-listening');
                inputBtn.setAttribute('data-listening', '1');
                setStatus('listening', 'جاري الاستماع… تكلم الآن');
                setStopVisible(true);
                showRecBar();
            };
            recognition.onresult = function (event) {
                if (mySession !== voiceSession) return;
                var cycle = '';
                try {
                    for (var i = 0; i < event.results.length; i += 1) {
                        if (event.results[i] && event.results[i][0]) {
                            cycle += event.results[i][0].transcript;
                        }
                    }
                } catch (eRes) {
                    cycle = '';
                }
                cycle = String(cycle || '').trim();
                if (!cycle) return;
                var merged = cycleBase ? (cycleBase + ' ' + cycle) : cycle;
                // Prefer longest / latest merge within this cycle.
                storeTranscript(merged);
                setStatus('listening', 'تم الالتقاط — يمكنك المتابعة أو ✓');
            };
            recognition.onerror = function (event) {
                if (mySession !== voiceSession) return;
                listening = false;
                inputBtn.classList.remove('is-listening');
                inputBtn.removeAttribute('data-listening');
                var err = event && event.error ? String(event.error) : '';
                var denied = err === 'not-allowed' || err === 'service-not-allowed';
                if (denied) {
                    setStatus('ready', 'المتصفح يمنع المايك — اسمح بالميكروفون لهذا الموقع');
                    setStopVisible(false);
                    return;
                }
                if (err === 'aborted') return;
                if (err === 'no-speech') {
                    setStatus('listening', 'لم أسمع شيئاً — تكلم بوضوح…');
                    return;
                }
                if (err === 'network') {
                    setStatus('listening', 'شبكة التعرف على الصوت ضعيفة — أعد المحاولة');
                    return;
                }
                if (isRecActive()) {
                    setStatus('listening', 'جاري الاستماع…');
                } else {
                    setStatus('ready', 'تعذر تشغيل المايك');
                    setStopVisible(false);
                }
            };
            recognition.onend = function () {
                if (mySession !== voiceSession) return;
                listening = false;
                inputBtn.classList.remove('is-listening');
                inputBtn.removeAttribute('data-listening');
                // Snapshot what we have so far as the base for the next cycle.
                cycleBase = String(api.__pendingVoiceTranscript || pendingTranscript || '').trim();
                if (isRecActive() && !api.loading) {
                    clearRestartTimer();
                    restartTimer = root.setTimeout(function () {
                        restartTimer = null;
                        if (!isRecActive() || mySession !== voiceSession || api.loading) return;
                        try {
                            recognition.start();
                        } catch (eRestart) {
                            try { startRecognitionEngine(); } catch (e2) {}
                        }
                    }, 220);
                    return;
                }
                if (!speaking && !api.loading && !waitingForVoiceReply) {
                    setStatus('ready');
                    setStopVisible(false);
                }
            };
            try {
                recognition.start();
            } catch (error) {
                listening = false;
                if (isRecActive()) {
                    clearRestartTimer();
                    restartTimer = root.setTimeout(function () {
                        restartTimer = null;
                        if (isRecActive() && mySession === voiceSession) {
                            try { startRecognitionEngine(); } catch (e3) {}
                        }
                    }, 280);
                } else {
                    setStatus('ready', 'تعذر تشغيل المايك');
                }
            }
        }

        function startListening() {
            // Always show the capsule UI immediately on mic click (even before permission).
            showRecBar();
            setStatus('listening', 'جاري تفعيل المايك…');
            ensureMicPermission(function (ok) {
                if (!isRecActive()) return;
                if (!ok) return;
                startRecognitionEngine();
            });
        }

        function stopAll() {
            voiceMode = false;
            waitingForVoiceReply = false;
            voiceSession += 1;
            clearRestartTimer();
            clearOpenTimer();
            releaseMicStream();
            try { if (recognition) recognition.abort(); } catch (eStop) {
                try { if (recognition) recognition.stop(); } catch (eStop2) {}
            }
            listening = false;
            stopSpeaking();
            modeBtn.setAttribute('aria-pressed', 'false');
            modeBtn.classList.remove('is-active');
            inputBtn.classList.remove('is-listening');
            inputBtn.removeAttribute('data-listening');
            setStatus('ready');
            setStopVisible(false);
            hideRecBar();
        }

        function cancelRecording() {
            // Ignore ghost clicks that land on ✕ because the capsule replaced the mic under the cursor.
            if (recArmedRecently() && !readTranscript()) return false;
            pendingTranscript = '';
            api.__pendingVoiceTranscript = '';
            updateRecPreview('');
            var input = doc.getElementById('aiInput');
            if (input) {
                input.value = '';
                try { input.dispatchEvent(new Event('input', { bubbles: true })); } catch (eIn) {}
            }
            stopAll();
            return false;
        }

        function confirmRecording() {
            var text = readTranscript();
            // Only block ghost ✓ when nothing was captured yet.
            if (recArmedRecently() && !text) return false;

            var finishing = false;
            function doSend() {
                if (finishing) return;
                finishing = true;
                var finalText = readTranscript() || text;
                listening = false;
                hideRecBar();
                setStopVisible(false);
                inputBtn.classList.remove('is-listening');
                inputBtn.removeAttribute('data-listening');

                if (!finalText) {
                    setStatus('ready', 'لم يُلتقط كلام — تكلم ثم اضغط ✓');
                    return;
                }

                pendingTranscript = '';
                api.__pendingVoiceTranscript = '';
                updateRecPreview('');
                setStatus('processing', 'جاري الإرسال…');
                waitingForVoiceReply = true;
                // Match sendFromInput: never leave loading stuck blocking chat send.
                api.loading = false;
                var chat = root.ratebAi || api;
                var sent = false;
                try {
                    if (chat && typeof chat.send === 'function') {
                        sent = chat.send.call(chat, finalText) !== false;
                    }
                } catch (eSend) {
                    sent = false;
                    try { console.warn('ratebAi.voiceSend', eSend); } catch (eLog) {}
                }
                if (!sent) {
                    putTranscript(finalText);
                    setStatus('ready', 'النص جاهز في الحقل — اضغط إرسال');
                    return;
                }
                setStatus('ready');
            }

            // Stop recognition (finalize interim → final) then send into chat like Cursor voice.
            clearRestartTimer();
            clearOpenTimer();
            voiceSession += 1;
            if (recognition) {
                try {
                    recognition.onresult = function (event) {
                        var full = '';
                        try {
                            for (var i = 0; i < event.results.length; i += 1) {
                                if (event.results[i] && event.results[i][0]) {
                                    full += event.results[i][0].transcript;
                                }
                            }
                        } catch (eRes) { full = ''; }
                        full = String(full || '').trim();
                        if (full) storeTranscript(full);
                    };
                    recognition.onend = function () { doSend(); };
                    recognition.onerror = function () { doSend(); };
                    recognition.stop();
                } catch (eStop) {
                    doSend();
                    return false;
                }
                root.setTimeout(function () { doSend(); }, 450);
                return false;
            }
            doSend();
            return false;
        }

        function toggleVoiceInput() {
            // Mic is bound from master-click + toolbar + inline — debounce duplicate fires.
            var now = Date.now();
            if (now - lastMicToggleAt < 450) return false;
            lastMicToggleAt = now;
            // Capsule stays open until ✕ / ✓ — do not toggle-off on second mic click.
            if (isRecActive()) {
                if (!listening) {
                    clearOpenTimer();
                    openTimer = root.setTimeout(function () {
                        openTimer = null;
                        if (isRecActive() && !listening) startListening();
                    }, 80);
                } else {
                    showRecBar();
                }
                return false;
            }
            pendingTranscript = '';
            api.__pendingVoiceTranscript = '';
            updateRecPreview('');
            // Mark active immediately (survives rebind), but defer DOM swap so this click
            // cannot land on ✕ that appears where the mic was.
            api.__voiceRecActive = true;
            api.__voiceRecArmedAt = Date.now();
            clearOpenTimer();
            openTimer = root.setTimeout(function () {
                openTimer = null;
                if (!api.__voiceRecActive) return;
                startListening();
            }, 80);
            return false;
        }

        function toggleVoiceMode() {
            voiceMode = !voiceMode;
            modeBtn.setAttribute('aria-pressed', voiceMode ? 'true' : 'false');
            modeBtn.classList.toggle('is-active', voiceMode);
            if (voiceMode) startListening(); else stopAll();
            return false;
        }

        setStopVisible(false);
        inputBtn.removeAttribute('disabled');
        modeBtn.removeAttribute('disabled');
        if (!Recognition) {
            setStatus('ready', 'الميكروفون يحتاج Chrome مع إذن المايك');
        }

        // Prefer document master + toolbar binders — avoid stacking per-button listeners on rebind.
        if (inputBtn.getAttribute('data-rateb-voice-click') !== '1') {
            inputBtn.setAttribute('data-rateb-voice-click', '1');
            inputBtn.addEventListener('click', function (e) {
                if (e) { e.preventDefault(); e.stopPropagation(); }
                toggleVoiceInput();
            });
        }
        if (stopBtn.getAttribute('data-rateb-voice-click') !== '1') {
            stopBtn.setAttribute('data-rateb-voice-click', '1');
            stopBtn.addEventListener('click', function (e) {
                if (e) { e.preventDefault(); e.stopPropagation(); }
                stopAll();
            });
        }
        if (modeBtn.getAttribute('data-rateb-voice-click') !== '1') {
            modeBtn.setAttribute('data-rateb-voice-click', '1');
            modeBtn.addEventListener('click', function (e) {
                if (e) { e.preventDefault(); e.stopPropagation(); }
                toggleVoiceMode();
            });
        }
        if (recCancel && recCancel.getAttribute('data-rateb-voice-click') !== '1') {
            recCancel.setAttribute('data-rateb-voice-click', '1');
            recCancel.addEventListener('click', function (e) {
                if (e) { e.preventDefault(); e.stopPropagation(); }
                cancelRecording();
            });
        }
        if (recOk && recOk.getAttribute('data-rateb-voice-click') !== '1') {
            recOk.setAttribute('data-rateb-voice-click', '1');
            recOk.addEventListener('click', function (e) {
                if (e) { e.preventDefault(); e.stopPropagation(); }
                confirmRecording();
            });
        }
        languageSelect.addEventListener('change', function () {
            if (listening) {
                if (recognition) recognition.stop();
                listening = false;
                startListening();
            }
        });

        // Inline onclick (same pattern as send) — works even if soft-nav skipped deferred rebind.
        api.toggleVoiceInput = toggleVoiceInput;
        api.toggleVoiceMode = toggleVoiceMode;
        api.cancelVoiceRecording = cancelRecording;
        api.confirmVoiceRecording = confirmRecording;
        api.stopVoice = function () { stopAll(); return false; };
        api.bindVoice = function () { installVoiceFeatures(api); };

        var observer = new root.MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                Array.prototype.forEach.call(mutation.addedNodes || [], function (node) {
                    if (node.nodeType !== 1) return;
                    decorateMessage(node);
                    if (node.classList.contains('assistant') && !node.classList.contains('rateb-ai-typing-container')) {
                        if (waitingForVoiceReply) {
                            waitingForVoiceReply = false;
                            var response = node.querySelector('.rateb-ai-message-content');
                            if (response) speak(response.textContent, voiceMode);
                        }
                    }
                });
            });
        });
        observer.observe(messages, { childList: true });
        Array.prototype.forEach.call(messages.querySelectorAll('.rateb-ai-message.assistant'), decorateMessage);

        // Mark bound only after listeners are on the live button nodes
        api.__ratebVoiceBound = true;
        api.__ratebVoiceBoundRoot = rootNode;
        api.__ratebVoiceInputBtn = inputBtn;
        api.__ratebVoiceStopBtn = stopBtn;
        api.__ratebVoiceModeBtn = modeBtn;
    }

    function buildApi() {
        return {
            loading: false,
            lastMessage: '',
            pendingConfirmKeys: [],
            __p0WriteConfirm: true,
            __p0SendFix: true,
            __p0LangFix: true,
            __p0I18nUi: true,
            __p0ToolLabels: true,
            __p0ChatHistory: true,
            __unifiedConfirmFix: true,
            isConfirmPhrase: function (message) {
                var m = String(message || '').trim();
                if (!m || m.length > 40) return false;
                return /^(نعم|موافق|تأكيد|أكد|اكد|أكمل|اكمل|نفذ|نفّذ|انشئ|أنشئ|انشاء|إنشاء|تنفيذ|confirm|yes|ok|okay|go|proceed|do\s*it|create|execute)!*$/i.test(m);
            },
            isRejectPhrase: function (message) {
                var m = String(message || '').trim();
                if (!m || m.length > 40) return false;
                return /^(لا|الغاء|إلغاء|توقف|ألغ|الغ|cancel|no|stop|abort|nevermind)!*$/i.test(m);
            },
            send: function (message, confirmedWrites) {
                var box = doc.getElementById('ratebAiRoot');
                if (!box) return false;
                var messages = doc.getElementById('aiMessages');
                var input = doc.getElementById('aiInput');
                var welcome = doc.getElementById('aiWelcome');
                var statusDot = doc.getElementById('aiStatusDot');
                var statusText = doc.getElementById('aiStatusText');
                var endpoint = box.getAttribute('data-endpoint') || '';
                var csrf = box.getAttribute('data-csrf') || '';
                var t = function (key, fallback) {
                    return box.getAttribute('data-i18n-' + key) || fallback;
                };
                var toolLabels = {};
                try {
                    toolLabels = JSON.parse(box.getAttribute('data-tool-labels') || '{}') || {};
                } catch (eLabels) {
                    toolLabels = {};
                }
                var toolLabel = function (name) {
                    return (toolLabels && toolLabels[name]) ? toolLabels[name] : name;
                };
                message = String(message || '').trim();
                if (!message || !messages || !endpoint || this.loading) return false;
                confirmedWrites = Array.isArray(confirmedWrites) ? confirmedWrites : [];
                if (!confirmedWrites.length && this.isConfirmPhrase(message) && this.pendingConfirmKeys && this.pendingConfirmKeys.length) {
                    confirmedWrites = this.pendingConfirmKeys.slice();
                }
                if (this.isRejectPhrase(message)) {
                    this.pendingConfirmKeys = [];
                }

                function setStatus(state) {
                    if (!statusText) return;
                    if (state === 'thinking') {
                        if (statusDot) statusDot.style.animation = 'none';
                        statusText.textContent = statusText.getAttribute('data-thinking') || 'Thinking...';
                    } else {
                        if (statusDot) statusDot.style.animation = 'pulse 2s infinite';
                        statusText.textContent = statusText.getAttribute('data-ready') || 'Ready';
                    }
                }
                function esc(text) {
                    return String(text)
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;').replace(/'/g, '&#039;')
                        .replace(/\n/g, '<br>');
                }
                function addMsg(role, content) {
                    var div = doc.createElement('div');
                    div.className = 'rateb-ai-message ' + role;
                    div.innerHTML = '<div class="rateb-ai-message-avatar"><i class="fa-solid fa-' +
                        (role === 'user' ? 'user' : 'robot') + '"></i></div>' +
                        '<div class="rateb-ai-message-content">' + esc(content) + '</div>';
                    messages.appendChild(div);
                    messages.scrollTop = messages.scrollHeight;
                }
                function typing() {
                    var div = doc.createElement('div');
                    div.className = 'rateb-ai-message assistant rateb-ai-typing-container';
                    div.innerHTML = '<div class="rateb-ai-message-avatar"><i class="fa-solid fa-robot"></i></div>' +
                        '<div class="rateb-ai-typing"><div class="rateb-ai-typing-dot"></div><div class="rateb-ai-typing-dot"></div><div class="rateb-ai-typing-dot"></div></div>';
                    messages.appendChild(div);
                    messages.scrollTop = messages.scrollHeight;
                    return div;
                }
                function showConfirm(pending, originalMessage) {
                    var keys = [];
                    var labels = [];
                    var actionId = '';
                    (pending || []).forEach(function (p) {
                        if (p && p.confirm_key) keys.push(p.confirm_key);
                        if (p && p.tool) labels.push(toolLabel(p.tool));
                        if (p && p.action_id && !actionId) actionId = String(p.action_id);
                    });
                    if (!keys.length) return;
                    var wrap = doc.createElement('div');
                    wrap.className = 'rateb-ai-message assistant rateb-ai-confirm-chrome';
                    wrap.setAttribute('data-action-id', actionId || '');
                    wrap.innerHTML = '<div class="rateb-ai-message-avatar"><i class="fa-solid fa-robot"></i></div>' +
                        '<div class="rateb-ai-message-content">' +
                        '<div>' + esc(labels.join(', ')) + '</div>' +
                        '<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">' +
                        '<button type="button" class="rateb-ai-suggestion-btn" data-rateb-ai-confirm="1">' + esc(t('confirm', 'Confirm')) + '</button>' +
                        '<button type="button" class="rateb-ai-suggestion-btn" data-rateb-ai-cancel="1">' + esc(t('cancel', 'Cancel')) + '</button>' +
                        '</div></div>';
                    messages.appendChild(wrap);
                    messages.scrollTop = messages.scrollHeight;
                    var confirmBtn = wrap.querySelector('[data-rateb-ai-confirm]');
                    var cancelBtn = wrap.querySelector('[data-rateb-ai-cancel]');
                    if (confirmBtn) {
                        confirmBtn.addEventListener('click', function () {
                            if (confirmBtn.disabled || root.ratebAi.loading) return;
                            confirmBtn.disabled = true;
                            if (cancelBtn) cancelBtn.disabled = true;
                            if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
                            // Deterministic confirmation — never re-send the original create utterance
                            root.ratebAi.send(t('confirm', 'تأكيد'), keys.slice());
                        });
                    }
                    if (cancelBtn) {
                        cancelBtn.addEventListener('click', function () {
                            if (cancelBtn.disabled || root.ratebAi.loading) return;
                            cancelBtn.disabled = true;
                            if (confirmBtn) confirmBtn.disabled = true;
                            if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
                            root.ratebAi.pendingConfirmKeys = [];
                            root.ratebAi.send(t('cancel', 'إلغاء'));
                        });
                    }
                }

                if (welcome) welcome.style.display = 'none';
                var history = (root.RatebAiHistory && root.RatebAiHistory.getApiHistory)
                    ? root.RatebAiHistory.getApiHistory()
                    : (this.getHistory ? this.getHistory() : []);
                if (!confirmedWrites.length || this.isConfirmPhrase(message) || this.isRejectPhrase(message)) {
                    addMsg('user', message);
                    this.lastMessage = message;
                    try { root.RatebAiHistory && root.RatebAiHistory.appendMessage('user', message); } catch (eHistU) {}
                }
                if (input) {
                    input.value = '';
                    input.style.height = 'auto';
                }
                this.loading = true;
                this.syncSendBtn();
                setStatus('thinking');
                var tip = typing();
                var self = this;

                fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        message: message,
                        history: history,
                        confirmed_writes: confirmedWrites
                    })
                }).then(function (res) {
                    return res.json().catch(function () {
                        return { success: false, message: t('request-failed', 'Request failed') + ' (' + res.status + ')' };
                    }).then(function (data) { return { ok: res.ok, data: data }; });
                }).then(function (result) {
                    if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
                    var data = result.data || {};
                    if (!result.ok || !data.success) {
                        addMsg('assistant', t('error-prefix', 'Error: ') + (data.message || t('request-failed', 'Request failed')));
                        return;
                    }
                    addMsg('assistant', (data.data && data.data.response) ? data.data.response : t('no-response', 'No response'));
                    try {
                        var replyText = (data.data && data.data.response) ? data.data.response : '';
                        if (replyText && root.RatebAiHistory) root.RatebAiHistory.appendMessage('assistant', replyText);
                        if (root.RatebAiHistory && root.RatebAiHistory.decorateDomActions) root.RatebAiHistory.decorateDomActions();
                    } catch (eHistA) {}
                    var pending = (data.data && data.data.pending_confirmations) ? data.data.pending_confirmations : [];
                    if (pending.length) {
                        var keys = [];
                        pending.forEach(function (p) { if (p && p.confirm_key) keys.push(p.confirm_key); });
                        self.pendingConfirmKeys = keys;
                        showConfirm(pending, message);
                    } else if (confirmedWrites.length) {
                        self.pendingConfirmKeys = [];
                    }
                }).catch(function (err) {
                    if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
                    addMsg('assistant', t('error-prefix', 'Error: ') + (err && err.message ? err.message : t('network-error', 'Network error')));
                }).then(function () {
                    self.loading = false;
                    setStatus('ready');
                    self.syncSendBtn();
                });
                return false;
            },
            clickSuggest: function (btn) {
                try {
                    var now = Date.now();
                    if (this.__lastSuggestAt && (now - this.__lastSuggestAt) < 450) {
                        return false;
                    }
                    this.__lastSuggestAt = now;
                    var prompt = '';
                    if (btn) {
                        prompt = btn.getAttribute('data-prompt') || '';
                        if (!String(prompt || '').trim()) {
                            prompt = btn.textContent || btn.innerText || '';
                        }
                    }
                    prompt = String(prompt || '').trim();
                    if (!prompt) return false;
                    this.loading = false;
                    return this.send(prompt);
                } catch (eSuggest) {
                    try { console.warn('ratebAi.clickSuggest', eSuggest); } catch (eLog) {}
                    return false;
                }
            },
            getHistory: function () {
                var messages = doc.getElementById('aiMessages');
                if (!messages) return [];
                var out = [];
                var nodes = messages.querySelectorAll('.rateb-ai-message');
                for (var i = 0; i < nodes.length; i++) {
                    var el = nodes[i];
                    if (el.classList.contains('rateb-ai-typing-container')) continue;
                    if (el.classList.contains('rateb-ai-confirm-chrome')) continue;
                    var role = el.classList.contains('user') ? 'user' : (el.classList.contains('assistant') ? 'assistant' : '');
                    if (!role) continue;
                    var contentEl = el.querySelector('.rateb-ai-message-content');
                    var text = contentEl ? String(contentEl.innerText || contentEl.textContent || '').trim() : '';
                    if (!text) continue;
                    out.push({ role: role, content: text });
                }
                if (out.length > 16) out = out.slice(out.length - 16);
                return out;
            },
            sendFromInput: function () {
                this.loading = false;
                var input = doc.getElementById('aiInput');
                var status = doc.getElementById('aiVoiceStatus');
                var msg = input ? String(input.value || '').trim() : '';
                if (!msg) {
                    if (status) {
                        status.textContent = 'اكتب رسالة أو استخدم المايك أولاً';
                        status.setAttribute('data-state', 'ready');
                    }
                    return false;
                }
                return this.send(msg);
            },
            syncSendBtn: function () {
                var input = doc.getElementById('aiInput');
                var sendBtn = doc.getElementById('aiSendBtn');
                if (!sendBtn) return;
                var empty = !(input && String(input.value || '').trim());
                /* Never HTML-disable when idle — disabled swallows clicks (incl. onclick). */
                sendBtn.disabled = !!this.loading;
                sendBtn.classList.toggle('is-empty', empty && !this.loading);
                sendBtn.setAttribute('aria-disabled', (empty || this.loading) ? 'true' : 'false');
            },
            /* Keep inline onclick intact — cloning used to strip it and leave dead chips after soft-nav. */
            bindSuggestButtons: function () {
                var box = doc.getElementById('ratebAiRoot');
                if (!box) return;
                var nodes = box.querySelectorAll('.rateb-ai-suggestion-btn, .rateb-ai-capability-chip');
                Array.prototype.forEach.call(nodes, function (btn) {
                    if (btn.getAttribute('data-rateb-ai-confirm') || btn.getAttribute('data-rateb-ai-cancel')) return;
                    btn.setAttribute('data-suggest-v', '5');
                });
            },
            bind: function () {
                var box = doc.getElementById('ratebAiRoot');
                if (!box) return;
                var form = doc.getElementById('aiInputForm');
                var input = doc.getElementById('aiInput');
                var sendBtn = doc.getElementById('aiSendBtn');
                var self = this;
                if (form && form.getAttribute('data-rateb-ai-submit') !== '1') {
                    form.setAttribute('data-rateb-ai-submit', '1');
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        self.sendFromInput();
                    });
                }
                if (input && input.getAttribute('data-rateb-ai-input') !== '1') {
                    input.setAttribute('data-rateb-ai-input', '1');
                    var onType = function () {
                        this.style.height = 'auto';
                        this.style.height = Math.min(this.scrollHeight, 180) + 'px';
                        self.syncSendBtn();
                    };
                    input.addEventListener('input', onType);
                    input.addEventListener('keyup', onType);
                    input.addEventListener('change', onType);
                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            self.sendFromInput();
                        }
                    });
                }
                if (sendBtn && sendBtn.getAttribute('data-rateb-ai-click') !== '1') {
                    sendBtn.setAttribute('data-rateb-ai-click', '1');
                    sendBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        self.sendFromInput();
                    });
                }
                box.setAttribute('data-rateb-ai-bound', '1');
                self.loading = false;
                self.bindSuggestButtons();
                self.syncSendBtn();
                try { if (input) input.focus(); } catch (eFocus) {}
            }
        };
    }

    var API_VER = 11;

    function ensureVoiceReady(api) {
        if (!api) return;
        try {
            if (typeof api.bindVoice === 'function') {
                api.bindVoice();
            } else {
                installVoiceFeatures(api);
                api.bindVoice = function () { installVoiceFeatures(api); };
            }
        } catch (eVoice) {
            try { installVoiceFeatures(api); } catch (e2) {}
        }
    }

    function bindMasterClicks() {
        if (root.__ratebAiClickBoundV9) return;
        root.__ratebAiClickBoundV9 = true;
        root.__ratebAiClickBoundV8 = true; // legacy inline binder checks V8
        doc.addEventListener('click', function (e) {
            if (!doc.getElementById('ratebAiRoot') || !root.ratebAi) return;
            var t = e.target && e.target.closest ? e.target.closest(
                '.rateb-ai-suggestion-btn, .rateb-ai-capability-chip, #aiSendBtn, #aiVoiceModeBtn, #aiVoiceInputBtn, #aiVoiceStopBtn'
            ) : null;
            if (!t) return;
            if (t.getAttribute('data-rateb-ai-confirm') || t.getAttribute('data-rateb-ai-cancel')) return;
            e.preventDefault();
            // Do NOT stopImmediatePropagation — keep onclick / other listeners as backup.
            var api = root.ratebAi;
            if (t.id === 'aiSendBtn' || t.id === 'aiVoiceModeBtn' || t.id === 'aiVoiceInputBtn' || t.id === 'aiVoiceStopBtn') {
                ensureVoiceReady(api);
            }
            if (t.id === 'aiSendBtn') {
                if (typeof api.sendFromInput === 'function') api.sendFromInput();
                return;
            }
            if (t.id === 'aiVoiceModeBtn') {
                if (typeof api.toggleVoiceMode === 'function') api.toggleVoiceMode();
                return;
            }
            if (t.id === 'aiVoiceInputBtn') {
                e.stopImmediatePropagation();
                if (typeof api.toggleVoiceInput === 'function') api.toggleVoiceInput();
                return;
            }
            if (t.id === 'aiVoiceStopBtn') {
                e.stopImmediatePropagation();
                if (typeof api.stopVoice === 'function') api.stopVoice();
                return;
            }
            if (typeof api.clickSuggest === 'function') {
                api.clickSuggest(t);
            }
        }, true);
    }

    function ensureApi() {
        var prev = root.ratebAi || {};
        if (prev && prev.__apiVer === API_VER && typeof prev.send === 'function' && typeof prev.clickSuggest === 'function') {
            bindMasterClicks();
            return prev;
        }
        root.ratebAi = buildApi();
        root.ratebAi.__apiVer = API_VER;
        root.ratebAi.loading = false;
        root.ratebAi.lastMessage = prev.lastMessage || '';
        root.ratebAi.pendingConfirmKeys = Array.isArray(prev.pendingConfirmKeys) ? prev.pendingConfirmKeys : [];
        bindMasterClicks();
        return root.ratebAi;
    }

    function boot() {
        try {
            if (doc.body) doc.body.setAttribute('data-rateb-hide-help-assistant', '1');
        } catch (eHide) {}
        var api = ensureApi();
        var box = doc.getElementById('ratebAiRoot');
        if (box) {
            box.removeAttribute('data-rateb-ai-bound');
            var form = doc.getElementById('aiInputForm');
            var input = doc.getElementById('aiInput');
            var sendBtn = doc.getElementById('aiSendBtn');
            if (form) form.removeAttribute('data-rateb-ai-submit');
            if (input) input.removeAttribute('data-rateb-ai-input');
            if (sendBtn) sendBtn.removeAttribute('data-rateb-ai-click');
            api.__ratebVoiceBound = false;
            api.bind();
            installVoiceFeatures(api);
            api.bindVoice = function () { installVoiceFeatures(api); };
            ensureVoiceReady(api);
        }
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    doc.addEventListener('rateb:nav:afterEnter', boot);
    doc.addEventListener('rateb:soft-nav:afterEnter', boot);
})(window, document);
