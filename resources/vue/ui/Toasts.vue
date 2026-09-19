<template>
    <Teleport to="body">
        <div
            class="wacu-root wacu-toasts"
            :class="themeClass(config)"
            role="status"
            aria-live="polite"
        >
            <div v-for="item in notices.items" :key="item.id" class="wacu-toast">
                <span class="wacu-toast-text">{{ item.message }}</span>
                <button
                    type="button"
                    class="wacu-icon-btn"
                    :aria-label="t('app.close')"
                    @click="dismiss(item.id)"
                >
                    <Icon name="cross" />
                </button>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { inject } from 'vue';
import Icon from './Icon.vue';
import { dismiss, notices } from '../../js/libs/notify.js';
import { t } from '../../js/libs/i18n.js';
import { themeClass } from '../../js/libs/theme.js';

/**
 * Confirmations, bottom-right.
 *
 * `aria-live="polite"` so the message is announced without interrupting whatever a screen reader was in
 * the middle of. Failures do not come through here — httpUi renders those next to the thing that failed,
 * which is a better place for something that needs acting on.
 */
const config = inject('acuConfig');
</script>
