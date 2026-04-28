/**
 * EN: Implements frontend interaction behavior in `js/system-settings-alerts.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/system-settings-alerts.js`.
 */
// Modern Alert System for System Settings
class SystemSettingsAlert {
    static isAlertOpen = false;
    
    static show(options) {
        // Prevent multiple alerts
        if (this.isAlertOpen) {
            return Promise.resolve({ action: 'cancel' });
        }
        
        this.isAlertOpen = true;
        
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'system-settings-alert-overlay';
            
            const alert = document.createElement('div');
            alert.className = 'system-settings-alert';
            
            const iconMap = {
                success: 'fa-check-circle',
                warning: 'fa-exclamation-triangle',
                danger: 'fa-times-circle',
                info: 'fa-info-circle'
            };
            
            const icons = iconMap[options.type] || iconMap.info;
            
            alert.innerHTML = `
                <div class="system-settings-alert-header">
                    <div class="system-settings-alert-icon ${options.type || 'info'}">
                        <i class="fas ${icons}"></i>
                    </div>
                    <h3 class="system-settings-alert-title">${options.title || 'Alert'}</h3>
                </div>
                <div class="system-settings-alert-body">
                    ${options.message || ''}
                </div>
                <div class="system-settings-alert-footer">
                    ${options.showCancel !== false ? `<button class="system-settings-alert-btn system-settings-alert-btn-secondary" data-action="cancel">${options.cancelText || 'Cancel'}</button>` : ''}
                    <button class="system-settings-alert-btn ${options.confirmClass || 'system-settings-alert-btn-primary'}" data-action="confirm">${options.confirmText || 'OK'}</button>
                </div>
            `;
            
            overlay.appendChild(alert);
            document.body.appendChild(overlay);
            
            // Animate in
            setTimeout(() => {
                overlay.style.opacity = '1';
                alert.style.transform = 'scale(1)';
            }, 10);
            
            // Handle button clicks
            const buttons = alert.querySelectorAll('[data-action]');
            buttons.forEach(btn => {
                const action = btn.dataset.action;
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.close(overlay);
                    resolve({ action });
                });
            });
            
            // Handle Escape key
            const handleEscape = (e) => {
                if (e.key === 'Escape') {
                    this.close(overlay);
                    document.removeEventListener('keydown', handleEscape);
                    resolve({ action: 'cancel' });
                }
            };
            document.addEventListener('keydown', handleEscape);
        });
    }
    
    static close(overlay) {
        this.isAlertOpen = false;
        if (overlay) {
            overlay.style.opacity = '0';
            const alert = overlay.querySelector('.system-settings-alert');
            if (alert) {
                alert.style.transform = 'scale(0.9)';
            }
            setTimeout(() => {
                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
            }, 200);
        }
    }
    
    // Convenience methods
    static confirm(message, title = 'Confirm') {
        return this.show({
            title,
            message,
            type: 'warning',
            confirmText: 'Confirm',
            cancelText: 'Cancel'
        });
    }
    
    static success(message, title = 'Success') {
        return this.show({
            title,
            message,
            type: 'success',
            showCancel: false,
            confirmText: 'OK'
        });
    }
    
    static error(message, title = 'Error') {
        return this.show({
            title,
            message,
            type: 'danger',
            showCancel: false,
            confirmText: 'OK'
        });
    }
    
    static info(message, title = 'Information') {
        return this.show({
            title,
            message,
            type: 'info',
            showCancel: false,
            confirmText: 'OK'
        });
    }
}

// Make it globally available
window.SystemSettingsAlert = SystemSettingsAlert;

