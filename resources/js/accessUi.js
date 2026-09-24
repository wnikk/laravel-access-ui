import { createApp, reactive } from 'vue';

import App from '../vue/App.vue';
import Widget from '../vue/Widget.vue';

import { configure } from './libs/api.js';
import { addMessages, getLocale, setLocale, t } from './libs/i18n.js';

import '../css/httpui.css';
import '../css/accessUi.css';

/*!
 * Laravel Access UI
 * https://github.com/wnikk/laravel-access-ui
 * Released under the MIT License.
 */

/**
 * The whole public surface: two ways to mount, and two ways to adjust.
 *
 *     accessUi.init(element, options)     the full panel
 *     accessUi.widget(element, options)   one card for a host page
 *     accessUi.addMessages(locale, msgs)  translations
 *     accessUi.setDefaults(options)       anything every mount should share
 *
 * `options` is what the server put on the page: routes, which screens are on, which entities exist,
 * the locale and a CSRF token. Nothing is read from globals, so two mounts on one page — a panel and
 * a widget, or two widgets for two accounts — do not interfere.
 */

const defaults = {
    locale: null,
    theme: 'auto',
    csrfToken: null,
    screens: {},
    entities: [],
    picker: { perPage: 15, inlineLimit: 100 },
    problems: {},
    ruleTree: false,
    readOnly: false,
    routes: {},
};

/**
 * Turn whatever the page passed in into the config every component expects.
 *
 * Reactive because `noticeHost` is filled in after mount: the element httpUi should draw its error
 * boxes into only exists once the component is on the page.
 *
 * @param {Object} options
 * @returns {Object}
 */
function makeConfig(options) {
    const merged = Object.assign({}, defaults, options || {});

    merged.screens = Object.assign(
        {
            rules: { enabled: false, write: false },
            owners: { enabled: false, write: false },
            permissions: { enabled: false, write: false },
            inherit: { enabled: false, write: false },
            explain: { enabled: false, write: false },
            health: { enabled: false, write: false },
            xacml: { enabled: false, write: false },
        },
        merged.screens || {}
    );

    merged.picker = Object.assign({}, defaults.picker, merged.picker || {});
    merged.noticeHost = null;

    return reactive(merged);
}

/**
 * @param {HTMLElement|string} target
 * @returns {HTMLElement|null}
 */
function resolveTarget(target) {
    if (typeof target === 'string') {
        return document.querySelector(target);
    }

    return target instanceof HTMLElement ? target : null;
}

/**
 * @param {Object} component
 * @param {HTMLElement|string} target
 * @param {Object} options
 * @param {Object} props
 * @returns {Object|null} the Vue application instance
 */
function mount(component, target, options, props) {
    const element = resolveTarget(target);

    if (!element) {
        console.error('[accessUi] nothing to mount into.');
        return null;
    }

    const config = makeConfig(options);

    // Locale and CSRF are process-wide rather than per-mount: the first is what the page is written
    // in, the second is what the page was served with, and neither differs between two cards on it.
    setLocale(config.locale || document.documentElement.getAttribute('lang') || 'en');
    configure({ csrfToken: config.csrfToken });

    element.classList.add('wacu-root');

    // 'auto' adds nothing and lets prefers-color-scheme decide. The two explicit values put a class on
    // the mount point, which the stylesheet also honours on any ancestor — so a host that already
    // toggles its own theme by class can leave this on 'auto' and be followed anyway.
    if (config.theme === 'light' || config.theme === 'dark') {
        element.classList.remove('wacu-light', 'wacu-dark');
        element.classList.add('wacu-' + config.theme);
    }

    const app = createApp(component, props || {});

    app.provide('acuConfig', config);
    app.config.globalProperties.$t = t;
    app.mount(element);

    return app;
}

const accessUi = {
    /**
     * Mount the full panel.
     *
     * @param {HTMLElement|string} target
     * @param {Object} options
     * @returns {Object|null}
     */
    init(target, options) {
        return mount(App, target, options, {});
    },

    /**
     * Mount the assignment card for one owner.
     *
     * `options.owner` is an owner id — the id of the row in the access-rules owner table, not your own
     * user id. A host page gets it from `$user->getOwner()->id`, using the trait access-rules asks you
     * to put on the model anyway. Asking for it there rather than resolving it here is what keeps this
     * package free of any knowledge about your models.
     *
     * @param {HTMLElement|string} target
     * @param {Object} options
     * @returns {Object|null}
     */
    widget(target, options) {
        const settings = options || {};
        const ownerId = Number(settings.owner);

        if (!ownerId) {
            console.error('[accessUi] widget() needs an owner id, e.g. { owner: 17 }.');
            return null;
        }

        return mount(Widget, target, settings, {
            ownerId,
            title: settings.title || '',
            compact: !!settings.compact,
        });
    },

    /**
     * @param {string} locale
     * @param {Object} messages
     */
    addMessages(locale, messages) {
        addMessages(locale, messages);
    },

    /**
     * Anything every later mount on this page should start from.
     *
     * @param {Object} options
     */
    setDefaults(options) {
        Object.assign(defaults, options || {});
    },

    /** @returns {string} */
    locale() {
        return getLocale();
    },

    /** @returns {Function} */
    t,
};

// A global rather than an export: the bundle is built as an IIFE and loaded with a plain
// `<script src>`, which is the whole point: a page gets the interface by adding two files, with no
// module loader, no import map and no build step of its own. A project that would rather bundle it
// can import this file from source instead of the built one.
if (typeof window !== 'undefined' && !window.accessUi) {
    window.accessUi = accessUi;
}
