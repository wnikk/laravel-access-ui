<template>

    <section class="accessUi-inherits-list">

        <alert :status="alertStatus" :message="alertText" />

        <div class="section-body custom-style">

            <div v-if="displayCreateInherit" data-id="0">
                <InheritAdd
                    :types="typesList"
                    :owners="ownersList"
                    @cancel="cancelCreateInherit()"
                ></InheritAdd>
            </div>

            <table class="table table-striped list">
                <thead class="text-center">
                <tr>
                    <th class="th-id">#</th>
                    <th class="th-created_at">&nbsp;</th>
                    <th class="th-type">{{ $t('owner.edit.type') }}</th>
                    <th class="th-name th-lg">{{ $t('owner.edit.name') }}</th>
                    <th class="th-original_id">{{ $t('owner.edit.original_id') }}</th>
                    <th class="th-btn">
                        <button v-if=availableCreate class="btn btn-icon btn-outline-primary btn-sm" @click="openCreateInherit($event)">
                            <i class="icon icon-create"></i>
                        </button>
                    </th>
                </tr>
                </thead>
                <tbody v-if="(inheritList && inheritList.length)">
                    <inherit v-for="child in inheritList"
                             :inherit="child"
                             :availableDelete=availableDelete
                    ></inherit>
                </tbody>
                <tbody v-else>
                    <tr>
                        <td colspan="7">
                            <div class="no-result text-center">{{ $t('global.inp.select.noResult') }}</div>
                        </td>
                    </tr>
                </tbody>
            </table>

        </div>
    </section>

</template>

<script>
import Inherit from './elements/inherit.vue'
import InheritAdd from './elements/inheritAdd.vue'
import Alert from './elements/alert.vue'

export default {
    name: "InheritsList",
    components: {
        Inherit,
        InheritAdd,
        Alert
    },
    props: {
        routeInherit: {
            type: Object,
            default: {
                list:   null,
                create: null,
                delete: null,
            },
        },
    },
    data () {
        return {
            availableCreate: true,
            availableDelete: true,

            displayCreateInherit: false,
            newInheritId: ':new:',

            alertStatus: true,
            alertText: null,

            inheritList: [],
            typesList: {},
            ownersList: [],
        };
    },
    methods: {

        openCreateInherit: function () {
            this.displayCreateInherit = true;
        },
        cancelCreateInherit: function () {
            this.displayCreateInherit = false;
        },

        saveInherit: function (form, owner_id, data, callback)
        {
            let fun, url;

            fun = 'POST';
            url = this.routeInherit.create;
            data.append('owner_id', owner_id);

            this.$http.request(url, {
                method:  fun,
                context: form,
                data:    JSON.stringify(Object.fromEntries(data)),
                headers: {'Content-Type': 'application/json;charset=UTF-8'}
            })
                .then((e) => {
                    callback(e.ok, e.data?.message);
                    this.emitter.emit('loadInherit');
                })
                .catch((e, res) => {
                    let msg = e.data?.message;
                    if (!msg) msg = e.data?.Message;
                    if (!msg) msg = e.data;
                    if (!msg) msg = e.status+e.statusText;
                    callback(false,  msg);
                });
        },

        deleteInherit: function (form, owner_id, callback)
        {
            this.$http.delete(this.routeInherit.delete.replace(':id:', owner_id), {context: form})
                .then((e) => {
                    callback(e.ok, e.data?.message);
                    this.emitter.emit('loadInherit');
                })
                .catch((e, res) => {
                    let msg = e.data?.message;
                    if (!msg) msg = e.data?.Message;
                    if (!msg) msg = e.data;
                    if (!msg) msg = e.status+e.statusText;
                    callback(false,  msg);
                });
        },

        loadInherit: function ()
        {
            const context = this.$el;
            let that = this;
            this.$http.get(this.routeInherit.list, {context: context})
                .then((e) => {
                    if (!e.data.list) {
                        let msg = e.data?.message;
                        if (!msg) msg = e.data;
                        that.alertStatus = false;
                        that.alertText   = 'EMPTY json prop "list"! '+msg;
                        return;
                    }

                    let types = e.data?.types;
                    if (!types) types = {};
                    that.typesList = types;

                    that.ownersList = [];
                    for (const owners of e.data?.owners) {
                        let typeName = typeof (owners.type) !== 'undefined'?owners.type:null;
                        typeName = typeof (types[typeName]) === 'undefined'?'#'+typeName:types[typeName];
                        that.ownersList.push({
                            ...owners,
                            typeName
                        });
                    }

                    let exp = [];
                    for (const item of e.data?.list) {
                        let typeName = typeof (item.type) !== 'undefined'?item.type:null;
                        typeName = typeof (types[typeName]) === 'undefined'?'#'+typeName:types[typeName];
                        exp.push({
                            id:          item.id?item.id:null,
                            created_at:  item.created_at?new Date(item.created_at).toLocaleString():null,
                            owner_id:    item.owner_id?item.owner_id:null,
                            type:        typeof(item.type) !== 'undefined'?item.type:null,
                            typeName:    typeName,
                            name:        item.name?item.name:null,
                            original_id: typeof(item.original_id) !== 'undefined'?item.original_id:null,
                        });
                    }
                    that.inheritList = exp;
                })
                .catch((e, res) => {
                    let msg = e.data?.message;
                    if (!msg) msg = e.data;
                    that.alertStatus = false;
                    that.alertText   = msg;
                });
        },
    },
    beforeMount () {
        this.availableCreate  = !!this.routeInherit?.create;
        this.availableDelete  = !!this.routeInherit?.delete;
        this.emitter.on('saveInherit', this.saveInherit);
        this.emitter.on('deleteInherit', this.deleteInherit);
        this.emitter.on('loadInherit', this.loadInherit);
    },
    mounted () {
        this.emitter.emit('loadInherit');
    },
};
</script>
