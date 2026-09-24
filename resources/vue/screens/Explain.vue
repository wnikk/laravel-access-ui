<template>
    <section>
        <header class="wacu-screen-head">
            <div>
                <h2 class="wacu-screen-title">{{ t('explain.title') }}</h2>
                <p class="wacu-muted">{{ t('explain.intro') }}</p>
            </div>
        </header>

        <div ref="card" class="wacu-card">
            <div class="wacu-grid wacu-card-body">
                <div class="wacu-field">
                    <span class="wacu-label">{{ t('explain.owner') }}</span>
                    <div class="wacu-assign">
                        <input :value="ownerLabel" type="text" class="wacu-input" readonly :placeholder="t('explain.pickOwner')" />
                        <button type="button" class="wacu-btn" @click="pickerOpen = true"><Icon name="search" /></button>
                    </div>
                </div>

                <label class="wacu-field">
                    <span class="wacu-label">{{ t('explain.ability') }}</span>
                    <input v-model="form.ability" type="text" class="wacu-input" list="wacu-abilities" placeholder="orders.view" />
                    <datalist id="wacu-abilities">
                        <option v-for="name in abilities" :key="name" :value="name"></option>
                    </datalist>
                </label>

                <label class="wacu-field">
                    <span class="wacu-label">{{ t('explain.record') }}</span>
                    <input v-model="form.record" type="text" class="wacu-input" list="wacu-records" placeholder="order:17" />
                    <datalist id="wacu-records">
                        <option v-for="alias in aliases" :key="alias" :value="alias + ':'"></option>
                        <option v-for="alias in aliases" :key="alias + '*'" :value="alias"></option>
                    </datalist>
                    <span class="wacu-hint">{{ t('explain.recordHint') }}</span>
                </label>

                <div class="wacu-field wacu-field-full wacu-right">
                    <button type="button" class="wacu-btn wacu-btn-primary" :disabled="!form.owner || !form.ability" @click="ask">{{ t('explain.ask') }}</button>
                </div>
            </div>
        </div>

        <div v-if="report" class="wacu-card">
            <div class="wacu-card-head">
                <h3 class="wacu-card-title">
                    <code class="wacu-code">{{ report.ability }}</code>
                    <span class="wacu-muted">· {{ t('explain.asked.' + report.asked) }}</span>
                </h3>
                <span class="wacu-tag" :class="decisionClass">{{ decisionText }}</span>
            </div>

            <p v-if="report.stale_cache" class="wacu-notice wacu-notice-warn">
                <Icon name="warn" />
                {{ t('explain.staleCache') }}
            </p>

            <div class="wacu-table-wrap">
                <table class="wacu-table">
                    <thead>
                        <tr>
                            <th class="wacu-col-narrow"></th>
                            <th>{{ t('explain.effect') }}</th>
                            <th>{{ t('explain.from') }}</th>
                            <th>{{ t('explain.condition') }}</th>
                            <th>{{ t('explain.result') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(entry, index) in report.entries" :key="index" :class="{ 'wacu-row-on': entry.decisive, 'wacu-row-soft': entry.result === 'not reached' }">
                            <td class="wacu-center">{{ entry.decisive ? '→' : index + 1 }}</td>
                            <td>
                                <span class="wacu-tag" :class="entry.effect === 'permit' ? 'wacu-tag-on' : 'wacu-tag-no'">
                                    {{ entry.effect === 'permit' ? t('permissions.allowed') : t('permissions.forbidden') }}
                                </span>
                                <span v-if="entry.option" class="wacu-sub">{{ entry.option }}</span>
                                <span v-if="entry.via && entry.via !== 'direct'" class="wacu-sub">{{ t('explain.via.' + entry.via) }}</span>
                            </td>
                            <td>
                                {{ entry.own ? t('explain.own') : entry.from }}
                                <span class="wacu-sub"><code class="wacu-code">{{ entry.rule }}</code></span>
                            </td>
                            <td><code v-if="entry.condition" class="wacu-code wacu-when">{{ entry.condition }}</code><span v-else class="wacu-muted">·</span></td>
                            <td>
                                <span :class="{ 'wacu-muted': entry.result === 'not reached' || entry.result === 'skipped' }">{{ resultText(entry) }}</span>
                                <span v-if="entry.error" class="wacu-sub wacu-condition-bad">{{ entry.error }}</span>
                            </td>
                        </tr>
                        <tr v-if="!report.entries.length">
                            <td colspan="5" class="wacu-center"><p class="wacu-empty">{{ t('explain.nothingSaid') }}</p></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="values.length" class="wacu-card-body">
                <h4 class="wacu-card-title">{{ t('explain.values') }}</h4>
                <table class="wacu-table wacu-table-tight">
                    <tbody>
                        <tr v-for="pair in values" :key="pair.name">
                            <td><code class="wacu-code">{{ pair.name }}</code></td>
                            <td><code class="wacu-code">{{ pair.value }}</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Picker
            :open="pickerOpen"
            :title="t('explain.pickOwner')"
            :endpoint="config.routes.pick"
            scope="all"
            :per-page="config.picker.perPage"
            :empty-text="t('app.nothingMatches')"
            @close="pickerOpen = false"
            @choose="onPick"
        />
    </section>
</template>

<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue';
import Icon from '../ui/Icon.vue';
import Picker from '../ui/Picker.vue';
import { get, query, url } from '../../js/libs/api.js';
import { t } from '../../js/libs/i18n.js';

/**
 * "Why can't Ann see order 17?": the answer of the core, drawn.
 *
 * Every permission that took part, strongest first, an arrow on the one that decided, the values
 * its conditions read, and a warning when the cache disagrees with the database. Nothing here is
 * computed by the panel: the report is what acr:explain prints, as data.
 */
const props = defineProps({
    ownerId: { type: [Number, String], default: null },
});

const config = inject('acuConfig');

const card = ref(null);
const pickerOpen = ref(false);
const abilities = ref([]);
/** Aliases of config access.resources: "order:" completes to "order:17" by hand. */
const aliases = ref([]);
const form = ref({ owner: props.ownerId ? Number(props.ownerId) : null, ownerTitle: '', ability: '', record: '' });
const report = ref(null);

const ownerLabel = computed(() => (form.value.owner ? (form.value.ownerTitle || '#' + form.value.owner) : ''));

const decisionClass = computed(() => {
    if (!report.value) return '';

    return report.value.decision === true ? 'wacu-tag-on' : report.value.decision === false ? 'wacu-tag-no' : '';
});

const decisionText = computed(() => {
    if (!report.value) return '';

    return report.value.decision === true ? t('explain.permitted') : report.value.decision === false ? t('explain.prohibited') : t('explain.nothingSaid');
});

const values = computed(() =>
    Object.keys((report.value && report.value.values) || {}).map((name) => ({ name, value: JSON.stringify(report.value.values[name]) }))
);

function resultText(entry) {
    if (entry.result === true) return t('explain.resultTrue');
    if (entry.result === false) return t('explain.resultFalse');
    if (entry.result === null) return t('explain.resultUnknown');

    const key = 'explain.result.' + String(entry.result).replace(' ', '_');
    const text = t(key);

    return text === key ? String(entry.result) : text;
}

/**
 * A name for an owner that arrived by id, from a link or the permissions screen.
 */
async function loadOwnerTitle() {
    if (!form.value.owner || form.value.ownerTitle) return;

    const data = await get(url(config.routes.ownerHeirs, { OWNER: form.value.owner }) + '?limit=1', { silent: true, quiet: true });

    if (data && data.owner) form.value.ownerTitle = data.owner.title;
}

async function loadAliases() {
    if (!config.routes.vocabulary) return;

    const data = await get(config.routes.vocabulary, { silent: true, quiet: true });

    aliases.value = data && data.resources ? Object.keys(data.resources) : [];
}

async function loadAbilities() {
    if (!config.routes.rules) return;

    const data = await get(config.routes.rules, { silent: true, quiet: true });

    abilities.value = data ? (data.list || []).map((rule) => rule.guard_name) : [];
}

async function ask() {
    const endpoint = query(config.routes.explain, { owner: form.value.owner, ability: form.value.ability.trim(), record: form.value.record.trim() });
    const data = await get(endpoint, { lock: card.value, status: config.noticeHost });

    if (data !== null) report.value = data;
}

function onPick(row) {
    pickerOpen.value = false;
    form.value.owner = row.id;
    form.value.ownerTitle = row.title;
}

watch(
    () => props.ownerId,
    (next) => {
        form.value.owner = next ? Number(next) : form.value.owner;
        form.value.ownerTitle = '';
        loadOwnerTitle();
    }
);

onMounted(() => {
    loadAbilities();
    loadAliases();
    loadOwnerTitle();
});
</script>
