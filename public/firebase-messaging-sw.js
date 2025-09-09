// Firebase Messaging Service Worker for Sen-Billets
importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging-compat.js');

// Initialize Firebase
firebase.initializeApp({
    apiKey: "your-api-key",
    authDomain: "sen-billets.firebaseapp.com",
    projectId: "sen-billets",
    storageBucket: "sen-billets.appspot.com",
    messagingSenderId: "123456789",
    appId: "your-app-id"
});

const messaging = firebase.messaging();

// Handle background messages
messaging.onBackgroundMessage((payload) => {
    console.log('Received background message:', payload);

    const notificationTitle = payload.notification.title || 'Sen-Billets';
    const notificationOptions = {
        body: payload.notification.body || 'Nouvelle notification',
        icon: payload.notification.icon || '/assets/images/logo-192.png',
        badge: '/assets/images/badge-72.png',
        tag: payload.data?.tag || 'sen-billets',
        data: payload.data,
        actions: [
            {
                action: 'view',
                title: 'Voir',
                icon: '/assets/images/view-icon.png'
            },
            {
                action: 'dismiss',
                title: 'Ignorer',
                icon: '/assets/images/dismiss-icon.png'
            }
        ]
    };

    self.registration.showNotification(notificationTitle, notificationOptions);
});

// Handle notification clicks
self.addEventListener('notificationclick', (event) => {
    console.log('Notification clicked:', event);

    event.notification.close();

    if (event.action === 'view') {
        // Open the app
        const urlToOpen = event.notification.data?.click_action || '/';
        
        event.waitUntil(
            clients.matchAll({ type: 'window', includeUncontrolled: true })
                .then((clientList) => {
                    // Check if app is already open
                    for (const client of clientList) {
                        if (client.url.includes(self.location.origin) && 'focus' in client) {
                            client.focus();
                            client.navigate(urlToOpen);
                            return;
                        }
                    }
                    
                    // Open new window
                    if (clients.openWindow) {
                        return clients.openWindow(urlToOpen);
                    }
                })
        );
    }
});

// Handle push events
self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    const data = event.data.json();
    const title = data.notification?.title || 'Sen-Billets';
    const options = {
        body: data.notification?.body || 'Nouvelle notification',
        icon: data.notification?.icon || '/assets/images/logo-192.png',
        badge: '/assets/images/badge-72.png',
        tag: data.data?.tag || 'sen-billets',
        data: data.data,
        requireInteraction: data.notification?.requireInteraction || false,
        silent: data.notification?.silent || false
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});