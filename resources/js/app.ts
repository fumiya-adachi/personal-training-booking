import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';

createInertiaApp({
    title: title => title ? `${title} | FORM` : 'FORM | パーソナルトレーニング',
    resolve: name => {
        const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', { eager: true, import: 'default' });
        const page = pages[`./pages/${name}.vue`];
        if (!page) throw new Error(`Page not found: ${name}`);
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    progress: { color: '#e87968' },
});
