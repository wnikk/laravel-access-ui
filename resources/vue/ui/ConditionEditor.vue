<template>
    <div class="wacu-condition">
        <textarea
            ref="box"
            :value="modelValue"
            class="wacu-input wacu-condition-input"
            :placeholder="placeholder"
            rows="2"
            spellcheck="false"
            @input="onInput"
            @keydown="onKey"
        ></textarea>

        <!-- Names the cursor may be completing: "order." lists columns and relations of that alias. -->
        <ul v-if="suggestions.length" class="wacu-suggest" role="listbox">
            <li
                v-for="(item, index) in suggestions"
                :key="item.text"
                class="wacu-suggest-item"
                :class="{ 'wacu-suggest-on': index === cursor }"
                role="option"
                @mousedown.prevent="accept(item)"
            >
                <code class="wacu-code">{{ item.text }}</code>
                <span v-if="item.note" class="wacu-sub">{{ item.note }}</span>
            </li>
        </ul>

        <p class="wacu-condition-status" :class="statusClass">
            <template v-if="state.checking">{{ t('conditions.checking') }}</template>
            <template v-else-if="state.error">{{ state.error }}</template>
            <template v-else-if="state.text">
                <Icon name="check" />
                <code class="wacu-code">{{ state.text }}</code>
                <span v-if="state.aggregates" class="wacu-sub">{{ t('conditions.aggregatesCost') }}</span>
            </template>
            <template v-else>{{ t('conditions.hint') }}</template>
        </p>
    </div>
</template>

<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue';
import Icon from './Icon.vue';
import { attempt, get } from '../../js/libs/api.js';
import { t } from '../../js/libs/i18n.js';

/**
 * One field for the language of conditions, checked by the core while the administrator types.
 *
 * The check is the compiler the core saves with, reached through one route with a pause after
 * the last keystroke; the message of a refusal is the one the core would give on "Save". The
 * suggestions are the vocabulary of the panel: aliases, their columns and relations, the user
 * and environment attributes, the functions. Approximate on purpose, the core has the last word.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    /** Alias of the model the rule is about; "order.cost" then means that model. */
    resource: { type: String, default: '' },
    placeholder: { type: String, default: 'order.cost > 100 && order.items.count < 3' },
});

const emit = defineEmits(['update:modelValue', 'checked']);

const config = inject('acuConfig');

const box = ref(null);
const cursor = ref(0);
const suggestions = ref([]);
const state = ref({ checking: false, error: '', text: '', aggregates: false });

/** Loaded once per page and shared by every editor on it. */
const vocabulary = inject('acuVocabulary', ref(null));

let timer = null;

const statusClass = computed(() => ({
    'wacu-condition-bad': !!state.value.error,
    'wacu-condition-ok': !state.value.error && !!state.value.text,
}));

async function loadVocabulary() {
    if (vocabulary.value !== null || !config.routes.vocabulary) return;

    const data = await get(config.routes.vocabulary, { silent: true, quiet: true });

    vocabulary.value = data || { resources: {}, user: [], env: [], functions: {}, custom: { attributes: [], functions: [] } };
}

/**
 * What the word before the caret could become.
 *
 * @param {string} text
 * @param {number} at caret position
 * @returns {Array<{text: string, note: string}>}
 */
