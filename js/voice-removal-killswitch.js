/**
 * EN: Implements frontend interaction behavior in `js/voice-removal-killswitch.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/voice-removal-killswitch.js`.
 */
/**
 * VOICE NARRATION REMOVAL KILL SWITCH
 * This script forcefully removes all voice narration functionality
 * Run this immediately after page load to ensure voice features are disabled
 */

(function() {
    'use strict';
    
    // Prevent VoiceNarration class from being declared (if cached scripts try to load)
    if (typeof window.VoiceNarration !== 'undefined') {
        delete window.VoiceNarration;
    }
    
    // Block VoiceNarration class declaration
    Object.defineProperty(window, 'VoiceNarration', {
        get: function() { return undefined; },
        set: function() { return; },
        configurable: true
    });
    
    // Remove voiceNarration instance if it exists
    if (window.voiceNarration) {
        try {
            if (window.voiceNarration.stop) {
                window.voiceNarration.stop();
            }
            delete window.voiceNarration;
        } catch(e) {}
    }
    
    // Immediately cancel any speech synthesis
    if (window.speechSynthesis) {
        try {
            window.speechSynthesis.cancel();
            window.speechSynthesis.cancel();
            window.speechSynthesis.cancel();
        } catch(e) {}
    }
    
    // Remove all voice-related buttons and elements
    function removeVoiceElements() {
        // Remove explain buttons
        document.querySelectorAll('.voice-explain-table-btn, .voice-explain-form-btn, [class*="voice-explain"], [class*="explain-table"], [class*="explain-form"]').forEach(el => {
            el.remove();
        });
        
        // Remove voice toggle button
        document.querySelectorAll('.voice-toggle-btn, [class*="voice-toggle"], [id*="voice-toggle"]').forEach(el => {
            el.remove();
        });
        
        // Remove voice controls panel
        document.querySelectorAll('.voice-controls, [class*="voice-control"], [id*="voice-control"]').forEach(el => {
            el.remove();
        });
        
        // Remove tooltips
        document.querySelectorAll('.voice-tooltip, [class*="voice-tooltip"]').forEach(el => {
            el.remove();
        });
        
        // Remove any elements with voice-related data attributes
        document.querySelectorAll('[data-voice], [data-explain], [onclick*="explain"], [onclick*="voice"]').forEach(el => {
            const onclick = el.getAttribute('onclick');
            if (onclick && (onclick.includes('explain') || onclick.includes('voice'))) {
                el.removeAttribute('onclick');
            }
        });
    }
    
    // Cancel speech on any speak attempt
    const originalSpeak = window.SpeechSynthesisUtterance;
    if (window.SpeechSynthesis && window.SpeechSynthesis.prototype) {
        const originalSpeakMethod = window.SpeechSynthesis.prototype.speak;
        window.SpeechSynthesis.prototype.speak = function() {
            this.cancel();
            return;
        };
    }
    
    // Block voice-related event listeners
    document.addEventListener('click', function(e) {
        const target = e.target;
        const classList = target.className || '';
        const id = target.id || '';
        
        if (classList.includes('voice') || classList.includes('explain') || 
            id.includes('voice') || id.includes('explain') ||
            target.closest('.voice-explain-table-btn') || 
            target.closest('.voice-explain-form-btn') ||
            target.closest('.voice-toggle-btn')) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            target.remove();
            return false;
        }
    }, true);
    
    // Run removal immediately
    removeVoiceElements();
    
    // Run removal after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', removeVoiceElements);
    } else {
        removeVoiceElements();
    }
    
    // Run removal after page load
    window.addEventListener('load', removeVoiceElements);
    
    // Continuously monitor and remove voice elements
    const observer = new MutationObserver(function(mutations) {
        removeVoiceElements();
        // Cancel any speech attempts
        if (window.speechSynthesis) {
            try {
                window.speechSynthesis.cancel();
            } catch(e) {}
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'id']
    });
    
    // Block SpeechSynthesis globally
    if (window.SpeechSynthesis) {
        Object.defineProperty(window, 'speechSynthesis', {
            get: function() {
                return {
                    speak: function() { return; },
                    cancel: function() { return; },
                    pause: function() { return; },
                    resume: function() { return; },
                    getVoices: function() { return []; }
                };
            },
            configurable: false
        });
    }
    
    // Prevent VoiceNarration class errors - catch any attempts to declare it
    try {
        // If VoiceNarration already exists, delete it
        if (typeof VoiceNarration !== 'undefined') {
            delete window.VoiceNarration;
        }
        // Prevent class declaration
        window.VoiceNarration = undefined;
        Object.defineProperty(window, 'VoiceNarration', {
            value: undefined,
            writable: false,
            configurable: true
        });
    } catch(e) {
        // Ignore errors
    }
    
    // Remove from localStorage
    try {
        localStorage.removeItem('voiceNarrationEnabled');
        localStorage.removeItem('voiceNarrationSettings');
        localStorage.removeItem('voiceHoverEnabled');
    } catch(e) {}
    
    // Catch and suppress VoiceNarration declaration errors
    window.addEventListener('error', function(e) {
        if (e.message && e.message.includes('VoiceNarration')) {
            e.preventDefault();
            e.stopPropagation();
            console.log('[Voice Removal] Suppressed VoiceNarration error');
            return false;
        }
    }, true);
    
})();
