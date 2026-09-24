import { computed, ref } from 'vue';
import { del, get, post, query, url } from './api.js';
import { t } from './i18n.js';

/**
 * Loading, assigning and removing inheritance links.
 *
 * One link, two questions, and which one you are asking depends on where you are standing:
 *
 *   'children'  who inherits from this owner. The panel's inheritance screen: pick a role, see and
 *               change who has it.
 *   'parents'   whom this owner inherits from. The widget on somebody's page.
 *
 * Both go through the same three endpoints, so both go through this. The only thing the direction
 * changes here is what gets sent and what `available` comes back full of.
 *
 * @param {Object} config the bootstrap payload
 * @param {import('vue').Ref<number>|number} ownerId reactive, because the panel screen changes it
 * @param {string} direction 'parents' or 'children'
 * @param {Function} hosts () => ({ lock, status }) — resolved late, because template refs are null
 *                         until the component is mounted
 */
export function useInheritance(config, ownerId, direction, hosts) {
    const owner = ref(null);
    const list = ref([]);
    const available = ref({ list: [], truncated: false, total: 0, scope: 'assignable' });
    const permissions = ref({ own: 0, inherited: 0, conditional: 0, forbidden: 0 });
    const write = ref(false);
    const loaded = ref(false);

    /** Ids already on screen, so a dialog does not offer what is behind it. */
    const linked = computed(() => list.value.filter((row) => row.direct).map((row) => row.id));

    /** Titles by owner id, so an indirect row can name the link it arrived through. */
    const titles = computed(() => {
        const map = {};

        list.value.forEach((row) => {
            map[row.id] = row.title;
        });

        return map;
    });

    /**
     * How an indirect link got here.
     *
     * @param {Object} row
     * @returns {string}
     */
    function via(row) {
        const name = titles.value[row.through];

        return name ? t('inherit.through', { name }) : t('inherit.throughUnknown');
    }

    function current() {
        return typeof ownerId === 'object' && ownerId !== null ? ownerId.value : ownerId;
    }

    function where() {
        const at = hosts ? hosts() : {};

        return { lock: at.lock || null, status: at.status || null };
    }

    function reset() {
        owner.value = null;
        list.value = [];
        available.value = { list: [], truncated: false, total: 0, scope: 'assignable' };
        permissions.value = { own: 0, inherited: 0, conditional: 0, forbidden: 0 };
        loaded.value = false;
    }

    async function load() {
        const id = current();

        if (!id) {
            reset();
            return;
        }

        const endpoint = query(url(config.routes.inherit, { OWNER: id }), { direction });
        const data = await get(endpoint, where());

        if (data === null) return;

        owner.value = data.owner || null;
        list.value = data.list || [];
        available.value = data.available || available.value;
        permissions.value = data.permissions || permissions.value;
        // The server says what may be changed; the page may still ask for a card that only shows.
        write.value = !!data.write && !config.readOnly;
        loaded.value = true;
    }

    /**
     * @param {number|string} targetId owner id to link
     * @returns {Promise<boolean>}
     */
    async function add(targetId) {
        const id = current();

        if (!id || !targetId) return false;

        const endpoint = url(config.routes.inheritAdd, { OWNER: id });
        const data = await post(endpoint, { direction, target: Number(targetId) }, where());

        if (data === null) return false;

        await load();

        return true;
    }

    /**
     * Only a direct link can be removed: an indirect one is a consequence, and the thing to change is
     * whichever direct link is producing it.
     *
     * @param {Object} row
     * @returns {Promise<boolean>}
     */
    async function remove(row) {
        const id = current();

        if (!id || !row || !row.inheritance_id) return false;

        if (!window.confirm(t('inherit.confirmRemove') + '\n\n' + row.title)) return false;

        const endpoint = url(config.routes.inheritRemove, { OWNER: id, LINK: row.inheritance_id });
        const data = await del(endpoint, where());

        if (data === null) return false;

        await load();

        return true;
    }

    return { owner, list, available, permissions, write, loaded, linked, via, load, add, remove, reset };
}
