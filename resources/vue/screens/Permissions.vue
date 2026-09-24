<template>
    <section>
        <header class="wacu-screen-head">
            <div>
                <button type="button" class="wacu-link" @click="$emit('open', 'owners', null)">
                    <Icon name="back" />
                    {{ t('owners.title') }}
                </button>
                <h2 class="wacu-screen-title">{{ owner.title }}</h2>
                <p class="wacu-muted">
                    <code class="wacu-code">{{ owner.original_id }}</code>
                    <span class="wacu-tag">{{ owner.type_label }}</span>
                    · {{ t('permissions.intro') }}
                </p>
            </div>
            <button v-if="config.screens.explain.enabled" type="button" class="wacu-btn" @click="$emit('open', 'explain', ownerId)">
                {{ t('nav.explain') }}
            </button>
        </header>

        <p class="wacu-muted wacu-foot-note">{{ t('permissions.priorityNote') }}</p>
        <p v-if="config.ruleTree" class="wacu-notice wacu-notice-warn">
            <Icon name="warn" />
            {{ t('permissions.treeNote') }}
        </p>

        <div ref="card" class="wacu-card">
            <div class="wacu-card-head">
                <label class="wacu-check">
                    <input v-model="onlySet" type="checkbox" />
                    <span>{{ t('permissions.onlySet') }}</span>
                </label>
                <label class="wacu-search">
                    <Icon name="search" />
                    <input v-model="search" type="search" class="wacu-input" :placeholder="t('app.filter')" />
                </label>
            </div>

            <div class="wacu-table-wrap">
                <table class="wacu-table">
                    <thead>
                        <tr>
                            <th class="wacu-col-wide">{{ t('permissions.rule') }}</th>
                            <th class="wacu-col-narrow">{{ t('permissions.state') }}</th>
                            <th>{{ t('permissions.entries') }}</th>
                            <th v-if="write" class="wacu-col-actions wacu-right">{{ t('permissions.set') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="rule in visible" :key="rule.id">
                            <td>
                                <span class="wacu-tree" :style="indent(rule.depth)">
                                    <span v-if="rule.depth" class="wacu-tree-mark" aria-hidden="true">└</span>
                                    <code class="wacu-code">{{ rule.guard_name }}</code>
                                    <span v-if="rule.guard_name.endsWith('.self')" class="wacu-tag" :title="t('rules.selfHint')">{{ t('rules.selfTag') }}</span>
                                </span>
                                <span v-if="rule.title" class="wacu-sub" :style="indent(rule.depth, 0.75)">{{ rule.title }}</span>
                                <span v-if="rule.when" class="wacu-sub" :style="indent(rule.depth, 0.75)" :title="t('rules.ruleConditionHint')">
                                    <code class="wacu-code wacu-when">{{ rule.when }}</code>
                                </span>
                            </td>

                            <!-- The label by the five steps, read from the rows. "Depends on the record" is where a condition decides. -->
                            <td>
                                <span v-if="rule.summary.state === 'allowed'" class="wacu-tag wacu-tag-on" :class="{ 'wacu-tag-inherited': !rule.summary.own }">
                                    <Icon v-if="!rule.summary.own" name="tree" />
                                    {{ t('permissions.allowed') }}
                                </span>
                                <span v-else-if="rule.summary.state === 'forbidden'" class="wacu-tag wacu-tag-no" :class="{ 'wacu-tag-inherited': !rule.summary.own }">
                                    <Icon v-if="!rule.summary.own" name="tree" />
                                    {{ t('permissions.forbidden') }}
                                </span>
                                <span v-else-if="rule.summary.state === 'conditional'" class="wacu-tag wacu-tag-warn" :title="t('permissions.conditionalHint')">
                                    {{ t('permissions.conditional') }}
                                </span>
                                <span v-else-if="rule.summary.state === 'options'" class="wacu-tag" :title="t('permissions.byOptionHint')">
                                    {{ t('permissions.byOption') }}
                                </span>
                                <span v-else class="wacu-muted">{{ t('permissions.notSet') }}</span>
                            </td>

                            <!-- Every row that reaches the owner, strongest first. Own rows can be removed here. -->
                            <td>
                                <span class="wacu-chips">
                                    <span
                                        v-for="entry in rule.entries"
                                        :key="entry.from_id + ':' + entry.effect + ':' + entry.option + ':' + entry.when + ':' + (entry.via_rule || '')"
                                        class="wacu-chip"
                                        :class="[entry.effect === 'allow' ? 'wacu-chip-on' : 'wacu-chip-no', { 'wacu-chip-soft': !entry.own || entry.via }]"
                                        :title="entry.via === 'tree' ? t('permissions.viaTreeHint') : ''"
                                    >
                                        <span>
                                            {{ entry.effect === 'allow' ? t('permissions.allowed') : t('permissions.forbidden') }}
                                            <template v-if="entry.option"> · {{ entry.option }}</template>
                                            <template v-if="!entry.own"> · {{ entry.from }}</template>
                                            <template v-if="entry.via === 'tree'"> · {{ t('permissions.viaTree', { rule: entry.via_rule }) }}</template>
                                        </span>
                                        <code v-if="entry.when" class="wacu-code wacu-chip-when" :title="entry.when">{{ entry.when }}</code>
                                        <button
                                            v-if="write && entry.own && !entry.via"
                                            type="button"
                                            class="wacu-chip-x"
                                            :title="t('permissions.removeEntry')"
                                            @click="remove(rule, entry)"
                                        >
                                            <Icon name="cross" />
                                        </button>
                                    </span>
                                    <span v-if="!rule.entries.length" class="wacu-muted">·</span>
                                </span>
                            </td>

                            <td v-if="write" class="wacu-right wacu-nowrap">
                                <button type="button" class="wacu-btn wacu-btn-on-soft" @click="quick(rule, 'allow')">{{ t('permissions.allow') }}</button>
                                <button type="button" class="wacu-btn wacu-btn-no-soft" @click="quick(rule, 'deny')">{{ t('permissions.forbid') }}</button>
                                <button type="button" class="wacu-btn" :title="t('permissions.withConditionHint')" @click="openForm(rule)">
                                    {{ t('permissions.withCondition') }}
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

        <!--
            Everything this owner holds on one rule, in one place: the rows it has, each to change
            or to delete, and the fields for a new row or the changed one. The window stays open
            across writes, so a permit and a prohibition can be set one after the other.
        -->
        <Modal :open="form.open" :title="form.rule ? form.rule.guard_name : ''" wide @close="nav.closeModal()">
            <dl v-if="form.rule && (form.rule.title || form.rule.when)" class="wacu-facts">
                <template v-if="form.rule.title">
                    <dt>{{ t('rules.ruleTitle') }}</dt>
                    <dd>{{ form.rule.title }}</dd>
                </template>
                <template v-if="form.rule.when">
                    <dt>{{ t('rules.ruleCondition') }}</dt>
                    <dd><code class="wacu-code wacu-when">{{ form.rule.when }}</code> <span class="wacu-sub">{{ t('permissions.ruleConditionApplies') }}</span></dd>
                </template>
            </dl>

            <h4 class="wacu-form-title">{{ t('permissions.ownRows') }}</h4>
            <table v-if="ownEntries.length" class="wacu-table wacu-table-tight wacu-own-rows">
                <tbody>
                    <tr v-for="entry in ownEntries" :key="entry.effect + ':' + entry.option + ':' + entry.when" :class="{ 'wacu-row-on': isEditing(entry) }">
                        <td class="wacu-nowrap">
                            <span class="wacu-tag" :class="entry.effect === 'allow' ? 'wacu-tag-on' : 'wacu-tag-no'">
                                {{ entry.effect === 'allow' ? t('permissions.allowed') : t('permissions.forbidden') }}
                            </span>
                        </td>
                        <td class="wacu-nowrap">{{ entry.option || '·' }}</td>
                        <td>
                            <code v-if="entry.when" class="wacu-code wacu-when">{{ entry.when }}</code>
                            <span v-else class="wacu-muted">{{ t('rules.noCondition') }}</span>
                        </td>
                        <td v-if="write" class="wacu-right wacu-nowrap">
                            <button type="button" class="wacu-btn" @click="edit(entry)">{{ t('app.edit') }}</button>
                            <button type="button" class="wacu-btn wacu-btn-danger" @click="drop(entry)">{{ t('app.delete') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="wacu-muted wacu-foot-note">{{ t('permissions.noOwnRows') }}</p>

            <template v-if="write">
                <h4 class="wacu-form-title">{{ form.editing ? t('permissions.changeRow') : t('permissions.newRow') }}</h4>
                <div class="wacu-grid">
                    <label class="wacu-field">
                        <span class="wacu-label">{{ t('permissions.effect') }}</span>
                        <select v-model="form.effect" class="wacu-input">
                            <option value="allow">{{ t('permissions.allow') }}</option>
                            <option value="deny">{{ t('permissions.forbid') }}</option>
                        </select>
                    </label>

                    <label v-if="form.rule && form.rule.options" class="wacu-field">
                        <span class="wacu-label">{{ t('permissions.value') }}</span>
                        <select v-if="spec.type === 'select'" v-model="form.option" class="wacu-input">
                            <option v-if="spec.nullable" value="">{{ t('permissions.valueEmpty') }}</option>
                            <option v-for="value in spec.values" :key="value" :value="value">{{ value }}</option>
                        </select>
                        <input v-else v-model="form.option" :type="spec.type === 'number' ? 'number' : 'text'" class="wacu-input" />
                        <span class="wacu-hint">{{ t('permissions.checkedAgainst') }}: <code class="wacu-code">{{ form.rule.options }}</code></span>
                    </label>

                    <div class="wacu-field wacu-field-full">
                        <span class="wacu-label">{{ t('permissions.condition') }}</span>
                        <ConditionEditor v-model="form.when" :resource="form.rule ? form.rule.resource || '' : ''" @checked="form.valid = $event.valid" />
                        <span class="wacu-hint">{{ t('permissions.conditionHint') }}</span>
                    </div>
                </div>
                <p v-if="form.editing" class="wacu-hint wacu-foot-note">{{ t('permissions.changeRowHint') }}</p>
            </template>

            <template #foot>
                <button type="button" class="wacu-btn" @click="nav.closeModal()">{{ t('app.close') }}</button>
                <template v-if="write">
                    <button v-if="form.editing" type="button" class="wacu-btn" @click="startNew">{{ t('permissions.newRow') }}</button>
                    <button type="button" class="wacu-btn" :class="form.effect === 'allow' ? 'wacu-btn-on' : 'wacu-btn-no'" :disabled="!form.valid" @click="submit">
                        {{ form.editing ? t('app.save') : t('permissions.addRow') }}
                    </button>
                </template>
            </template>
        </Modal>
    </section>
</template>

<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue';
import ConditionEditor from '../ui/ConditionEditor.vue';
import Icon from '../ui/Icon.vue';
import Modal from '../ui/Modal.vue';
import { del, get, post, url } from '../../js/libs/api.js';
import { flatten, indent, readSpec } from '../../js/libs/tree.js';
import { t } from '../../js/libs/i18n.js';

/**
 * What one owner holds, rule by rule.
 *
 * A permit and a prohibition are two rows and may exist together, each with a condition: "may see
 * orders of its team, may not see locked ones". So a row is written and removed on its own, and
 * pressing Allow never turns a prohibition into a permit. The label of a rule follows the five
 * steps of the core, and "depends on the record" says that a condition decides; the explain
 * screen answers for one record.
 */
const props = defineProps({
    ownerId: { type: [Number, String], required: true },
});

defineEmits(['open']);

const config = inject('acuConfig');
const route = inject('acuRoute');
const nav = inject('acuNav');

const card = ref(null);
const rows = ref([]);
const loaded = ref(false);
const owner = ref({ title: '', original_id: '', type_label: '' });
const write = ref(false);
const search = ref('');
const onlySet = ref(false);
const form = ref({ open: false, rule: null, editing: null, effect: 'allow', option: '', when: '', valid: true });

const spec = computed(() => readSpec(form.value.rule ? form.value.rule.options : ''));

/** The rows of the owner itself on the rule of the window, strongest first. */
const ownEntries = computed(() => (form.value.rule ? form.value.rule.entries.filter((entry) => entry.own && !entry.via) : []));

function sameRow(a, b) {
    return !!a && !!b && a.effect === b.effect && (a.option || '') === (b.option || '') && (a.when || '') === (b.when || '');
}

function isEditing(entry) {
    return sameRow(form.value.editing, entry);
}

const visible = computed(() => {
    let list = rows.value;

    if (onlySet.value) list = list.filter((rule) => rule.entries.length);

    const term = search.value.trim().toLowerCase();

    if (term) {
        list = list.filter(
            (rule) => String(rule.guard_name).toLowerCase().includes(term) || String(rule.title || '').toLowerCase().includes(term)
        );
    }

    return list;
});

async function load() {
    const data = await get(url(config.routes.permissions, { OWNER: props.ownerId }), { lock: card.value, status: config.noticeHost });

    if (data === null) return;

    rows.value = flatten(data.list || []);
    owner.value = data.owner || owner.value;
    write.value = !!data.write;
    loaded.value = true;
}

/**
 * The plain buttons: a row without an option and without a condition. A rule with an option
 * needs a value, so those open the form.
 */
function quick(rule, effect) {
    if (rule.options) return openForm(rule, effect);

    send(rule, effect, null, null);
}

/**
 * Opens on what the owner already holds. A button with an effect lands on the row of that effect
 * when there is one; a single own row is put into the fields, so it is read and changed instead
 * of typed again; otherwise the fields are empty for a new row.
 */
function openForm(rule, effect = null) {
    askedEffect = effect;
    nav.openModal('rule', rule.id);
}

/** The effect a button asked for; a pasted link asks for none. */
let askedEffect = null;

function showWindow(rule, effect) {
    form.value = { open: true, rule, editing: null, effect: effect || 'allow', option: '', when: '', valid: true };

    const own = ownEntries.value;
    const first = effect ? own.find((entry) => entry.effect === effect) : own.length === 1 ? own[0] : null;

    if (first) edit(first);
}

// The window is named in the hash ("rule/12"), so the back button closes it and a link opens it.
watch(
    () => [route.modal, route.modalId, loaded.value],
    () => {
        if (route.modal !== 'rule') {
            form.value.open = false;
            return;
        }
        if (!loaded.value || form.value.open) return;

        const rule = rows.value.find((row) => row.id === Number(route.modalId));
        if (!rule) return nav.closeModal();

        showWindow(rule, askedEffect);
        askedEffect = null;
    },
    { immediate: true }
);

/** Put a row into the fields; saving then replaces it. */
function edit(entry) {
    form.value.editing = entry;
    form.value.effect = entry.effect;
    form.value.option = entry.option || '';
    form.value.when = entry.when || '';
    form.value.valid = true;
}

function startNew() {
    form.value.editing = null;
    form.value.effect = 'allow';
    form.value.option = '';
    form.value.when = '';
    form.value.valid = true;
}

/**
 * The window stays open and shows the rows as they are now. The rule object of the window is
 * taken from the reloaded list, so the table of rows and the matrix behind agree.
 */
async function refresh() {
    await load();

    const fresh = form.value.rule ? rows.value.find((rule) => rule.id === form.value.rule.id) : null;
    if (fresh) form.value.rule = fresh;
}

/**
 * A row is keyed by effect and value. Saving with the same key replaces the row; with another
 * key it would sit next to the old one, so the old one is removed first.
 */
async function submit() {
    const current = form.value;
    const option = current.option === '' ? null : current.option;
    const when = current.when.trim() || null;

    if (current.editing && (current.editing.effect !== current.effect || (current.editing.option || null) !== option)) {
        if (!(await dropRow(current.rule, current.editing))) return;
    }

    const data = await post(url(config.routes.permissionSet, { OWNER: props.ownerId }), { rule: current.rule.id, effect: current.effect, option, when }, { status: config.noticeHost });

    if (data === null) return;

    await refresh();
    form.value.editing = ownEntries.value.find((entry) => sameRow(entry, { effect: current.effect, option, when })) || null;
}

async function drop(entry) {
    if (!(await dropRow(form.value.rule, entry))) return;

    if (isEditing(entry)) startNew();
    await refresh();
}

async function dropRow(rule, entry) {
    const data = await del(url(config.routes.permissionDrop, { OWNER: props.ownerId }), { status: config.noticeHost }, { rule: rule.id, effect: entry.effect, option: entry.option });

    return data !== null;
}

async function send(rule, effect, option, when) {
    if (!write.value) return;

    const data = await post(url(config.routes.permissionSet, { OWNER: props.ownerId }), { rule: rule.id, effect, option, when }, { status: config.noticeHost });

    if (data !== null) load();
}

async function remove(rule, entry) {
    if (await dropRow(rule, entry)) load();
}

watch(() => props.ownerId, load);
onMounted(load);
</script>
