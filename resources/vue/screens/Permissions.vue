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
                    · {{ t('permissions.intro') }}
                </p>
            </div>
        </header>

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
                            <th>{{ t('permissions.optionsGranted') }}</th>
                            <th v-if="write" class="wacu-col-actions wacu-right">{{ t('permissions.set') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="rule in visible" :key="rule.id" :class="{ 'wacu-row-off': rule.deleted_at }">
                            <td>
                                <span class="wacu-tree" :style="indent(rule.depth)">
                                    <span v-if="rule.depth" class="wacu-tree-mark" aria-hidden="true">└</span>
                                    <code class="wacu-code">{{ rule.guard_name }}</code>
                                </span>
                                <span v-if="rule.title" class="wacu-sub" :style="indent(rule.depth, 0.75)">
                                    {{ rule.title }}
                                </span>
                                <span v-if="rule.deleted_at" class="wacu-tag wacu-tag-off">
                                    {{ t('rules.deactivated') }}
                                </span>
                            </td>

                            <td>
                                <span v-if="rule.direct === 'allow'" class="wacu-tag wacu-tag-on">
                                    {{ t('permissions.allowed') }}
                                </span>
                                <span v-else-if="rule.direct === 'deny'" class="wacu-tag wacu-tag-no">
                                    {{ t('permissions.forbidden') }}
                                </span>
                                <span
                                    v-else-if="rule.effective"
                                    class="wacu-tag wacu-tag-inherited"
                                    :class="rule.effective === 'allow' ? 'wacu-tag-on' : 'wacu-tag-no'"
                                    :title="t('permissions.inherited')"
                                >
                                    <Icon name="tree" />
                                    {{
                                        rule.effective === 'allow'
                                            ? t('permissions.allowed')
                                            : t('permissions.forbidden')
                                    }}
                                </span>
                                <span v-else class="wacu-muted">{{ t('permissions.notSet') }}</span>
                            </td>

                            <td>
                                <span v-if="!rule.options" class="wacu-muted">—</span>
                                <span v-else class="wacu-chips">
                                    <button
                                        v-for="entry in rule.option_permissions"
                                        :key="String(entry.option)"
                                        type="button"
                                        class="wacu-chip"
                                        :class="entry.permission === 'allow' ? 'wacu-chip-on' : 'wacu-chip-no'"
                                        :disabled="!write"
                                        :title="t('permissions.clearOption')"
                                        @click="send(rule.id, 'remove', entry.option)"
                                    >
                                        {{
                                            entry.option === null || entry.option === ''
                                                ? t('permissions.emptyOption')
                                                : entry.option
                                        }}
                                        <Icon v-if="write" name="cross" />
                                    </button>
                                    <span v-if="!rule.option_permissions.length" class="wacu-muted">
                                        {{ t('permissions.noOptions') }}
                                    </span>
                                </span>
                            </td>

                            <td v-if="write" class="wacu-right wacu-nowrap">
                                <button
                                    type="button"
                                    class="wacu-btn"
                                    :class="{ 'wacu-btn-on': !rule.options && rule.direct === 'allow' }"
                                    @click="onAllow(rule)"
                                >
                                    {{ t('permissions.allow') }}
                                </button>
                                <button
                                    type="button"
                                    class="wacu-btn"
                                    :class="{ 'wacu-btn-no': !rule.options && rule.direct === 'deny' }"
                                    @click="onDeny(rule)"
                                >
                                    {{ t('permissions.forbid') }}
                                </button>
                                <span v-if="rule.options" class="wacu-sub">
                                    {{ t('permissions.asksForValue') }}
                                </span>
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

        <p class="wacu-muted wacu-foot-note">{{ t('permissions.pressAgain') }}</p>

        <!-- Asking for an option value. The input shape is read off the rule's own spec. -->
        <Modal
            :open="ask.open"
            :title="ask.action === 'allow' ? t('permissions.allowOption') : t('permissions.forbidOption')"
            @close="ask.open = false"
        >
            <p class="wacu-muted">
                <code class="wacu-code">{{ ask.rule && ask.rule.guard_name }}</code>
            </p>

            <label class="wacu-field">
                <span class="wacu-label">{{ t('permissions.value') }}</span>

                <select v-if="ask.spec.type === 'select'" v-model="ask.value" class="wacu-input">
                    <option v-if="ask.spec.nullable" value="">{{ t('permissions.valueEmpty') }}</option>
                    <option v-for="value in ask.spec.values" :key="value" :value="value">{{ value }}</option>
                </select>
                <input
                    v-else
                    v-model="ask.value"
                    :type="ask.spec.type === 'number' ? 'number' : 'text'"
                    class="wacu-input"
                />

                <span class="wacu-hint">
                    {{ t('permissions.checkedAgainst') }}:
                    <code class="wacu-code">{{ ask.rule && ask.rule.options }}</code>
                </span>
            </label>

            <template #foot>
                <button type="button" class="wacu-btn" @click="ask.open = false">{{ t('app.cancel') }}</button>
                <button
                    type="button"
                    class="wacu-btn"
                    :class="ask.action === 'allow' ? 'wacu-btn-on' : 'wacu-btn-no'"
                    @click="submitOption"
                >
                    {{ ask.action === 'allow' ? t('permissions.allow') : t('permissions.forbid') }}
                </button>
            </template>
        </Modal>
    </section>
</template>

<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue';
import Icon from '../ui/Icon.vue';
import Modal from '../ui/Modal.vue';
import { get, put, url } from '../../js/libs/api.js';
import { flatten, indent, readSpec } from '../../js/libs/tree.js';
import { t } from '../../js/libs/i18n.js';

/**
 * The permission matrix for one owner.
 *
 * Pressing a state the rule already holds clears it — that is how a grant is taken away, and it is why
 * there is no third button. Cleared, the rule falls back to whatever inheritance says, which the state
 * column then reports as inherited.
 */
const props = defineProps({
    ownerId: { type: [Number, String], required: true },
});

defineEmits(['open']);

const config = inject('acuConfig');

const card = ref(null);
const rows = ref([]);
const owner = ref({ title: '', original_id: '' });
const write = ref(false);
const search = ref('');
const onlySet = ref(false);

/**
 * The "which option value?" dialog.
 *
 * Named `ask` rather than `option`, because in a template a ref unwraps and `option.value` would then
 * mean the field on the object, while in script it means the ref's contents. Same expression, two
 * meanings, one afternoon lost.
 */
const ask = ref(blankOption());

function blankOption() {
    return {
        open: false,
        rule: null,
        action: 'allow',
        value: '',
        spec: { type: 'text', values: [], nullable: false },
    };
}

const visible = computed(() => {
    let list = rows.value;

    if (onlySet.value) {
        list = list.filter((rule) => rule.direct || rule.effective || rule.option_permissions.length);
    }

    const term = search.value.trim().toLowerCase();

    if (term) {
        list = list.filter(
            (rule) =>
                String(rule.guard_name).toLowerCase().includes(term) ||
                String(rule.title || '').toLowerCase().includes(term)
        );
    }

    return list;
});

async function load() {
    const endpoint = url(config.routes.permissions, { OWNER: props.ownerId });
    const data = await get(endpoint, { lock: card.value, status: config.noticeHost });

    if (data === null) return;

    rows.value = flatten(data.list || []);
    owner.value = data.owner || owner.value;
    write.value = !!data.write;
}

/** Pressing the state a rule already has clears it. */
function next(current, button) {
    if (button === 'allow') return current === 'allow' ? 'remove' : 'allow';

    return current ? 'remove' : 'deny';
}

function onAllow(rule) {
    if (rule.options) return askOption(rule, 'allow');

    send(rule.id, next(rule.direct, 'allow'), null);
}

function onDeny(rule) {
    if (rule.options) return askOption(rule, 'deny');

    send(rule.id, next(rule.direct, 'deny'), null);
}

function askOption(rule, action) {
    ask.value = {
        open: true,
        rule,
        action,
        value: '',
        spec: readSpec(rule.options),
    };
}

function submitOption() {
    const rule = ask.value.rule;
    const value = ask.value.value === '' ? null : ask.value.value;

    // Already granted? Then this behaves like pressing the state again, and clears it.
    const existing = rule.option_permissions.find((entry) => String(entry.option) === String(value));
    const action = next(existing ? existing.permission : null, ask.value.action);

    ask.value.open = false;
    send(rule.id, action, value);
}

async function send(ruleId, action, value) {
    if (!write.value) return;

    const endpoint = url(config.routes.permissionSet, { OWNER: props.ownerId, RULE: ruleId });
    const data = await put(endpoint, { permission: action, option: value }, {
        status: config.noticeHost,
    });

    if (data !== null) load();
}

watch(() => props.ownerId, load);
onMounted(load);
</script>
