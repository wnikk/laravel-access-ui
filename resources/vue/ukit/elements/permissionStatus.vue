<template>

    <i :class="[
      className,
      permission ? 'yes' : 'no',
      inherit ? 'inherit':'',
    ]" :title="$t('permission.status.'+(permission ? 'yes' : 'no')+(inherit ? '_inherit':''))"></i>

</template>
<script>
export default {
    name: "permissionStatus",
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
