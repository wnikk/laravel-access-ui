<template>
    <Transition name="fade">
        <div class="modal show delete" tabindex="-1" aria-modal="true" style="display: block;">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" :data-id=rule.id @submit.prevent="deleteRule($event)">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ rule.guard_name }}</h5>
                    </div>
                    <div class="modal-body">
                        <div v-html="$t('rule.delete.attention')"></div>

                        <alert :status="alertStatus" :message="alertText" />
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-danger btn-save" :disabled=lock>
                            {{ $t('global.btn.delete') }}
                        </button>
                        <button class="btn btn-secondary" @click="$emit('cancel')" :disabled=lock>
                            {{ $t('global.btn.cancel') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
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
