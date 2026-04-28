/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/login.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/login.js`.
 */
/**
 * Login Page JavaScript
 * Handles login method switching, biometric authentication, and dark mode
 */

function getLoginApiBase() {
    const cfg = window.APP_CONFIG;
    if (cfg && cfg.apiBase) return cfg.apiBase.replace(/\/$/, '');
    if (cfg && cfg.baseUrl) return (cfg.baseUrl.replace(/\/$/, '') || '') + '/api';
    const path = window.location.pathname || '';
    const base = path.replace(/\/pages\/[^/]*\.php$/, '').replace(/\/control\/[^/]*\.php$/, '') || '/';
    const normalized = base.endsWith('/') ? base.slice(0, -1) : base;
    return (normalized || '') + '/api';
}

// Generate mock fingerprint data for authentication
// Uses only username-based seed (no timestamp/random) to ensure consistency with stored template
function generateMockFingerprintData(username) {
    const normalized = (username || '').toLowerCase().trim();
    // Use only the base seed without timestamp/random for consistent matching
    const seed = normalized ? `fingerprint_template_${normalized}` : 'fingerprint_template_unknown';
    
    if (typeof TextEncoder !== 'undefined') {
        const encoder = new TextEncoder();
        const bytes = encoder.encode(seed);
        let binary = '';
        bytes.forEach(byte => {
            binary += String.fromCharCode(byte);
        });
        return btoa(binary);
    }
    try {
        return btoa(unescape(encodeURIComponent(seed)));
    } catch (error) {
        return btoa(seed);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const loginMethodSelect = document.getElementById('login-method');
    const passwordForm = document.getElementById('password-form');
    const fingerprintForm = document.getElementById('fingerprint-form');
    const fingerprintBtn = document.getElementById('fingerprint-btn');
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const body = document.body;
    const animatedBackground = document.getElementById('animated-background');
    
    // Ad phrases for background animation
    const adPhrases = [
        'Ratibprogram',
        'Manage Your Business',
        'Streamline Operations',
        'Boost Productivity',
        'Smart Solutions',
        'Efficient Management',
        'Digital Transformation',
        'Work Smarter',
        'Innovation First',
        'Your Success Partner',
        'Simplify & Grow',
        'Future Ready',
        'Excellence Delivered',
        'Trusted Platform',
        'Secure & Reliable',
        'Powerful Tools',
        'Seamless Experience',
        'Next Generation',
        'Professional Grade',
        'Transform Your Workflow'
    ];
    
    // Professional symbols for background animation
    const professionalSymbols = [
        'fa-briefcase',
        'fa-chart-line',
        'fa-cog',
        'fa-lightbulb',
        'fa-rocket',
        'fa-shield-alt',
        'fa-star',
        'fa-bullseye',
        'fa-trophy',
        'fa-network-wired',
        'fa-database',
        'fa-cloud',
        'fa-lock',
        'fa-chart-bar',
        'fa-gem',
        'fa-certificate',
        'fa-award',
        'fa-handshake',
        'fa-users-cog',
        'fa-microchip'
    ];
    
    // Generate animated background text elements
    if (animatedBackground) {
        // Create 6 animated text elements with random phrases
        for (let i = 0; i < 6; i++) {
            const textElement = document.createElement('div');
            textElement.className = 'animated-text';
            // Randomly select a phrase
            const randomPhrase = adPhrases[Math.floor(Math.random() * adPhrases.length)];
            textElement.textContent = randomPhrase;
            animatedBackground.appendChild(textElement);
        }
        
        // Create 8-10 professional symbol elements
        for (let i = 0; i < 10; i++) {
            const symbolElement = document.createElement('div');
            symbolElement.className = 'animated-symbol';
            const randomSymbol = professionalSymbols[Math.floor(Math.random() * professionalSymbols.length)];
            symbolElement.innerHTML = '<i class="fas ' + randomSymbol + '"></i>';
            animatedBackground.appendChild(symbolElement);
        }
        
        // Rotate phrases every 30 seconds for variety
        setInterval(function() {
            const textElements = animatedBackground.querySelectorAll('.animated-text');
            textElements.forEach(function(element) {
                const randomPhrase = adPhrases[Math.floor(Math.random() * adPhrases.length)];
                element.textContent = randomPhrase;
            });
        }, 30000);
        
        // Rotate symbols every 45 seconds
        setInterval(function() {
            const symbolElements = animatedBackground.querySelectorAll('.animated-symbol');
            symbolElements.forEach(function(element) {
                const randomSymbol = professionalSymbols[Math.floor(Math.random() * professionalSymbols.length)];
                element.innerHTML = '<i class="fas ' + randomSymbol + '"></i>';
            });
        }, 45000);
    }
    
    // Dark Mode Toggle Functionality
    if (darkModeToggle) {
        // Check for saved theme preference or default to dark mode
        const savedTheme = localStorage.getItem('theme') || 'dark';
        if (savedTheme === 'dark') {
            body.classList.add('dark-mode');
            if (themeIcon) {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            }
        }
        
        darkModeToggle.addEventListener('click', function() {
            body.classList.toggle('dark-mode');
            
            // Update icon
            if (themeIcon) {
                if (body.classList.contains('dark-mode')) {
                    themeIcon.classList.remove('fa-moon');
                    themeIcon.classList.add('fa-sun');
                    localStorage.setItem('theme', 'dark');
                } else {
                    themeIcon.classList.remove('fa-sun');
                    themeIcon.classList.add('fa-moon');
                    localStorage.setItem('theme', 'light');
                }
            }
        });
    }

    // Login method switcher - show only selected method
    if (loginMethodSelect) {
        // Function to hide all forms
        function hideAllForms() {
            if (passwordForm) {
                passwordForm.classList.add('d-none');
                passwordForm.classList.remove('d-block');
            }
            if (fingerprintForm) {
                fingerprintForm.classList.add('d-none');
                fingerprintForm.classList.remove('d-block');
            }
        }
        
        // Function to show selected form
        function showForm(form) {
            if (form) {
                form.classList.remove('d-none');
                form.classList.add('d-block');
            }
        }
        
        // Initialize - ensure only password form is visible (default)
        // Hide fingerprint form
        if (fingerprintForm) {
            fingerprintForm.classList.add('d-none');
            fingerprintForm.classList.remove('d-block');
        }
        // Ensure password form is visible
        if (passwordForm) {
            passwordForm.classList.remove('d-none');
            passwordForm.classList.add('d-block');
        }
        
        loginMethodSelect.addEventListener('change', function() {
            const method = this.value;
            
            // Hide all forms first
            hideAllForms();
            
            // Show only the selected form
            if (method === 'password') {
                showForm(passwordForm);
            } else if (method === 'fingerprint') {
                showForm(fingerprintForm);
                // Automatically trigger fingerprint authentication
                autoAuthenticateFingerprint();
            }
        });
    }
    
    // Auto-dismiss success message after 5 seconds
    const successMessage = document.querySelector('.success-message');
    if (successMessage) {
        setTimeout(function() {
            successMessage.classList.add('fade-out');
            setTimeout(function() {
                successMessage.classList.add('d-none');
                successMessage.classList.remove('fade-out');
            }, 500);
        }, 5000); // Hide after 5 seconds
    }
    
    // Auto-authenticate with fingerprint when method is selected (real WebAuthn scan)
    async function autoAuthenticateFingerprint() {
        const statusDiv = document.getElementById('fingerprint-status');
        const spinner = document.getElementById('fingerprint-spinner');
        
        if (!statusDiv) return;
        
        // Check if WebAuthn is supported
        if (!window.PublicKeyCredential) {
            showFingerprintStatus('❌ Your browser does not support fingerprint authentication. Please use a modern browser (Chrome, Edge, or Firefox).', 'error');
            return;
        }
        
        // Check if Windows Hello is available
        try {
            const isAvailable = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
            if (!isAvailable) {
                showFingerprintStatus('❌ Windows Hello is not available. Please set up Windows Hello in Windows Settings > Accounts > Sign-in options > Windows Hello Fingerprint.', 'error');
                return;
            }
        } catch (checkError) {
            console.warn('Could not check Windows Hello availability:', checkError);
        }
        
        try {
            // Show loading state
            if (spinner) {
                spinner.classList.remove('d-none');
                spinner.classList.add('d-block');
            }
            showFingerprintStatus('👆 Please scan your fingerprint...', 'info');
            
            // Step 1: Get authentication challenge (auto-finds any registered credential)
            const apiBase = getLoginApiBase();
            const startResponse = await fetch(apiBase + '/webauthn/authenticate_start_auto.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            });
            
            if (!startResponse.ok) {
                const errorText = await startResponse.text();
                throw new Error(`Server error (${startResponse.status}): ${errorText || 'Failed to start authentication'}`);
            }
            
            const responseText = await startResponse.text();
            if (!responseText || responseText.trim() === '') {
                throw new Error('Empty response from server.');
            }
            
            let startData;
            try {
                startData = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error. Response:', responseText);
                throw new Error('Invalid response from server.');
            }
            
            if (!startData.publicKey) {
                showFingerprintStatus(`❌ ${startData.message || 'No fingerprint registered. Please register in admin panel first.'}`, 'error');
                if (spinner) {
                    spinner.classList.add('d-none');
                    spinner.classList.remove('d-block');
                }
                return;
            }
            
            // Step 2: Convert challenge from base64 to ArrayBuffer
            const challenge = Uint8Array.from(atob(startData.publicKey.challenge), c => c.charCodeAt(0));
            const allowCredentials = startData.publicKey.allowCredentials.map(cred => ({
                ...cred,
                id: Uint8Array.from(atob(cred.id), c => c.charCodeAt(0))
            }));
            
            // Step 3: Request authentication from hardware fingerprint scanner
            const publicKeyCredentialRequestOptions = {
                challenge: challenge,
                allowCredentials: allowCredentials,
                timeout: 60000,
                userVerification: 'required' // This triggers fingerprint scan
            };
            
            showFingerprintStatus('👆 Place your finger on the scanner...', 'info');
            
            const credential = await navigator.credentials.get({
                publicKey: publicKeyCredentialRequestOptions
            });
            
            if (!credential) {
                // User cancelled - silently handle, just hide spinner
                if (spinner) {
                    spinner.classList.add('d-none');
                    spinner.classList.remove('d-block');
                }
                // Clear any previous status messages
                const statusDiv = document.getElementById('fingerprint-status');
                if (statusDiv) {
                    statusDiv.classList.add('d-none');
                    statusDiv.classList.remove('d-block');
                }
                return;
            }
            
            // Step 4: Convert credential to sendable format
            const credentialId = btoa(String.fromCharCode(...new Uint8Array(credential.rawId)));
            const clientDataJSON = btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON)));
            const authenticatorData = btoa(String.fromCharCode(...new Uint8Array(credential.response.authenticatorData)));
            const signature = btoa(String.fromCharCode(...new Uint8Array(credential.response.signature)));
            const userHandle = credential.response.userHandle ? 
                btoa(String.fromCharCode(...new Uint8Array(credential.response.userHandle))) : null;
            
            // Step 5: Send credential to server for verification
            showFingerprintStatus('🔐 Verifying fingerprint...', 'info');
            
            const finishResponse = await fetch(apiBase + '/webauthn/authenticate_finish_auto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    credentialId: credentialId,
                    clientDataJSON: clientDataJSON,
                    authenticatorData: authenticatorData,
                    signature: signature,
                    userHandle: userHandle
                }),
                credentials: 'same-origin'
            });
            
            if (!finishResponse.ok) {
                const errorText = await finishResponse.text();
                throw new Error(`Server error (${finishResponse.status}): ${errorText || 'Failed to verify authentication'}`);
            }
            
            const finishResponseText = await finishResponse.text();
            if (!finishResponseText || finishResponseText.trim() === '') {
                throw new Error('Empty response from server during verification.');
            }
            
            let finishData;
            try {
                finishData = JSON.parse(finishResponseText);
            } catch (parseError) {
                console.error('JSON parse error. Response:', finishResponseText);
                throw new Error('Invalid response from server.');
            }
            
            if (finishData.success) {
                showFingerprintStatus('✅ Authentication successful! Redirecting...', 'success');
                if (spinner) {
                    spinner.classList.add('d-none');
                    spinner.classList.remove('d-block');
                }
                // Redirect to dashboard after short delay
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 1000);
            } else {
                showFingerprintStatus(`❌ ${finishData.message || 'Authentication failed'}`, 'error');
                if (spinner) {
                    spinner.classList.add('d-none');
                    spinner.classList.remove('d-block');
                }
            }
        } catch (error) {
            // Check if user cancelled - NotAllowedError with "timed out" or "not allowed" usually means cancellation
            const errorMsg = error.message ? error.message.toLowerCase() : '';
            const isCancellation = error.name === 'NotAllowedError' && (
                errorMsg.includes('cancel') ||
                errorMsg.includes('timed out') ||
                errorMsg.includes('timeout') ||
                errorMsg.includes('not allowed') ||
                errorMsg === 'the operation either timed out or was not allowed.'
            );
            
            if (isCancellation) {
                // User cancelled - silently handle, just hide spinner and status
                if (spinner) {
                    spinner.classList.add('d-none');
                    spinner.classList.remove('d-block');
                }
                const statusDiv = document.getElementById('fingerprint-status');
                if (statusDiv) {
                    statusDiv.classList.add('d-none');
                    statusDiv.classList.remove('d-block');
                }
                // Don't log cancellation as an error - it's a user choice
                return;
            }
            
            // Only log and show errors for actual failures, not cancellations
            console.error('Fingerprint authentication error:', error);
            if (spinner) {
                spinner.classList.add('d-none');
                spinner.classList.remove('d-block');
            }
            let errorMessage = 'Failed to authenticate';
            
            if (error.name === 'NotAllowedError') {
                // This could be Windows Hello not configured or permission denied (not cancellation)
                errorMessage = 'Windows Hello is not set up. Please configure Windows Hello in Settings.';
            } else if (error.name === 'InvalidStateError') {
                errorMessage = 'No fingerprint registered. Please register in admin panel first.';
            } else if (error.name === 'NotSupportedError') {
                errorMessage = 'Fingerprint authentication is not supported on this device.';
            } else if (error.message) {
                errorMessage = error.message;
            }
            
            showFingerprintStatus(`❌ ${errorMessage}`, 'error');
        }
    }
    
    // Helper function to show fingerprint status
    function showFingerprintStatus(message, type) {
        const statusDiv = document.getElementById('fingerprint-status');
        if (!statusDiv) return;
        
        statusDiv.classList.remove('d-none');
        statusDiv.classList.add('d-block');
        statusDiv.className = `fingerprint-status ${type}-message d-block`;
        // Use textContent to preserve line breaks (\n)
        statusDiv.textContent = message;
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                statusDiv.classList.add('d-none');
                statusDiv.classList.remove('d-block');
            }, 3000);
        }
    }
    
});

