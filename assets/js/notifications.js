// Notification system for Sen-Billets
class NotificationManager {
    constructor() {
        this.registration = null;
        this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window;
        this.init();
    }

    async init() {
        if (!this.isSupported) {
            console.warn('Push notifications are not supported');
            return;
        }

        try {
            // Register service worker
            this.registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
            console.log('Service Worker registered successfully');

            // Check current permission
            this.updatePermissionStatus();
        } catch (error) {
            console.error('Service Worker registration failed:', error);
        }
    }

    async enableNotifications() {
        if (!this.isSupported) {
            throw new Error('Les notifications push ne sont pas supportées par ce navigateur');
        }

        try {
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                await this.subscribeUser();
                this.updatePermissionStatus();
                return true;
            } else {
                throw new Error('Permission refusée pour les notifications');
            }
        } catch (error) {
            console.error('Erreur lors de l\'activation des notifications:', error);
            throw error;
        }
    }

    async subscribeUser() {
        if (!this.registration) {
            throw new Error('Service Worker non enregistré');
        }

        try {
            const subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(window.VAPID_PUBLIC_KEY || '')
            });

            // Send subscription to server
            await this.sendSubscriptionToServer(subscription);
        } catch (error) {
            console.error('Erreur lors de l\'abonnement:', error);
            throw error;
        }
    }

    async sendSubscriptionToServer(subscription) {
        try {
            const response = await fetch('/api/notifications/token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    subscription: subscription,
                    token: JSON.stringify(subscription)
                })
            });

            if (!response.ok) {
                throw new Error('Erreur lors de l\'envoi de l\'abonnement');
            }
        } catch (error) {
            console.error('Erreur envoi abonnement:', error);
            throw error;
        }
    }

    updatePermissionStatus() {
        const statusElement = document.getElementById('push-status');
        const checkbox = document.getElementById('push_notifications');
        
        if (!statusElement || !checkbox) return;

        const permission = Notification.permission;
        
        switch (permission) {
            case 'granted':
                statusElement.className = 'alert alert-success';
                statusElement.innerHTML = '<i class="bi bi-check-circle me-2"></i><small>Notifications push activées</small>';
                checkbox.checked = true;
                checkbox.disabled = false;
                break;
            case 'denied':
                statusElement.className = 'alert alert-danger';
                statusElement.innerHTML = '<i class="bi bi-x-circle me-2"></i><small>Notifications push bloquées</small>';
                checkbox.checked = false;
                checkbox.disabled = true;
                break;
            default:
                statusElement.className = 'alert alert-warning';
                statusElement.innerHTML = `
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <small>Notifications push non activées</small>
                    <button type="button" class="btn btn-sm btn-warning ms-2" onclick="notificationManager.enableNotifications()">
                        Activer
                    </button>
                `;
                checkbox.checked = false;
                checkbox.disabled = false;
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // Show local notification
    showNotification(title, options = {}) {
        if (Notification.permission === 'granted') {
            new Notification(title, {
                icon: '/assets/images/logo-192.png',
                badge: '/assets/images/badge-72.png',
                ...options
            });
        }
    }

    // Test notification
    async testNotification() {
        try {
            const response = await fetch('/api/notifications/test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Test réussi', {
                    body: 'Notification de test envoyée avec succès !',
                    tag: 'test'
                });
            }
        } catch (error) {
            console.error('Erreur test notification:', error);
        }
    }
}

// Initialize notification manager
let notificationManager;

document.addEventListener('DOMContentLoaded', () => {
    notificationManager = new NotificationManager();
    
    // Make it globally available
    window.notificationManager = notificationManager;
});

export default NotificationManager;