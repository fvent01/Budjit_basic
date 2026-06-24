// public/sw.js — Budjit Service Worker for Push Notifications

self.addEventListener('push', function(event) {
  const data = event.data ? event.data.json() : {};
  const title   = data.title   || 'Budjit Reminder';
  const options = {
    body:    data.body    || 'You have a reminder.',
    icon:    data.icon    || '/budjit/public/assets/icon-192.png',
    badge:   data.badge   || '/budjit/public/assets/icon-192.png',
    tag:     data.tag     || 'budjit-reminder',
    data:    { url: data.url || '/budjit/public/calendar' },
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  event.waitUntil(clients.openWindow(event.notification.data.url));
});
