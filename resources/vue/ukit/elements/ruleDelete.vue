<template>
    <Transition name="fade">
        <details open="open" class="delete">
            <summary>{{ rule.guard_name }}</summary>
            <form :data-id=rule.id @submit.prevent="deleteRule($event)">

                <fieldset v-html="$t('rule.delete.attention')"></fieldset>

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
import Alert from './alert.vue'

export default {
    name: "ruleDelete",
    components: {
        Alert
    },
    props: {
        rule: Object,
    },
    data() {
        return {
            lock: false,
            alertStatus: true,
            alertText: null,
        };
    },
    methods: {
        deleteRule: async function(e)
        {
            const that = this;
            const form = this.$el.querySelector('form');

            this.lock = true;
            this.alertText = null;

            // wait before vue update [Alert] component
            await new Promise(resolve => setTimeout(resolve, 10));

            this.emitter.emit(
                'deleteRule',
                form,
                this.rule.id,
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
