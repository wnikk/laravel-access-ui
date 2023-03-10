<template>
    <Transition name="fade">
        <details open="open" class="delete">
            <summary>{{ inherit.name }}</summary>
            <form :data-id=inherit.id @submit.prevent="deleteInherit($event)">

                <fieldset v-html="$t('inherit.delete.attention')"></fieldset>

                <alert :status="alertStatus" :message="alertText" />

                <fieldset>
                    <button class="btn btn-save" :disabled=lock>
                        {{ $t('global.btn.delete') }}
                    </button>
                    <button class="btn" @click="$emit('cancel')" :disabled=lock>
                        {{ $t('global.btn.cancel') }}
                    </button>
                </fieldset>

            </form>
        </details>
    </Transition>
</template>

<script>
import Alert from '@/elements/alert.vue'

export default {
    name: "inheritDelete",
    components: {
        Alert
    },
    props: {
        inherit: Object,
    },
    data() {
        return {
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
                this.inherit.id,
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
