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
                    <strong>{{ problem.key }}</strong>: {{ problem.reason }}
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

        <Rules v-if="view === 'rules'" @open="open" />
        <Owners v-else-if="view === 'owners'" @open="open" />
        <Permissions v-else-if="view === 'permissions'" :owner-id="ownerId" @open="open" />
        <Inherit v-else-if="view === 'inherit'" :owner-id="ownerId" @open="open" />
        <Explain v-else-if="view === 'explain'" :owner-id="ownerId" />
        <Health v-else-if="view === 'health'" @open="open" />
        <Xacml v-else-if="view === 'xacml'" />
        <p v-else class="wacu-empty">{{ t('app.noScreens') }}</p>

        <Toasts />
    </div>
</template>

<script setup>
import { computed, inject, onMounted, onUnmounted, provide, reactive, ref } from 'vue';
import Icon from './ui/Icon.vue';
import Toasts from './ui/Toasts.vue';
import Explain from './screens/Explain.vue';
import Health from './screens/Health.vue';
import Inherit from './screens/Inherit.vue';
import Owners from './screens/Owners.vue';
import Permissions from './screens/Permissions.vue';
import Rules from './screens/Rules.vue';
import Xacml from './screens/Xacml.vue';
import { t } from '../js/libs/i18n.js';
import { build, parse } from '../js/libs/route.js';

/**
 * The panel shell: the screens of the core, and a matrix reached from the owners.
 *
 *   Rules        the names the application checks against
 *   Owners       the records that hold permissions; from here, the permissions of one owner
 *   Inheritance  who inherits from whom
 *   Why          the explanation of one check
 *   Health       stored conditions and rules against the models of today
 *   XACML        export and import
 *
 * Navigation lives in the hash, see libs/route.js: every screen and every window is a history
 * entry, so the back button works and a link to an editor can be pasted to somebody else.
 */
const config = inject('acuConfig');

// The vocabulary of the condition editor, loaded once for every editor of the page.
provide('acuVocabulary', ref(null));

const view = ref('');
const ownerId = ref(null);

/** The window a screen has open, from the hash: `rules/edit/12` is modal "edit", modalId "12". */
const route = reactive({ view: '', ownerId: null, modal: null, modalId: null });

provide('acuRoute', route);

const problems = computed(() =>
    Object.keys(config.problems || {}).map((key) => ({ key, reason: config.problems[key] }))
);

const tabs = computed(() => {
    const available = [];

    ['rules', 'owners', 'inherit', 'explain', 'health', 'xacml'].forEach((key) => {
        if (config.screens[key] && config.screens[key].enabled) available.push({ key, label: t('nav.' + key) });
    });

    return available;
});

/** The tab to highlight. The permission matrix belongs to the owners screen it is opened from. */
const section = computed(() => (view.value === 'permissions' ? 'owners' : view.value));

/** True while the window on screen was pushed by the panel, so closing it is one step back. */
let pushedModal = false;

/**
 * Go somewhere: a screen, an owner, a window. A history entry, so the back button undoes it.
 *
 * @param {{view?: string, ownerId?: ?number, modal?: ?string, modalId?: ?(string|number)}} next
 * @param {boolean} [replace] Rewrite the current entry instead of adding one.
 */
function navigate(next, replace = false) {
    const target = build({
        view: next.view || route.view,
        ownerId: 'ownerId' in next ? next.ownerId : route.ownerId,
        modal: next.modal || null,
        modalId: next.modal ? next.modalId : null,
    });

    if (target !== window.location.hash) {
        // pushState with a hash fires no hashchange, so the hash is read here as well.
        window.history[replace ? 'replaceState' : 'pushState'](null, '', target);
    }

    readHash();
}

/**
 * @param {string} next
 * @param {?(number|string)} id
 */
function open(next, id) {
    navigate({ view: next, ownerId: id ? Number(id) : null });
}

function openModal(modal, id) {
    pushedModal = true;
    navigate({ modal, modalId: id });
}

/**
 * Closing a window the panel opened is one step back, so the history holds no closed windows.
 * A window that was opened by a pasted link has nothing to go back to, so its entry is rewritten.
 */
function closeModal() {
    if (route.modal && pushedModal) {
        window.history.back();
        return;
    }

    navigate({ modal: null }, true);
}

provide('acuNav', { open, openModal, closeModal });

/**
 * Read the hash, falling back to the first screen that is switched on.
 *
 * A hash naming a disabled screen is ignored rather than honoured: the endpoints behind it would answer
 * 403, and an empty screen explaining nothing is the worst of the options.
 */
function readHash() {
    const found = parse(window.location.hash);
    const enabled = found.view && config.screens[found.view] && config.screens[found.view].enabled;

    if (enabled) {
        view.value = found.view;
        ownerId.value = found.ownerId;

        // The matrix is about one owner and makes no sense without it.
        if (view.value === 'permissions' && !ownerId.value) {
            view.value = tabs.value.length ? tabs.value[0].key : '';
        }
    } else {
        view.value = tabs.value.length ? tabs.value[0].key : '';
        ownerId.value = null;
    }

    route.view = view.value;
    route.ownerId = ownerId.value;
    route.modal = enabled ? found.modal : null;
    route.modalId = enabled ? found.modalId : null;

    if (!route.modal) pushedModal = false;
}

// Resolved before the first render, so the panel does not flash an empty state on its way to the screen
// the hash actually asked for.
readHash();

onMounted(() => {
    // The first entry names the screen it shows, so a reload and the back button agree on it.
    navigate({ view: view.value, ownerId: ownerId.value, modal: route.modal, modalId: route.modalId }, true);
    window.addEventListener('hashchange', readHash);
});

onUnmounted(() => window.removeEventListener('hashchange', readHash));
</script>
