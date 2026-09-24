import httpUi from './httpUi.js';
import { notify } from './notify.js';

/**
 * One JSON call, one place that knows about CSRF, the response envelope and where the spinner goes.
 *
 * httpUi does the transport and all of the failure reporting: it shows the message, the validation
 * detail and, when the server answered with something that was not JSON at all, an inspectable dump.
 * So everything here is about the success path, and a caller reads as if failures did not exist:
 *
 *     const data = await get(url);
 *     if (data === null) return;   // already reported to the user
 *
 * The loader and the error box are placed rather than left to default to `document.body`: a spinner
 * over the card being loaded says which part of the screen is busy, and an error above the same card
 * says which part failed.
 */

/** @type {{csrfToken: ?string}} */
const settings = { csrfToken: null };

/**
 * @param {Object} options
 */
export function configure(options) {
    if (options && typeof options.csrfToken === 'string') {
        settings.csrfToken = options.csrfToken;
    }
}

/**
 * Request headers.
 *
 * httpUi reads the CSRF token from a `<meta name="csrf-token">` by itself, which covers a Laravel
 * layout. The token from the bootstrap payload is the fallback for a page without that meta tag —
 * and it has to be merged in here, because passing `headers` to httpUi replaces the whole object
 * rather than extending it.
 *
 * @returns {Object}
 */
function headers() {
    const fromMeta = document.querySelector('meta[name="csrf-token"]');
    const token = (fromMeta && fromMeta.content) || settings.csrfToken;

    const all = {
        Accept: 'application/json, text/javascript',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (token) all['X-CSRF-TOKEN'] = token;

    return all;
}

/**
 * @param {string} method
 * @param {string} url
 * @param {Object|null} data
 * @param {{lock: ?HTMLElement, status: ?HTMLElement, silent: ?boolean, quiet: ?boolean}} [ui]
 * @returns {Promise<Object|null>} the envelope's `data`, or null when the request failed
 */
export async function request(method, url, data, ui) {
    const envelope = await attempt(method, url, data, ui);

    return envelope.ok ? envelope.data : null;
}

/**
 * The whole envelope, success or failure. httpUi has shown the failure already; a caller reads
 * this when the failure carries something to draw, such as who holds a rule that could not be deleted.
 *
 * @returns {Promise<{ok: boolean, message: string, code: ?string, errors: Object, data: Object}>}
 */
export async function attempt(method, url, data, ui) {
    const client = httpUi;
    const where = ui || {};
    const params = {
        method,
        url,
        headers: headers(),
        context: where.lock || where.status || document.body,
    };

    if (where.lock) params.contextLock = where.lock;
    if (where.status) params.contextStatus = where.status;

    // Quiet: no loader and no alert. For checks that run while somebody types; the caller draws the answer.
    if (where.quiet) {
        params.contextLock = false;
        params.onError = () => {};
    }

    // Never for GET: fetch refuses a GET with a body, and httpUi would otherwise scrape the
    // surrounding element for form fields when `data` is left undefined.
    if (method !== 'GET') params.data = data || {};

    let answer;

    try {
        answer = await client.request(params);
    } catch (failed) {
        // A refused request comes back as the query with the response on it. Anything else is a
        // mistake of the bundle itself, and must not pass as a quiet refusal.
        if (failed instanceof Error) {
            console.error('[accessUi]', failed);
        }

        const body = (failed && failed.response && failed.response.data) || {};

        return { ok: false, message: body.message || '', code: body.code || null, errors: body.errors || {}, data: body.data || {} };
    }

    const envelope = answer.data || {};

    if (envelope.message && !where.silent) notify(envelope.message);

    if (envelope.reload === true) {
        setTimeout(() => window.location.reload(), 600);
    } else if (typeof envelope.reload === 'string' && envelope.reload) {
        setTimeout(() => {
            window.location.href = envelope.reload;
        }, 600);
    }

    // `data` is null on a bare acknowledgement; an object keeps callers from having to check.
    return { ok: true, message: envelope.message || '', code: null, errors: {}, data: envelope.data === null || envelope.data === undefined ? {} : envelope.data };
}

/**
 * @param {string} url
 * @param {Object} [ui]
 * @returns {Promise<Object|null>}
 */
export function get(url, ui) {
    return request('GET', url, null, ui);
}

/**
 * @param {string} url
 * @param {Object} data
 * @param {Object} [ui]
 * @returns {Promise<Object|null>}
 */
export function post(url, data, ui) {
    return request('POST', url, data, ui);
}

/**
 * @param {string} url
 * @param {Object} data
 * @param {Object} [ui]
 * @returns {Promise<Object|null>}
 */
export function put(url, data, ui) {
    return request('PUT', url, data, ui);
}

/**
 * @param {string} url
 * @param {Object} [ui]
 * @param {Object} [data] a body, for the one route that names what to delete in it
 * @returns {Promise<Object|null>}
 */
export function del(url, ui, data) {
    return request('DELETE', url, data || {}, ui);
}

/**
 * Fill the placeholders of a URL template.
 *
 * The server hands out templates such as `/access/owners/__OWNER__/inherit` rather than a prefix to
 * join, so the host can move or rename the route group without anything here having to agree.
 *
 * @param {string} template
 * @param {Object} values e.g. { OWNER: 'user:42', RULE: 7 }
 * @returns {string}
 */
export function url(template, values) {
    let filled = template || '';

    Object.keys(values || {}).forEach((name) => {
        filled = filled.split('__' + name + '__').join(encodeURIComponent(values[name]));
    });

    return filled;
}

/**
 * @param {string} base
 * @param {Object} params
 * @returns {string}
 */
export function query(base, params) {
    const pairs = [];

    Object.keys(params || {}).forEach((name) => {
        const value = params[name];

        if (value === null || value === undefined || value === '') return;

        pairs.push(encodeURIComponent(name) + '=' + encodeURIComponent(value));
    });

    if (!pairs.length) return base;

    return base + (base.indexOf('?') === -1 ? '?' : '&') + pairs.join('&');
}

/**
 * A multipart POST, for the one screen that sends a file. httpUi leaves FormData to the browser,
 * which sets the boundary itself.
 *
 * @param {string} url
 * @param {FormData} form
 * @param {Object} [ui]
 * @returns {Promise<Object|null>}
 */
export function upload(url, form, ui) {
    return request('POST', url, form, ui);
}
