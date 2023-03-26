<template>

    <span :class="[
      className,
      'badge',
      permission ? 'yes text-bg-success badge-success' : 'no text-bg-danger badge-danger',
      inherit ? 'inherit':'',
    ]" :title="$t('permission.status.'+(permission ? 'yes' : 'no')+(inherit ? '_inherit':''))">
        <slot></slot>
    </span>

</template>
<script>
export default {
    name: "badge",
    props: {
        className: {
            type: String,
            default: 'permission-icon'
        },
        permission: {
            type: Boolean,
            default: true
        },
        inherit: {
            type: Boolean,
            default: false
        },
    }
};
</script>
