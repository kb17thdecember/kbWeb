import { library } from '@fortawesome/fontawesome-svg-core';
import {
    faArrowLeft,
    faArrowRight,
    faCheck,
    faComments,
    faEye,
    faEyeSlash,
    faMagnifyingGlass,
    faNewspaper,
    faPaperPlane,
    faPhone,
    faSpinner,
    faXmark,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, Fragment, h, type DefineComponent } from 'vue';
import { setupInlineFormValidation } from '../../../../resources/js/lib/inlineFormValidation';
import '../assets/sass/style.scss';
import PageTransitionLoader from './components/PageTransitionLoader.Vue';

library.add(
    faArrowLeft,
    faArrowRight,
    faCheck,
    faComments,
    faEye,
    faEyeSlash,
    faMagnifyingGlass,
    faNewspaper,
    faPaperPlane,
    faPhone,
    faSpinner,
    faXmark,
);
setupInlineFormValidation();

const appName = 'HVAC Mart';

const pages = import.meta.glob<{
    default: DefineComponent;
}>('./pages/**/*.Vue', {
    eager: true,
});

void createInertiaApp({
    title: (title) => (title && !title.includes(appName) ? `${title} | ${appName}` : title || appName),

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
            render: () => h(Fragment, [h(App, props), h(PageTransitionLoader)]),
        })
            .use(plugin)
            .component('FontAwesomeIcon', FontAwesomeIcon)
            .mount(el);
    },

    progress: {
        color: '#b79a63',
        delay: 80,
        includeCSS: true,
        showSpinner: false,
    },
});
