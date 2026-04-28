/**
 * EN: Implements frontend interaction behavior in `js/settings/settings.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/settings/settings.js`.
 */
/**
 * System Settings JavaScript
 * Handles all settings-related functionality
 */

function getSettingsApiBase() {
    if (window.APP_CONFIG && window.APP_CONFIG.apiBase) return window.APP_CONFIG.apiBase.replace(/\/$/, '');
    if (window.API_BASE) return String(window.API_BASE).replace(/\/$/, '');
    const path = (window.location.pathname || '').replace(/\/pages\/.*$/, '').replace(/\/control\/.*$/, '') || '/';
    const base = path.endsWith('/') ? path.slice(0, -1) : path;
    return (base || '') + '/api';
}

class SettingsManager {
    constructor() {
        this.currentTab = 'general';
        this.settings = {};
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadSettings();
        this.showTab(this.currentTab);
    }

    bindEvents() {
        // Settings form submit handler
        const settingsForm = document.getElementById('settingsForm');
        if (settingsForm) {
            settingsForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveSettings(settingsForm);
            });
        }
        
        // Remove inline onclick handlers and add event listeners
        document.querySelectorAll('[onclick]').forEach(element => {
            const onclickValue = element.getAttribute('onclick');
            element.removeAttribute('onclick');
            
            // Handle window.location.href
            if (onclickValue && onclickValue.includes('window.location.href')) {
                const match = onclickValue.match(/window\.location\.href=['"]([^'"]+)['"]/);
                if (match) {
                    element.addEventListener('click', function(e) {
                        e.preventDefault();
                        window.location.href = match[1];
                    });
                }
            }
            
            // Handle addNewItem
            if (onclickValue && onclickValue.includes('addNewItem()')) {
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (typeof addNewItem === 'function') {
                        addNewItem();
                    }
                });
            }
            
            // Handle closeSettingsModal
            if (onclickValue && onclickValue.includes('closeSettingsModal()')) {
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (typeof closeSettingsModal === 'function') {
                        closeSettingsModal();
                    }
                });
            }
            
            // Handle closeFormModal
            if (onclickValue && onclickValue.includes('closeFormModal()')) {
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (window.modernForms && typeof window.modernForms.closeFormModal === 'function') {
                        window.modernForms.closeFormModal();
                    }
                });
            }
        });
        
        // Tab navigation
        document.querySelectorAll('.settings-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                const tabName = tab.getAttribute('data-tab');
                this.showTab(tabName);
            });
        });

        // Form submissions
        document.querySelectorAll('.settings-form').forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveSettings(form);
            });
        });

        // Auto-save on input change
        document.querySelectorAll('.settings-form input, .settings-form select, .settings-form textarea').forEach(input => {
            input.addEventListener('change', (e) => {
                this.autoSave(e.target.closest('.settings-form'));
            });
        });

        // Reset buttons
        document.querySelectorAll('.reset-settings').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                this.resetSettings(e.target.closest('.settings-form'));
            });
        });

        // Export/Import buttons
        const exportBtn = document.getElementById('export-settings');
        const importBtn = document.getElementById('import-settings');
        
        if (exportBtn) {
            exportBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.exportSettings();
            });
        }

        if (importBtn) {
            importBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.importSettings();
            });
        }
    }

    showTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.settings-content').forEach(content => {
            content.style.display = 'none';
        });

        // Remove active class from all tabs
        document.querySelectorAll('.settings-tab').forEach(tab => {
            tab.classList.remove('active');
        });

        // Show selected tab
        const selectedContent = document.getElementById(tabName + '-settings');
        const selectedTab = document.querySelector(`[data-tab="${tabName}"]`);

        if (selectedContent) {
            selectedContent.style.display = 'block';
        }
        if (selectedTab) {
            selectedTab.classList.add('active');
        }

        this.currentTab = tabName;
    }

    async loadSettings() {
        try {
            const base = getSettingsApiBase();
            const response = await fetch(base + '/settings/get_settings.php');
            const result = await response.json();

            if (result.success) {
                this.settings = result.settings;
                this.populateSettings();
            } else {
                this.showNotification('Error loading settings: ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Error loading settings:', error);
            this.showNotification('Failed to load settings', 'error');
        }
    }

    populateSettings() {
        // Populate form fields with loaded settings
        Object.keys(this.settings).forEach(key => {
            const input = document.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = this.settings[key] === '1' || this.settings[key] === true;
                } else {
                    input.value = this.settings[key];
                }
            }
        });
    }

    async saveSettings(form) {
        const formData = new FormData(form);
        const settings = {};

        // Convert FormData to object
        for (let [key, value] of formData.entries()) {
            settings[key] = value;
        }

        try {
                        const base = getSettingsApiBase();
            const response = await fetch(base + '/settings/update_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(settings)
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Settings saved successfully!', 'success');
                this.settings = { ...this.settings, ...settings };
            } else {
                this.showNotification('Error saving settings: ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Error saving settings:', error);
            this.showNotification('Failed to save settings', 'error');
        }
    }

    async autoSave(form) {
        // Debounce auto-save
        clearTimeout(this.autoSaveTimeout);
        this.autoSaveTimeout = setTimeout(() => {
            this.saveSettings(form);
        }, 1000);
    }

    async resetSettings(form) {
        if (confirm('Are you sure you want to reset these settings to default values?')) {
            try {
                            const base = getSettingsApiBase();
            const response = await fetch(base + '/settings/reset_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ section: form.getAttribute('data-section') })
                });

                const result = await response.json();

                if (result.success) {
                    this.showNotification('Settings reset successfully!', 'success');
                    this.loadSettings(); // Reload settings
                } else {
                    this.showNotification('Error resetting settings: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error resetting settings:', error);
                this.showNotification('Failed to reset settings', 'error');
            }
        }
    }

    async exportSettings() {
        try {
                        const base = getSettingsApiBase();
            const response = await fetch(base + '/settings/export_settings.php');
            const blob = await response.blob();
            
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'system_settings_' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            this.showNotification('Settings exported successfully!', 'success');
        } catch (error) {
            console.error('Error exporting settings:', error);
            this.showNotification('Failed to export settings', 'error');
        }
    }

    async importSettings() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';
        
        input.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            try {
                const formData = new FormData();
                formData.append('settings_file', file);

                            const base = getSettingsApiBase();
            const response = await fetch(base + '/settings/import_settings.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    this.showNotification('Settings imported successfully!', 'success');
                    this.loadSettings(); // Reload settings
                } else {
                    this.showNotification('Error importing settings: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error importing settings:', error);
                this.showNotification('Failed to import settings', 'error');
            }
        };

        input.click();
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <span class="notification-message">${message}</span>
            <button class="notification-close">&times;</button>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);

        // Close button functionality
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        });

        // Show notification
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
    }

    // Utility methods
    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    validateUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

// Initialize settings manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.settingsManager = new SettingsManager();
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SettingsManager;
} 