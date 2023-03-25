<template>
    <Transition name="fade">
        <details open="open" class="edit">
            <summary>{{ owner.name || owner.form_title }}</summary>
            <form :data-id=owner.id @submit.prevent="saveOwner($event)">

                <fieldset v-if=types>
                    <label>{{ $t('owner.edit.type') }}</label>
                    <select name="type">
                        <option v-for="(option, index) in types" :value=index>
                            {{ option }}
                        </option>
                    </select>
                </fieldset>

                <fieldset>
                    <label>{{ $t('owner.edit.name') }}</label>
                    <input name="name" :value=owner.name />
                </fieldset>

                <fieldset>
                    <label>{{ $t('owner.edit.original_id') }}</label>
                    <input name="original_id" :value=owner.original_id />
                </fieldset>

                <alert :status="alertStatus" :message="alertText" />

                <fieldset>
                    <input type="hidden" name="id" :value=owner.id />
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
    name: "ownerEdit",
    components: {
        Alert
    },
    props: {
        types: Object,
        owner: Object,
    },
    data() {
        return {
            lock: false,
            alertStatus: true,
            alertText: null,
        };
    },
    methods: {
        saveOwner: async function(e)
        {
            const that = this;
            const form = this.$el.querySelector('form');
            const data = new FormData(form);

            this.lock = true;
            this.alertText = null;

            // wait before vue update [Alert] component
            await new Promise(resolve => setTimeout(resolve, 10));

            this.emitter.emit(
                'saveOwner',
                form,
                this.owner.id,
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
};
</script>
