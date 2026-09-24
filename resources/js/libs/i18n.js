import en from '../lang/en.json';

/**
 * Translation, small enough to read in one sitting.
 *
 * Keys are flat and dotted (`rules.title`), messages are plain objects, and English is always the
 * fallback — so an installation that ships no translations shows English rather than key names, and
 * a partial translation falls back key by key rather than all at once.
 *
 * Interpolation is `{name}`. Nothing else: no pluralisation rules, no date formatting, no compiled
 * message AST. When a host needs more than this it already has a translation library, and can pass
 * finished strings in through `accessUi.addMessages()`.
 */
const bundles = { en };

let locale = 'en';

// Translations a page put on `window.accessUiMessages` before the bundle loaded, keyed by locale.
// The bundle mounts as soon as it runs, so a script that comes after it would be too late for the
// first render; a global set before it is the one way to be early without a build step.
if (typeof window !== 'undefined' && window.accessUiMessages && typeof window.accessUiMessages === 'object') {
    Object.keys(window.accessUiMessages).forEach((name) => {
        const messages = window.accessUiMessages[name];
        if (messages && typeof messages === 'object') bundles[name] = Object.assign({}, bundles[name] || {}, messages);
    });
}

/**
 * Add or override messages for one locale.
 *
 * Merged over whatever is already there, so a host can correct a single string without restating the
 * rest of the file.
 *
 * @param {string} name
 * @param {Object} messages
 */
export function addMessages(name, messages) {
    if (!name || !messages) return;

    bundles[name] = Object.assign({}, bundles[name] || {}, messages);
}

/**
 * Pick the active locale.
 *
 * A regional tag falls back to its base language, so `de-AT` finds `de` when only `de` was shipped.
 *
 * @param {string} name
 */
export function setLocale(name) {
    if (!name) return;

    const wanted = String(name);

    if (bundles[wanted]) {
        locale = wanted;
        return;
    }

    const base = wanted.split('-')[0];

    locale = bundles[base] ? base : 'en';
}

/**
 * @returns {string}
 */
export function getLocale() {
    return locale;
}

/**
 * Translate one key.
 *
 * @param {string} key
 * @param {Object} [params]
 * @returns {string}
 */
export function t(key, params) {
    const message = (bundles[locale] && bundles[locale][key]) || en[key] || key;

    if (!params) return message;

    return message.replace(/\{(\w+)}/g, (whole, name) =>
        Object.prototype.hasOwnProperty.call(params, name) ? String(params[name]) : whole
    );
}
