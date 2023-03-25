<template>
    <Transition name="fade">
        <details open="open" class="edit">
            <summary>{{ rule.guard_name || rule.form_title }}</summary>
            <form :data-id=rule.id @submit.prevent="saveRule($event)">
                <fieldset>
                    <label>{{ $t('rule.edit.title') }}</label>
                    <input name="title" :value=rule.title />
                </fieldset>
                <fieldset>
                    <label>{{ $t('rule.edit.description') }}</label>
                    <textarea name="description" :value=rule.description></textarea>
                </fieldset>
                <fieldset>
                    <label>{{ $t('rule.edit.guard_name') }}</label>
                    <input name="guard_name" :value=rule.guard_name />
                </fieldset>
                <fieldset>
                    <label>{{ $t('rule.edit.options') }}</label>
                    <input name="options" :value=rule.options />
                </fieldset>
                <alert :status="alertStatus" :message="alertText" />
                <fieldset>
                    <input type="hidden" name="id" :value=rule.id />
                    <button class="btn btn-save" :disabled=lock>
                        {{ $t('global.btn.save') }}
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
