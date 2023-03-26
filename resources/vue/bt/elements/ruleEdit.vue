<template>
    <Transition name="fade">
        <div class="modal show edit" tabindex="-1" aria-modal="true" style="display: block;">
            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content" :data-id=rule.id @submit.prevent="saveRule($event)">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ rule.guard_name || rule.form_title }}</h5>
                    </div>
                    <div class="modal-body">

                        <div class="mb-3">
                            <label class="form-label">{{ $t('rule.edit.title') }}</label>
                            <input name="title" class="form-control" :value=rule.title />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ $t('rule.edit.description') }}</label>
                            <textarea name="description" class="form-control" :value=rule.description></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ $t('rule.edit.guard_name') }}</label>
                            <input name="guard_name" class="form-control" :value=rule.guard_name />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ $t('rule.edit.options') }}</label>
                            <input name="options" class="form-control" :value=rule.options />
                        </div>

                        <alert :status="alertStatus" :message="alertText" />

                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="id" :value=rule.id />
                        <button class="btn btn-success btn-save" :disabled=lock>
                            {{ $t('global.btn.save') }}
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
    name: "ruleEdit",
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
        saveRule: async function(e)
        {
            const that = this;
            const form = this.$el.querySelector('form');
            const data = new FormData(form);

            this.lock = true;
            this.alertText = null;

            // wait before vue update [Alert] component
            await new Promise(resolve => setTimeout(resolve, 10));

            this.emitter.emit(
                'saveRule',
                form,
                this.rule.id,
                data,
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
    },
    created() {
    },
};
</script>
