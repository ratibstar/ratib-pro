/**
 * EN: Implements frontend interaction behavior in `js/utils/notifications.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/utils/notifications.js`.
 */
class NotificationSystem {
    constructor() {
        this.container = this.createContainer();
    }

    createContainer() {
        const container = document.createElement('div');
        container.className = 'notification-container';
        document.body.appendChild(container);
        return container;
    }

    show(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${this.getIcon(type)}"></i>
                <span>${message}</span>
            </div>
            <button class="notification-close">&times;</button>
        `;

        this.container.appendChild(notification);

        // Add close handler
        notification.querySelector('.notification-close').addEventListener('click', 
            () => this.close(notification)
        );

        // Auto close
        if (duration > 0) {
            setTimeout(() => this.close(notification), duration);
        }
    }

    close(notification) {
        notification.classList.add('notification-hiding');
        setTimeout(() => notification.remove(), 300);
    }

    getIcon(type) {
        switch (type) {
            case 'success': return 'fa-check-circle';
            case 'error': return 'fa-exclamation-circle';
            case 'warning': return 'fa-exclamation-triangle';
            default: return 'fa-info-circle';
        }
    }

    success(message) {
        this.show(message, 'success');
    }

    error(message) {
        this.show(message, 'error');
    }

    warning(message) {
        this.show(message, 'warning');
    }

    info(message) {
        this.show(message, 'info');
    }
}

// Create global notifications instance
window.notifications = new NotificationSystem();

export default window.notifications; 