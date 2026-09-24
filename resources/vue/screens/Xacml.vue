<template>
    <section>
        <header class="wacu-screen-head">
            <div>
                <h2 class="wacu-screen-title">{{ t('xacml.title') }}</h2>
                <p class="wacu-muted">{{ t('xacml.intro') }}</p>
            </div>
            <a class="wacu-btn wacu-btn-primary" :href="config.routes.xacmlExport">{{ t('xacml.download') }}</a>
        </header>

        <div ref="card" class="wacu-card">
            <div class="wacu-card-body">
                <label class="wacu-field">
                    <span class="wacu-label">{{ t('xacml.file') }}</span>
                    <input ref="fileInput" type="file" class="wacu-input" accept=".xml,text/xml,application/xml" @change="onFile" />
                    <span class="wacu-hint">{{ t('xacml.fileHint') }}</span>
                </label>

                <div class="wacu-nowrap">
                    <button type="button" class="wacu-btn wacu-btn-primary" :disabled="!file" @click="check">{{ t('xacml.check') }}</button>
                </div>
            </div>
        </div>

        <!-- The plan: what the document would change. Nothing has been written when this shows. -->
        <div v-if="plan" class="wacu-card">
            <div class="wacu-card-head">
                <h3 class="wacu-card-title">
                    {{ t('xacml.plan') }}
                    <span class="wacu-muted">· {{ plan.own ? t('xacml.ownDocument') : t('xacml.foreignDocument') }}</span>
                    <span v-if="plan.exported_at" class="wacu-muted">&nbsp;· {{ t('xacml.exportedAt', { date: exportedAt }) }}</span>
                </h3>
                <span class="wacu-muted">{{ summaryText }}</span>
            </div>

            <div v-if="plan.errors.length" class="wacu-card-body">
                <p class="wacu-notice wacu-notice-warn">
                    <Icon name="warn" />
                    {{ t('xacml.hasErrors') }}
                </p>
                <ul class="wacu-plain-list">
                    <li v-for="(error, index) in plan.errors" :key="'e' + index"><code class="wacu-code">{{ error[0] }}</code> {{ error[1] }}</li>
                </ul>
            </div>

            <div v-if="plan.warnings.length" class="wacu-card-body">
                <ul class="wacu-plain-list wacu-muted">
                    <li v-for="(warning, index) in plan.warnings" :key="'w' + index"><code class="wacu-code">{{ warning[0] }}</code> {{ warning[1] }}</li>
                </ul>
            </div>

            <div class="wacu-card-head">
                <label class="wacu-check">
                    <input v-model="showSame" type="checkbox" />
                    <span>{{ t('xacml.showSame') }}</span>
                </label>
            </div>

            <div class="wacu-table-wrap">
                <table class="wacu-table">
                    <thead>
                        <tr>
                            <th class="wacu-col-narrow">{{ t('xacml.kind') }}</th>
                            <th class="wacu-col-narrow">{{ t('xacml.action') }}</th>
                            <th>{{ t('xacml.what') }}</th>
                            <th>{{ t('xacml.database') }}</th>
                            <th>{{ t('xacml.document') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(change, index) in changes" :key="index" :class="{ 'wacu-row-soft': change.action === 'same' }">
                            <td>{{ t('xacml.kinds.' + change.kind) }}</td>
                            <td><span class="wacu-tag" :class="actionClass(change.action)">{{ t('xacml.actions.' + change.action) }}</span></td>
                            <td>{{ change.what }}</td>
                            <td>
                                <code v-if="change.action === 'differs' || change.action === 'only_in_database'" class="wacu-code wacu-when">{{ change.database || change.document }}</code>
                                <span v-else-if="change.action === 'same'" class="wacu-muted">{{ change.document }}</span>
                                <span v-else class="wacu-muted">·</span>
                            </td>
                            <td>
                                <code v-if="change.action === 'differs' || change.action === 'create'" class="wacu-code wacu-when">{{ change.document }}</code>
                                <span v-else-if="change.action === 'only_in_database'" class="wacu-muted">{{ t('xacml.absent') }}</span>
                                <span v-else class="wacu-muted">·</span>
                            </td>
                        </tr>
                        <tr v-if="!changes.length">
                            <td colspan="5" class="wacu-center"><p class="wacu-empty">{{ t('xacml.nothingToChange') }}</p></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="write" class="wacu-card-body">
                <label class="wacu-check">
                    <input v-model="options.replace" type="checkbox" />
                    <span>{{ t('xacml.replace') }}</span>
                </label>
                <p v-if="options.replace" class="wacu-notice wacu-notice-warn">
                    <Icon name="warn" />
                    {{ t('xacml.replaceWarning', { count: counts.differs }) }}
                </p>

                <label v-if="plan.errors.length" class="wacu-check">
                    <input v-model="options.partial" type="checkbox" />
                    <span>{{ t('xacml.partial') }}</span>
                </label>
                <p v-if="options.partial" class="wacu-notice wacu-notice-warn">
                    <Icon name="warn" />
                    {{ t('xacml.partialWarning') }}
                </p>

                <div class="wacu-right">
                    <button type="button" class="wacu-btn wacu-btn-primary" :disabled="!canImport" @click="doImport">
                        {{ t('xacml.import', { count: counts.create + (options.replace ? counts.differs : 0) }) }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="report" class="wacu-card">
            <div class="wacu-card-head">
                <h3 class="wacu-card-title">{{ report.written ? t('xacml.written') : t('xacml.notWritten') }}</h3>
            </div>
            <div class="wacu-card-body">
                <span v-for="(count, kind) in report.applied" :key="kind" class="wacu-figure">
                    <span class="wacu-figure-value">{{ count }}</span>
                    <span class="wacu-figure-label">{{ t('xacml.applied.' + kind) }}</span>
                </span>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, inject, ref } from 'vue';
import Icon from '../ui/Icon.vue';
import { upload } from '../../js/libs/api.js';
import { t } from '../../js/libs/i18n.js';

/**
 * XACML 3.0: download the export, look at what a document would change, then import it.
 *
 * "Look first" is the design: the plan is what the core computes for check(), every row marked
 * create, same, differs or only in the database, and the two versions of what differs side by
 * side. The date of the export sits in the heading, and the warnings of the core say when the
 * database moved on after it. Import executes that very plan. "Replace" and "partial" are choices with a cost, so each
 * sits behind its own box with its warning, and the button counts what it is about to write.
 */
const config = inject('acuConfig');

const card = ref(null);
const fileInput = ref(null);
const file = ref(null);
const plan = ref(null);
const report = ref(null);
const showSame = ref(false);
const options = ref({ replace: false, partial: false });

const write = computed(() => !!config.screens.xacml.write);

const changes = computed(() => (plan.value ? plan.value.changes.filter((change) => showSame.value || change.action !== 'same') : []));

const counts = computed(() => {
    const out = { create: 0, same: 0, differs: 0, only_in_database: 0 };

    (plan.value ? plan.value.changes : []).forEach((change) => {
        out[change.action] = (out[change.action] || 0) + 1;
    });

    return out;
});

// The date the document was exported, in the locale of the page; the report says so for an export of this package.
const exportedAt = computed(() => (plan.value && plan.value.exported_at ? new Date(plan.value.exported_at).toLocaleString() : ''));

const summaryText = computed(() =>
    Object.keys(counts.value)
        .filter((action) => counts.value[action])
        .map((action) => counts.value[action] + ' ' + t('xacml.actions.' + action).toLowerCase())
        .join(' · ')
);

// Refused while the plan holds an error, unless "partial" was chosen knowingly. A lost prohibition means wider access.
const canImport = computed(() => !!plan.value && (!plan.value.errors.length || options.value.partial));

function onFile(event) {
    file.value = event.target.files && event.target.files[0] ? event.target.files[0] : null;
    plan.value = null;
    report.value = null;
}

function form() {
    const data = new FormData();

    data.append('policy', file.value);
    if (options.value.replace) data.append('replace', '1');
    if (options.value.partial) data.append('partial', '1');

    return data;
}

async function check() {
    report.value = null;
    options.value = { replace: false, partial: false };

    const data = await upload(config.routes.xacmlCheck, form(), { lock: card.value, status: config.noticeHost });

    if (data !== null) plan.value = data;
}

async function doImport() {
    if (!window.confirm(t('xacml.confirmImport'))) return;

    const data = await upload(config.routes.xacmlImport, form(), { lock: card.value, status: config.noticeHost });

    if (data === null) return;

    report.value = data;
    plan.value = null;
}

function actionClass(action) {
    return { create: 'wacu-tag-on', differs: 'wacu-tag-warn', only_in_database: 'wacu-tag-inherited', same: '' }[action] || '';
}
</script>
