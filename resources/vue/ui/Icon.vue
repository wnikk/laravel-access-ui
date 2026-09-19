<template>
    <svg
        class="wacu-icon"
        viewBox="0 0 16 16"
        fill="none"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
    >
        <template v-if="name === 'plus'">
            <path d="M8 3v10M3 8h10" />
        </template>
        <template v-else-if="name === 'search'">
            <circle cx="7" cy="7" r="4" />
            <path d="M10 10l3 3" />
        </template>
        <template v-else-if="name === 'trash'">
            <path d="M3 5h10M6.5 5V3.5h3V5M5 5l.5 8h5l.5-8" />
        </template>
        <template v-else-if="name === 'check'">
            <path d="M3 8.5l3 3 7-7" />
        </template>
        <template v-else-if="name === 'cross'">
            <path d="M4 4l8 8M12 4l-8 8" />
        </template>
        <template v-else-if="name === 'lock'">
            <rect x="3.5" y="7" width="9" height="6.5" rx="1" />
            <path d="M5.5 7V5a2.5 2.5 0 015 0v2" />
        </template>
        <template v-else-if="name === 'tree'">
            <path d="M3 3v8h4M3 7h4M11 3h2M7 7h6M7 11h6" />
        </template>
        <template v-else-if="name === 'back'">
            <path d="M12 8H4M7 4L3.5 8 7 12" />
        </template>
        <template v-else-if="name === 'left'">
            <path d="M10 3L5 8l5 5" />
        </template>
        <template v-else-if="name === 'right'">
            <path d="M6 3l5 5-5 5" />
        </template>
        <template v-else-if="name === 'restore'">
            <path d="M3 8a5 5 0 105-5M3 3v3h3" />
        </template>
        <template v-else-if="name === 'warn'">
            <path d="M8 2.5l5.5 10h-11z" />
            <path d="M8 6.5v3M8 11.2v.3" />
        </template>
    </svg>
</template>

<script setup>
/**
 * Icons as inline paths rather than a font or a sprite file.
 *
 * A font would be a second asset to publish and a third thing to go missing; a sprite would need a URL,
 * and a URL needs to know where it was published to. A dozen paths in a template cost nothing and cannot
 * be half-installed.
 */
defineProps({
    name: { type: String, required: true },
});
</script>
