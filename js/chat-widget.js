/**
 * EN: Implements frontend interaction behavior in `js/chat-widget.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/chat-widget.js`.
 */
/**
 * Chat Widget - Customer Support Chat (Chat Only - No Voice Calls)
 * Floating chat widget for customer-company communication
 */

(function() {
    'use strict';

    // Chat Widget State
    const chatWidget = {
        isOpen: false,
        messages: [],
        isTyping: false,
        sending: false,
        unreadCount: 0,
        escalated: false,
        chatId: null,
        chatToken: null,
        pollInterval: null,
        pollAfterId: 0
    };
    const CHAT_MESSAGES_KEY = 'chatWidgetMessages';
    const ESCALATED_KEY = 'chatWidgetEscalated';
    /** Persists across tab close so escalate can merge into one agency board in control panel */
    const SUPPORT_BOARD_TOKEN_KEY = 'ratibSupportBoardToken';
    /** Bump when a forced storage wipe is needed for all users */
    const CHAT_PURGE_KEY = 'chatWidgetHistoryPurgeV4';
    /** Live-support transcript on this browser: drop if idle longer than this (ms). */
    const LIVE_CHAT_HISTORY_MAX_MS = 7 * 24 * 60 * 60 * 1000;
    const LIVE_CHAT_MAX_STORED = 25;
    /** Shown right after visitor starts live support (chip or typed phrase). */
    const LIVE_SUPPORT_ACK_MESSAGE = 'Thank you for reaching out — our team will contact you soon.\n\nStay on this chat; when we reply, it will show up here. You can add more details below anytime.';
    /** After another tap on Talk to support while already connected (message was sent to control panel). */
    const LIVE_SUPPORT_RENOTIFY_MESSAGE = 'Thanks — we\'ve notified our team again. They\'ll see it on their side and will contact you soon.';
    const helpCenterPlainContentCache = new WeakMap();

    function htmlToPlainText(html) {
        if (!html) return '';
        try {
            var d = document.createElement('div');
            d.innerHTML = String(html);
            var t = d.textContent || '';
            return String(t).replace(/\s+/g, ' ').trim();
        } catch (e) {
            return String(html).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        }
    }

    function getTutorialPlainLower(tutorial) {
        if (!tutorial || !tutorial.content) return '';
        var cached = helpCenterPlainContentCache.get(tutorial);
        if (cached !== undefined) return cached;
        var low = htmlToPlainText(tutorial.content).toLowerCase();
        helpCenterPlainContentCache.set(tutorial, low);
        return low;
    }

    const HC_STOPWORDS = ['how', 'to', 'the', 'a', 'an', 'is', 'are', 'was', 'were', 'do', 'does', 'did', 'can', 'could', 'what', 'where', 'when', 'why', 'which', 'who', 'with', 'from', 'for', 'about', 'into', 'that', 'this', 'and', 'or', 'not', 'use', 'using', 'need', 'want', 'please', 'help', 'me', 'my', 'i'];
    /** Verbs in questions — paragraphs that include these get a score boost when the user asked about that action. */
    const HC_ACTION_VERBS = ['delete', 'remove', 'deactivate', 'erase', 'add', 'create', 'edit', 'update', 'save', 'export', 'import', 'search', 'filter', 'login', 'reset', 'send', 'submit', 'cancel', 'bulk'];

    /**
     * Fix common typos so "delet contact", "contct", etc. still match intents, KB, and Help Center scoring.
     * Whole-word replacements only (idempotent for correct spellings).
     */
    function normalizeHelpQuery(lower) {
        if (!lower) return '';
        var s = String(lower).toLowerCase().trim();
        var pairs = [
            [/\bdelets\b/g, 'delete'],
            [/\bdelet\b/g, 'delete'],
            [/\bdelte\b/g, 'delete'],
            [/\bdeleet\b/g, 'delete'],
            [/\bdelted\b/g, 'deleted'],
            [/\bremvoe\b/g, 'remove'],
            [/\bremov\b/g, 'remove'],
            [/\bremve\b/g, 'remove'],
            [/\bedti\b/g, 'edit'],
            [/\bcontcts\b/g, 'contacts'],
            [/\bcontct\b/g, 'contact'],
            [/\bcontac\b/g, 'contact'],
            [/\bcntacts\b/g, 'contacts'],
            [/\bcntact\b/g, 'contact'],
            [/\bworkrs\b/g, 'workers'],
            [/\bwokers\b/g, 'workers'],
            [/\bworke\b/g, 'worker'],
            [/\bagnts\b/g, 'agents'],
            [/\bagnt\b/g, 'agent'],
            [/\bsubaget\b/g, 'subagent'],
            [/\breprot\b/g, 'report'],
            [/\breprots\b/g, 'reports'],
            [/\bacounting\b/g, 'accounting'],
            [/\baccouting\b/g, 'accounting'],
            [/\baccunt\b/g, 'account'],
            [/\blogn\b/g, 'login'],
            [/\blogni\b/g, 'login'],
            [/\bpasword\b/g, 'password'],
            [/\bpsw\b/g, 'password']
        ];
        for (var i = 0; i < pairs.length; i++) {
            s = s.replace(pairs[i][0], pairs[i][1]);
        }
        return s;
    }

    function buildHelpCenterSearchWords(lowerQuery) {
        if (!lowerQuery || lowerQuery.length < 2) return [];
        lowerQuery = normalizeHelpQuery(String(lowerQuery).toLowerCase().trim());
        if (lowerQuery.length < 2) return [];
        const queryWords = lowerQuery.split(/\s+/)
            .filter(w => w.length > 2 && !HC_STOPWORDS.includes(w))
            .map(w => w.replace(/[^\w]/g, ''))
            .filter(Boolean);
        const allWords = lowerQuery.split(/\s+/).filter(w => w.length > 1 && !HC_STOPWORDS.includes(w));
        const words = queryWords.length > 0 ? queryWords : allWords;
        return words.filter(w => w.length > 1);
    }

    /** Split tutorial HTML into plain-text chunks for query-focused excerpts. */
    function htmlToPlainParagraphList(html) {
        if (!html) return [];
        var s = String(html)
            .replace(/<\/(p|h2|h3|h4|li|ul|ol|div)>/gi, '\n\n')
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<\/(tr|table)>/gi, '\n');
        s = s.replace(/<[^>]+>/g, ' ');
        var parts = s.split(/\n+/);
        var out = [];
        for (var i = 0; i < parts.length; i++) {
            var p = parts[i].replace(/\s+/g, ' ').trim();
            if (p.length >= 28) out.push(p);
        }
        return out;
    }

    function truncateAtSentence(text, maxLen) {
        var s = String(text || '').replace(/\s+/g, ' ').trim();
        if (s.length <= maxLen) return s;
        var cut = s.slice(0, maxLen);
        var dot = cut.lastIndexOf('. ');
        if (dot > 50) return cut.slice(0, dot + 1).trim();
        var sp = cut.lastIndexOf(' ');
        return (sp > 30 ? cut.slice(0, sp) : cut).trim() + '…';
    }

    function queryActionWords(lowerQ) {
        var w = buildHelpCenterSearchWords(lowerQ);
        return w.filter(function(x) { return HC_ACTION_VERBS.indexOf(x) !== -1; });
    }

    function queryEntityWords(lowerQ) {
        var w = buildHelpCenterSearchWords(lowerQ);
        return w.filter(function(x) { return HC_ACTION_VERBS.indexOf(x) === -1; });
    }

    /** Bonus when an action from the question appears near an entity word in the same paragraph (e.g. delete + contact). */
    function excerptProximityBonus(pl, actions, entities) {
        if (!actions.length || !entities.length) return 0;
        var synonyms = { delete: ['delete', 'removes', 'remove'], remove: ['remove', 'removes', 'delete'] };
        for (var a = 0; a < actions.length; a++) {
            var variants = synonyms[actions[a]] || [actions[a]];
            for (var v = 0; v < variants.length; v++) {
                var ia = pl.indexOf(variants[v]);
                if (ia === -1) continue;
                for (var e = 0; e < entities.length; e++) {
                    var ie = pl.indexOf(entities[e]);
                    if (ie === -1) continue;
                    if (Math.abs(ia - ie) < 240) return 14;
                }
            }
        }
        return 0;
    }

    /** Top paragraphs that match the question; action verbs (delete, add, …) weighted heavily. */
    function pickRelevantExcerptsFromHtml(htmlContent, query, maxTotalChars, maxParas) {
        maxParas = maxParas == null ? 3 : Math.min(4, Math.max(1, maxParas));
        var lowerQ = String(query || '').toLowerCase().trim();
        var words = buildHelpCenterSearchWords(lowerQ);
        var actions = queryActionWords(lowerQ);
        var entities = queryEntityWords(lowerQ);
        var paras = htmlToPlainParagraphList(htmlContent);
        if (paras.length === 0) return '';
        var scored = paras.map(function(p) {
            var pl = p.toLowerCase();
            var sc = 0;
            for (var i = 0; i < words.length; i++) {
                if (words[i].length > 1 && pl.indexOf(words[i]) !== -1) sc += 2;
            }
            for (var j = 0; j < actions.length; j++) {
                if (pl.indexOf(actions[j]) !== -1) sc += 9;
            }
            sc += excerptProximityBonus(pl, actions, entities);
            if (pl.indexOf('contact your administrator') !== -1 || pl.indexOf('contact us') !== -1) sc -= 4;
            return { text: p, score: sc };
        });
        scored.sort(function(a, b) {
            if (b.score !== a.score) return b.score - a.score;
            return a.text.length - b.text.length;
        });
        var needHit = words.length > 0 ? 1 : 0;
        var out = [];
        var total = 0;
        for (var j = 0; j < scored.length && out.length < maxParas; j++) {
            if (scored[j].score < needHit) break;
            var t = scored[j].text;
            if (total + t.length + 2 > maxTotalChars) {
                var room = maxTotalChars - total - 2;
                if (room < 90) break;
                t = t.slice(0, room - 1).trim();
                var ld = t.lastIndexOf('. ');
                if (ld > room * 0.35) t = t.slice(0, ld + 1).trim();
                t += '…';
            }
            out.push(t);
            total += t.length + 2;
        }
        return out.join('\n\n');
    }

    /** Prefer a focused tutorial over the global program overview when the question names a module. */
    function tutorialBroadOverviewPenalty(tutorial, lowerQuery) {
        if (!tutorial || tutorial.id !== 'builtin-1-0') return 0;
        var needles = ['worker', 'workers', 'agent', 'agents', 'subagent', 'subagents', 'accounting', 'case', 'cases', 'report', 'reports', 'hr', 'visa', 'payroll', 'salary', 'invoice', 'login', 'password', 'communication', 'contact', 'notification', 'dashboard', 'sub-agent', 'partner', 'agencies', 'deployment', 'deployments'];
        for (var i = 0; i < needles.length; i++) {
            if (lowerQuery.indexOf(needles[i]) !== -1) return 42;
        }
        return 0;
    }

    function getWelcomeMessageText() {
        const hc = absoluteChatPageHref('pages/help-center.php');
        var msg = 'Hi 👋 Ask me here in chat anytime — quick topics give fast tips, or type your own question.\n' +
            'Help Center is optional if you want long tutorials: [Help Center →](' + hc + ') · Live agent: `Talk to support`';
        if (chatWidget.escalated && chatWidget.chatId && chatWidget.chatToken) {
            msg += '\n\nLive support is on — type below; new agent messages still show up here.';
        }
        return msg;
    }

    function hasOpenEscalationSession() {
        try {
            const esc = sessionStorage.getItem(ESCALATED_KEY);
            if (!esc) return false;
            const d = JSON.parse(esc);
            const cid = parseInt(d && d.chatId, 10);
            const tok = (d && d.chatToken) ? String(d.chatToken).trim() : '';
            return cid >= 1 && tok.length >= 32;
        } catch (e) {
            return false;
        }
    }

    // Get Current Language
    function getCurrentLanguage() {
        // English-only mode
        return 'en';
    }

    // Knowledge Base - Auto Answer System (English)
    const knowledgeBaseEN = [
        {
            keywords: ['hello', 'hi', 'hey', 'greetings', 'good morning', 'good afternoon', 'good evening', 'salam', 'مرحبا'],
            answer: "Hello! 👋 Welcome to Ratib Program support. I'm your AI assistant and I'm here to help you with any questions about the system. What would you like to know?",
            category: 'greeting',
            synonyms: ['greet', 'welcome', 'salutation']
        },
        {
            keywords: ['password', 'forgot password', 'reset password', 'change password', 'login', 'cannot login', 'login issue'],
            answer: "To reset your password:\n1. Click 'Forgot Password' on the login page\n2. Enter your email address\n3. Check your email for the reset link\n4. Follow the instructions to create a new password\n\nIf you're still having issues, contact your system administrator.",
            category: 'account'
        },
        {
            keywords: ['permission', 'access', 'cannot access', 'denied', 'unauthorized', 'no permission'],
            answer: "If you can't access a module:\n• Your user role may not have the required permissions\n• Contact your administrator to request access\n• Check System Settings → User Management for your permissions\n• You may only have view permissions for certain modules",
            category: 'permissions'
        },
        {
            keywords: ['dashboard', 'home', 'main page', 'overview'],
            answer: "The Dashboard provides an overview of:\n• Key metrics and statistics\n• Recent activities\n• Quick access to main modules\n• Notifications and alerts\n\nYou can navigate to different sections using the left sidebar menu.",
            category: 'navigation'
        },
        {
            keywords: ['agent', 'agents', 'add agent', 'create agent', 'agent management'],
            answer: "To add or manage agents in the program:\n1. Open Agent from the left menu\n2. Click Add New Agent (or open an existing row to edit)\n3. Fill company/contact details and any required codes\n4. Set permissions or limits if your admin allows it\n5. Save\n\nSub-agents live under SubAgent — link them to the right parent agent when you create them.",
            category: 'agents'
        },
        {
            keywords: [
                'how i delete worker', 'how do i delete worker', 'how to delete worker',
                'delete worker', 'delete workers', 'remove worker', 'remove workers',
                'deactivate worker', 'deactivate workers', 'erase worker', 'cancel worker',
                'remove a worker', 'delete a worker', 'worker delete', 'worker removal',
                'terminate worker', 'fire worker', 'unlink worker', 'drop worker',
                'archive worker', 'get rid of worker'
            ],
            answer: "To **remove** a worker (not add one):\n1. Open **Workers** from the left menu\n2. Find the person in the list and open the row or use the **⋮ / Actions** menu on that line\n3. Choose **Delete** or **Remove** if your role shows it — many offices use **Inactive** status instead of a hard delete\n4. Confirm if the system asks\n\nIf you don’t see Delete, your account may be **view-only**; ask an admin to change permissions or remove the record.\n\nStill stuck? Use **Talk to support** and we’ll walk you through it.",
            category: 'workers_delete'
        },
        {
            keywords: ['worker', 'workers', 'add worker', 'create worker', 'new worker', 'worker management', 'employee', 'passport', 'ticket', 'medical', 'police'],
            answer: "To **add** a worker (answered here in chat):\n1. Open Workers from the left menu\n2. Click Add Worker\n3. Enter name, ID, passport, contact info, and nationality if asked\n4. Assign to an agent if your workflow uses that\n5. Set status (e.g. active/pending) and save\n\nAfter that you can open the worker anytime to update documents, medical, or tickets from the same list.",
            category: 'workers'
        },
        {
            keywords: ['accounting', 'invoice', 'bill', 'payment', 'transaction', 'chart of accounts'],
            answer: "Accounting in Ratib covers:\n• Chart of accounts and journals\n• Invoices and money you’re owed\n• Bills and what you pay\n• Bank/cash movements\n• Financial reports by period\n\nTypical flow: open Accounting → choose the area (e.g. invoices or journal) → use Add / New → post or save. Use Reports when you need a printed or exported summary.",
            category: 'accounting'
        },
        {
            keywords: ['case', 'cases', 'my cases', 'case file', 'case files'],
            answer: "To work with cases:\n1. Open Cases from the left menu\n2. Use the table to see open or closed files\n3. Add a case or open one to change status, notes, or linked worker/agent\n4. Use search or filters to find a file quickly\n5. Save after each update so the timeline stays correct.",
            category: 'cases'
        },
        {
            keywords: ['visa', 'visa type', 'sponsor', 'musaned'],
            answer: "For visa-related records:\n1. Open Visa from the menu (or Worker Management if your guides point there)\n2. Find the worker or application you’re updating\n3. Enter or adjust visa type, sponsor, dates, and status fields the form shows\n4. Save — linked workers usually show the latest visa state on their profile\n\nExact field names depend on your agency setup; use the form labels as your checklist.",
            category: 'visa'
        },
        {
            keywords: ['report', 'reports', 'analytics', 'export', 'pdf', 'excel'],
            answer: "To run reports:\n1. Open Reports from the left menu\n2. Pick the report type (agents, workers, cases, HR, financial, etc.)\n3. Set the date range and filters you need\n4. Run / Generate and read the on-screen result\n5. Use Export (PDF/Excel) if you need to share or archive\n\nStart with a narrow date range if the list is large.",
            category: 'reports'
        },
        {
            keywords: ['help', 'support', 'tutorial', 'guide', 'learn', 'documentation', 'faq', 'help center', 'learning center'],
            answer: "I can answer quick questions right here in chat.\n\nYou can also use:\n• Quick topic buttons above\n• The Help & Learning Center for long tutorials — only when you want them",
            category: 'help'
        },
        {
            keywords: ['register', 'registration', 'sign up', 'create account', 'agency registration', 'apply', 'join'],
            answer: "To register your agency:\n1. Scroll to the 'Register Your Agency' section on this page\n2. Fill in your agency name, country, contact email, and other details\n3. Choose your plan (Pro, Gold, or Platinum)\n4. Click 'Submit Request'\n\nWe will review your request and contact you. You can also use the 'Register' link in the navigation.",
            category: 'registration'
        },
        {
            keywords: ['price', 'pricing', 'cost', 'fee', 'plan', 'gold', 'platinum', '550', '600', '1100', '1200'],
            answer: "Our plans & pricing:\n• Gold (1 Branch): $1,100 → $550 (50% off) one-time setup\n• Platinum (No Branches): $1,200 → $600 (50% off) one-time setup\n• Pro: Contact us for details\n\nScroll to 'Plans & Pricing' section for full features. Discount applies to new registrations.",
            category: 'pricing'
        },
        {
            keywords: ['hosting', 'server', 'database', 'ssl'],
            answer: "We provide secure hosting for your agency portal:\n• Database hosting included\n• SSL certificate included\n• Each plan includes hosting\n\nContact us for custom hosting needs. Scroll to the Hosting section for more info.",
            category: 'hosting'
        },
        {
            keywords: ['payment', 'pay', 'bank transfer', 'how to pay', 'payment method'],
            answer: "We accept:\n• Bank transfer\n• Other methods (details provided after approval)\n\nPayment details are provided after your registration request is approved. Submit the registration form to get started.",
            category: 'payment'
        },
        {
            keywords: ['add contact', 'new contact', 'contacts page', 'contact list', 'how to add contact', 'how to add a contact', 'edit contact', 'manage contacts', 'contacts and', 'contact form', 'save contact'],
            answer: "To add someone to your Contacts list:\n1. Open Contact from the left menu\n2. Click Add Contact\n3. Fill in name, phone, email, and any other fields\n4. Save\n\nFrom there you can message them and see history in the same area.",
            category: 'contacts_module'
        },
        {
            keywords: ['how to delete contact', 'how to delete a contact', 'delete contact', 'remove contact', 'deleting contact', 'delete a contact', 'erase contact'],
            answer: "To delete a contact:\n1. Open Contact from the left menu\n2. Find the person in the table (use Search or filters if needed)\n3. Click Delete (trash icon) on that row, or open the row and choose Delete\n4. Confirm if the program asks\n\nYou need permission to delete. If you don't see Delete, ask your administrator.",
            category: 'contacts_module'
        },
        {
            keywords: ['contact us', 'company phone', 'your email', 'call you', 'whatsapp number', 'support phone', 'reach ratib'],
            answer: "Contact us:\n• Phone: +966 59 986 3868\n• WhatsApp: Chat via the green button or 'Live via WhatsApp' in the header\n• Email: ratibsrar@gmail.com\n\nYou can also use the registration form to request a callback.",
            category: 'contact'
        },
        {
            keywords: ['recruitment', 'recruit', 'agency program', 'ratib program', 'your program', 'about ratib'],
            answer: "Ratib is a recruitment program for agencies in worker-sending countries. It helps you manage:\n• Candidates & documents\n• Your branded agency portal\n• E-invoice system\n• Contracts & compliance\n\nRegister above to get started or watch the 'How it works' video for an overview.",
            category: 'program'
        },
        {
            keywords: [
                'partner agency', 'partner agencies', 'partner-agencies', 'workers sent', 'deployment status',
                'overseas partner', 'sent workers', 'partner office', 'partner recruitment'
            ],
            answer: "**🌍 Partner Agencies** — manage overseas partner offices and every worker sent to them.\n\n1. Open **Partner Agencies** from the left menu (same permission as Workers).\n2. In the **Workers Sent** column, click **View** on an agency row.\n3. A table opens: passport, country, agency, **colored status** dropdown, contract & timeline, job, salary, **Profile**, **Delete**, and **Export CSV**.\n4. Change deployment status when it updates in real life; the system saves on selection.\n\nDeployments are tracked **per agency** from this screen.",
            category: 'partner_agencies'
        }
    ];

    function getKnowledgeBase() {
        return knowledgeBaseEN;
    }

    /**
     * Quick topics — hc = same tutorial buckets as Help & Learning Center (HELP_CENTER_BUILTIN category ids).
     * cases: category 9 contains mixed modules; filter to titles that mention Cases.
     * help_center: Getting Started, Troubleshooting, Best Practices, plus Help Center guides in cat 9.
     * visa: Contracts & recruitment + Worker Management (visas live there in the program).
     */
    const QUICK_TOPICS = [
        { id: 'workers', label: 'Workers', hc: { categoryIds: ['6'] } },
        { id: 'agents', label: 'Agents & Subagents', hc: { categoryIds: ['5'] } },
        { id: 'partner_agencies', label: '🌍 Partner Agencies', hc: { categoryIds: ['13'] } },
        {
            id: 'cases',
            label: 'Cases',
            hc: { categoryIds: ['9'], filter: function(t) { return /cases/i.test(t.title || ''); } }
        },
        { id: 'accounting', label: 'Accounting', hc: { categoryIds: ['7'] } },
        { id: 'reports', label: 'Reports', hc: { categoryIds: ['8'] } },
        { id: 'visa', label: 'Visa', hc: { categoryIds: ['4', '6'] } },
        {
            id: 'help_center',
            label: 'Help Center',
            hc: {
                segments: [
                    { categoryIds: ['1'] },
                    { categoryIds: ['9'], filter: function(t) { return /help center/i.test(t.title || ''); } },
                    { categoryIds: ['10'] },
                    { categoryIds: ['11'] }
                ]
            }
        },
        { id: 'live_support', label: 'Talk to support', escalate: true }
    ];

    /** Map quick-topic chip id → knowledgeBaseEN category for full in-chat answers. */
    const QUICK_TOPIC_KB_CATEGORY = {
        workers: 'workers',
        agents: 'agents',
        partner_agencies: 'partner_agencies',
        accounting: 'accounting',
        help_center: 'help',
        cases: 'cases',
        reports: 'reports',
        visa: 'visa'
    };

    function knowledgeAnswerByTopicId(topicId) {
        var cat = QUICK_TOPIC_KB_CATEGORY[topicId];
        if (!cat) return null;
        var kb = getKnowledgeBase();
        for (var i = 0; i < kb.length; i++) {
            if (kb[i].category === cat) return kb[i].answer;
        }
        return null;
    }

    /** Typed message equals a chip label (e.g. "Workers") — treat like a chip tap. */
    function matchQuickTopicFromText(raw) {
        var t = String(raw || '').trim().toLowerCase();
        if (!t) return null;
        if (t === 'partner agencies' || t === 'partner-agencies' || t === 'partner_agencies' || (t.indexOf('partner') !== -1 && t.indexOf('agenc') !== -1)) {
            for (var p = 0; p < QUICK_TOPICS.length; p++) {
                if (QUICK_TOPICS[p].id === 'partner_agencies') return QUICK_TOPICS[p];
            }
        }
        for (var i = 0; i < QUICK_TOPICS.length; i++) {
            var q = QUICK_TOPICS[i];
            if (q.escalate) continue;
            if (String(q.label).trim().toLowerCase() === t) return q;
            if (String(q.id).replace(/_/g, ' ') === t) return q;
        }
        return null;
    }

    function rowClassForSender(sender) {
        return sender === 'support'
            ? 'chat-widget-message chat-widget-msg-assistant'
            : 'chat-widget-message chat-widget-msg-user';
    }

    function collectHelpCenterTutorialsForTopic(topic) {
        if (!topic || !topic.hc) return [];
        var src = window.HELP_CENTER_BUILTIN;
        if (!src || typeof src !== 'object') return [];
        var seen = {};
        var out = [];

        function addCategory(catId, filterFn) {
            var arr = src[catId];
            if (!Array.isArray(arr)) return;
            for (var i = 0; i < arr.length; i++) {
                var t = arr[i];
                if (!t || !t.id || seen[t.id]) continue;
                if (filterFn && !filterFn(t)) continue;
                seen[t.id] = true;
                out.push({
                    id: t.id,
                    title: String(t.title || 'Guide').trim(),
                    categoryId: catId
                });
            }
        }

        var hc = topic.hc;
        if (hc.segments) {
            for (var s = 0; s < hc.segments.length; s++) {
                var seg = hc.segments[s];
                var ids = seg.categoryIds || [];
                var f = seg.filter || null;
                for (var j = 0; j < ids.length; j++) {
                    addCategory(String(ids[j]), f);
                }
            }
        } else if (hc.categoryIds) {
            var f2 = hc.filter || null;
            for (var k = 0; k < hc.categoryIds.length; k++) {
                addCategory(String(hc.categoryIds[k]), f2);
            }
        }
        return out;
    }

    function findBuiltinTutorialGlobal(tutorialId) {
        var id = String(tutorialId || '').trim();
        if (!id || !window.HELP_CENTER_BUILTIN) return null;
        var H = window.HELP_CENTER_BUILTIN;
        for (var cid in H) {
            if (!Object.prototype.hasOwnProperty.call(H, cid)) continue;
            var arr = H[cid];
            if (!Array.isArray(arr)) continue;
            for (var i = 0; i < arr.length; i++) {
                if (arr[i] && arr[i].id === id) {
                    return { tutorial: arr[i], categoryId: cid };
                }
            }
        }
        return null;
    }

    let chatQuickPicksEl = null;

    function ensureQuickPicksMount() {
        if (!chatMessages) return null;
        var existing = document.getElementById('chatWidgetQuickPicks');
        if (existing) {
            chatQuickPicksEl = existing;
            return existing;
        }
        var el = document.createElement('div');
        el.id = 'chatWidgetQuickPicks';
        el.className = 'chat-widget-quick-picks';
        el.setAttribute('role', 'group');
        el.setAttribute('aria-label', 'Quick topics');
        chatMessages.appendChild(el);
        chatQuickPicksEl = el;
        return el;
    }

    /** Keep chips directly under the first assistant bubble (welcome), not above the composer. */
    function positionQuickPicksInThread() {
        if (!chatMessages || !chatQuickPicksEl) return;
        var anchor = chatMessages.querySelector('.chat-widget-message.chat-widget-msg-assistant');
        var picks = chatQuickPicksEl;
        if (anchor) {
            if (picks.previousElementSibling === anchor) return;
            chatMessages.insertBefore(picks, anchor.nextSibling);
        } else if (chatMessages.firstChild !== picks) {
            chatMessages.insertBefore(picks, chatMessages.firstChild);
        }
    }

    function updateQuickPicksVisibility() {
        if (!chatQuickPicksEl) return;
        // Keep topic chips visible during live support so the bar is never empty (they send the label to the agent).
        chatQuickPicksEl.hidden = false;
        chatQuickPicksEl.setAttribute('aria-hidden', 'false');
        chatQuickPicksEl.classList.toggle('chat-widget-quick-picks--live', !!(chatWidget.escalated && chatWidget.chatId));
    }

    function renderQuickPicks() {
        var el = ensureQuickPicksMount();
        if (!el) return;
        el.innerHTML = '';
        var label = document.createElement('div');
        label.className = 'chat-widget-quick-picks-label';
        label.textContent = 'Quick topics';
        el.appendChild(label);
        var row = document.createElement('div');
        row.className = 'chat-widget-quick-picks-row';
        var rowPrimary = null;
        QUICK_TOPICS.forEach(function(topic) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'chat-widget-quick-pick';
            btn.textContent = topic.label;
            btn.setAttribute('data-topic-id', topic.id);
            if (topic.escalate) {
                btn.classList.add('chat-widget-quick-pick--primary', 'chat-widget-quick-pick--full-width', 'chat-widget-quick-pick--shimmer-primary');
                if (!rowPrimary) {
                    rowPrimary = document.createElement('div');
                    rowPrimary.className = 'chat-widget-quick-picks-row chat-widget-quick-picks-row--primary-only';
                }
                rowPrimary.appendChild(btn);
            } else {
                btn.classList.add('chat-widget-quick-pick--shimmer', 'chat-widget-quick-pick--t-' + topic.id);
                row.appendChild(btn);
            }
            btn.addEventListener('click', function() {
                selectQuickTopic(topic);
            });
        });
        el.appendChild(row);
        if (rowPrimary) el.appendChild(rowPrimary);
        updateQuickPicksVisibility();
        positionQuickPicksInThread();
    }

    /**
     * Short chip replies for Workers, Agents, Cases, Accounting, Reports, Visa, Help Center + one Help Center deep link.
     */
    function buildQuickTopicShortMessage(topic) {
        if (!topic || topic.escalate) return '';
        var id = topic.id;
        var base = getApiBase();
        var p = function(f) { return base + '/pages/' + f; };
        var guides = collectHelpCenterTutorialsForTopic(topic);
        var hcPath = 'pages/help-center.php';
        var hcDeep = absoluteChatPageHref(hcPath);
        if (guides.length > 0 && guides[0].id) {
            hcDeep = absoluteChatPageHref(hcPath + '?tutorial=' + encodeURIComponent(String(guides[0].id)));
        }
        var moreHc = guides.length > 1
            ? '\n[More ' + topic.label + ' guides →](' + absoluteChatPageHref(hcPath) + ')'
            : '';

        var snippets = {
            workers:
                '**Workers** — register and manage labour, IDs, visas, documents.\n' +
                '• Menu → **Workers** → search/filter the table\n' +
                '• **Add Worker** for new people; open a row to edit or update status\n' +
                '• Assign to an **agent** if your office uses that\n\n' +
                '[Open Workers →](' + p('Worker.php') + ')',
            agents:
                '**Agents & Subagents** — your business partners.\n' +
                '• **Agent** for main partners · **SubAgent** for sub-partners (choose parent agent)\n' +
                '• Use the table: add, edit, search, filters\n\n' +
                '[Agent →](' + p('agent.php') + ') · [SubAgent →](' + p('subagent.php') + ')',
            cases:
                '**Cases** — track each file through your workflow.\n' +
                '• **Cases** → add or open a case → status, notes, links to workers/agents\n' +
                '• Search and filters on the list\n\n' +
                '[Open Cases →](' + base + '/pages/cases/cases-table.php' + ')',
            accounting:
                '**Accounting** — money in and out.\n' +
                '• Invoices, bills, journals, chart of accounts (your menu may group these)\n' +
                '• Open a sub-area → **Add** / **New** → save entries\n\n' +
                '[Open Accounting →](' + p('accounting.php') + ')',
            reports:
                '**Reports** — lists and summaries.\n' +
                '• **Reports** → pick type → dates & filters → run\n' +
                '• **Export** PDF/Excel when you need a file\n\n' +
                '[Open Reports →](' + p('reports.php') + ')',
            visa:
                '**Visa** — worker visa details.\n' +
                '• **Visa** or worker profile (depends on your layout)\n' +
                '• Update type, sponsor, dates, status → **Save**\n\n' +
                '[Open Visa →](' + p('visa.php') + ')',
            partner_agencies:
                '**🌍 Partner Agencies** — overseas offices and workers sent.\n' +
                '• Menu → **Partner Agencies** → **View** on a row\n' +
                '• Table: status (colors), contract timeline, job, salary, Profile, Delete, Export CSV\n\n' +
                '[Open Partner Agencies →](' + p('partner-agencies.php') + ')',
            help_center:
                '**Help Center** — long tutorials for Workers, Agents, Cases, Accounting, Reports, Visa, and more.\n' +
                '• Left menu → **Help & Learning Center**, or use the link below'
        };

        var body = snippets[id];
        if (!body) return '';
        var footer = '\n\n**Full walkthrough:** [Help Center: ' + topic.label + ' →](' + hcDeep + ')' + moreHc;
        return body + footer;
    }

    /** Quick topic taps: always short in-chat text; no long HC paste. */
    function deliverQuickTopicHelpResponse(topic) {
        var msg = buildQuickTopicShortMessage(topic);
        if (msg) {
            addMessage('support', msg);
            updateQuickPicksVisibility();
            return;
        }
        addMessage('support', getAIResponse(topic.label || ''));
        updateQuickPicksVisibility();
    }

    function selectQuickTopic(topic) {
        if (!topic || chatWidget.sending || chatWidget.isTyping) return;

        if (topic.escalate) {
            if (chatWidget.escalated && chatWidget.chatId && chatWidget.chatToken) {
                chatWidget.sending = true;
                addMessage('user', topic.label);
                setComposerLocked(true);
                showTypingIndicator();
                sendEscalatedMessage(topic.label, function(ok, errDetail) {
                    hideTypingIndicator();
                    setComposerLocked(false);
                    chatWidget.sending = false;
                    if (ok) {
                        addMessage('support', LIVE_SUPPORT_RENOTIFY_MESSAGE);
                    } else {
                        var line = errDetail ? String(errDetail) : 'try again';
                        addMessage('support', 'We couldn\'t notify our team with that tap: ' + line + '\n\nType below or say `I need to talk to support` to reconnect.');
                    }
                    updateClearChatButton();
                    updateQuickPicksVisibility();
                });
                return;
            }
            chatWidget.sending = true;
            addMessage('user', topic.label);
            showTypingIndicator();
            escalateToSupport(function(data) {
                hideTypingIndicator();
                chatWidget.sending = false;
                if (data.success) {
                    addMessage('support', LIVE_SUPPORT_ACK_MESSAGE);
                    startEscalationPolling();
                } else {
                    addMessage('support', (data.message || 'Could not connect. Please try WhatsApp or email.'));
                }
                updateClearChatButton();
                updateQuickPicksVisibility();
            });
            return;
        }

        chatWidget.sending = true;
        addMessage('user', topic.label);
        showTypingIndicator();
        setTimeout(function() {
            hideTypingIndicator();
            chatWidget.sending = false;
            deliverQuickTopicHelpResponse(topic);
        }, 320);
    }

    function onHcTutorialButtonClick(tutorialId) {
        if (!tutorialId || chatWidget.sending || chatWidget.isTyping) return;
        var found = findBuiltinTutorialGlobal(tutorialId);
        var title = (found && found.tutorial && found.tutorial.title) ? String(found.tutorial.title).trim() : '';

        if (chatWidget.escalated && chatWidget.chatId && chatWidget.chatToken) {
            var toAgent = title ? ('Question about: ' + title) : ('Help topic: ' + tutorialId);
            chatWidget.sending = true;
            addMessage('user', title || 'Help topic');
            if (found && found.tutorial) {
                addMessage('support', formatHelpCenterAnswer(found.tutorial, title || 'Guide'));
            }
            setComposerLocked(true);
            sendEscalatedMessage(toAgent, function(ok, errDetail) {
                setComposerLocked(false);
                chatWidget.sending = false;
                if (!ok) {
                    var line = errDetail ? String(errDetail) : 'Check your connection and try again.';
                    addMessage('support', 'Your message could not be sent: ' + line);
                }
            });
            return;
        }

        if (!found || !found.tutorial) {
            addMessage('user', 'Open in Help Center');
            var fallback = '[Open Help & Learning Center](' + getApiBase() + '/pages/help-center.php?tutorial=' +
                encodeURIComponent(String(tutorialId)) + ')';
            addMessage('support', 'You can open the full guide whenever you like — optional:\n' + fallback);
            return;
        }
        title = title || 'Guide';
        chatWidget.sending = true;
        addMessage('user', title);
        showTypingIndicator();
        setTimeout(function() {
            hideTypingIndicator();
            chatWidget.sending = false;
            var body = formatHelpCenterAnswer(found.tutorial, title);
            addMessage('support', body);
        }, 380);
    }

    /**
     * High-confidence routing before fuzzy intent + Help Center (fixes e.g. subagents vs wrong article).
     */
    function resolveGuidedIntent(raw) {
        var text = normalizeHelpQuery((raw || '').toLowerCase().trim());
        if (text.length < 2) return null;

        if (/\bworkers?\b/.test(text) && /\b(delete|remove|deactivate|terminate|erase|cancel|unlink|drop|archive)\b/.test(text)) {
            return 'workers_delete';
        }

        if (/\b(contact|contacts)\b/.test(text) && /\b(delete|remove|erase)\b/.test(text)) {
            return 'contacts_delete';
        }

        if (/\b(agent|agents|subagent|subagents|sub-agent)\b/.test(text) && /\b(delete|remove|erase)\b/.test(text)) {
            return 'agents_delete';
        }

        if (/\bsub\s*-?agents?\b/.test(text) || text.indexOf('subagent') !== -1 || text.indexOf('sub-agent') !== -1) {
            return 'agents';
        }

        if (/\bpartner\s+agenc(y|ies)\b/.test(text) || /\bpartner[\s-]*agenc(y|ies)\b/.test(text) || text.indexOf('workers sent') !== -1) {
            return 'partner_agencies';
        }

        var tokens = text.split(/\s+/).filter(function(w) { return w.length > 0; });
        if (tokens.length === 1) {
            var one = tokens[0].replace(/[^\w]/g, '');
            var singleMap = {
                worker: 'workers', workers: 'workers', employee: 'workers',
                agent: 'agents', agents: 'agents',
                case: 'cases', cases: 'cases',
                accounting: 'accounting', invoice: 'accounting', bill: 'accounting',
                report: 'reports', reports: 'reports',
                visa: 'visa', hr: 'hr', dashboard: 'dashboard',
                login: 'login', password: 'login',
                help: 'help_center', tutorial: 'help_center',
                contact: 'contacts', contacts: 'contacts',
                deployment: 'partner_agencies', deployments: 'partner_agencies',
                partnerships: 'partner_agencies', overseas: 'partner_agencies'
            };
            if (singleMap[one]) return singleMap[one];
        }

        if (text.indexOf('individual report') !== -1) return 'individual_reports';
        if (text.indexOf('help center') !== -1 || text.indexOf('learning center') !== -1) return 'help_center';

        return null;
    }

    // DOM Elements
    let chatButton, chatContainer, chatClose, chatClear, chatMessages, chatInput, chatSend;

    // Initialize Chat Widget
    function initChatWidget() {
        // Get DOM elements
        chatButton = document.getElementById('chatWidgetButton');
        chatContainer = document.getElementById('chatWidgetContainer');
        chatClose = document.getElementById('chatWidgetClose');
        chatClear = document.getElementById('chatWidgetClear');
        chatMessages = document.getElementById('chatWidgetMessages');
        chatInput = document.getElementById('chatWidgetInput');
        chatSend = document.getElementById('chatWidgetSend');

        if (!chatButton || !chatContainer) {
            return;
        }

        if (chatMessages) {
            chatMessages.setAttribute('role', 'log');
            chatMessages.setAttribute('aria-live', 'polite');
            chatMessages.setAttribute('aria-relevant', 'additions');
            if (!chatMessages.dataset.hcPickBound) {
                chatMessages.dataset.hcPickBound = '1';
                chatMessages.addEventListener('click', function(ev) {
                    var btn = ev.target && ev.target.closest ? ev.target.closest('.chat-widget-hc-pick') : null;
                    if (!btn || !chatMessages.contains(btn)) return;
                    var tid = btn.getAttribute('data-hc-tutorial');
                    if (tid) {
                        ev.preventDefault();
                        onHcTutorialButtonClick(tid);
                    }
                });
            }
        }

        // Event Listeners
        chatButton.addEventListener('click', toggleChat);
        if (chatClose) chatClose.addEventListener('click', closeChat);
        if (chatClear) {
            chatClear.addEventListener('click', function() {
                clearAssistantConversation();
            });
        }
        if (chatSend) chatSend.addEventListener('click', sendMessage);
        if (chatInput) {
            chatInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
            chatInput.addEventListener('input', function() {
                // Auto-resize textarea
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });
        }

        // Update placeholder and header based on language
        updateChatPlaceholder();
        updateChatHeader();

        // One-time cleanup: remove all previous chat history
        try {
            if (!localStorage.getItem(CHAT_PURGE_KEY)) {
                localStorage.removeItem(CHAT_MESSAGES_KEY);
                sessionStorage.removeItem(ESCALATED_KEY);
                try { localStorage.removeItem(SUPPORT_BOARD_TOKEN_KEY); } catch (e2) {}
                localStorage.setItem(CHAT_PURGE_KEY, '1');
            }
        } catch (e) {}

        // Restore live support session before loading stored transcript
        try {
            const esc = sessionStorage.getItem(ESCALATED_KEY);
            if (esc) {
                const d = JSON.parse(esc);
                var cid = parseInt(d && d.chatId, 10);
                var tok = (d && d.chatToken) ? String(d.chatToken).trim() : '';
                if (cid >= 1 && tok.length >= 32) {
                    chatWidget.escalated = true;
                    chatWidget.chatId = cid;
                    chatWidget.chatToken = tok;
                }
            }
        } catch (e) {}

        loadSavedMessages();

        if (chatWidget.escalated && chatWidget.chatId && chatWidget.chatToken) {
            startEscalationPolling();
        }

        // Check if Help Center content is available (with delay to ensure scripts are loaded)
        setTimeout(() => {
            checkHelpCenterConnection();
        }, 100);

        if (chatWidget.messages.length === 0) {
            if (chatWidget.escalated) {
                addMessage('support', 'Live chat — type below. Topics still open short guides; picking one also pings the agent.');
            } else {
                addMessage('support', getWelcomeMessageText());
            }
        }
        renderQuickPicks();
        positionQuickPicksInThread();
        updateClearChatButton();

        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible' && chatWidget.escalated && chatWidget.chatId && chatWidget.chatToken) {
                performEscalationPoll();
            }
        });

    }

    function setComposerLocked(locked) {
        if (chatInput) {
            chatInput.disabled = !!locked;
            chatInput.setAttribute('aria-busy', locked ? 'true' : 'false');
        }
        if (chatSend) {
            chatSend.disabled = !!locked;
        }
    }

    // Check Help Center Connection (warm-up search so first user query is not cold)
    function checkHelpCenterConnection() {
        if (window.HELP_CENTER_BUILTIN && typeof window.HELP_CENTER_BUILTIN === 'object') {
            searchHelpCenterContent('how to add worker');
            return true;
        }
        return false;
    }
    
    // Expose test function globally for debugging
    window.testChatWidgetConnection = function() {
        checkHelpCenterConnection();
    };

    // Toggle Chat Widget
    function toggleChat() {
        chatWidget.isOpen = !chatWidget.isOpen;
        if (chatWidget.isOpen) {
            hardResetAssistantThreadToWelcome();
            chatContainer.classList.add('active');
            if (chatInput && !chatInput.disabled) {
                chatInput.focus();
            }
            chatWidget.unreadCount = 0;
            updateUnreadBadge();
        } else {
            chatContainer.classList.remove('active');
            hardResetAssistantThreadToWelcome();
        }
    }

    // Close Chat Widget
    function closeChat() {
        chatWidget.isOpen = false;
        chatContainer.classList.remove('active');
        hardResetAssistantThreadToWelcome();
    }

    // Get API base URL (must match server base path, e.g. '' or '/ratib')
    function getApiBase() {
        if (typeof window.RATIB_BASE_URL !== 'undefined' && window.RATIB_BASE_URL) {
            return (window.RATIB_BASE_URL + '').replace(/\/+$/, '');
        }
        const path = window.location.pathname || '';
        const basePath = path.replace(/\/pages\/[^/]*$/, ''); // path before /pages/xxx
        return window.location.origin + (basePath || '');
    }

    /** In-app links open same tab; external URLs open new tab */
    function getChatLinkTarget(url) {
        try {
            const u = new URL(url, window.location.href);
            if (u.origin === window.location.origin) return '_self';
        } catch (e) { /* ignore */ }
        return '_blank';
    }

    /** Absolute https URL for markdown links in bubbles (getApiBase may be path-only). */
    function absoluteChatPageHref(pathAfterBase) {
        var tail = String(pathAfterBase || '').replace(/^\/+/, '');
        var b = getApiBase();
        if (/^https?:\/\//i.test(b)) {
            return b.replace(/\/+$/, '') + '/' + tail;
        }
        try {
            var prefix = b ? (b.charAt(0) === '/' ? b : '/' + b) : '';
            return new URL(prefix + '/' + tail, window.location.origin).href;
        } catch (e2) {
            return (b || '') + '/' + tail;
        }
    }

    /** One friendly [Open …](url) line for knowledge-base categories */
    function appendKbQuickLinks(category) {
        const b = getApiBase();
        const line = function(label, path) {
            return '\n[' + label + '](' + b + '/pages/' + path + ')';
        };
        switch (category) {
            case 'account':
                return line('Open Login', 'login.php');
            case 'permissions':
                return line('Open System Settings', 'system-settings.php');
            case 'navigation':
                return line('Open Dashboard', 'dashboard.php');
            case 'agents':
                return '\n[Open Agent](' + b + '/pages/agent.php) · [Open SubAgent](' + b + '/pages/subagent.php)';
            case 'workers':
            case 'workers_delete':
                return line('Open Workers', 'Worker.php');
            case 'accounting':
                return line('Open Accounting', 'accounting.php');
            case 'help':
                return '\nOptional longer tutorials: [Help Center →](' + absoluteChatPageHref('pages/help-center.php') + ')';
            case 'greeting':
                return '\nOptional: [Help Center →](' + absoluteChatPageHref('pages/help-center.php') + ')';
            case 'registration':
            case 'pricing':
            case 'hosting':
            case 'payment':
            case 'program':
                return line('Open Help & Learning Center', 'help-center.php');
            case 'contact':
                return '\nOptional: [Help Center →](' + absoluteChatPageHref('pages/help-center.php') + ')';
            case 'contacts_module':
                return '\n[Open Contact page →](' + absoluteChatPageHref('pages/contact.php') + ')\nOptional guides: [Help Center →](' + absoluteChatPageHref('pages/help-center.php') + ')';
            case 'cases':
                return line('Open Cases', 'cases/cases-table.php');
            case 'visa':
                return line('Open Visa', 'visa.php');
            case 'reports':
                return line('Open Reports', 'reports.php');
            case 'partner_agencies':
                return line('Open Partner Agencies', 'partner-agencies.php');
            default:
                return '';
        }
    }

    // Check if user message indicates they want live support
    function isEscalationPhrase(message) {
        const lower = (message || '').toLowerCase().trim();
        const phrases = [
            'i need to talk to support',
            'i need to talk to you', 'talk to you', 'speak to you',
            'i need support', 'need support team', 'support team',
            'speak with someone', 'talk to someone', 'talk to a person',
            'connect me to support', 'connect to support',
            'live support', 'human support', 'real person', 'real agent',
            'i want to talk', 'want to speak', 'speak with support',
            'talk to support', 'contact support', 'speak to support',
            'i need help from', 'help from support', 'human agent',
            'operator', 'representative', 'customer service'
        ];
        return phrases.some(p => lower.includes(p));
    }

    // Escalate chat to live support
    function escalateToSupport(callback) {
        const apiBase = getApiBase();
        const conversation = chatWidget.messages.map(m => ({ sender: m.sender, text: m.text }));
        const sourcePage = (window.location.pathname || '').replace(/^\/+/, '') || 'unknown';
        var resumeTok = '';
        try {
            resumeTok = (localStorage.getItem(SUPPORT_BOARD_TOKEN_KEY) || '').trim();
        } catch (e) {}

        fetch(apiBase + '/api/support-chat-escalate.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                conversation: conversation,
                source_page: sourcePage,
                resume_chat_token: resumeTok.length >= 32 ? resumeTok : undefined
            })
        }).then(function(r) { return r.text(); }).then(function(txt) {
            var data = null;
            try {
                data = txt ? JSON.parse(txt) : null;
            } catch (e) {
                data = null;
            }
            if (data && data.success && data.chat_id && data.chat_token) {
                chatWidget.escalated = true;
                chatWidget.chatId = parseInt(data.chat_id, 10);
                chatWidget.chatToken = String(data.chat_token).trim();
                try {
                    sessionStorage.setItem(ESCALATED_KEY, JSON.stringify({ chatId: parseInt(data.chat_id, 10), chatToken: String(data.chat_token).trim() }));
                    localStorage.setItem(SUPPORT_BOARD_TOKEN_KEY, String(data.chat_token).trim());
                } catch (e) {}
                if (callback) callback(data);
            } else if (callback) {
                callback({ success: false, message: (data && data.message) ? data.message : 'Could not connect to support' });
            }
        }).catch(function() {
            if (callback) callback({ success: false, message: 'Connection failed. Please try again.' });
        });
    }

    // Send message to escalated chat (backend)
    function sendEscalatedMessage(messageText, onDone) {
        const apiBase = getApiBase();
        var cid = parseInt(chatWidget.chatId, 10);
        var tok = chatWidget.chatToken != null ? String(chatWidget.chatToken).trim() : '';
        fetch(apiBase + '/api/support-chat-send.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chat_id: cid, chat_token: tok, message: messageText })
        }).then(function(r) { return r.text(); }).then(function(txt) {
            var data = null;
            try {
                data = txt ? JSON.parse(txt) : null;
            } catch (e) {
                data = null;
            }
            if (data && data.success) {
                if (onDone) onDone(true, null);
            } else {
                var msg = (data && data.message) ? String(data.message) : '';
                if (!msg && txt && txt.indexOf('{') === -1) {
                    msg = 'Server did not return JSON (check PHP errors / API path).';
                } else if (!msg) {
                    msg = 'Could not send (invalid server response).';
                }
                if (msg.toLowerCase().indexOf('closed') !== -1 || msg.toLowerCase().indexOf('not found') !== -1) {
                    resetEscalatedChat(true);
                }
                if (onDone) onDone(false, msg);
            }
        }).catch(function() { if (onDone) onDone(false, 'Network error — check connection or try again.'); });
    }

    function performEscalationPoll() {
        if (!chatWidget.escalated || !chatWidget.chatId || !chatWidget.chatToken) return;
        const apiBase = getApiBase();
        const after = chatWidget.pollAfterId > 0 ? '&after_id=' + chatWidget.pollAfterId : '';
        const url = apiBase + '/api/support-chat-poll.php?chat_id=' + chatWidget.chatId + '&chat_token=' + encodeURIComponent(chatWidget.chatToken) + after;
        fetch(url, { credentials: 'same-origin' }).then(function(r) { return r.text(); }).then(function(txt) {
            var data = null;
            try {
                data = txt ? JSON.parse(txt) : null;
            } catch (e) {
                return;
            }
            if (data && data.chat_closed) {
                resetEscalatedChat(true);
                return;
            }
            if (data && data.success && Array.isArray(data.messages) && data.messages.length > 0) {
                data.messages.forEach(function(m) {
                    chatWidget.pollAfterId = Math.max(chatWidget.pollAfterId, m.id || 0);
                    addMessage('support', m.text, m.id);
                });
            }
        }).catch(function() {});
    }

    // Poll for new admin messages
    function startEscalationPolling() {
        if (chatWidget.pollInterval) return;
        chatWidget.pollAfterId = 0;
        chatWidget.messages.forEach(function(m) {
            if (m.id && m.id > chatWidget.pollAfterId) chatWidget.pollAfterId = m.id;
        });
        performEscalationPoll();
        chatWidget.pollInterval = setInterval(performEscalationPoll, 2500);
    }

    function stopEscalationPolling() {
        if (chatWidget.pollInterval) {
            clearInterval(chatWidget.pollInterval);
            chatWidget.pollInterval = null;
        }
    }

    function clearChatBoard() {
        chatWidget.messages = [];
        try { localStorage.removeItem(CHAT_MESSAGES_KEY); } catch (e) {}
        if (chatMessages) {
            chatMessages.innerHTML = '';
        }
        chatQuickPicksEl = null;
    }

    /**
     * Full reset: stop live support, clear all storage, wipe message DOM, welcome + quick picks.
     * Used when opening/closing the panel (FAB/X) so each open is a new board; also trash / internal refresh.
     */
    function hardResetAssistantThreadToWelcome() {
        hideTypingIndicator();
        chatWidget.isTyping = false;
        chatWidget.sending = false;
        chatWidget.escalated = false;
        chatWidget.chatId = null;
        chatWidget.chatToken = null;
        chatWidget.pollAfterId = 0;
        stopEscalationPolling();
        try {
            sessionStorage.removeItem(ESCALATED_KEY);
            localStorage.removeItem(SUPPORT_BOARD_TOKEN_KEY);
            localStorage.removeItem(CHAT_MESSAGES_KEY);
        } catch (e) {}
        chatWidget.messages = [];
        chatQuickPicksEl = null;
        if (chatMessages) {
            chatMessages.innerHTML = '';
        }
        addMessage('support', getWelcomeMessageText());
        renderQuickPicks();
        positionQuickPicksInThread();
        updateClearChatButton();
    }

    function clearAssistantConversation() {
        hardResetAssistantThreadToWelcome();
    }

    function updateClearChatButton() {
        if (chatClear) {
            chatClear.hidden = false;
            chatClear.setAttribute('aria-hidden', 'false');
        }
        updateQuickPicksVisibility();
    }

    function resetEscalatedChat(closedBySupport) {
        chatWidget.escalated = false;
        chatWidget.chatId = null;
        chatWidget.chatToken = null;
        chatWidget.pollAfterId = 0;
        stopEscalationPolling();
        try {
            sessionStorage.removeItem(ESCALATED_KEY);
            if (closedBySupport) {
                localStorage.removeItem(SUPPORT_BOARD_TOKEN_KEY);
            }
        } catch (e) {}
        clearChatBoard();
        if (closedBySupport && chatMessages) {
            const hc = getApiBase() + '/pages/help-center.php';
            addMessage('support', 'Chat closed by support. Ask again anytime.\n' +
                '[Help Center →](' + hc + ') · Live: `Talk to support`');
        }
        renderQuickPicks();
        positionQuickPicksInThread();
        updateClearChatButton();
    }

    // Send Message
    function sendMessage() {
        const messageText = chatInput?.value.trim();
        if (!messageText || chatWidget.isTyping || chatWidget.sending) return;

        chatWidget.sending = true;
        addMessage('user', messageText);
        chatInput.value = '';
        chatInput.style.height = 'auto';

        // Escalated mode: send to backend, poll for reply
        if (chatWidget.escalated && chatWidget.chatId && chatWidget.chatToken) {
            var topicFromTyping = matchQuickTopicFromText(messageText);
            if (topicFromTyping) {
                chatWidget.sending = false;
                showTypingIndicator();
                setTimeout(function() {
                    hideTypingIndicator();
                    deliverQuickTopicHelpResponse(topicFromTyping);
                }, 300);
                return;
            }
            setComposerLocked(true);
            showTypingIndicator();
            sendEscalatedMessage(messageText, function(ok, errDetail) {
                hideTypingIndicator();
                setComposerLocked(false);
                chatWidget.sending = false;
                if (!ok) {
                    var line = errDetail ? String(errDetail) : 'Check your connection and try again.';
                    addMessage('support', 'Could not send: ' + line + '\n\nIf live chat expired, type: `I need to talk to support` to reconnect.');
                }
            });
            return;
        }

        // Check if user wants live support
        if (isEscalationPhrase(messageText)) {
            showTypingIndicator();
            escalateToSupport(function(data) {
                hideTypingIndicator();
                chatWidget.sending = false;
                if (data.success) {
                    addMessage('support', LIVE_SUPPORT_ACK_MESSAGE);
                    startEscalationPolling();
                    updateClearChatButton();
                } else {
                    addMessage('support', (data.message || 'Could not connect. Please try WhatsApp or email.'));
                }
            });
            return;
        }

        // Auto-answer mode
        showTypingIndicator();
        const delay = 450 + Math.random() * 500;
        setTimeout(function() {
            hideTypingIndicator();
            chatWidget.sending = false;
            const response = getAIResponse(messageText);
            addMessage('support', response);
        }, delay);
    }

    function escapeHtmlChat(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function escapeAttrChat(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;');
    }

    /** Support bubbles: [Label](https://...) links, `code`, newlines, bare http(s) URLs */
    function formatSupportBubbleHtml(rawText) {
        const s = String(rawText);
        const linkRe = /\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g;
        const chunks = [];
        let last = 0;
        let m;
        while ((m = linkRe.exec(s)) !== null) {
            if (m.index > last) {
                chunks.push({ type: 'text', value: s.slice(last, m.index) });
            }
            if (/^https?:\/\//i.test(m[2])) {
                chunks.push({ type: 'link', label: m[1], url: m[2] });
            } else {
                chunks.push({ type: 'text', value: m[0] });
            }
            last = linkRe.lastIndex;
        }
        if (last < s.length) {
            chunks.push({ type: 'text', value: s.slice(last) });
        }

        function formatTextSegment(t) {
            const parts = t.split(/(https?:\/\/[^\s]+)/gi);
            return parts.map(function(part, i) {
                if (i % 2 === 1 && /^https?:\/\//i.test(part)) {
                    var linkTgt = getChatLinkTarget(part);
                    return '<a href="' + escapeAttrChat(part) + '" target="' + linkTgt + '" rel="noopener noreferrer">' +
                        escapeHtmlChat(part) + '</a>';
                }
                let x = escapeHtmlChat(part);
                x = x.replace(/`([^`]+)`/g, '<code>$1</code>');
                x = x.replace(/\n/g, '<br>');
                return x;
            }).join('');
        }

        return chunks.map(function(ch) {
            if (ch.type === 'link') {
                var tgt = getChatLinkTarget(ch.url);
                return '<a href="' + escapeAttrChat(ch.url) + '" target="' + tgt + '" rel="noopener noreferrer">' +
                    escapeHtmlChat(ch.label) + '</a>';
            }
            return formatTextSegment(ch.value);
        }).join('');
    }

    // Add Message to Chat
    function addMessage(sender, text, msgId, options) {
        if (!chatMessages) return;
        options = options || {};
        // Avoid duplicate from polling
        if (msgId && chatWidget.messages.some(m => m.id === msgId)) return;
        const message = {
            sender: sender,
            text: text,
            timestamp: new Date(),
            id: msgId || null,
            supportHtml: (sender === 'support' && options.html) ? options.html : null
        };

        chatWidget.messages.push(message);
        saveMessages();

        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.className = rowClassForSender(sender);

        const bubble = document.createElement('div');
        bubble.className = 'chat-widget-message-bubble';
        const rawText = (text != null && text !== undefined) ? String(text) : '';
        if (sender === 'support') {
            bubble.innerHTML = message.supportHtml ? message.supportHtml : formatSupportBubbleHtml(rawText);
        } else {
            bubble.textContent = rawText;
        }

        const time = document.createElement('div');
        time.className = 'chat-widget-message-time';
        time.textContent = formatTime(message.timestamp);

        messageDiv.appendChild(bubble);
        messageDiv.appendChild(time);
        chatMessages.appendChild(messageDiv);
        positionQuickPicksInThread();

        // Scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // Update unread count if chat is closed
        if (!chatWidget.isOpen && sender === 'support') {
            chatWidget.unreadCount++;
            updateUnreadBadge();
        }
        updateClearChatButton();
    }

    // Show Typing Indicator
    function showTypingIndicator() {
        if (!chatMessages) return;
        chatWidget.isTyping = true;
        const typingDiv = document.createElement('div');
        typingDiv.className = 'chat-widget-typing';
        typingDiv.id = 'typingIndicator';
        typingDiv.innerHTML = '<span></span><span></span><span></span>';
        chatMessages.appendChild(typingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Hide Typing Indicator
    function hideTypingIndicator() {
        chatWidget.isTyping = false;
        const typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }

    function detectStructuredTaskIntent(lowerMessage) {
        const text = normalizeHelpQuery(String(lowerMessage || '').toLowerCase().trim());
        if (/\bworkers?\b/.test(text) && /\b(delete|remove|deactivate|terminate|erase)\b/.test(text)) {
            return 'workers_delete';
        }
        if (/\b(contact|contacts)\b/.test(text) && /\b(delete|remove|erase)\b/.test(text)) {
            return 'contacts_delete';
        }
        if (/\b(subagent|sub-agent|subagents)\b/.test(text) && /\b(delete|remove|erase)\b/.test(text)) {
            return 'agents_delete';
        }
        if (/\b(agent|agents)\b/.test(text) && !/\b(subagent|sub-agent|subagents)\b/.test(text) && /\b(delete|remove|erase)\b/.test(text)) {
            return 'agents_delete';
        }
        return null;
    }

    function detectIntent(message) {
        const text = normalizeHelpQuery(String(message || '').toLowerCase().trim());
        if (text.indexOf('subagent') !== -1 || text.indexOf('sub-agent') !== -1 || /\bsub\s*-?agents?\b/.test(text)) {
            return 'agents';
        }
        const intents = [
            { id: 'help_center', keywords: ['help center', 'learning center', 'help and learning', 'documentation', 'user guide', 'faq', 'tutorials', 'tutorial', 'video tutorial'] },
            { id: 'individual_reports', keywords: ['individual report', 'individual reports'] },
            { id: 'dashboard', keywords: ['dashboard', 'main page', 'home page', 'go home', 'take me home'] },
            { id: 'workers', keywords: ['worker', 'workers', 'employee', 'passport', 'ticket', 'medical', 'police'] },
            { id: 'visa', keywords: ['visa', 'visa type', 'sponsor', 'musaned'] },
            { id: 'agents', keywords: ['agent', 'agents', 'client', 'clients'] },
            { id: 'cases', keywords: ['case', 'cases', 'my cases', 'case file', 'case files'] },
            { id: 'hr', keywords: ['human resources', 'payroll', 'attendance', 'hr dashboard', 'staff records'] },
            { id: 'notifications', keywords: ['notification', 'notifications', 'in-app alert'] },
            { id: 'contacts', keywords: ['contacts page', 'contact list', 'my contacts'] },
            { id: 'communications', keywords: ['communications', 'communication', 'broadcast'] },
            { id: 'accounting', keywords: ['accounting', 'invoice', 'bill', 'transaction', 'journal', 'ledger', 'receivable', 'payable', 'chart of accounts', 'financial report'] },
            { id: 'reports', keywords: ['report', 'reports', 'analytics', 'export', 'pdf', 'excel'] },
            { id: 'settings', keywords: ['settings', 'permission', 'permissions', 'role', 'roles', 'system settings'] },
            { id: 'login', keywords: ['login', 'password', 'forgot password', 'reset password'] }
        ];
        let best = null;
        for (const intent of intents) {
            let score = 0;
            for (const kw of intent.keywords) {
                if (text.includes(kw)) score++;
            }
            if (!best || score > best.score) best = { id: intent.id, score: score };
        }
        return best && best.score > 0 ? best.id : null;
    }

    function intentReply(intentId) {
        const base = getApiBase();
        const pageLink = function(path) { return base + '/pages/' + path; };
        const replies = {
            help_center: "I can explain things right here in chat.\n\nThe Help & Learning Center is optional — use it when you want searchable tutorials and long guides:\n[Help Center →](" + pageLink('help-center.php') + ")",
            dashboard: "The Dashboard is your home screen:\n• Key numbers (agents, workers, cases, etc.)\n• Shortcuts into main modules\n• Recent activity and alerts\n\nOpen it anytime from the top of the left menu:\n[Dashboard →](" + pageLink('dashboard.php') + ")",
            workers_delete: "To remove a worker:\n1. Open Workers\n2. Find the row → open it or use Actions on that line\n3. Use Delete/Remove if shown, or set status to Inactive if your office archives that way\n4. Confirm if asked\n\nNo delete button? Your role may be view-only — ask an admin.\n\n[Open Workers →](" + pageLink('Worker.php') + ")",
            contacts_delete: "To delete a contact:\n1. Open Contact from the left menu\n2. Find the person in the table (use Search or filters if needed)\n3. Click Delete (trash icon) on that row, or open the record and choose Delete\n4. Confirm if the program asks\n\nNo Delete? Your role may be view-only — ask an admin.\n\n[Open Contact →](" + pageLink('contact.php') + ")",
            agents_delete: "To delete an agent or sub-agent:\n1. Open Agent or SubAgent from the left menu\n2. Find the row in the table\n3. Click Delete (trash) on that row or inside the record\n4. Confirm if asked\n\nNo Delete? Your role may not allow it — ask an admin.\n\n[Agent →](" + pageLink('agent.php') + ") · [SubAgent →](" + pageLink('subagent.php') + ")",
            workers: "To add a worker:\n1. Open Workers\n2. Add Worker → fill ID, passport, contact, nationality\n3. Assign to an agent if your office uses that\n4. Save — then edit anytime for documents or status\n\n[Open Workers →](" + pageLink('Worker.php') + ")",
            visa: "For visa info on a worker:\n1. Open Visa (or the worker profile if your layout links it)\n2. Update visa type, sponsor, dates, and status\n3. Save so reports and lists stay accurate\n\n[Open Visa →](" + pageLink('visa.php') + ")",
            agents: "To add an agent:\n1. Open Agent\n2. Add New Agent\n3. Fill details and permissions\n4. Save\n\nSub-agents are created under SubAgent and linked to a parent agent:\n[Agent →](" + pageLink('agent.php') + ") · [SubAgent →](" + pageLink('subagent.php') + ")",
            cases: "Cases track a file through your office workflow:\n1. Open Cases\n2. Add or open a case\n3. Update status, notes, and links to workers/agents\n4. Save\n\n[Open Cases →](" + base + '/pages/cases/cases-table.php' + ")",
            accounting: "Use Accounting for money in and out:\n• Invoices, bills, journals, chart of accounts\n• Banking and basic financial reports\n\nPick the sub-area from the Accounting menu, then Add or New on each screen:\n[Accounting →](" + pageLink('accounting.php') + ")",
            reports: "To pull a report:\n1. Open Reports\n2. Choose type (workers, agents, financial, etc.)\n3. Set dates and filters\n4. Run, then export if you need PDF/Excel\n\n[Open Reports →](" + pageLink('reports.php') + ")",
            settings: "System Settings is where admins manage users, roles, and permissions.\n\nIf you can’t see a menu item, your role may not allow it — ask an admin to adjust access:\n[Settings →](" + pageLink('system-settings.php') + ")",
            login: "If you can’t sign in:\n1. Use Forgot Password on the login page\n2. Enter your email and check the reset link\n3. Choose a new password and try again\n\n[Open Login →](" + pageLink('login.php') + ")",
            hr: "HR covers internal staff (not recruitment workers):\n• Employees, attendance, payroll-related data\n1. Open HR from the menu\n2. Use the tabs or list your version shows\n3. Add or edit records and save\n\n[Open HR →](" + pageLink('hr.php') + ")",
            notifications: "Notifications are system messages and reminders:\n1. Open Notifications\n2. Read or dismiss items\n3. Follow any link inside an alert to the related page\n\n[Open Notifications →](" + pageLink('notifications.php') + ")",
            communications: "Communications is for messages and broadcasts to users or groups:\n1. Open Communications\n2. Create or choose a conversation / broadcast\n3. Write and send; history stays in the thread\n\n[Open Communications →](" + pageLink('communications.php') + ")",
            contacts: "To add a contact person you work with:\n1. Open Contact\n2. Add Contact\n3. Fill name, phone, email\n4. Save — you can message them from the same area\n\n[Open Contact →](" + pageLink('contact.php') + ")",
            individual_reports: "Individual reports focus on one entity (one worker, agent, etc.):\n1. Open Individual reports (or Reports → individual section if grouped there)\n2. Pick the person and report type\n3. Run and export if needed\n\n[Open Individual reports →](" + pageLink('individual-reports.php') + ")"
        };
        return replies[intentId] || null;
    }

    function matchKnowledgeBaseItem(lowerMessage) {
        const lm = normalizeHelpQuery(String(lowerMessage || '').toLowerCase().trim());
        const knowledgeBase = getKnowledgeBase();
        let bestItem = null;
        let bestKwLen = -1;
        for (const item of knowledgeBase) {
            const keywords = [...item.keywords, ...(item.synonyms || [])];
            for (const keyword of keywords) {
                const k = String(keyword).toLowerCase();
                if (!k) continue;
                if (lm.includes(k) && k.length > bestKwLen) {
                    bestKwLen = k.length;
                    bestItem = item;
                }
            }
        }
        return bestItem;
    }

    // Structured tasks (delete/remove/…) get exact steps first, then Help Center excerpts; otherwise HC when it matches.
    function getAIResponse(userMessage) {
        const qn = normalizeHelpQuery(userMessage.toLowerCase().trim());
        const structId = detectStructuredTaskIntent(qn);
        const hcTutorial = (window.HELP_CENTER_BUILTIN) ? findBestHelpCenterTutorial(qn) : null;

        if (structId) {
            const lead = intentReply(structId);
            if (lead) {
                if (hcTutorial) {
                    return lead + '\n\n' + formatHelpCenterAnswer(hcTutorial, qn, {
                        skipOverview: true,
                        excerptMax: 920,
                        maxParas: 3
                    });
                }
                const linkCat = structId === 'contacts_delete' ? 'contacts_module' : structId === 'workers_delete' ? 'workers' : 'agents';
                return lead + appendKbQuickLinks(linkCat);
            }
        }

        if (hcTutorial) {
            return formatHelpCenterAnswer(hcTutorial, qn);
        }

        const kbItem = matchKnowledgeBaseItem(qn);
        if (kbItem) {
            return kbItem.answer + appendKbQuickLinks(kbItem.category);
        }

        const guidedId = resolveGuidedIntent(qn);
        if (guidedId) {
            const g = intentReply(guidedId);
            if (g) return g;
        }

        const intentId = detectIntent(qn);
        if (intentId) {
            const quick = intentReply(intentId);
            if (quick) return quick;
        }

        return 'I\'m right here in chat — try a quick topic above or ask again in your own words.\n' +
            'Optional longer guides: [Help Center →](' + absoluteChatPageHref('pages/help-center.php') + ')';
    }

    /** Best matching builtin tutorial for a user question, or null. Scores title/overview/plain body (HTML stripped). */
    function findBestHelpCenterTutorial(query) {
        const contentSource = window.HELP_CENTER_BUILTIN;
        if (!contentSource || typeof contentSource !== 'object') {
            return null;
        }

        const lowerQuery = normalizeHelpQuery(String(query || '').toLowerCase().trim());
        if (lowerQuery.length < 2) {
            return null;
        }

        const searchWords = buildHelpCenterSearchWords(lowerQuery);
        if (searchWords.length === 0) {
            return null;
        }

        let bestMatch = null;
        let bestAdj = -Infinity;
        const matches = [];

        for (const categoryId in contentSource) {
            const tutorials = contentSource[categoryId];
            if (!Array.isArray(tutorials)) continue;

            tutorials.forEach(tutorial => {
                let titlePart = 0;
                let overviewPart = 0;
                let contentPart = 0;

                const titleLower = (tutorial.title && String(tutorial.title).toLowerCase()) || '';
                const overviewLower = (tutorial.overview && String(tutorial.overview).toLowerCase()) || '';
                const contentPlainLower = getTutorialPlainLower(tutorial);

                if (titleLower) {
                    searchWords.forEach(word => {
                        if (word.length > 1 && titleLower.includes(word)) titlePart += 13;
                    });
                    if (lowerQuery.length >= 7 && titleLower.includes(lowerQuery)) {
                        titlePart += 26;
                    }
                }
                titlePart = Math.min(titlePart, 48);

                if (overviewLower) {
                    searchWords.forEach(word => {
                        if (word.length > 1 && overviewLower.includes(word)) overviewPart += 8;
                    });
                    if (lowerQuery.length >= 8 && overviewLower.includes(lowerQuery)) {
                        overviewPart += 18;
                    }
                }
                overviewPart = Math.min(overviewPart, 32);

                if (contentPlainLower) {
                    searchWords.forEach(word => {
                        if (word.length > 1 && contentPlainLower.includes(word)) contentPart += 4;
                    });
                }
                contentPart = Math.min(contentPart, 52);

                const score = titlePart + overviewPart + contentPart;
                const hasTitleHit = titlePart > 0;
                const hasOverviewHit = overviewPart > 0;
                const pen = tutorialBroadOverviewPenalty(tutorial, lowerQuery);
                const adj = score - pen;

                if (score > 0) {
                    matches.push({
                        tutorial: tutorial,
                        score: adj,
                        hasTitleHit: hasTitleHit,
                        hasOverviewHit: hasOverviewHit
                    });
                }

                const strongEnough =
                    (hasTitleHit && score >= 11) ||
                    (hasOverviewHit && score >= 16) ||
                    score >= 24;

                if (strongEnough && adj > bestAdj) {
                    bestAdj = adj;
                    bestMatch = tutorial;
                }
            });
        }

        if (bestMatch) {
            return bestMatch;
        }
        if (matches.length > 0) {
            matches.sort((a, b) => b.score - a.score);
            const top = matches[0];
            if (!top || !top.tutorial) return null;
            if (top.hasTitleHit && top.score >= 9) return top.tutorial;
            if (top.hasOverviewHit && top.score >= 14) return top.tutorial;
            if (top.score >= 20) return top.tutorial;
        }
        return null;
    }

    function searchHelpCenterContent(query) {
        const t = findBestHelpCenterTutorial(query);
        return t ? formatHelpCenterAnswer(t, query) : null;
    }

    /**
     * Overview + excerpts that match the question (action verbs weighted). Options: skipOverview, excerptMax, maxParas.
     */
    function formatHelpCenterAnswer(tutorial, query, options) {
        options = options || {};
        var qNorm = normalizeHelpQuery(String(query || '').toLowerCase().trim());
        const title = (tutorial.title && String(tutorial.title).trim()) ? String(tutorial.title).trim() : 'Help topic';
        var overview = (!options.skipOverview && tutorial.overview)
            ? truncateAtSentence(String(tutorial.overview).trim(), 280)
            : '';
        var excerptMax = options.excerptMax != null ? options.excerptMax : 1000;
        var maxParas = options.maxParas != null ? options.maxParas : 3;
        var excerpt = tutorial.content ? pickRelevantExcerptsFromHtml(tutorial.content, qNorm, excerptMax, maxParas) : '';
        var hcUrl = absoluteChatPageHref('pages/help-center.php' +
            (tutorial && tutorial.id ? '?tutorial=' + encodeURIComponent(String(tutorial.id)) : ''));
        var parts = [];
        var qActs = queryActionWords(qNorm);
        var overviewLower = overview.toLowerCase();
        var overviewMissesAction = qActs.length > 0 && qActs.every(function(a) { return overviewLower.indexOf(a) === -1; });
        if (overviewMissesAction && excerpt) {
            parts.push(excerpt);
            if (overview) parts.push(overview);
        } else {
            if (overview) parts.push(overview);
            if (excerpt) parts.push(excerpt);
        }
        if (parts.length === 0) {
            parts.push('Open the guide below for step-by-step help.');
        }
        parts.push('Full walkthrough: [Help Center: ' + title + ' →](' + hcUrl + ')');
        return parts.join('\n\n');
    }

    // Update Chat Placeholder based on language
    function updateChatPlaceholder() {
        if (!chatInput) return;
        const fromPage = chatInput.getAttribute('data-chat-widget-placeholder');
        if (fromPage && String(fromPage).trim() !== '') {
            chatInput.placeholder = fromPage;
            return;
        }
        const lang = getCurrentLanguage();
        if (typeof getTranslation === 'function') {
            chatInput.placeholder = getTranslation('chatPlaceholder', lang);
        } else if (window.HELP_CENTER_TRANSLATIONS && window.HELP_CENTER_TRANSLATIONS[lang]) {
            chatInput.placeholder = window.HELP_CENTER_TRANSLATIONS[lang].chatPlaceholder || 'Type your message...';
        } else {
            chatInput.placeholder = 'Ask about Workers, Reports… or: I need to talk to support';
        }
    }

    // Update Chat Header based on language
    function updateChatHeader() {
        const lang = getCurrentLanguage();
        const headerText = document.querySelector('.chat-widget-header-text h3');
        const onlineText = document.querySelector('.chat-widget-header-text p.online');
        
        if (headerText) {
            if (typeof getTranslation === 'function') {
                headerText.textContent = getTranslation('supportTeam', lang);
            } else if (window.HELP_CENTER_TRANSLATIONS && window.HELP_CENTER_TRANSLATIONS[lang]) {
                headerText.textContent = window.HELP_CENTER_TRANSLATIONS[lang].supportTeam || 'Ratib Assistant';
            } else {
                headerText.textContent = 'Ratib Assistant';
            }
        }
        
        if (onlineText) {
            if (typeof getTranslation === 'function') {
                onlineText.textContent = getTranslation('online', lang);
            } else if (window.HELP_CENTER_TRANSLATIONS && window.HELP_CENTER_TRANSLATIONS[lang]) {
                onlineText.textContent = window.HELP_CENTER_TRANSLATIONS[lang].online || 'Help guides & live support';
            } else {
                onlineText.textContent = 'Help guides & live support';
            }
        }
    }

    // Format Time
    function formatTime(date) {
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        return `${hours}:${minutes}`;
    }

    // Save Messages to LocalStorage (live support only — AI replies are not persisted)
    function saveMessages() {
        if (!chatWidget.escalated) return;
        try {
            localStorage.setItem(CHAT_MESSAGES_KEY, JSON.stringify(chatWidget.messages.slice(-LIVE_CHAT_MAX_STORED)));
        } catch (e) {
            // Silent fail: localStorage may be disabled or full
        }
    }

    // Load Messages from LocalStorage
    function loadSavedMessages() {
        if (!chatMessages) return;
        if (!hasOpenEscalationSession()) {
            try { localStorage.removeItem(CHAT_MESSAGES_KEY); } catch (e) {}
            return;
        }
        try {
            const saved = localStorage.getItem(CHAT_MESSAGES_KEY);
            if (saved) {
                const messages = JSON.parse(saved);
                var newestTs = 0;
                for (var mi = 0; mi < messages.length; mi++) {
                    if (messages[mi].timestamp) {
                        var tms = new Date(messages[mi].timestamp).getTime();
                        if (!isNaN(tms)) newestTs = Math.max(newestTs, tms);
                    }
                }
                if (newestTs > 0 && (Date.now() - newestTs) > LIVE_CHAT_HISTORY_MAX_MS) {
                    try { localStorage.removeItem(CHAT_MESSAGES_KEY); } catch (e2) {}
                    return;
                }
                chatWidget.messages = messages.slice(-LIVE_CHAT_MAX_STORED);
                
                // Render messages (clears any prior nodes, including stale quick-picks ref)
                chatMessages.innerHTML = '';
                chatQuickPicksEl = null;
                chatWidget.messages.forEach(msg => {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = rowClassForSender(msg.sender);

                    const bubble = document.createElement('div');
                    bubble.className = 'chat-widget-message-bubble';
                    if (msg.sender === 'support') {
                        bubble.innerHTML = msg.supportHtml ? msg.supportHtml : formatSupportBubbleHtml(msg.text);
                    } else {
                        bubble.textContent = msg.text;
                    }

                    const time = document.createElement('div');
                    time.className = 'chat-widget-message-time';
                    var msgDate = msg.timestamp ? new Date(msg.timestamp) : new Date();
                    time.textContent = (msgDate && !isNaN(msgDate.getTime())) ? formatTime(msgDate) : '--:--';

                    messageDiv.appendChild(bubble);
                    messageDiv.appendChild(time);
                    chatMessages.appendChild(messageDiv);
                });

                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        } catch (e) {
            // Silent fail: localStorage may be disabled or corrupted
        }
    }

    // Update Unread Badge
    function updateUnreadBadge() {
        if (chatButton && chatWidget.unreadCount > 0) {
            let badge = chatButton.querySelector('.chat-widget-badge');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'chat-widget-badge';
                chatButton.appendChild(badge);
            }
            badge.textContent = chatWidget.unreadCount > 9 ? '9+' : chatWidget.unreadCount;
        } else {
            const badge = chatButton?.querySelector('.chat-widget-badge');
            if (badge) badge.remove();
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChatWidget);
    } else {
        initChatWidget();
    }

})();
