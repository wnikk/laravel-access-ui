<template>
    <section>
        <header class="wacu-screen-head">
            <div>
                <h2 class="wacu-screen-title">{{ t('rules.title') }}</h2>
                <p class="wacu-muted">{{ t('rules.intro') }}</p>
            </div>
            <button v-if="write" type="button" class="wacu-btn wacu-btn-primary" @click="openCreate">
                <Icon name="plus" />
                {{ t('rules.add') }}
            </button>
        </header>

        <p v-if="loaded && !write" class="wacu-notice">
            <Icon name="lock" />
            {{ t('rules.readOnlyNotice') }}
        </p>

        <div ref="card" class="wacu-card">
            <div class="wacu-card-head">
                <label class="wacu-search">
                    <Icon name="search" />
                    <input
                        v-model="search"
                        type="search"
                        class="wacu-input"
                        :placeholder="t('rules.filterPlaceholder')"
                    />
                </label>
                <span class="wacu-muted">{{ visible.length }} / {{ rows.length }}</span>
            </div>

            <div class="wacu-table-wrap">
                <table class="wacu-table">
                    <thead>
                        <tr>
                            <th class="wacu-col-wide">{{ t('rules.guardName') }}</th>
                            <th>{{ t('rules.ruleTitle') }}</th>
                            <th class="wacu-col-narrow wacu-center">{{ t('rules.option') }}</th>
                            <th v-if="write" class="wacu-col-actions wacu-right">{{ t('app.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="rule in visible" :key="rule.id" :class="{ 'wacu-row-off': rule.deleted_at }">
                            <td>
                                <span class="wacu-tree" :style="indent(rule.depth)">
                                    <span v-if="rule.depth" class="wacu-tree-mark" aria-hidden="true">└</span>
                                    <code class="wacu-code">{{ rule.guard_name }}</code>
                                </span>
                                <span v-if="rule.deleted_at" class="wacu-tag wacu-tag-off">
                                    {{ t('rules.deactivated') }}
                                </span>
                            </td>
                            <td>
                                <span>{{ rule.title || '—' }}</span>
                                <span v-if="rule.description" class="wacu-sub">{{ rule.description }}</span>
                            </td>
                            <td class="wacu-center">
                                <span v-if="rule.options" class="wacu-tag wacu-tag-on" :title="rule.options">
                                    {{ t('rules.optionYes') }}
                                </span>
                                <span v-else class="wacu-muted">—</span>
                            </td>
                            <td v-if="write" class="wacu-right wacu-nowrap">
                                <button type="button" class="wacu-btn" @click="openEdit(rule)">
                                    {{ t('app.edit') }}
                                </button>
                                <button
                                    v-if="rule.deleted_at"
                                    type="button"
                                    class="wacu-btn"
                                    @click="restore(rule)"
                                >
                                    {{ t('rules.restore') }}
                                </button>
                                <button v-else type="button" class="wacu-btn" @click="deactivate(rule)">
                                    {{ t('rules.deactivate') }}
                                </button>
                                <button type="button" class="wacu-btn wacu-btn-danger" @click="erase(rule)">
                                    {{ t('rules.deleteForever') }}
                                </button>
                            </td>
                        </tr>

                        <tr v-if="!visible.length">
                            <td :colspan="write ? 4 : 3" class="wacu-center">
                                <p class="wacu-empty">{{ t('app.nothingMatches') }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- One dialog for create and edit: the fields are identical, and two would drift apart. -->
        <Modal
            :open="formOpen"
            :title="editing ? t('rules.edit') : t('rules.add')"
            wide
            @close="formOpen = false"
        >
            <div class="wacu-grid">
                <label class="wacu-field">
                    <span class="wacu-label">{{ t('rules.guardName') }}</span>
                    <input
                        v-model="form.guard_name"
                        type="text"
                        class="wacu-input"
                        placeholder="module.section.action"
                    />
                    <span class="wacu-hint">{{ t('rules.guardNameHint') }}</span>
                </label>

                <label class="wacu-field">
                    <span class="wacu-label">{{ t('rules.parent') }}</span>
                    <select v-model="form.parent_id" class="wacu-input">
                        <option value="0">{{ t('rules.parentNone') }}</option>
                        <option v-for="option in parentOptions" :key="option.id" :value="option.id">
                            {{ '— '.repeat(option.depth) + option.guard_name }}
                        </option>
                    </select>
                </label>

                <label class="wacu-field">
                    <span class="wacu-label">{{ t('rules.ruleTitle') }}</span>
                    <input v-model="form.title" type="text" class="wacu-input" />
                </label>

                <label class="wacu-field">
                    <span class="wacu-label">{{ t('rules.optionSpec') }}</span>
                    <input
                        v-model="form.options"
                        type="text"
                        class="wacu-input"
                        placeholder="nullable|in:read,write"
                    />
                    <span class="wacu-hint">{{ t('rules.optionSpecHint') }}</span>
                </label>

                <label class="wacu-field wacu-field-full">
                    <span class="wacu-label">{{ t('rules.description') }}</span>
                    <input v-model="form.description" type="text" class="wacu-input" />
                </label>
            </div>

            <template #foot>
                <button type="button" class="wacu-btn" @click="formOpen = false">{{ t('app.cancel') }}</button>
                <button type="button" class="wacu-btn wacu-btn-primary" @click="submit">
                    {{ editing ? t('app.save') : t('app.create') }}
                </button>
            </template>
        </Modal>
    </section>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue';
import Icon from '../ui/Icon.vue';
import Modal from '../ui/Modal.vue';
import { del, get, post, put, url } from '../../js/libs/api.js';
import { flatten, indent } from '../../js/libs/tree.js';
import { t } from '../../js/libs/i18n.js';

/**
 * The rule tree: the guard names the rest of the application checks against.
 *
 * Deleting is offered twice over, because the two meanings are genuinely different. Deactivating is
 * reversible and is what you reach for when you are not certain the guard name has left the code: every
 * check starts answering no, the permissions stay, and restoring puts it back. Deleting for good takes
 * the permissions with it and moves the children up a level.
 */
const config = inject('acuConfig');

const card = ref(null);
const rows = ref([]);
const write = ref(false);
const loaded = ref(false);
const search = ref('');
const formOpen = ref(false);
const editing = ref(null);
const form = ref(blank());

function blank() {
    return { guard_name: '', parent_id: 0, options: '', title: '', description: '' };
}

const visible = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) return rows.value;

    return rows.value.filter(
        (rule) =>
            String(rule.guard_name).toLowerCase().includes(term) ||
            String(rule.title || '').toLowerCase().includes(term)
    );
});

// A rule cannot be offered itself as a parent. Its descendants are refused by the server, which is the
// check that counts; this only keeps the obvious mistake out of sight.
const parentOptions = computed(() => rows.value.filter((rule) => rule.id !== editing.value));

async function load() {
    const data = await get(config.routes.rules, { lock: card.value, status: config.noticeHost });

    if (data === null) return;

    rows.value = flatten(data.list || []);
    write.value = !!data.write;
    loaded.value = true;
}

function openCreate() {
    editing.value = null;
    form.value = blank();
    formOpen.value = true;
}

function openEdit(rule) {
    editing.value = rule.id;
    form.value = {
        guard_name: rule.guard_name || '',
        parent_id: rule.parent_id || 0,
        options: rule.options || '',
        title: rule.title || '',
        description: rule.description || '',
    };
    formOpen.value = true;
}

async function submit() {
    const payload = { ...form.value, parent_id: Number(form.value.parent_id) || 0 };

    const data = editing.value
        ? await put(url(config.routes.ruleUpdate, { ID: editing.value }), payload, {
              status: config.noticeHost,
          })
        : await post(config.routes.ruleCreate, payload, { status: config.noticeHost });

    if (data === null) return;

    formOpen.value = false;
    load();
}

async function deactivate(rule) {
    if (!window.confirm(t('rules.confirmDeactivate') + '\n\n' + rule.guard_name)) return;

    const data = await del(url(config.routes.ruleDelete, { ID: rule.id }), {
        status: config.noticeHost,
    });

    if (data !== null) load();
}

async function erase(rule) {
    if (!window.confirm(t('rules.confirmDelete') + '\n\n' + rule.guard_name)) return;

    const data = await del(url(config.routes.ruleDelete, { ID: rule.id }) + '?force=1', {
        status: config.noticeHost,
    });

    if (data !== null) load();
}

async function restore(rule) {
    const data = await post(url(config.routes.ruleRestore, { ID: rule.id }), {}, {
        status: config.noticeHost,
    });

    if (data !== null) load();
}

onMounted(load);
</script>
