
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';
import '../assets/sass/style.scss';

const appName = import.meta.env.VITE_APP_NAME || 'KbAdmin';

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
            throw new Error(
                `Không tìm thấy trang Vue: ${pagePath}`,
            );
        }

        return page;
    },

    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(App, props),
        })
            .use(plugin)
            .mount(el);
    },

    progress: {
        color: '#4B5563',
    },
});
