import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import AppLayout from './Layouts/AppLayout.vue';
import Icon from './Components/Icon.vue';
import { fmt } from './lib/format';

const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });

createInertiaApp({
    title: (title) => (title ? `${title} · ManRisk ERM` : 'ManRisk ERM'),
    resolve: (name) => {
        const page = pages[`./Pages/${name}.vue`];
        if (!page) throw new Error(`Halaman ${name} tidak ditemukan`);
        if (page.default.layout === undefined && !name.startsWith('Auth/') && name !== 'Error') page.default.layout = AppLayout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.use(plugin);
        app.component('Icon', Icon);
        app.config.globalProperties.$fmt = fmt;
        app.mount(el);
    },
    progress: { color: '#34a8e0' },
});

// Tema tersimpan per peramban
try {
    const t = localStorage.getItem('mr-theme');
    if (t) document.documentElement.dataset.theme = t;
} catch (e) { /* abaikan */ }

// Setelah 419 (CSRF kedaluwarsa) muat ulang halaman
router.on('invalid', (event) => {
    if (event.detail.response?.status === 419) { event.preventDefault(); window.location.reload(); }
});
