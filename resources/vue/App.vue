<template>
    <div class="wacu-app">
        <header class="wacu-app-head">
            <h1 class="wacu-app-title">{{ t('app.title') }}</h1>
            <p class="wacu-muted">{{ t('app.intro') }}</p>
        </header>

        <p v-if="problems.length" class="wacu-notice wacu-notice-warn">
            <Icon name="warn" />
            <span>
                {{ t('app.problems') }}
                <span v-for="problem in problems" :key="problem.key" class="wacu-sub">
                    <strong>{{ problem.key }}</strong> — {{ problem.reason }}
                </span>
            </span>
        </p>

        <nav v-if="tabs.length > 1" class="wacu-tabs wacu-app-tabs" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="wacu-tab"
                :class="{ 'wacu-tab-on': tab.key === section }"
                role="tab"
                :aria-selected="tab.key === section"
                @click="open(tab.key, null)"
            >
                {{ tab.label }}
            </button>
        </nav>

        <!--
            Where httpUi puts its error boxes: above the screen, inside the panel.

            A function ref rather than a template ref, because it has to be set before any screen runs its
            first request. Function refs fire while the tree is being patched; `onMounted` of a child runs
            before `onMounted` of this component, so a template ref would still be null the first time a
            screen loaded.
        -->
        <div :ref="(element) => (config.noticeHost = element)" class="wacu-notices"></div>

        <Rules v-if="view === 'rules'" />
        <Owners v-else-if="view === 'owners'" @open="open" />
        <Permissions v-else-if="view === 'permissions'" :owner-id="ownerId" @open="open" />
        <Inherit v-else-if="view === 'inherit'" :owner-id="ownerId" @open="open" />
        <p v-else class="wacu-empty">{{ t('app.noScreens') }}</p>

        <Toasts />
    </div>
</template>

<script setup>
import { computed, inject, onMounted, onUnmounted, ref } from 'vue';
import Icon from './ui/Icon.vue';
import Toasts from './ui/Toasts.vue';
import Inherit from './screens/Inherit.vue';
import Owners from './screens/Owners.vue';
import Permissions from './screens/Permissions.vue';
import Rules from './screens/Rules.vue';
import { t } from '../js/libs/i18n.js';

/**
 * The panel shell: three managers, and a matrix reached from the second.
 *
 *   Rules        the guard names the application checks against
 *   Owners       the records that hold permissions; from here, one owner's permission matrix
 *   Inheritance  who inherits from whom
 *
 * Navigation lives in the hash rather than in component state alone, so a reload — and the reload that
 * follows some writes — comes back to the same place, and a link to one owner's permissions can be pasted
 * to somebody else. Nothing about it needs a router: four views and an owner id fit in a string.
 */
const config = inject('acuConfig');

const view = ref('');
const ownerId = ref(null);

const problems = computed(() =>
    Object.keys(config.problems || {}).map((key) => ({ key, reason: config.problems[key] }))
);

const tabs = computed(() => {
    const available = [];

    if (config.screens.rules.enabled) available.push({ key: 'rules', label: t('nav.rules') });
    if (config.screens.owners.enabled) available.push({ key: 'owners', label: t('nav.owners') });
    if (config.screens.inherit.enabled) available.push({ key: 'inherit', label: t('nav.inherit') });

    return available;
});

/** The tab to highlight. The permission matrix belongs to the owners screen it is opened from. */
const section = computed(() => (view.value === 'permissions' ? 'owners' : view.value));

/**
 * @param {string} next
 * @param {?(number|string)} id
 */
function open(next, id) {
    view.value = next;
    ownerId.value = id ? Number(id) : null;
    writeHash();
}

function writeHash() {
    const path = ownerId.value
        ? '#!/' + view.value + '/' + ownerId.value
        : '#!/' + view.value;

    if (window.location.hash !== path) {
        window.history.replaceState(null, '', path);
    }
}

/**
 * Read the hash, falling back to the first screen that is switched on.
 *
 * A hash naming a disabled screen is ignored rather than honoured: the endpoints behind it would answer
 * 403, and an empty screen explaining nothing is the worst of the options.
 */
function readHash() {
    const raw = String(window.location.hash || '').replace(/^#!?\/?/, '');
    const parts = raw.split('/').filter((part) => part !== '');
    const wanted = parts[0];

    if (wanted && config.screens[wanted] && config.screens[wanted].enabled) {
        view.value = wanted;
        ownerId.value = parts[1] ? Number(parts[1]) || null : null;

        // The matrix is about one owner and makes no sense without it.
        if (view.value === 'permissions' && !ownerId.value) {
            view.value = tabs.value.length ? tabs.value[0].key : '';
        }

        return;
    }

    view.value = tabs.value.length ? tabs.value[0].key : '';
    ownerId.value = null;
}

// Resolved before the first render, so the panel does not flash an empty state on its way to the screen
// the hash actually asked for.
readHash();

onMounted(() => {
    writeHash();
    window.addEventListener('hashchange', readHash);
});

onUnmounted(() => window.removeEventListener('hashchange', readHash));
</script>
