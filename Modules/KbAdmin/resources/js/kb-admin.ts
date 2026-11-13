import { library } from '@fortawesome/fontawesome-svg-core';
import { faGoogle } from '@fortawesome/free-brands-svg-icons';
import {
    faAlignCenter,
    faAlignLeft,
    faAlignRight,
    faArrowLeft,
    faArrowRight,
    faArrowUpRightFromSquare,
    faBell,
    faBold,
    faBolt,
    faBox,
    faBoxesStacked,
    faBuilding,
    faChartLine,
    faChartSimple,
    faCheck,
    faCircleCheck,
    faClipboardCheck,
    faClock,
    faComments,
    faCopy,
    faDesktop,
    faEnvelope,
    faEraser,
    faEye,
    faEyeSlash,
    faFileArrowUp,
    faFileExport,
    faFileImport,
    faFileInvoiceDollar,
    faFilePdf,
    faFloppyDisk,
    faFolderTree,
    faFont,
    faGear,
    faGlobe,
    faHeadset,
    faHighlighter,
    faHouse,
    faImage,
    faItalic,
    faLayerGroup,
    faLink,
    faListCheck,
    faListOl,
    faListUl,
    faLocationDot,
    faMagnifyingGlass,
    faMagnifyingGlassChart,
    faMobileScreenButton,
    faNewspaper,
    faPaperPlane,
    faPhone,
    faPhoneVolume,
    faRightFromBracket,
    faRobot,
    faRotateLeft,
    faRotateRight,
    faShareNodes,
    faSitemap,
    faSpinner,
    faStrikethrough,
    faTabletScreenButton,
    faTags,
    faTrash,
    faTrashCan,
    faTriangleExclamation,
    faUnderline,
    faUpload,
    faUsers,
    faWandMagicSparkles,
    faXmark,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';
import { setupInlineFormValidation } from '../../../../resources/js/lib/inlineFormValidation';
import '../assets/sass/style.scss';

library.add(
    faAlignCenter,
    faAlignLeft,
    faAlignRight,
    faArrowLeft,
    faArrowRight,
    faArrowUpRightFromSquare,
    faBell,
    faBold,
    faBolt,
    faBox,
    faBoxesStacked,
    faBuilding,
    faChartLine,
    faChartSimple,
    faCheck,
    faCircleCheck,
    faClipboardCheck,
    faClock,
    faComments,
    faCopy,
    faDesktop,
    faEnvelope,
    faEraser,
    faEye,
    faEyeSlash,
    faFileArrowUp,
    faFileExport,
    faFileImport,
    faFileInvoiceDollar,
    faFilePdf,
    faFloppyDisk,
    faFolderTree,
    faFont,
    faGear,
    faGlobe,
    faGoogle,
    faHeadset,
    faHighlighter,
    faHouse,
    faImage,
    faItalic,
    faLayerGroup,
    faLink,
    faListCheck,
    faListOl,
    faListUl,
    faLocationDot,
    faMagnifyingGlass,
    faMagnifyingGlassChart,
    faMobileScreenButton,
    faNewspaper,
    faPaperPlane,
    faPhone,
    faPhoneVolume,
    faRightFromBracket,
    faRobot,
    faRotateLeft,
    faRotateRight,
    faShareNodes,
    faSitemap,
    faSpinner,
    faStrikethrough,
    faTabletScreenButton,
    faTags,
    faTrash,
    faTrashCan,
    faTriangleExclamation,
    faUnderline,
    faUpload,
    faUsers,
    faWandMagicSparkles,
    faXmark,
);
setupInlineFormValidation();

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
            .component('FontAwesomeIcon', FontAwesomeIcon)
            .mount(el);
    },

    progress: {
        color: '#4B5563',
    },
});
