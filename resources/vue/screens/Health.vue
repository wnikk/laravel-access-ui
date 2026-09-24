<template>
    <section>
        <header class="wacu-screen-head">
            <div>
                <h2 class="wacu-screen-title">{{ t('health.title') }}</h2>
                <p class="wacu-muted">{{ t('health.intro') }}</p>
            </div>
            <div class="wacu-nowrap">
                <button type="button" class="wacu-btn" @click="load">{{ t('health.check') }}</button>
                <button v-if="write" type="button" class="wacu-btn" :title="t('health.fixHint')" @click="fix">{{ t('health.fix') }}</button>
                <button v-if="write" type="button" class="wacu-btn" :title="t('health.flushHint')" @click="flush">{{ t('health.flush') }}</button>
            </div>
        </header>

        <div ref="card" class="wacu-card">
            <p v-if="loaded && !problems.length" class="wacu-notice wacu-notice-ok">
                <Icon name="check" />
                {{ t('health.allGood') }}
            </p>

            <div v-else-if="problems.length" class="wacu-table-wrap">
                <table class="wacu-table">
                    <thead>
                        <tr>
                            <th class="wacu-col-narrow">{{ t('health.code') }}</th>
                            <th>{{ t('health.where') }}</th>
                            <th class="wacu-col-wide">{{ t('health.problem') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in problems" :key="index">
                            <td><span class="wacu-tag wacu-tag-warn">{{ t('health.codes.' + row.code) }}</span></td>
                            <td>
                                <button v-if="row.subject === 'permission' && row.owner_id" type="button" class="wacu-link" @click="$emit('open', 'permissions', row.owner_id)">{{ row.where }}</button>
                                <span v-else>{{ row.where }}</span>
                            </td>
                            <td>{{ row.problem }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>

<script setup>
import { inject, onMounted, ref } from 'vue';
import Icon from '../ui/Icon.vue';
import { get, post } from '../../js/libs/api.js';
import { t } from '../../js/libs/i18n.js';

/**
 * What acr:lint finds, drawn. Conditions are checked when they are saved; then a migration renames
 * a column or a model leaves config, and nothing says so until somebody loses access. "Fix" saves
 * again what only changed its column types. The cache button is for changes made past the core,
 * straight in the tables; every change through the panel turns the cache over by itself.
 */
defineEmits(['open']);

const config = inject('acuConfig');

const card = ref(null);
const problems = ref([]);
const write = ref(false);
const loaded = ref(false);

async function load() {
    const data = await get(config.routes.health, { lock: card.value, status: config.noticeHost });

    if (data === null) return;

    problems.value = data.problems || [];
    write.value = !!data.write;
    loaded.value = true;
}

async function fix() {
    const data = await post(config.routes.healthFix, {}, { lock: card.value, status: config.noticeHost });

    if (data !== null) load();
}

async function flush() {
    await post(config.routes.cacheFlush, {}, { status: config.noticeHost });
}

onMounted(load);
</script>
