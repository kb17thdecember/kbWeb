import { library } from '@fortawesome/fontawesome-svg-core';
import { fab } from '@fortawesome/free-brands-svg-icons';
import { far } from '@fortawesome/free-regular-svg-icons';
import { fas } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';
import '../assets/sass/style.scss';

library.add(fas, far, fab);

const appName = import.meta.env.VITE_APP_NAME || 'KbUser';

const pages = import.meta.glob<{
    default: DefineComponent;
}>('./pages/**/*.Vue', {
    eager: true,
});

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),

    resolve: (name) => {
        const pagePath = `./pages/${name}.Vue`;
        const page = pages[pagePath];

        if (!page) {
            throw new Error(`Không tìm thấy trang Vue: ${pagePath}`);
        }

        return page;
    },

    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(App, props),
        })
            .use(plugin)
            .component('FontAwesomeIcon', FontAwesomeIcon)
            .mount(el);
    },

    progress: {
        color: '#4B5563',
    },
});
