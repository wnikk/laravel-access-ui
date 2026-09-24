<template>
    <section>
        <header class="wacu-screen-head">
            <div>
                <h2 class="wacu-screen-title">{{ t('owners.title') }}</h2>
                <p class="wacu-muted">{{ t('owners.intro') }}</p>
            </div>
            <button
                v-if="write && creatable.length"
                type="button"
                class="wacu-btn wacu-btn-primary"
                @click="openCreate"
            >
                <Icon name="plus" />
                {{ t('owners.add') }}
            </button>
        </header>

        <div ref="card" class="wacu-card">
            <div class="wacu-card-head">
                <div class="wacu-tabs" role="tablist">
                    <button
                        type="button"
                        class="wacu-tab"
                        :class="{ 'wacu-tab-on': entity === 'all' }"
                        role="tab"
                        :aria-selected="entity === 'all'"
                        @click="select('all')"
                    >
                        {{ t('owners.all') }}
                    </button>
                    <button
                        v-for="item in config.entities"
                        :key="item.key"
                        type="button"
                        class="wacu-tab"
                        :class="{ 'wacu-tab-on': entity === item.key }"
                        role="tab"
                        :aria-selected="entity === item.key"
                        @click="select(item.key)"
                    >
                        {{ item.label }}
                    </button>
                </div>

                <label class="wacu-search">
                    <Icon name="search" />
                    <input v-model="search" type="search" class="wacu-input" :placeholder="t('app.filter')" @input="searchLater" />
                </label>
            </div>

            <div class="wacu-table-wrap">
                <table class="wacu-table">
                    <thead>
                        <tr>
                            <th>{{ t('owners.name') }}</th>
                            <th>{{ t('owners.identifier') }}</th>
                            <th class="wacu-col-narrow">{{ t('owners.kind') }}</th>
                            <th class="wacu-col-narrow wacu-center">{{ t('owners.permissions') }}</th>
                            <th class="wacu-col-narrow wacu-center">{{ t('owners.sources') }}</th>
                            <th class="wacu-col-actions wacu-right">{{ t('app.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="owner in rows" :key="owner.id">
                            <td>{{ owner.title }}</td>
                            <td><code class="wacu-code">{{ owner.original_id }}</code></td>
                            <td>
                                <span class="wacu-tag">{{ owner.type_label }}</span>
                                <span v-if="owner.tenant" class="wacu-tag" :title="t('owners.tenantHint')">{{ t('owners.tenant') }}</span>
                                <span v-if="owner.guest" class="wacu-tag wacu-tag-warn" :title="t('owners.guestHint')">{{ t('owners.guest') }}</span>
                                <!-- Not one of the configured entities: it is here because it holds a
                                     permission of its own, which is worth knowing about. -->
                                <span v-if="!owner.managed" class="wacu-sub" :title="t('owners.notManagedHint')">
                                    {{ t('owners.notManaged') }}
                                </span>
                            </td>
                            <td class="wacu-center">
                                <span
                                    class="wacu-count"
                                    :class="{ 'wacu-count-on': owner.permissions_count }"
                                >
                                    {{ owner.permissions_count }}
                                </span>
                            </td>
                            <td class="wacu-center">
                                <span class="wacu-count">{{ owner.sources_count }}</span>
                            </td>
                            <td class="wacu-right wacu-nowrap">
                                <button
                                    v-if="config.screens.permissions.enabled"
                                    type="button"
                                    class="wacu-btn"
                                    @click="$emit('open', 'permissions', owner.id)"
                                >
                                    {{ t('owners.permissions') }}
                                </button>
                                <button
                                    v-if="config.screens.inherit.enabled"
                                    type="button"
                                    class="wacu-btn"
                                    @click="$emit('open', 'inherit', owner.id)"
                                >
                                    {{ t('nav.inherit') }}
                                </button>
                                <button v-if="owner.inheritors_count" type="button" class="wacu-btn" :title="t('owners.heirsHint')" @click="showHeirs(owner)">
                                    {{ t('owners.heirs') }} · {{ owner.inheritors_count }}
                                </button>
                                <template v-if="write">
                                    <button type="button" class="wacu-btn" @click="openRename(owner)">
                                        {{ t('owners.rename') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="wacu-btn wacu-btn-danger"
                                        @click="destroy(owner)"
                                    >
                                        {{ t('app.delete') }}
                                    </button>
                                </template>
                            </td>
                        </tr>

                        <tr v-if="!rows.length">
                            <td colspan="6" class="wacu-center">
                                <p class="wacu-empty">{{ t('app.nothingMatches') }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="meta.last_page > 1" class="wacu-pager">
                <button type="button" class="wacu-btn" :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)"><Icon name="left" /></button>
                <span class="wacu-muted">{{ meta.current_page }} / {{ meta.last_page }} · {{ meta.total }}</span>
                <button type="button" class="wacu-btn" :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)"><Icon name="right" /></button>
            </div>
        </div>

        <!-- Who is affected by a change to this owner: everyone that inherits from it, at any depth. -->
        <Modal :open="heirs.open" :title="t('owners.heirsOf', { name: heirs.owner })" @close="nav.closeModal()">
            <ul class="wacu-assign-list">
                <li v-for="row in heirs.rows" :key="row.id" class="wacu-assign-item">
                    <span class="wacu-assign-main">
                        <button type="button" class="wacu-link" @click="$emit('open', 'permissions', row.id)">{{ row.title }}</button>
                        <span class="wacu-tag">{{ row.type_label }}</span>
                    </span>
                </li>
                <li v-if="!heirs.rows.length" class="wacu-assign-item"><p class="wacu-empty">{{ t('owners.noHeirs') }}</p></li>
            </ul>
            <p v-if="heirs.meta.total > heirs.rows.length" class="wacu-muted">{{ t('app.showingOf', { shown: heirs.rows.length, total: heirs.meta.total }) }}</p>
        </Modal>

        <Modal
            :open="createOpen"
            :title="t('owners.addTitle', { entity: createEntityLabel })"
            @close="nav.closeModal()"
        >
            <label v-if="creatable.length > 1" class="wacu-field">
                <span class="wacu-label">{{ t('owners.kind') }}</span>
                <select v-model="createForm.entity" class="wacu-input">
                    <option v-for="item in creatable" :key="item.key" :value="item.key">
                        {{ item.single }}
                    </option>
                </select>
            </label>

            <label class="wacu-field">
                <span class="wacu-label">{{ t('owners.identifier') }}</span>
                <input
                    v-model="createForm.original_id"
                    type="text"
                    class="wacu-input"
                    placeholder="content-editor"
                />
                <span class="wacu-hint">{{ t('owners.identifierHint') }}</span>
            </label>

            <label class="wacu-field">
                <span class="wacu-label">{{ t('owners.name') }}</span>
                <input v-model="createForm.name" type="text" class="wacu-input" />
                <span class="wacu-hint">{{ t('owners.nameHint') }}</span>
            </label>

            <template #foot>
                <button type="button" class="wacu-btn" @click="nav.closeModal()">
                    {{ t('app.cancel') }}
                </button>
                <button type="button" class="wacu-btn wacu-btn-primary" @click="create">
                    {{ t('app.create') }}
                </button>
            </template>
        </Modal>

        <Modal :open="renameOpen" :title="t('owners.renameTitle')" @close="nav.closeModal()">
            <dl class="wacu-facts">
                <dt>{{ t('owners.kind') }}</dt>
                <dd>{{ renaming && renaming.type_label }}</dd>
                <dt>{{ t('owners.identifier') }}</dt>
                <dd><code class="wacu-code">{{ renaming && renaming.original_id }}</code></dd>
            </dl>
            <label class="wacu-field">
                <span class="wacu-label">{{ t('owners.name') }}</span>
                <input v-model="renameName" type="text" class="wacu-input" />
                <span class="wacu-hint">{{ t('owners.nameHint') }}</span>
            </label>

            <template #foot>
                <button type="button" class="wacu-btn" @click="nav.closeModal()">
                    {{ t('app.cancel') }}
                </button>
                <button type="button" class="wacu-btn wacu-btn-primary" @click="rename">
                    {{ t('app.save') }}
                </button>
            </template>
        </Modal>
    </section>
</template>

<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue';
import Icon from '../ui/Icon.vue';
import Modal from '../ui/Modal.vue';
import { del, get, post, put, query, url } from '../../js/libs/api.js';
import { t } from '../../js/libs/i18n.js';

/**
 * The owner records: the rows that can hold permissions.
 *
 * Which types appear by default is configuration, but anything holding a permission appears whatever its
 * type, marked as unlisted. That is the case the screen exists for as much as for roles: a permission
 * granted straight to one account is invisible on a list of roles, and an administrator who cannot see it
 * cannot take it away.
 *
 * Renaming and deleting are offered on every row, including those. This screen works on the owner table,
 * and deleting an owner row takes away its permissions and assignments; the user, the team, or whatever
 * else it stood for in the tables of the application stays.
 */
const config = inject('acuConfig');
const route = inject('acuRoute');
const nav = inject('acuNav');

defineEmits(['open']);

const card = ref(null);
const rows = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const write = ref(false);
const entity = ref('all');
const search = ref('');
const heirs = ref({ open: false, owner: '', rows: [], meta: { total: 0 } });

let debounce = null;

const createOpen = ref(false);
const createForm = ref({ entity: '', original_id: '', name: '' });
const renameOpen = ref(false);
const renaming = ref(null);
const renameName = ref('');

// Only entities the configuration lets the panel create. A user's owner row appears when the application
// first touches them; typing one by hand would invite a typo that holds permissions and belongs to nobody.
const creatable = computed(() => config.entities.filter((item) => item.create));

const createEntityLabel = computed(() => {
    const found = creatable.value.find((item) => item.key === createForm.value.entity);

    return found ? found.single : '';
});

/**
 * Paged and searched on the server: a list of users is the size of the application.
 */
async function load(page = 1) {
    const endpoint = query(config.routes.owners, { entity: entity.value, search: search.value.trim(), page });
    const data = await get(endpoint, { lock: card.value, status: config.noticeHost });

    if (data === null) return;

    rows.value = data.rows || [];
    meta.value = data.meta || meta.value;
    write.value = !!data.write;
}

function go(page) {
    load(page);
}

function searchLater() {
    clearTimeout(debounce);
    debounce = setTimeout(() => load(1), 300);
}

function select(key) {
    entity.value = key;
    load(1);
}

/*
 * The windows of this screen are named in the hash ("new", "rename/7", "heirs/7") and opened by
 * it, so the back button closes them and a pasted link opens one straight away. An owner named by
 * the hash may be on another page of the list; the route of heirs returns the owner too.
 */
function showHeirs(owner) {
    nav.openModal('heirs', owner.id);
}

function openCreate() {
    nav.openModal('new');
}

function openRename(owner) {
    nav.openModal('rename', owner.id);
}

async function loadHeirs(id) {
    const data = await get(url(config.routes.ownerHeirs, { OWNER: id }) + '?limit=100', { status: config.noticeHost });

    if (data === null) return nav.closeModal();

    heirs.value = { open: true, owner: data.owner ? data.owner.title : '', rows: data.rows || [], meta: data.meta || { total: 0 } };
}

async function ownerNamed(id) {
    const found = rows.value.find((row) => row.id === id);
    if (found) return found;

    const data = await get(url(config.routes.ownerHeirs, { OWNER: id }) + '?limit=1', { silent: true, quiet: true });

    return data && data.owner ? data.owner : null;
}

watch(
    () => [route.modal, route.modalId],
    async () => {
        createOpen.value = route.modal === 'new';
        if (route.modal === 'new') {
            createForm.value = { entity: creatable.value.length ? creatable.value[0].key : '', original_id: '', name: '' };
        }

        if (route.modal === 'rename') {
            const owner = await ownerNamed(Number(route.modalId));
            if (!owner) return nav.closeModal();

            renaming.value = owner;
            renameName.value = owner.name || '';
        }
        renameOpen.value = route.modal === 'rename' && renaming.value !== null;

        if (route.modal === 'heirs') {
            await loadHeirs(Number(route.modalId));
        } else {
            heirs.value.open = false;
        }
    },
    { immediate: true }
);

async function create() {
    const data = await post(config.routes.ownerCreate, createForm.value, {
        status: config.noticeHost,
    });

    if (data === null) return;

    nav.closeModal();
    load(meta.value.current_page);
}

async function rename() {
    const target = url(config.routes.ownerUpdate, { OWNER: renaming.value.id });
    const data = await put(target, { name: renameName.value }, { status: config.noticeHost });

    if (data === null) return;

    nav.closeModal();
    load(meta.value.current_page);
}

async function destroy(owner) {
    if (!window.confirm(t('owners.confirmDelete') + '\n\n' + owner.title)) return;

    const data = await del(url(config.routes.ownerDelete, { OWNER: owner.id }), {
        status: config.noticeHost,
    });

    if (data !== null) load(meta.value.current_page);
}

onMounted(() => load(1));
</script>
