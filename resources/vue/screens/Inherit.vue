<template>
    <section>
        <header class="wacu-screen-head">
            <div>
                <h2 class="wacu-screen-title">{{ t('inherit.title') }}</h2>
                <p class="wacu-muted">{{ t('inherit.intro') }}</p>
            </div>
        </header>

        <div class="wacu-split">
            <!-- Left: what can be inherited from. Configured entities, plus anything holding a
                 permission of its own — a hand-tuned account belongs on this screen too. -->
            <div ref="sourcesCard" class="wacu-card">
                <div class="wacu-card-head">
                    <h3 class="wacu-card-title">{{ t('inherit.sources') }}</h3>
                    <label class="wacu-search">
                        <Icon name="search" />
                        <input v-model="search" type="search" class="wacu-input" :placeholder="t('app.filter')" @input="searchLater" />
                    </label>
                </div>

                <div class="wacu-list">
                    <button
                        v-for="row in sources"
                        :key="row.id"
                        type="button"
                        class="wacu-list-row"
                        :class="{ 'wacu-list-row-on': row.id === selectedId }"
                        @click="select(row)"
                    >
                        <span class="wacu-list-main">
                            <span>{{ row.title }}</span>
                            <span class="wacu-tag">{{ row.type_label }}</span>
                            <span v-if="!row.managed" class="wacu-tag wacu-tag-inherited">
                                {{ t('inherit.holdsRights') }}
                            </span>
                        </span>
                        <span class="wacu-count" :title="t('inherit.inheritorsCount')">
                            {{ row.inheritors_count }}
                        </span>
                    </button>

                    <p v-if="!sources.length" class="wacu-empty">{{ t('app.nothingMatches') }}</p>
                </div>

                <div v-if="meta.last_page > 1" class="wacu-pager">
                    <button type="button" class="wacu-btn" :disabled="meta.current_page <= 1" @click="loadSources(meta.current_page - 1)"><Icon name="left" /></button>
                    <span class="wacu-muted">{{ meta.current_page }} / {{ meta.last_page }}</span>
                    <button type="button" class="wacu-btn" :disabled="meta.current_page >= meta.last_page" @click="loadSources(meta.current_page + 1)"><Icon name="right" /></button>
                </div>
            </div>

            <!-- Right: who inherits from the one picked on the left. -->
            <div ref="childrenCard" class="wacu-card">
                <div class="wacu-card-head">
                    <h3 class="wacu-card-title">
                        {{ t('inherit.inheritors') }}
                        <span v-if="selected" class="wacu-muted">· {{ selected.title }}</span>
                    </h3>
                    <button
                        v-if="selected && write"
                        type="button"
                        class="wacu-btn wacu-btn-primary"
                        @click="pickerOpen = true"
                    >
                        <Icon name="plus" />
                        {{ t('inherit.addInheritor') }}
                    </button>
                </div>

                <p v-if="!selected" class="wacu-empty">{{ t('inherit.pickSource') }}</p>

                <div v-else class="wacu-table-wrap">
                    <table class="wacu-table">
                        <thead>
                            <tr>
                                <th>{{ t('inherit.inheritor') }}</th>
                                <th class="wacu-col-narrow">{{ t('owners.kind') }}</th>
                                <th class="wacu-col-narrow">{{ t('permissions.state') }}</th>
                                <th v-if="write" class="wacu-col-actions wacu-right">{{ t('app.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in list" :key="row.id" :class="{ 'wacu-row-soft': !row.direct }">
                                <td>
                                    <span>{{ row.title }}</span>
                                    <code class="wacu-code wacu-sub">{{ row.original_id }}</code>
                                </td>
                                <td><span class="wacu-tag">{{ row.type_label }}</span></td>
                                <td>
                                    <span v-if="row.direct" class="wacu-tag wacu-tag-on">
                                        {{ t('inherit.direct') }}
                                    </span>
                                    <span v-else class="wacu-tag wacu-tag-inherited" :title="t('inherit.indirectHint')">
                                        <Icon name="tree" />
                                        {{ via(row) }}
                                    </span>
                                </td>
                                <td v-if="write" class="wacu-right">
                                    <button
                                        v-if="row.direct"
                                        type="button"
                                        class="wacu-btn wacu-btn-danger"
                                        @click="removeRow(row)"
                                    >
                                        {{ t('app.remove') }}
                                    </button>
                                    <span v-else class="wacu-muted">—</span>
                                </td>
                            </tr>

                            <tr v-if="!list.length">
                                <td :colspan="write ? 4 : 3" class="wacu-center">
                                    <p class="wacu-empty">{{ t('inherit.noInheritors') }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Adding an inheritor draws from the whole owner table: who may receive rights is not a
             decision the entity configuration makes. -->
        <Picker
            :open="pickerOpen"
            :title="t('inherit.addInheritorTitle')"
            :endpoint="config.routes.pick"
            scope="all"
            :exclude="excluded"
            :per-page="config.picker.perPage"
            :empty-text="t('inherit.nothingToAdd')"
            @close="pickerOpen = false"
            @choose="onPick"
        />
    </section>
</template>

<script setup>
import { computed, onMounted, inject, ref, watch } from 'vue';
import Icon from '../ui/Icon.vue';
import Picker from '../ui/Picker.vue';
import { get, query } from '../../js/libs/api.js';
import { useInheritance } from '../../js/libs/useInheritance.js';
import { t } from '../../js/libs/i18n.js';

/**
 * Who inherits from whom.
 *
 * Parent-centric, two panes: choose a source on the left, manage its inheritors on the right. The
 * asymmetry between the panes is the design. What may be a source is a decision: the configured
 * entities, widened by whoever holds a permission, because a grant nobody can see is a grant nobody
 * can revoke. What may be an inheritor is not a decision: if an owner row exists, something in the
 * application put it there, so any of them may be given rights.
 *
 * The other direction of the same data lives in the widget, where somebody standing on one account's
 * page asks what it inherits from.
 */
const props = defineProps({
    ownerId: { type: [Number, String], default: null },
});

const emit = defineEmits(['open']);

const config = inject('acuConfig');

const sourcesCard = ref(null);
const childrenCard = ref(null);
const sources = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const search = ref('');

let debounce = null;
const selectedId = ref(props.ownerId ? Number(props.ownerId) : null);
const pickerOpen = ref(false);

const { owner, list, write, via, load, add, remove, linked, reset } = useInheritance(
    config,
    selectedId,
    'children',
    () => ({ lock: childrenCard.value, status: config.noticeHost })
);

/** The one picked on the left, or what the right pane loaded for a selection made elsewhere. */
const selected = computed(() => sources.value.find((row) => row.id === selectedId.value) || owner.value || null);

// The source itself plus everyone already inheriting from it: neither belongs in the add dialog.
const excluded = computed(() =>
    selectedId.value ? [selectedId.value].concat(linked.value) : linked.value
);

/**
 * Paged and searched on the server, like the owners screen. A selection carried in from elsewhere
 * may sit on another page; the right pane loads it by id all the same.
 */
async function loadSources(page = 1) {
    const endpoint = query(config.routes.owners, { entity: 'all', search: search.value.trim(), page });
    const data = await get(endpoint, { lock: sourcesCard.value, status: config.noticeHost });

    if (data === null) return;

    sources.value = data.rows || [];
    meta.value = data.meta || meta.value;
}

function searchLater() {
    clearTimeout(debounce);
    debounce = setTimeout(() => loadSources(1), 300);
}

function select(row) {
    selectedId.value = row.id;
    emit('open', 'inherit', row.id);
    load();
}

async function onPick(row) {
    pickerOpen.value = false;

    // Reload the left pane too: its inheritor counts just changed.
    if (await add(row.id)) loadSources(meta.value.current_page);
}

async function removeRow(row) {
    if (await remove(row)) loadSources(meta.value.current_page);
}

watch(
    () => props.ownerId,
    (next) => {
        const id = next ? Number(next) : null;

        if (id === selectedId.value) return;

        selectedId.value = id;
        id ? load() : reset();
    }
);

onMounted(async () => {
    await loadSources();

    if (selectedId.value) load();
});
</script>
