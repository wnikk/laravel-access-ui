<template>
    <nav aria-label="breadcrumb" v-if="availableOwners">
        <ol class="breadcrumb">
            <li
                v-if="availableRules"
                :class="[
                    'breadcrumb-item',
                    openedComponent === 'rules' ? 'active':'',
                ]"
                aria-current="page"
            >
                <a href="#" v-on:click.prevent.stop="openComponent('rules')">{{ $t('breadcrumbs.rules') }}</a>
            </li>

            <li
                v-if="availableOwners"
                :class="[
                    'breadcrumb-item',
                    openedComponent === 'owners' ? 'active':'',
                ]"
                aria-current="page"
            >
                <a href="#" v-on:click.prevent.stop="openComponent('owners')">{{ $t('breadcrumbs.owners') }}</a>
            </li>

            <li v-if="availableInherit && openedComponent === 'inherit'" class="breadcrumb-item active" aria-current="page">
                {{ $t('breadcrumbs.inherit') }}
            </li>

            <li v-if="availablePermission && openedComponent === 'permission'" class="breadcrumb-item active" aria-current="page">
                {{ $t('breadcrumbs.permission') }}
            </li>
        </ol>
    </nav>
</template>
<script>
export default {
    name: "Breadcrumbs",
    props: {
        openedComponent: String,

        availableRules: Boolean,
        availableOwners: Boolean,
        availableInherit: Boolean,
        availablePermission: Boolean,
    },
    data() {
        return {
        };
    },
    methods: {
        openComponent: function (name) {
            this.emitter.emit('selectComponent', name);
        },
    },
}
</script>
