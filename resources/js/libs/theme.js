/**
 * Which theme class, if any, belongs on an element.
 *
 * Needed in more than one place because dialogs and toasts are teleported to the body, outside the
 * mount point that carries the class. Without repeating it there, a forced theme would apply to the
 * screen and not to the dialog on top of it.
 *
 * @param {Object} config the bootstrap payload
 * @returns {string} '', 'wacu-light' or 'wacu-dark'
 */
export function themeClass(config) {
    const theme = config && config.theme;

    return theme === 'light' || theme === 'dark' ? 'wacu-' + theme : '';
}
