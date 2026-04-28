/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/hr/hr-page-init.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/hr/hr-page-init.js`.
 */
// HR Page Initialization Script
// Moved from inline script in pages/hr.php
// Includes date picker init (English only) - inlined to avoid 404 on separate file

(function() {
    // CRITICAL: Override Intl.NumberFormat to force English/Western numerals globally
    if (typeof Intl !== 'undefined' && Intl.NumberFormat) {
        var OriginalNumberFormat = Intl.NumberFormat;
        Intl.NumberFormat = function(locales, options) {
            // Force 'en-US' locale to prevent Arabic numerals
            return new OriginalNumberFormat('en-US', options);
        };
        Intl.NumberFormat.prototype = OriginalNumberFormat.prototype;
    }
    
    // Override Number.prototype.toLocaleString to force English
    if (typeof Number.prototype._originalToLocaleString === 'undefined') {
        Number.prototype._originalToLocaleString = Number.prototype.toLocaleString;
        Number.prototype.toLocaleString = function(locales, options) {
            return this._originalToLocaleString('en-US', options);
        };
    }
    
    // Override Number.prototype.toString to force Western numerals
    if (typeof Number.prototype._originalToString === 'undefined') {
        Number.prototype._originalToString = Number.prototype.toString;
        Number.prototype.toString = function(radix) {
            var result = this._originalToString(radix);
            if (typeof toWesternNumerals === 'function') {
                return toWesternNumerals(String(result));
            }
            return result;
        };
    }
    
    // Force Flatpickr to use English locale globally - prevent Arabic fallback
    var englishLocale = {
        firstDayOfWeek: 0,
        weekdays: { shorthand: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], longhand: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] },
        months: { shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], longhand: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] },
        amPM: ['AM', 'PM'],
        rangeSeparator: ' to ',
        weekAbbreviation: 'Wk',
        scrollTitle: 'Scroll to increment',
        toggleTitle: 'Click to toggle',
        today: 'Today',
        clear: 'Clear'
    };
    if (typeof flatpickr !== 'undefined' && flatpickr.localize) {
        flatpickr.localize(englishLocale);
    }

    function forceFlatpickrCalendarEnglish(calendarEl) {
        if (!calendarEl) return;
        calendarEl.setAttribute('dir', 'ltr');
        calendarEl.setAttribute('lang', 'en');
        calendarEl.style.direction = 'ltr';
        var arabicNumerals = '٠١٢٣٤٥٦٧٨٩';
        var walker = document.createTreeWalker(calendarEl, NodeFilter.SHOW_TEXT, null, false);
        var textNode;
        while (textNode = walker.nextNode()) {
            var t = textNode.textContent;
            if (!t || !t.trim()) continue;
            var out = t;
            for (var i = 0; i <= 9; i++) {
                out = out.replace(new RegExp(arabicNumerals[i], 'g'), String(i));
            }
            var arabicMonths = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
            var enMonths = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            for (var j = 0; j < arabicMonths.length; j++) {
                out = out.replace(new RegExp(arabicMonths[j], 'g'), enMonths[j]);
            }
            if (/\bاليوم\b/.test(out)) out = out.replace(/\bاليوم\b/g, 'Today');
            if (/\bمحو\b/.test(out)) out = out.replace(/\bمحو\b/g, 'Clear');
            if (out !== t) textNode.textContent = out;
        }
    }

    function initHRDatePickers(container) {
        if (typeof flatpickr === 'undefined') return;
        container = container || document.getElementById('hrModalBody') || document;
        var dateInputs = container.querySelectorAll ? container.querySelectorAll('input.date-input') : [];
        dateInputs.forEach(function(input) {
            if (input._flatpickr) return;
            // Force English attributes BEFORE flatpickr initialization
            input.setAttribute('dir', 'ltr');
            input.setAttribute('lang', 'en');
            input.style.direction = 'ltr';
            // Ensure placeholder is English-only
            if (!input.placeholder || input.placeholder.indexOf('YYYY') === -1) {
                input.placeholder = 'YYYY-MM-DD';
            }
            // Remove any Arabic text from placeholder
            var arabicPlaceholderPattern = /[ةنس|رهش|موي|٠-٩]/;
            if (input.placeholder && arabicPlaceholderPattern.test(input.placeholder)) {
                input.placeholder = 'YYYY-MM-DD';
            }
            try {
                flatpickr(input, {
                    locale: englishLocale,
                    dateFormat: 'Y-m-d',
                    altInput: false,
                    allowInput: false,
                    enableTime: false,
                    static: true,
                    clickOpens: true,
                    onReady: function(selectedDates, dateStr, instance) {
                        // Force English attributes on input element
                        if (instance.input) {
                            instance.input.setAttribute('dir', 'ltr');
                            instance.input.setAttribute('lang', 'en');
                            instance.input.style.direction = 'ltr';
                            // Ensure placeholder is English
                            if (!instance.input.placeholder || instance.input.placeholder.indexOf('YYYY') === -1) {
                                instance.input.placeholder = 'YYYY-MM-DD';
                            }
                        }
                        if (instance.calendarContainer) {
                            forceFlatpickrCalendarEnglish(instance.calendarContainer);
                            instance.calendarContainer.setAttribute('lang', 'en');
                            instance.calendarContainer.setAttribute('dir', 'ltr');
                        }
                    },
                    onOpen: function() {
                        var inst = input._flatpickr;
                        // Force English on input
                        if (inst && inst.input) {
                            inst.input.setAttribute('dir', 'ltr');
                            inst.input.setAttribute('lang', 'en');
                            inst.input.style.direction = 'ltr';
                            // Ensure placeholder is English
                            if (!inst.input.placeholder || inst.input.placeholder.indexOf('YYYY') === -1) {
                                inst.input.placeholder = 'YYYY-MM-DD';
                            }
                        }
                        if (inst && inst.calendarContainer) {
                            forceFlatpickrCalendarEnglish(inst.calendarContainer);
                            setTimeout(function() { forceFlatpickrCalendarEnglish(inst.calendarContainer); }, 50);
                        }
                    }
                });
            } catch (e) {
                console.warn('HR date init failed:', e);
            }
        });
        // Skip Flatpickr for time inputs - use native HTML5 time input instead
        // Native time inputs respect lang="en" and won't show Arabic numerals
        var timeInputs = container.querySelectorAll ? container.querySelectorAll('input[type="time"]') : [];
        timeInputs.forEach(function(input) {
            // Ensure native time input has correct attributes
            input.setAttribute('dir', 'ltr');
            input.setAttribute('lang', 'en');
            input.style.fontVariantNumeric = 'lining-nums';
            input.style.direction = 'ltr';
            // Convert Arabic numerals if any exist
            if (typeof toWesternNumerals === 'function' && input.value) {
                var converted = toWesternNumerals(String(input.value));
                if (converted !== input.value) input.value = converted;
            }
            // Watch for changes
            if (!input._timeWatcher) {
                input._timeWatcher = true;
                input.addEventListener('change', function() {
                    if (typeof toWesternNumerals === 'function' && this.value) {
                        var conv = toWesternNumerals(String(this.value));
                        if (conv !== this.value) this.value = conv;
                    }
                });
            }
        });
        // OLD: Flatpickr time inputs (disabled - using native instead)
        var oldTimeInputs = container.querySelectorAll ? container.querySelectorAll('input.time-input') : [];
        oldTimeInputs.forEach(function(input) {
            if (input._flatpickr) return;
            try {
                var fp = flatpickr(input, {
                    locale: englishLocale,
                    enableTime: true,
                    noCalendar: true,
                    dateFormat: 'H:i',
                    time_24hr: true,
                    allowInput: false,
                    clickOpens: true,
                    onReady: function(selectedDates, dateStr, instance) {
                        // Force Western numerals in Flatpickr time picker
                        var convertFlatpickrInputs = function() {
                            var timeWrapper = instance.calendarContainer.querySelector('.flatpickr-time');
                            if (timeWrapper) {
                                timeWrapper.setAttribute('dir', 'ltr');
                                timeWrapper.setAttribute('lang', 'en');
                                var numInputs = timeWrapper.querySelectorAll('.numInput, .numInputWrapper input, input');
                                numInputs.forEach(function(ni) {
                                    ni.setAttribute('dir', 'ltr');
                                    ni.setAttribute('lang', 'en');
                                    ni.setAttribute('inputmode', 'numeric');
                                    ni.style.fontVariantNumeric = 'lining-nums';
                                    ni.style.direction = 'ltr';
                                    ni.style.unicodeBidi = 'embed';
                                    ni.style.fontFeatureSettings = '"lnum"';
                                    // Convert Arabic numerals immediately in value
                                    if (typeof toWesternNumerals === 'function') {
                                        if (ni.value) {
                                            var converted = toWesternNumerals(String(ni.value));
                                            if (converted !== ni.value) ni.value = converted;
                                        }
                                        // Also convert textContent
                                        if (ni.textContent) {
                                            var convertedText = toWesternNumerals(String(ni.textContent));
                                            if (convertedText !== ni.textContent) ni.textContent = convertedText;
                                        }
                                    }
                                    // Watch for changes and convert Arabic numerals
                                    if (!ni._fpArabicWatcher) {
                                        ni._fpArabicWatcher = true;
                                        // Override value setter
                                        var originalValue = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(ni), 'value');
                                        Object.defineProperty(ni, 'value', {
                                            get: function() { return originalValue.get.call(this); },
                                            set: function(val) {
                                                if (typeof toWesternNumerals === 'function' && val) {
                                                    val = toWesternNumerals(String(val));
                                                }
                                                originalValue.set.call(this, val);
                                            },
                                            configurable: true
                                        });
                                        ni.addEventListener('input', function() {
                                            if (typeof toWesternNumerals === 'function' && this.value) {
                                                var conv = toWesternNumerals(String(this.value));
                                                if (conv !== this.value) {
                                                    var pos = this.selectionStart;
                                                    this.value = conv;
                                                    this.setSelectionRange(Math.min(pos, conv.length), Math.min(pos, conv.length));
                                                }
                                            }
                                        });
                                        ni.addEventListener('change', function() {
                                            if (typeof toWesternNumerals === 'function' && this.value) {
                                                var conv = toWesternNumerals(String(this.value));
                                                if (conv !== this.value) this.value = conv;
                                            }
                                        });
                                    }
                                });
                                // Convert all text nodes in time wrapper
                                if (typeof toWesternNumerals === 'function') {
                                    var walker = document.createTreeWalker(timeWrapper, NodeFilter.SHOW_TEXT, null, false);
                                    var textNode;
                                    while (textNode = walker.nextNode()) {
                                        if (textNode.textContent) {
                                            var converted = toWesternNumerals(String(textNode.textContent));
                                            if (converted !== textNode.textContent) {
                                                textNode.textContent = converted;
                                            }
                                        }
                                    }
                                }
                            }
                        };
                        setTimeout(convertFlatpickrInputs, 50);
                        // Also convert on every update
                        instance.config.onValueUpdate = function(selectedDates, dateStr, instance) {
                            setTimeout(convertFlatpickrInputs, 10);
                        };
                        // Watch for DOM changes in time wrapper
                        if (window.MutationObserver) {
                            setTimeout(function() {
                                var timeWrapper = instance.calendarContainer.querySelector('.flatpickr-time');
                                if (timeWrapper) {
                                    var textObserver = new MutationObserver(function() {
                                        convertFlatpickrInputs();
                                    });
                                    textObserver.observe(timeWrapper, { childList: true, subtree: true, characterData: true });
                                }
                            }, 100);
                        }
                    },
                    onChange: function(selectedDates, dateStr, instance) {
                        // Convert Arabic numerals when value changes
                        setTimeout(function() {
                            var timeWrapper = instance.calendarContainer.querySelector('.flatpickr-time');
                            if (timeWrapper) {
                                var numInputs = timeWrapper.querySelectorAll('.numInput, .numInputWrapper input, input');
                                numInputs.forEach(function(ni) {
                                    if (typeof toWesternNumerals === 'function' && ni.value) {
                                        var converted = toWesternNumerals(String(ni.value));
                                        if (converted !== ni.value) ni.value = converted;
                                    }
                                });
                            }
                        }, 10);
                    }
                });
            } catch (e) {
                console.warn('HR time init failed:', e);
            }
        });
    }
    window.initHRDatePickers = initHRDatePickers;

    function initHRNoArabicSanitizer(container) {
        if (!container) return;
        // Force English on all select elements to prevent Arabic validation messages
        var selects = container.querySelectorAll('select');
        selects.forEach(function(select) {
            select.setAttribute('dir', 'ltr');
            select.setAttribute('lang', 'en');
            // Override browser validation message
            select.addEventListener('invalid', function(e) {
                if (this.validity.valueMissing) {
                    this.setCustomValidity('Please select an item from the list');
                }
            });
            select.addEventListener('change', function() {
                this.setCustomValidity('');
            });
        });
        // Only target form inputs, NOT Flatpickr's internal inputs
        var inputs = container.querySelectorAll('input:not(.numInput):not(.numInputWrapper input):not([type="file"]), textarea');
        inputs.forEach(function(el) {
            if (el._hrNoArabicAttached) return;
            if (el.closest('.flatpickr-calendar') || el.closest('.flatpickr-time')) return; // Skip Flatpickr internals
            el._hrNoArabicAttached = true;
            el.setAttribute('dir', 'ltr');
            el.setAttribute('lang', 'en');
            el.addEventListener('blur', function() {
                if (typeof toWesternNumerals === 'function' && this.value) {
                    var converted = toWesternNumerals(String(this.value).replace(/^-/, ''));
                    if (converted !== this.value) this.value = converted;
                }
            });
        });
        // Handle file inputs separately with custom wrapper
        var fileInputs = container.querySelectorAll('input[type="file"]');
        fileInputs.forEach(function(input) {
            if (input._hrFileWrapperCreated) return;
            input._hrFileWrapperCreated = true;
            // Create custom file input wrapper
            var wrapper = document.createElement('div');
            wrapper.className = 'hr-file-input-wrapper';
            wrapper.style.position = 'relative';
            wrapper.style.display = 'flex';
            wrapper.style.width = '100%';
            
            // Create custom button
            var customBtn = document.createElement('button');
            customBtn.type = 'button';
            customBtn.className = 'hr-file-input-btn';
            customBtn.textContent = 'Select file';
            customBtn.style.cssText = 'position: absolute; left: 0; top: 0; padding: 8px 12px; background: rgba(139, 92, 246, 0.5); border: 1px solid rgba(139, 92, 246, 0.5); border-radius: 6px 0 0 6px; color: #e5e7eb; cursor: pointer; pointer-events: none; z-index: 1; font-size: 14px;';
            
            // Create file name display
            var fileNameDisplay = document.createElement('span');
            fileNameDisplay.className = 'hr-file-name-display';
            fileNameDisplay.textContent = 'No file chosen';
            fileNameDisplay.style.cssText = 'display: inline-block; padding: 8px 12px; background: rgba(30, 30, 40, 0.95); border: 1px solid rgba(139, 92, 246, 0.5); border-left: none; border-radius: 0 6px 6px 0; color: #a0aec0; font-size: 14px; min-height: 38px; line-height: 22px; flex: 1;';
            
            // Wrap the input
            var parent = input.parentNode;
            parent.insertBefore(wrapper, input);
            wrapper.appendChild(customBtn);
            wrapper.appendChild(fileNameDisplay);
            
            // Style the input to overlay
            input.style.cssText += 'position: absolute; left: 0; top: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 2;';
            wrapper.appendChild(input);
            
            // Update display when file is selected
            input.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    fileNameDisplay.textContent = this.files[0].name;
                    fileNameDisplay.style.color = '#e5e7eb';
                } else {
                    fileNameDisplay.textContent = 'No file chosen';
                    fileNameDisplay.style.color = '#a0aec0';
                }
            });
            
            // Force English attributes
            input.setAttribute('dir', 'ltr');
            input.setAttribute('lang', 'en');
        });
    }

    var flatpickrObserverTimeout = null;
    function forceFlatpickrWesternNumerals() {
        // Force Western numerals in all Flatpickr instances - debounced
        if (flatpickrObserverTimeout) clearTimeout(flatpickrObserverTimeout);
        flatpickrObserverTimeout = setTimeout(function() {
            var flatpickrCalendars = document.querySelectorAll('#hrModal .flatpickr-calendar, #hrModal .flatpickr-time');
            flatpickrCalendars.forEach(function(cal) {
                cal.setAttribute('dir', 'ltr');
                cal.setAttribute('lang', 'en');
                var numInputs = cal.querySelectorAll('.numInput, .numInputWrapper input, input');
                numInputs.forEach(function(ni) {
                    ni.setAttribute('dir', 'ltr');
                    ni.setAttribute('lang', 'en');
                    ni.setAttribute('inputmode', 'numeric');
                    ni.style.fontVariantNumeric = 'lining-nums';
                    ni.style.direction = 'ltr';
                    ni.style.unicodeBidi = 'embed';
                    ni.style.fontFeatureSettings = '"lnum"';
                    // Convert Arabic numerals immediately
                    if (typeof toWesternNumerals === 'function' && ni.value) {
                        var converted = toWesternNumerals(String(ni.value));
                        if (converted !== ni.value) ni.value = converted;
                    }
                    // Add watcher if not already added
                    if (!ni._fpArabicWatcher) {
                        ni._fpArabicWatcher = true;
                        ni.addEventListener('input', function() {
                            if (typeof toWesternNumerals === 'function' && this.value) {
                                var conv = toWesternNumerals(String(this.value));
                                if (conv !== this.value) {
                                    var pos = this.selectionStart;
                                    this.value = conv;
                                    this.setSelectionRange(Math.min(pos, conv.length), Math.min(pos, conv.length));
                                }
                            }
                        });
                        ni.addEventListener('change', function() {
                            if (typeof toWesternNumerals === 'function' && this.value) {
                                var conv = toWesternNumerals(String(this.value));
                                if (conv !== this.value) this.value = conv;
                            }
                        });
                    }
                });
            });
        }, 100);
    }
    
    // Continuous watcher for Flatpickr - converts Arabic to English in calendar and time inputs
    function startFlatpickrWatcher() {
        setInterval(function() {
            if (document.getElementById('hrModal') && document.getElementById('hrModal').classList.contains('show')) {
                // Force date calendar to English (month names, numerals, Today, Clear)
                var calendars = document.querySelectorAll('#hrModal .flatpickr-calendar');
                calendars.forEach(function(cal) {
                    forceFlatpickrCalendarEnglish(cal);
                });
                // Fix date input placeholders - remove Arabic text
                var dateInputs = document.querySelectorAll('#hrModal input.date-input');
                dateInputs.forEach(function(input) {
                    // Force English attributes
                    input.setAttribute('dir', 'ltr');
                    input.setAttribute('lang', 'en');
                    input.style.direction = 'ltr';
                    // Check and fix placeholder if it contains Arabic
                    if (input.placeholder) {
                        var arabicPlaceholderPattern = /[ةنس|رهش|موي|٠-٩]/;
                        if (arabicPlaceholderPattern.test(input.placeholder) || input.placeholder.indexOf('YYYY') === -1) {
                            input.placeholder = 'YYYY-MM-DD';
                        }
                    } else {
                        input.placeholder = 'YYYY-MM-DD';
                    }
                    // Also check the input value for Arabic text
                    if (input.value) {
                        var arabicValuePattern = /[ةنس|رهش|موي|٠-٩]/;
                        if (arabicValuePattern.test(input.value)) {
                            // Try to convert or clear
                            input.value = '';
                        }
                    }
                });
                // Fix select elements - prevent Arabic validation messages
                var selects = document.querySelectorAll('#hrModal select');
                selects.forEach(function(select) {
                    select.setAttribute('dir', 'ltr');
                    select.setAttribute('lang', 'en');
                    // Override browser validation message
                    if (!select._hrValidationFixed) {
                        select._hrValidationFixed = true;
                        select.addEventListener('invalid', function(e) {
                            if (this.validity.valueMissing) {
                                this.setCustomValidity('Please select an item from the list');
                            }
                        });
                        select.addEventListener('change', function() {
                            this.setCustomValidity('');
                        });
                    }
                });
                // Ensure file inputs have custom wrapper
                var fileInputs = document.querySelectorAll('#hrModal input[type="file"]');
                fileInputs.forEach(function(input) {
                    // Skip if already wrapped
                    if (input.closest('.hr-file-input-wrapper')) return;
                    
                    // Create wrapper if it doesn't exist
                    if (!input._hrFileWrapperCreated) {
                        input._hrFileWrapperCreated = true;
                        var wrapper = document.createElement('div');
                        wrapper.className = 'hr-file-input-wrapper';
                        wrapper.style.cssText = 'position: relative; display: flex; width: 100%;';
                        
                        var customBtn = document.createElement('button');
                        customBtn.type = 'button';
                        customBtn.className = 'hr-file-input-btn';
                        customBtn.textContent = 'Select file';
                        
                        var fileNameDisplay = document.createElement('span');
                        fileNameDisplay.className = 'hr-file-name-display';
                        fileNameDisplay.textContent = 'No file chosen';
                        
                        var parent = input.parentNode;
                        parent.insertBefore(wrapper, input);
                        wrapper.appendChild(customBtn);
                        wrapper.appendChild(fileNameDisplay);
                        
                        input.style.cssText += 'position: absolute; left: 0; top: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 2;';
                        wrapper.appendChild(input);
                        
                        input.addEventListener('change', function() {
                            if (this.files && this.files.length > 0) {
                                fileNameDisplay.textContent = this.files[0].name;
                                fileNameDisplay.style.color = '#e5e7eb';
                            } else {
                                fileNameDisplay.textContent = 'No file chosen';
                                fileNameDisplay.style.color = '#a0aec0';
                            }
                        });
                        
                        input.setAttribute('dir', 'ltr');
                        input.setAttribute('lang', 'en');
                    }
                });
                // Fix select elements validation messages
                var selects = document.querySelectorAll('#hrModal select');
                selects.forEach(function(select) {
                    select.setAttribute('dir', 'ltr');
                    select.setAttribute('lang', 'en');
                    if (!select._hrValidationFixed) {
                        select._hrValidationFixed = true;
                        select.addEventListener('invalid', function(e) {
                            if (this.validity.valueMissing) {
                                this.setCustomValidity('Please select an item from the list');
                            }
                        });
                        select.addEventListener('change', function() {
                            this.setCustomValidity('');
                        });
                    }
                });
                // Convert values in time inputs
                var numInputs = document.querySelectorAll('#hrModal .flatpickr-time .numInput, #hrModal .flatpickr-time .numInputWrapper input, #hrModal .flatpickr-time input');
                numInputs.forEach(function(ni) {
                    if (typeof toWesternNumerals === 'function') {
                        // Convert value
                        if (ni.value) {
                            var converted = toWesternNumerals(String(ni.value));
                            if (converted !== ni.value) {
                                ni.value = converted;
                            }
                        }
                        // Convert textContent (for display)
                        if (ni.textContent) {
                            var convertedText = toWesternNumerals(String(ni.textContent));
                            if (convertedText !== ni.textContent) {
                                ni.textContent = convertedText;
                            }
                        }
                    }
                });
                // Convert text nodes in Flatpickr time wrapper
                var timeWrappers = document.querySelectorAll('#hrModal .flatpickr-time');
                timeWrappers.forEach(function(tw) {
                    var walker = document.createTreeWalker(tw, NodeFilter.SHOW_TEXT, null, false);
                    var textNode;
                    while (textNode = walker.nextNode()) {
                        if (typeof toWesternNumerals === 'function' && textNode.textContent) {
                            var converted = toWesternNumerals(String(textNode.textContent));
                            if (converted !== textNode.textContent) {
                                textNode.textContent = converted;
                            }
                        }
                    }
                });
            }
        }, 100);
    }

    function onHRModalContentLoaded() {
        setTimeout(function() {
            var body = document.getElementById('hrModalBody');
            if (body) {
                initHRDatePickers(body);
                initHRNoArabicSanitizer(body);
                // Force Western numerals once after initialization
                setTimeout(forceFlatpickrWesternNumerals, 200);
            }
        }, 150);
    }

    document.addEventListener('DOMContentLoaded', function() {
        startFlatpickrWatcher(); // Start continuous watcher
        var hrModal = document.getElementById('hrModal');
        if (hrModal) {
            hrModal.addEventListener('shown.bs.modal', onHRModalContentLoaded);
            // Watch for Flatpickr elements being added
            if (window.MutationObserver) {
                var flatpickrObserver = new MutationObserver(function(mutations) {
                    var hasFlatpickr = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes) {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === 1 && (node.classList.contains('flatpickr-calendar') || node.classList.contains('flatpickr-time') || node.querySelector('.flatpickr-calendar, .flatpickr-time'))) {
                                    hasFlatpickr = true;
                                }
                            });
                        }
                        // Convert Arabic numerals in text changes
                        if (mutation.type === 'characterData' || (mutation.target && mutation.target.nodeType === 3)) {
                            if (typeof toWesternNumerals === 'function' && mutation.target.textContent) {
                                var converted = toWesternNumerals(String(mutation.target.textContent));
                                if (converted !== mutation.target.textContent) {
                                    mutation.target.textContent = converted;
                                }
                            }
                        }
                        // Convert Arabic in added text nodes
                        if (mutation.addedNodes) {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === 3 && typeof toWesternNumerals === 'function' && node.textContent) {
                                    var converted = toWesternNumerals(String(node.textContent));
                                    if (converted !== node.textContent) {
                                        node.textContent = converted;
                                    }
                                }
                            });
                        }
                    });
                    if (hasFlatpickr) {
                        setTimeout(forceFlatpickrWesternNumerals, 50);
                    }
                });
                flatpickrObserver.observe(hrModal, { childList: true, subtree: true, characterData: true });
            }
        }
    });
})();

