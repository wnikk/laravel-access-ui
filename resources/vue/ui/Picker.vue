<template>
    <Modal :open="open" :title="title" @close="$emit('close')">
        <label class="wacu-field">
            <span class="wacu-label">{{ t('app.search') }}</span>
            <input
                v-model="term"
                type="search"
                class="wacu-input"
                :placeholder="t('app.search')"
                @input="onType"
            />
        </label>

        <div ref="listBox" class="wacu-picker-list">
            <button
                v-for="row in rows"
                :key="row.id"
                type="button"
                class="wacu-picker-row"
                @click="$emit('choose', row)"
            >
                <span class="wacu-picker-title">{{ row.title }}</span>
                <span class="wacu-picker-hint">
                    {{ row.type_label }}
                    <template v-if="row.original_id">· {{ row.original_id }}</template>
                </span>
            </button>

            <p v-if="!rows.length" class="wacu-empty">{{ emptyText || t('app.nothingMatches') }}</p>
        </div>

        <template #foot>
            <span class="wacu-muted wacu-picker-count">
                {{ meta.current_page }} {{ t('app.of') }} {{ meta.last_page }} · {{ meta.total }}
                {{ t('app.total') }}
            </span>
            <span class="wacu-btn-row">
                <button
                    type="button"
                    class="wacu-btn"
                    :disabled="meta.current_page <= 1"
                    :aria-label="t('app.previous')"
                    @click="step(-1)"
                >
                    <Icon name="left" />
                </button>
                <button
                    type="button"
                    class="wacu-btn"
                    :disabled="meta.current_page >= meta.last_page"
                    :aria-label="t('app.next')"
                    @click="step(1)"
                >
                    <Icon name="right" />
                </button>
            </span>
        </template>
    </Modal>
</template>

<script setup>
import { ref, watch } from 'vue';
import Icon from './Icon.vue';
import Modal from './Modal.vue';
import { get, query } from '../../js/libs/api.js';
import { t } from '../../js/libs/i18n.js';

/**
 * A searchable, paged chooser over the owner table.
 *
 * Opened when a list is too long to send with the screen. Rows arrive already shaped by the server, so
 * this dialog serves every scope — assignable sources, every owner, whatever holds a permission —
 * without being told which it is showing.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    /** Endpoint to search; query parameters are added here. */
    endpoint: { type: String, required: true },
    /** Which set to search: 'assignable', 'all' or 'listed'. */
    scope: { type: String, default: 'assignable' },
    /** Owner ids to leave out — typically the ones already on screen. */
    exclude: { type: Array, default: () => [] },
    perPage: { type: Number, default: 15 },
    emptyText: { type: String, default: '' },
});

defineEmits(['close', 'choose']);

const term = ref('');
const rows = ref([]);
const listBox = ref(null);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });

let debounce = null;

async function load(page) {
    const url = query(props.endpoint, {
        scope: props.scope,
        exclude: props.exclude.join(','),
        page: page || 1,
        limit: props.perPage,
        search: term.value,
    });

    const data = await get(url, { lock: listBox.value });

    if (data === null) return;

    rows.value = data.rows || [];
    meta.value = data.meta || meta.value;
}

function onType() {
    if (debounce) clearTimeout(debounce);

    debounce = setTimeout(() => load(1), 350);
}

function step(delta) {
    const next = meta.value.current_page + delta;

    if (next < 1 || next > meta.value.last_page) return;

    load(next);
}

// Each opening starts clean: a stale search term from last time is a puzzle, not a convenience.
watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;

        term.value = '';
        rows.value = [];
        load(1);
    }
);
</script>
