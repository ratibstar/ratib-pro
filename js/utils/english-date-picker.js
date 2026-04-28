/**
 * EN: Implements frontend interaction behavior in `js/utils/english-date-picker.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/utils/english-date-picker.js`.
 */
/**
 * English date picker - replaces native date inputs with Flatpickr using English locale.
 * Use on pages where date pickers should display in English (months, weekdays, etc.)
 * regardless of browser/system language.
 */
(function() {
    'use strict';
    document.documentElement.setAttribute('lang', 'en');
    var origToLocale = Date.prototype.toLocaleDateString;
    Date.prototype.toLocaleDateString = function(locales, options) {
        if (!locales || (typeof locales === 'string' && locales.indexOf('en') !== 0)) locales = 'en-US';
        return origToLocale.call(this, locales, options);
    };
    var origToLocaleStr = Date.prototype.toLocaleString;
    Date.prototype.toLocaleString = function(locales, options) {
        if (!locales || (typeof locales === 'string' && locales.indexOf('en') !== 0)) locales = 'en-US';
        return origToLocaleStr.call(this, locales, options);
    };
    var englishLocale = {
        weekdays: { shorthand: ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'], longhand: ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] },
        months: { shorthand: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], longhand: ['January','February','March','April','May','June','July','August','September','October','November','December'] },
        firstDayOfWeek: 0,
        rangeSeparator: ' to ',
        weekAbbreviation: 'Wk',
        scrollTitle: 'Scroll to increment',
        toggleTitle: 'Click to toggle',
        amPM: ['AM','PM'],
        yearAriaLabel: 'Year',
        monthAriaLabel: 'Month',
        hourAriaLabel: 'Hour',
        minuteAriaLabel: 'Minute',
        time_24hr: false
    };
    window.initializeEnglishDatePickers = function(container) {
        container = container || document;
        if (typeof flatpickr === 'undefined') {
            setTimeout(function() { window.initializeEnglishDatePickers(container); }, 100);
            return;
        }
        var inputs = container.querySelectorAll('input[type="date"], input.date-input');
        inputs.forEach(function(input) {
            if (input._flatpickr) return;
            var origVal = input.value;
            if (input.type === 'date') {
                input.type = 'text';
                input.setAttribute('autocomplete', 'off');
            }
            try {
                if (origVal && /^\d{4}-\d{2}-\d{2}$/.test(origVal)) {
                    var p = origVal.split('-');
                    input.value = p[1] + '/' + p[2] + '/' + p[0];
                }
                flatpickr(input, {
                    theme: 'dark',
                    locale: englishLocale,
                    dateFormat: 'Y-m-d',
                    altInput: false,
                    allowInput: true,
                    enableTime: false,
                    time_24hr: false,
                    defaultDate: input.value || null,
                    clickOpens: true,
                    disableMobile: 'true'
                });
            } catch (e) {
                input.type = 'date';
            }
        });
    };
    function runInit() {
        window.initializeEnglishDatePickers(document);
        setTimeout(function() { window.initializeEnglishDatePickers(document); }, 200);
        setTimeout(function() { window.initializeEnglishDatePickers(document); }, 800);
        setTimeout(function() { window.initializeEnglishDatePickers(document); }, 2000);
    }
    function startObserver() {
        if (!document.body) {
            setTimeout(startObserver, 50);
            return;
        }
        runInit();
        var obs = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                m.addedNodes.forEach(function(node) {
                    if (node.nodeType !== 1) return;
                    if (node.tagName === 'INPUT' && (node.type === 'date' || node.classList.contains('date-input')) && !node._flatpickr) {
                        if (node.type === 'date') { node.type = 'text'; node.classList.add('date-input'); }
                        setTimeout(function() { window.initializeEnglishDatePickers(node.parentElement || document); }, 50);
                    }
                    var dt = node.querySelectorAll && node.querySelectorAll('input[type="date"], input.date-input');
                    if (dt && dt.length) {
                        dt.forEach(function(inp) { if (inp.type === 'date') { inp.type = 'text'; inp.classList.add('date-input'); } });
                        setTimeout(function() { window.initializeEnglishDatePickers(node); }, 50);
                    }
                });
            });
        });
        obs.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['type', 'class'] });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startObserver);
    } else {
        startObserver();
    }
})();
