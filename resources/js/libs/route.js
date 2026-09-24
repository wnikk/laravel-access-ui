/**
 * The place in the panel as a hash: `#!/view[/owner][/window[/id]]`.
 *
 *     #!/rules                  the rules screen
 *     #!/rules/edit/12          the editor of rule 12 open
 *     #!/owners/rename/7        renaming owner 7
 *     #!/permissions/7          the matrix of owner 7
 *     #!/permissions/7/rule/12  the window of rule 12 for that owner
 *
 * Every step is a history entry, so the back button closes a window and leaves a screen the way
 * it was entered, and a hash can be pasted to open an editor straight away. Seven views, an
 * owner and a window fit in a string; there is no router.
 */

/** Views whose second segment is an owner id. */
const WITH_OWNER = { permissions: true, inherit: true, explain: true };

/**
 * @param {string} hash
 * @returns {{view: string, ownerId: ?number, modal: ?string, modalId: ?string}}
 */
export function parse(hash) {
    const parts = String(hash || '')
        .replace(/^#!?\/?/, '')
        .split('/')
        .filter((part) => part !== '');

    const view = parts[0] || '';
    let at = 1;
    let ownerId = null;

    if (WITH_OWNER[view] && parts[1] && /^\d+$/.test(parts[1])) {
        ownerId = Number(parts[1]);
        at = 2;
    }

    return { view, ownerId, modal: parts[at] || null, modalId: parts[at + 1] || null };
}

/**
 * @param {{view: string, ownerId?: ?number, modal?: ?string, modalId?: ?(string|number)}} route
 * @returns {string}
 */
export function build(route) {
    let path = '#!/' + route.view;

    if (route.ownerId) path += '/' + route.ownerId;
    if (route.modal) {
        path += '/' + route.modal;
        if (route.modalId !== null && route.modalId !== undefined && route.modalId !== '') path += '/' + route.modalId;
    }

    return path;
}
