import Alpine from 'alpinejs';
import admin from './admin';
import chat from './chat';

window.Alpine = Alpine;

Alpine.data('admin', admin);
Alpine.data('chat', chat);

Alpine.start();
