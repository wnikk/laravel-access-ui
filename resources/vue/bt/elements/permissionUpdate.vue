<template>
    <Transition name="fade">
        <div class="modal show edit" tabindex="-1" aria-modal="true" style="display: block;">
            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content" :data-id=rule.id @submit.prevent="savePermission($event)">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <PermissionStatus :permission=permission :inherit=false>
                                &nbsp;
                            </PermissionStatus>
                            {{ rule.title || rule.guard_name }}
                        </h5>
                    </div>
                    <div class="modal-body">

                        <div class="mb-3" v-if="rule.options">
                            <label class="form-label">{{ $t('permission.edit.optioninfo') }}</label>

                            <div v-if="dataList">
                                <select class="form-control" name="option" :required="required">
                                    <option v-for="item in dataList">
                                        {{ item }}
                                    </option>
                                </select>
                            </div>
                            <div v-else>
                                <input class="form-control" name="option" value="" :list="'option-list-'+rule.id" :required="required" />
                                <datalist :id="'option-list-'+rule.id">
                                    <option v-if="dataList"  v-for="item in dataList" :value="item" />
                                </datalist>
                            </div>

                            <label class="form-text">{{ rule.options }}</label>
                        </div>

                        <alert :status="alertStatus" :message="alertText" />

                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="id" :value=rule.id />
                        <input type="hidden" name="permission" :value="permission?1:0" />
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
import PermissionStatus from './permissionStatus.vue'

export default {
    name: "permissionUpdate",
    components: {
        Alert,
        PermissionStatus
    },
    props: {
        rule: Object,
        permission: Boolean
    },
    data() {
        return {
            lock: false,
            alertStatus: true,
            alertText: null,
            dataList: [],
            required: false,
        };
    },
    methods: {
        savePermission: async function(e)
        {
            const that = this;
            const form = this.$el.querySelector('form');
            const data = new FormData(form);
            this.lock = true;
            this.alertText = null;

            // wait before vue update [Alert] component
            await new Promise(resolve => setTimeout(resolve, 10));

            this.emitter.emit(
                'savePermission',
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
        if (this.rule.options) {

            let validators = this.rule.options.split('|');
            let enums = [];
            for (const item of validators)
            {
                if (item === 'required') this.required = true;
                if (item === 'nullable') enums.push('');
                if (item.search('in:') === 0) {
                    enums = enums.concat(item.substring(3).split(','));
                }
            }
            if(enums.length > 1 || (enums.length === 1 && length[0] !== '')) this.dataList = enums;
        }
    },
    mounted() {
        if (!this.rule.options)
        {
            this.savePermission();
        }
    },
};
</script>
