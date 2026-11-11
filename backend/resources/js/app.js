import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';

createInertiaApp({
    title: (title) => (title ? `${title} · Haske Pay` : 'Haske Pay'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];
        if (!page) throw new Error(`Page not found: ${name}`);
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).use(ZiggyVue).mount(el);
    },
    progress: { color: '#93A1FF', showSpinner: false },
});
