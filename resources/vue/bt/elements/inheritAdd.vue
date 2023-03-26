<template>
    <Transition name="fade">
        <div class="modal show edit" tabindex="-1" aria-modal="true" style="display: block;">
            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content" @submit.prevent="saveInherit($event)">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $t('inherit.create.title') }}</h5>
                    </div>
                    <div class="modal-body">

                        <div class="mb-3" v-if=owners>
                            <label class="form-label">{{ $t('inherit.create.owners') }}</label>
                            <Multiselect
                                v-model="owner"
                                :options="options"
                                :allow-empty="false"
                                :placeholder="$t('global.inp.select')"
                                group-label="typeName"
                                group-values="list"
                                track-by="id"
                                label="name"
                            >
                                <span slot="noResult">{{ $t('global.inp.select.noResult') }}</span>
                            </Multiselect>
                        </div>

                        <alert :status="alertStatus" :message="alertText" />

                    </div>
                    <div class="modal-footer">
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
import Multiselect from 'vue-multiselect'

export default {
    name: "InheritAdd",
    components: {
        Alert,
        Multiselect
    },
    props: {
        types: Object,
        owners: Object,
    },
    data() {
        return {
            lock: false,
            alertStatus: true,
            alertText: null,
            options: [],
            owner: null,
        };
    },
    methods: {
        makeOwnerTree: async function()
        {
            let tree = {};
            for (const item of this.owners)
            {
                if( typeof(tree[item.typeName]) === 'undefined') {
                    tree[item.typeName] = {
                        typeName: item.typeName,
                        list: [],
                    };
                }
                tree[item.typeName].list.push(item);
            }
            this.options = Object.values(tree);
        },
        saveInherit: async function(e)
        {
            const that = this;
            const form = this.$el.querySelector('form');
            const data = new FormData(form);

            this.lock = true;
            this.alertText = null;

            // wait before vue update [Alert] component
            await new Promise(resolve => setTimeout(resolve, 10));

            this.emitter.emit(
                'saveInherit',
                form,
                this.owner?.id,
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
        this.makeOwnerTree();
    },
};
</script>