function complete(text, at) {
    const words = vocabulary.value;

    if (!words) return [];

    const before = text.slice(0, at);
    const match = before.match(/([A-Za-z_][\w.]*)$/);

    if (!match) return [];

    const typed = match[1];
    const parts = typed.split('.');
    const head = parts[0];
    const tail = parts[parts.length - 1].toLowerCase();
    const out = [];

    const push = (name, note) => {
        if (name.toLowerCase().startsWith(tail) && out.length < 12) out.push({ text: name, note: note || '' });
    };

    if (parts.length === 1) {
        Object.keys(words.resources).forEach((alias) => push(alias, t('conditions.alias')));
        if (props.resource) push('resource', t('conditions.theRecord'));
        push('user', t('conditions.theUser'));
        push('env', t('conditions.theEnvironment'));
        Object.keys(words.functions).forEach((group) => words.functions[group].forEach((fn) => push(fn, group)));
        words.custom.functions.forEach((fn) => push(fn, t('conditions.ofTheApplication')));
        ['and', 'or', 'not', 'in', 'between', 'true', 'false', 'null'].forEach((word) => push(word, ''));
        return out;
    }

    if (head === 'user') {
        words.user.forEach((name) => push(name.slice(5), ''));
        return out;
    }

    if (head === 'env') {
        words.env.forEach((name) => push(name.slice(4), ''));
        return out;
    }

    // "order.client.city": walk the chain of relations as far as the vocabulary knows it.
    let alias = head === 'resource' ? props.resource : head;
    let model = words.resources[alias];

    for (let i = 1; i < parts.length - 1 && model; i++) {
        const target = model.relations[parts[i]];
        const next = Object.keys(words.resources).find((key) => words.resources[key].model.split('\\').pop() === target);
        model = next ? words.resources[next] : null;
    }

    if (!model) return [];

    model.columns.forEach((column) => push(column, ''));
    Object.keys(model.relations).forEach((relation) => push(relation, model.relations[relation]));
    push('count', t('conditions.countOf'));

    return out;
}

function refresh() {
    const element = box.value;

    suggestions.value = element ? complete(element.value, element.selectionStart) : [];
    cursor.value = 0;
}

function accept(item) {
    const element = box.value;
    const at = element.selectionStart;
    const before = element.value.slice(0, at).replace(/[A-Za-z_]\w*$/, '');
    const after = element.value.slice(at);
    const next = before + item.text + after;

    emit('update:modelValue', next);
    suggestions.value = [];

    // Put the caret after what was inserted, once Vue has written the new value.
    setTimeout(() => {
        element.focus();
        element.setSelectionRange(before.length + item.text.length, before.length + item.text.length);
    }, 0);

    schedule(next);
}

function onInput(event) {
    emit('update:modelValue', event.target.value);
    refresh();
    schedule(event.target.value);
}

function onKey(event) {
    if (!suggestions.value.length) return;

    if (event.key === 'ArrowDown') {
        cursor.value = (cursor.value + 1) % suggestions.value.length;
        event.preventDefault();
    } else if (event.key === 'ArrowUp') {
        cursor.value = (cursor.value - 1 + suggestions.value.length) % suggestions.value.length;
        event.preventDefault();
    } else if (event.key === 'Tab' || event.key === 'Enter') {
        accept(suggestions.value[cursor.value]);
        event.preventDefault();
    } else if (event.key === 'Escape') {
        suggestions.value = [];
    }
}

/**
 * Ask the core after a pause. An empty field is valid and means "no condition".
 */
function schedule(text) {
    clearTimeout(timer);

    if (!config.routes.conditionCheck) return;

    if (!String(text || '').trim()) {
        state.value = { checking: false, error: '', text: '', aggregates: false };
        emit('checked', { valid: true, text: '' });
        return;
    }

    state.value.checking = true;
    timer = setTimeout(() => check(text), 350);
}

async function check(text) {
    const answer = await attempt('POST', config.routes.conditionCheck, { when: text, resource: props.resource || null }, { silent: true, quiet: true });

    // httpUi draws nothing for this call: the message belongs under the field, not above the card.
    if (answer.ok) {
        state.value = { checking: false, error: '', text: answer.data.text || '', aggregates: !!answer.data.aggregates };
        emit('checked', { valid: true, text: answer.data.text || '' });
    } else {
        const core = answer.errors && answer.errors.core ? answer.errors.core[0] : answer.message;

        state.value = { checking: false, error: core || answer.message, text: '', aggregates: false };
        emit('checked', { valid: false, text: '' });
    }
}

watch(() => props.resource, () => schedule(props.modelValue));

onMounted(() => {
    loadVocabulary();
    if (props.modelValue) schedule(props.modelValue);
});
</script>
