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
                    <input v-model="search" type="search" class="wacu-input" :placeholder="t('rules.filterPlaceholder')" />
                </label>
                <label class="wacu-check">
                    <input v-model="leftovers" type="checkbox" />
                    <span>{{ t('rules.leftovers') }}</span>
                </label>
                <span class="wacu-muted">{{ visible.length }} / {{ rows.length }}</span>
            </div>

            <div class="wacu-table-wrap">
                <table class="wacu-table">
                    <thead>
                        <tr>
                            <th class="wacu-col-wide">{{ t('rules.guardName') }}</th>
                            <th>{{ t('rules.ruleTitle') }}</th>
                            <th>{{ t('rules.conditionAndOptions') }}</th>
                            <th class="wacu-col-narrow wacu-center">{{ t('rules.holders') }}</th>
                            <th v-if="write" class="wacu-col-actions wacu-right">{{ t('app.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="rule in visible" :key="rule.id">
                            <td>
                                <span class="wacu-tree" :style="indent(rule.depth)">
                                    <span v-if="rule.depth" class="wacu-tree-mark" aria-hidden="true">└</span>
                                    <code class="wacu-code">{{ rule.guard_name }}</code>
                                </span>
                                <span class="wacu-sub" :style="indent(rule.depth, 0.75)">
                                    <span class="wacu-tag" :class="'wacu-tag-origin-' + rule.origin" :title="t('rules.origin.' + rule.origin + 'Hint')">
                                        {{ t('rules.origin.' + rule.origin) }}
                                    </span>
                                    <span v-if="rule.resource" class="wacu-tag" :title="t('rules.resourceHint')">{{ rule.resource }}</span>
                                    <span v-if="rule.guard_name.endsWith('.self')" class="wacu-tag" :title="t('rules.selfHint')">{{ t('rules.selfTag') }}</span>
                                </span>
                            </td>
                            <td>
                                <span>{{ rule.title || '' }}</span>
                                <span v-if="rule.description" class="wacu-sub">{{ rule.description }}</span>
                            </td>
                            <td>
                                <code v-if="rule.when" class="wacu-code wacu-when" :title="t('rules.ruleConditionHint')">{{ rule.when }}</code>
                                <span v-if="rule.options" class="wacu-tag wacu-tag-on" :title="rule.options">{{ t('rules.optionYes') }}</span>
                                <span v-if="!rule.when && !rule.options" class="wacu-muted">·</span>
                            </td>
                            <td class="wacu-center">
                                <button v-if="rule.holders_count" type="button" class="wacu-link" @click="showHolders(rule)">
                                    {{ rule.holders_count }}
                                </button>
                                <span v-else class="wacu-muted">0</span>
                            </td>
                            <td v-if="write" class="wacu-right wacu-nowrap">
                                <button type="button" class="wacu-btn" @click="openEdit(rule)">{{ t('app.edit') }}</button>
                                <button
                                    type="button"
                                    class="wacu-btn wacu-btn-danger"
                                    :disabled="rule.managed"
                                    :title="rule.managed ? t('rules.origin.codeHint') : ''"
                                    @click="erase(rule)"
                                >
                                    {{ t('app.delete') }}
                                </button>
                            </td>
                        </tr>

                        <tr v-if="!visible.length">
                            <td :colspan="write ? 5 : 4" class="wacu-center">
                                <p class="wacu-empty">{{ t('app.nothingMatches') }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!--
            One dialog for create and edit. What the code was written against is shown as facts
            when the rule comes with code, and as fields otherwise; what reads in the panel is a
            field for every rule. The two groups are apart, so what is open is what has an input.
        -->
        <Modal :open="formOpen" :title="editing ? t('rules.edit') : t('rules.add')" wide @close="nav.closeModal()">
            <template v-if="managed">
                <h4 class="wacu-form-title">{{ t('rules.asWritten') }}</h4>
                <p class="wacu-hint wacu-form-note"><Icon name="lock" /> {{ t('rules.managedNotice') }}</p>
                <dl class="wacu-facts">
                    <dt>{{ t('rules.guardName') }}</dt>
                    <dd><code class="wacu-code">{{ form.guard_name }}</code></dd>
                    <dt>{{ t('rules.resource') }}</dt>
                    <dd>{{ form.resource || t('rules.resourceNone') }}</dd>
                    <dt>{{ t('rules.ruleCondition') }}</dt>
                    <dd><code class="wacu-code wacu-when">{{ form.when || t('rules.noCondition') }}</code></dd>
                </dl>

                <h4 class="wacu-form-title">{{ t('rules.howItReads') }}</h4>
            </template>

            <div class="wacu-grid">
                <label v-if="!managed" class="wacu-field wacu-field-full">
                    <span class="wacu-label">{{ t('rules.guardName') }}</span>
                    <input v-model="form.guard_name" type="text" class="wacu-input" placeholder="module.section.action" />
                    <span class="wacu-hint">{{ t('rules.guardNameHint') }}</span>
                </label>

                <label class="wacu-field">
                    <span class="wacu-label">{{ t('rules.ruleTitle') }}</span>
                    <input v-model="form.title" type="text" class="wacu-input" />
                </label>

                <label class="wacu-field">
                    <span class="wacu-label">{{ t('rules.parent') }}</span>
                    <select v-model="form.parent_id" class="wacu-input">
                        <option value="0">{{ t('rules.parentNone') }}</option>
                        <option v-for="option in parentOptions" :key="option.id" :value="option.id">
                            {{ '· '.repeat(option.depth) + option.guard_name }}
                        </option>
                    </select>
                    <span class="wacu-hint">{{ t('rules.parentHint') }}</span>
                </label>

                <label class="wacu-field wacu-field-full">
                    <span class="wacu-label">{{ t('rules.description') }}</span>
                    <input v-model="form.description" type="text" class="wacu-input" />
                </label>
            </div>

            <h4 v-if="!managed" class="wacu-form-title">{{ t('rules.whatItDoes') }}</h4>

            <div class="wacu-grid">
                <label class="wacu-field" :class="{ 'wacu-field-full': managed }">
                    <span class="wacu-label">{{ t('rules.optionSpec') }}</span>
                    <input v-model="form.options" type="text" class="wacu-input" placeholder="nullable|in:read,write" />
                    <span class="wacu-hint">{{ t('rules.optionSpecHint') }}</span>
                </label>

                <label v-if="!managed" class="wacu-field">
                    <span class="wacu-label">{{ t('rules.resource') }}</span>
                    <select v-model="form.resource" class="wacu-input">
                        <option value="">{{ t('rules.resourceNone') }}</option>
                        <option v-for="alias in resources" :key="alias" :value="alias">{{ alias }}</option>
                    </select>
                    <span class="wacu-hint">{{ t('rules.resourceHint') }}</span>
                </label>

                <div v-if="!managed" class="wacu-field wacu-field-full">
                    <span class="wacu-label">{{ t('rules.ruleCondition') }}</span>
                    <ConditionEditor v-model="form.when" :resource="form.resource" />
                    <span class="wacu-hint">{{ t('rules.ruleConditionHint') }}</span>
                </div>
            </div>

            <template #foot>
                <button type="button" class="wacu-btn" @click="nav.closeModal()">{{ t('app.cancel') }}</button>
                <button type="button" class="wacu-btn wacu-btn-primary" @click="submit">
                    {{ editing ? t('app.save') : t('app.create') }}
                </button>
            </template>
        </Modal>

        <!-- Who holds a rule: shown on request, and shown when deleting was refused for that reason. -->
        <Modal :open="holders.open" :title="t('rules.holdersOf', { rule: holders.rule })" @close="nav.closeModal()">
            <p v-if="holders.refused" class="wacu-notice wacu-notice-warn">
                <Icon name="warn" />
                {{ t('rules.inUseNotice') }}
            </p>
            <ul class="wacu-assign-list">
                <li v-for="row in holders.rows" :key="row.id + ':' + row.effect + ':' + row.option" class="wacu-assign-item">
                    <span class="wacu-assign-main">
                        <button type="button" class="wacu-link" @click="$emit('open', 'permissions', row.id)">{{ row.title }}</button>
                        <span class="wacu-tag">{{ row.type_label }}</span>
                        <span class="wacu-tag" :class="row.effect === 'allow' ? 'wacu-tag-on' : 'wacu-tag-no'">
                            {{ row.effect === 'allow' ? t('permissions.allowed') : t('permissions.forbidden') }}
                            <template v-if="row.option"> · {{ row.option }}</template>
                        </span>
                        <code v-if="row.when" class="wacu-code wacu-when">{{ row.when }}</code>
                    </span>
                </li>
            </ul>
            <p v-if="holders.meta.total > holders.rows.length" class="wacu-muted">
                {{ t('app.showingOf', { shown: holders.rows.length, total: holders.meta.total }) }}
            </p>
        </Modal>
    </section>
</template>

<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue';
import ConditionEditor from '../ui/ConditionEditor.vue';
import Icon from '../ui/Icon.vue';
import Modal from '../ui/Modal.vue';
import { attempt, get, post, put, url } from '../../js/libs/api.js';
import { flatten, indent } from '../../js/libs/tree.js';
import { t } from '../../js/libs/i18n.js';

/**
 * The rule tree: the names the rest of the application checks against.
 *
 * Every rule has an origin. A rule of code is created and removed by a migration, and here it may
 * be reworded and its options edited, nothing else: the name, the model and the condition are what
 * the code was written against. A rule made here is "custom", for abilities whose names the code
 * builds at run time. Deleting is for good, and the core refuses it while somebody holds the rule;
 * the refusal lists who, so the administrator can go there instead of guessing.
 */
defineEmits(['open']);

const config = inject('acuConfig');
const route = inject('acuRoute');
const nav = inject('acuNav');

const card = ref(null);
const rows = ref([]);
const resources = ref([]);
const write = ref(false);
const loaded = ref(false);
const search = ref('');
const leftovers = ref(false);
const formOpen = ref(false);
const editing = ref(null);
const managed = ref(false);
const form = ref(blank());
const holders = ref({ open: false, rule: '', rows: [], meta: { total: 0 }, refused: false });

function blank() {
    return { guard_name: '', parent_id: 0, options: '', title: '', description: '', resource: '', when: '' };
}

const visible = computed(() => {
    const term = search.value.trim().toLowerCase();
    let list = rows.value;

    // Rules that 2.x had soft deleted came back from the upgrade under this prefix.
    if (leftovers.value) list = list.filter((rule) => String(rule.guard_name).startsWith('deprecated__'));

    if (!term) return list;

    return list.filter(
        (rule) =>
            String(rule.guard_name).toLowerCase().includes(term) ||
            String(rule.title || '').toLowerCase().includes(term) ||
            String(rule.when || '').toLowerCase().includes(term)
    );
});

// A rule is not offered itself as a parent. Its descendants are refused by the server, which is the check that counts.
const parentOptions = computed(() => rows.value.filter((rule) => rule.id !== editing.value));

async function load() {
    const data = await get(config.routes.rules, { lock: card.value, status: config.noticeHost });

    if (data === null) return;

    rows.value = flatten(data.list || []);
    resources.value = data.resources || [];
    write.value = !!data.write;
    loaded.value = true;
}

/*
 * The windows of this screen are named in the hash ("new", "edit/12", "holders/12") and opened by
 * it, so the back button closes them and a pasted link opens an editor straight away. A button
 * only changes the hash; what follows is the same whichever way the hash got there.
 */
function openCreate() {
    nav.openModal('new');
}

function openEdit(rule) {
    nav.openModal('edit', rule.id);
}

/** Set when deleting was refused, so the window of holders says why it opened. */
let refusedFor = null;

function showForm(rule) {
    editing.value = rule ? rule.id : null;
    managed.value = !!(rule && rule.managed);
    form.value = rule
        ? {
              guard_name: rule.guard_name || '',
              parent_id: rule.parent_id || 0,
              options: rule.options || '',
              title: rule.title || '',
              description: rule.description || '',
              resource: rule.resource || '',
              when: rule.when || '',
          }
        : blank();
    formOpen.value = true;
}

watch(
    () => [route.modal, route.modalId, loaded.value],
    async () => {
        if (route.modal === 'new') {
            holders.value.open = false;
            showForm(null);
            return;
        }

        const rule = loaded.value ? rows.value.find((row) => row.id === Number(route.modalId)) : null;

        if (route.modal === 'edit' || route.modal === 'holders') {
            if (!loaded.value) return;
            if (!rule) return nav.closeModal();

            if (route.modal === 'edit') {
                holders.value.open = false;
                showForm(rule);
            } else {
                formOpen.value = false;
                await loadHolders(rule, refusedFor === rule.id);
                refusedFor = null;
            }
            return;
        }

        formOpen.value = false;
        holders.value.open = false;
    },
    { immediate: true }
);

async function submit() {
    const payload = { ...form.value, parent_id: Number(form.value.parent_id) || 0 };

    const data = editing.value
        ? await put(url(config.routes.ruleUpdate, { ID: editing.value }), payload, { status: config.noticeHost })
        : await post(config.routes.ruleCreate, payload, { status: config.noticeHost });

    if (data === null) return;

    nav.closeModal();
    load();
}

async function erase(rule) {
    if (!window.confirm(t('rules.confirmDelete') + '\n\n' + rule.guard_name)) return;

    const answer = await attempt('DELETE', url(config.routes.ruleDelete, { ID: rule.id }), {}, { status: config.noticeHost });

    if (answer.ok) return load();

    // Held by somebody: the refusal names them, and that is the next thing to look at.
    if (answer.code === 'rule_in_use') {
        refusedFor = rule.id;
        nav.openModal('holders', rule.id);
    }
}

function showHolders(rule) {
    nav.openModal('holders', rule.id);
}

async function loadHolders(rule, refused) {
    const data = await get(url(config.routes.ruleHolders, { ID: rule.id }) + '?limit=100', { status: config.noticeHost });

    if (data === null) return nav.closeModal();

    holders.value = { open: true, rule: rule.guard_name, rows: data.rows || [], meta: data.meta || { total: 0 }, refused };
}

onMounted(load);
</script>
