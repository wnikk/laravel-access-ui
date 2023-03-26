<template>
    <Transition name="fade">
        <div class="modal show edit" tabindex="-1" aria-modal="true" style="display: block;">
            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content" :data-id=owner.id @submit.prevent="saveOwner($event)">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ owner.name || owner.form_title }}</h5>
                    </div>
                    <div class="modal-body">

                        <div class="mb-3" v-if=types>
                            <label class="form-label">{{ $t('owner.edit.type') }}</label>
                            <select name="type" class="form-control">
                                <option v-for="(option, index) in types" :value=index>
                                    {{ option }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ $t('owner.edit.name') }}</label>
                            <input name="name" class="form-control" :value=owner.name />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ $t('owner.edit.original_id') }}</label>
                            <input name="original_id" class="form-control" :value=owner.original_id />
                        </div>

                        <alert :status="alertStatus" :message="alertText" />

                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="id" :value=owner.id />
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
