<template>
    <div ref="card" class="wacu-card wacu-widget" :class="{ 'wacu-widget-compact': compact }">
        <div class="wacu-card-head">
            <h3 class="wacu-card-title">{{ heading }}</h3>
        </div>

        <div ref="notices" class="wacu-notices"></div>

        <!--
            Numbers, not contents. How much this account holds is worth knowing on a user page;
            which rules those are is the job of the permissions screen.

            The gap between the figures is the useful part: something of its own with nothing
            assigned is an account somebody hand-tuned. "Depends on the record" counts the rows
            that carry a condition; what they answer for one record is a question for "explain".
        -->
        <div class="wacu-figures">
            <span class="wacu-figure" :title="t('widget.inheritedHint')">
                <span class="wacu-figure-value">{{ permissions.inherited }}</span>
                <span class="wacu-figure-label">{{ t('widget.inherited') }}</span>
            </span>
            <span v-if="permissions.own" class="wacu-figure" :title="t('widget.ownHint')">
                <span class="wacu-figure-value">{{ permissions.own }}</span>
                <span class="wacu-figure-label">{{ t('widget.own') }}</span>
            </span>
            <span v-if="permissions.conditional" class="wacu-figure" :title="t('widget.conditionalHint')">
                <span class="wacu-figure-value">{{ permissions.conditional }}</span>
                <span class="wacu-figure-label">{{ t('widget.conditional') }}</span>
            </span>
            <span v-if="permissions.forbidden" class="wacu-figure">
                <span class="wacu-figure-value">{{ permissions.forbidden }}</span>
                <span class="wacu-figure-label">{{ t('widget.forbidden') }}</span>
            </span>
        </div>

        <ul class="wacu-assign-list">
            <li
                v-for="row in list"
                :key="row.id"
                class="wacu-assign-item"
                :class="{ 'wacu-row-soft': !row.direct }"
            >
                <span class="wacu-assign-main">
                    <span class="wacu-assign-name">{{ row.title }}</span>
                    <span class="wacu-tag">{{ row.type_label }}</span>
                    <span v-if="!row.direct" class="wacu-sub" :title="t('inherit.indirectHint')">
                        {{ via(row) }}
                    </span>
                </span>

                <button
                    v-if="write && row.direct"
                    type="button"
                    class="wacu-btn wacu-btn-danger"
                    @click="remove(row)"
                >
                    {{ t('app.remove') }}
                </button>
            </li>

            <li v-if="loaded && !list.length" class="wacu-assign-item">
                <p class="wacu-empty">{{ t('widget.nothingAssigned') }}</p>
            </li>
        </ul>

        <div v-if="write" class="wacu-assign">
            <select
                v-if="!available.truncated"
                v-model="chosen"
                class="wacu-input"
                :disabled="!available.list.length"
                :aria-label="t('inherit.add')"
            >
                <option value="">
                    {{ available.list.length ? t('inherit.choose') : t('inherit.nothingToAdd') }}
                </option>
                <option v-for="item in available.list" :key="item.id" :value="item.id">
                    {{ item.title }} — {{ item.type_label }}
                </option>
            </select>

            <button
                v-if="!available.truncated"
                type="button"
                class="wacu-btn wacu-btn-primary"
                :disabled="!chosen"
                @click="assign"
            >
                <Icon name="plus" />
                {{ t('inherit.add') }}
            </button>

            <!-- Too many to put in a dropdown: search instead. -->
            <button v-else type="button" class="wacu-btn wacu-btn-primary" @click="pickerOpen = true">
                <Icon name="search" />
                {{ t('inherit.add') }}
            </button>
        </div>

        <Picker
            :open="pickerOpen"
            :title="t('inherit.chooseTitle')"
            :endpoint="config.routes.pick"
            scope="assignable"
            :exclude="excluded"
            :per-page="config.picker.perPage"
            :empty-text="t('inherit.nothingToAdd')"
            @close="pickerOpen = false"
            @choose="onPick"
        />

        <Toasts />
    </div>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue';
import Icon from './ui/Icon.vue';
import Picker from './ui/Picker.vue';
import Toasts from './ui/Toasts.vue';
import { useInheritance } from '../js/libs/useInheritance.js';
import { t } from '../js/libs/i18n.js';

/**
 * One card for a page that is already showing somebody.
 *
 * Where this account takes its rights from, what may still be added, and how much it ends up holding.
 * The list includes what the direct assignments drag in behind them — an account inheriting from a role
 * that inherits from another holds both — with the indirect ones marked and not removable here: the
 * thing to change is whichever direct assignment is producing them.
 *
 * Everything it does, the panel's inheritance screen already did, asked from the other end. Same three
 * endpoints, same composable.
 *
 * The heading names the kind of thing that can be assigned, taken from the configuration, so an
 * installation that calls them groups does not end up with a card labelled "roles".
 */
const props = defineProps({
    ownerId: { type: [Number, String], required: true },
    title: { type: String, default: '' },
    compact: { type: Boolean, default: false },
});

const config = inject('acuConfig');

const card = ref(null);
const notices = ref(null);
const chosen = ref('');
const pickerOpen = ref(false);

const { list, available, permissions, write, loaded, linked, via, load, add, remove } = useInheritance(
    config,
    Number(props.ownerId),
    'parents',
    () => ({ lock: card.value, status: notices.value })
);

const heading = computed(() => {
    if (props.title) return props.title;

    const sources = (config.entities || []).filter((entity) => entity.assignable);

    return sources.length === 1 ? sources[0].label : t('widget.title');
});

const excluded = computed(() => [Number(props.ownerId)].concat(linked.value));

async function assign() {
    if (await add(chosen.value)) chosen.value = '';
}

async function onPick(row) {
    pickerOpen.value = false;
    await add(row.id);
}

onMounted(load);
</script>
