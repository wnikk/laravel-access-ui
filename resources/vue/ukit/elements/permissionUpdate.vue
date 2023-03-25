<template>
    <Transition name="fade">
        <details open="open" class="edit">
            <summary>
                <PermissionStatus :permission=permission :inherit=false />
                {{ rule.title || rule.guard_name }}
            </summary>
            <form :data-id=rule.id @submit.prevent="savePermission($event)">
                <fieldset v-if="rule.options">

                    <label>{{ $t('permission.edit.optioninfo') }}</label>

                    <div v-if="dataList">
                        <select name="option" :required="required">
                            <option v-for="item in dataList">
                                {{ item }}
                            </option>
                        </select>
                    </div>
                    <div v-else>
                        <input name="option" value="" :list="'option-list-'+rule.id" :required="required" />
                        <datalist :id="'option-list-'+rule.id">
                            <option v-if="dataList"  v-for="item in dataList" :value="item" />
                        </datalist>
                    </div>

                    <label>{{ rule.options }}</label>
                </fieldset>
                <alert :status="alertStatus" :message="alertText" />
                <fieldset>
                    <input type="hidden" name="id" :value=rule.id />
                    <input type="hidden" name="permission" :value="permission?1:0" />
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
