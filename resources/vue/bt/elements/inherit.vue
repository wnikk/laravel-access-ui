<template>
    <tr v-if="inherit" :data-id=inherit.id :class=className>
        <th class="lbl-id text-center">
            {{ inherit.owner_id }}
        </th>
        <td class="lbl-created_at text-secondary">
            {{ inherit.created_at }}
        </td>
        <th class="lbl-owner_type">
            {{ inherit.typeName }}
        </th>
        <td class="lbl-name">
            <alert :status="alertStatus" :message="alertText" />
            <label>{{ inherit.name }}</label>
        </td>
        <td class="lbl-original_id text-secondary">
            <label>{{ inherit.original_id }}</label>
        </td>
        <td class="lbl-btn text-center">

            <form v-if="availableDelete" :data-id=inherit.id @submit.prevent="deleteInherit($event)">
                <button class="btn btn-icon btn-outline-danger btn-sm">
                    <i class="icon icon-delete"></i>
                </button>
            </form>

        </td>
    </tr>
</template>

<script>
import Alert from './alert.vue'

export default {
    name: "Inherit",
    components: {
        Alert
    },
    props: {
        className: {
            type: String,
            default: 'inherit-item'
        },
        availableDelete: {
            type: Boolean,
            default: true
        },
        inherit: Object,
    },
    data() {
        return {
            displayDelete: false,

            lock: false,
            alertStatus: true,
            alertText: null,
        };
    },
    methods: {
        deleteInherit: async function(e)
        {
            const that = this;
            const form = this.$el.querySelector('form');

            this.lock = true;
            this.alertText = null;

            // wait before vue update [Alert] component
            await new Promise(resolve => setTimeout(resolve, 10));

            this.emitter.emit(
                'deleteInherit',
                form,
                this.inherit.owner_id,
                function (result, message)
                {
                    that.alertStatus = result;
                    that.alertText = message;

                    if (result) {
                        if(message) setTimeout(() => { that.$emit('cancel');},  500);
                        else that.$emit('cancel');
                    }
                },
            );

            setTimeout(() => {that.lock = false;}, 500);
            return false;
        },
    }
};
</script>
