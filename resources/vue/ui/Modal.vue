<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="wacu-root wacu-modal"
            :class="themeClass(config)"
            @mousedown.self="$emit('close')"
            @keydown.esc="$emit('close')"
        >
            <div
                ref="box"
                class="wacu-modal-box"
                :class="{ 'wacu-modal-box-wide': wide }"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                tabindex="-1"
            >
                <div class="wacu-modal-head">
                    <h3 class="wacu-modal-title">{{ title }}</h3>
                    <button
                        type="button"
                        class="wacu-icon-btn"
                        :aria-label="t('app.close')"
                        @click="$emit('close')"
                    >
                        <Icon name="cross" />
                    </button>
                </div>

                <div class="wacu-modal-body">
                    <slot />
                </div>

                <div class="wacu-modal-foot">
                    <slot name="foot" />
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { inject, nextTick, ref, watch } from 'vue';
import Icon from './Icon.vue';
import { t } from '../../js/libs/i18n.js';
import { themeClass } from '../../js/libs/theme.js';

/**
 * A dialog, teleported to the body.
 *
 * Teleported because the widget may sit inside a card with `overflow: hidden` on a host page, and a
 * dialog clipped by its container is worse than no dialog. Out there it carries `wacu-root` and the
 * theme class itself — the mount point it came from is no longer an ancestor, so nothing would be
 * inherited.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    wide: { type: Boolean, default: false },
});

defineEmits(['close']);

const config = inject('acuConfig');
const box = ref(null);

// Focus moves into the dialog when it opens, so Escape reaches it and a keyboard user is not left
// tabbing through the page behind.
watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) return;

        await nextTick();

        const first = box.value && box.value.querySelector('input, select, textarea, button');

        (first || box.value).focus();
    }
);
</script>
