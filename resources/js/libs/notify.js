import { reactive } from 'vue';

/**
 * Success messages.
 *
 * Failures are not here: httpUi already renders those, with the validation detail and an optional
 * dump of whatever the server actually said, and reimplementing that worse would be the only thing
 * gained by taking it over. So this covers the other half — telling somebody that the thing they
 * asked for happened.
 */
export const notices = reactive({ items: [] });

let counter = 0;

/**
 * @param {string} message
 * @param {number} [ttl] milliseconds on screen
 */
export function notify(message, ttl = 4000) {
    if (!message) return;

    const id = ++counter;

    notices.items.push({ id, message });

    setTimeout(() => dismiss(id), ttl);
}

/**
 * @param {number} id
 */
export function dismiss(id) {
    const at = notices.items.findIndex((item) => item.id === id);

    if (at !== -1) notices.items.splice(at, 1);
}
