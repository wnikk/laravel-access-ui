<template>

    <div v-if="message" :class="[
      'alert',
      status ? 'alert-success':'alert-warning',
    ]">

        {{ message }}

    </div>

</template>

<script>

export default {
    name: "Alert",
    props: {
        status: {
            type: Boolean,
            default: true
        },
        message: String,
    },
};
</script>
