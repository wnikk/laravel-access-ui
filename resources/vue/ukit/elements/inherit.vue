<template>
    <li v-if="inherit" :data-id=inherit.id :class=className>

        <alert :status="alertStatus" :message="alertText" />

        <article class="info">

            <h4>
                {{ inherit.name }}
                <span v-if="inherit.original_id" class="original_id">#{{ inherit.original_id }}</span>
            </h4>

            <fieldset v-if="availableDelete">
                <form :data-id=inherit.id @submit.prevent="deleteInherit($event)">
                    <button class="icon icon-delete"></button>
                </form>
            </fieldset>

            <label class="type">{{ inherit.typeName }}</label>
            <time>{{ inherit.created_at }}</time>

        </article>
    </li>
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
