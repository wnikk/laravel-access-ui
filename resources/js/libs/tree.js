/**
 * Flatten a parent/child list into rows that carry their own depth.
 *
 * The server sends rules flat, because the tree is a presentation concern and deciding the order
 * server-side would be deciding it for every screen at once. This puts the order back: alphabetical
 * within each level, children directly under their parent, and a `depth` per row so a table can
 * indent without nesting markup.
 *
 * A row whose parent is missing — deleted, or filtered out — is treated as a root rather than
 * dropped. Hiding a rule because its container went away would hide exactly the row somebody needs
 * to find.
 *
 * @param {Array<{id: number, parent_id: number, guard_name: string}>} rows
 * @returns {Array<Object>}
 */
export function flatten(rows) {
    const byId = {};
    const roots = [];

    (rows || []).forEach((row) => {
        byId[row.id] = Object.assign({}, row, { children: [] });
    });

    (rows || []).forEach((row) => {
        const parent = row.parent_id ? byId[row.parent_id] : null;

        if (parent && parent !== byId[row.id]) {
            parent.children.push(byId[row.id]);
        } else {
            roots.push(byId[row.id]);
        }
    });

    const byName = (a, b) => String(a.guard_name || '').localeCompare(String(b.guard_name || ''));
    const out = [];

    const walk = (node, depth) => {
        out.push(Object.assign({}, node, { depth, children: undefined }));
        node.children.sort(byName).forEach((child) => walk(child, depth + 1));
    };

    roots.sort(byName).forEach((root) => walk(root, 0));

    return out;
}

/**
 * Indentation for a tree row, as an inline style.
 *
 * @param {number} depth
 * @param {number} [extra] additional rem, for a second line under the name
 * @returns {Object}
 */
export function indent(depth, extra = 0) {
    return { paddingLeft: (depth * 1.25 + extra) + 'rem' };
}

/**
 * What kind of input an option value needs, read off the rule's own validation spec.
 *
 * Approximate on purpose: the server validates against the same spec regardless, so guessing wrong
 * here costs a rejected value and not a bad write. `in:` is the case worth getting right, because a
 * closed list is the difference between choosing and typing.
 *
 * @param {?string} spec
 * @returns {{type: string, values: Array<string>, nullable: boolean}}
 */
export function readSpec(spec) {
    const parts = String(spec || '')
        .split('|')
        .map((part) => part.trim());

    const nullable = parts.indexOf('nullable') !== -1;
    const list = parts.find((part) => part.indexOf('in:') === 0);

    if (list) {
        return {
            type: 'select',
            values: list.slice(3).split(',').map((value) => value.trim()),
            nullable,
        };
    }

    const numeric = parts.some(
        (part) => part === 'integer' || part === 'numeric' || part.indexOf('digits') === 0
    );

    return { type: numeric ? 'number' : 'text', values: [], nullable };
}
