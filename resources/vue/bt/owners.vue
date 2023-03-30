<template>

    <section class="accessUi-owners-list">

        <alert :status="alertStatus" :message="alertText" />

        <div class="section-body custom-style">

            <div v-if="displayCreateOwner" data-id="0">
                <OwnerEdit
                    :owner="{ id: newOwnerId, form_title: $t('owner.create.title') }"
                    :types="typesList"
                    @cancel="cancelCreateOwner()"
                ></OwnerEdit>
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
                        <button v-if=availableCreate class="btn btn-icon btn-outline-primary btn-sm" @click="openCreateOwner($event)">
                            <i class="icon icon-create"></i>
                        </button>
                    </th>
                </tr>
                </thead>
                <tbody v-if="(ownersList && ownersList.length)">
                    <owner v-for="child in ownersList"
                           :owner="child"
                           :availableEdit=availableEdit
                           :availableDelete=availableDelete
                           :availableInherit=availableInherit
                           :availablePermission=availablePermission
                           @editInherit="openInheritEdit"
                           @editPermission="openPermissionEdit"
                    ></owner>
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
import Owner from './elements/owner.vue'
import OwnerEdit from './elements/ownerEdit.vue'
import Alert from './elements/alert.vue'

export default {
    name: "OwnersList",
    components: {
        Owner,
        OwnerEdit,
        Alert
    },
    props: {
        availableInherit: Boolean,
        availablePermission: Boolean,
        routeOwners: {
            type: Object,
            default: {
                list:   null,
                create: null,
                update: null,
                delete: null,
            },
        },
    },
    data () {
        return {
            availableCreate: true,
            availableEdit: true,
            availableDelete: true,

            displayCreateOwner: false,
            newOwnerId: ':new:',

            alertStatus: true,
            alertText: null,

            ownersList: [],
            typesList: {},
        };
    },
    methods: {

        openCreateOwner: function () {
            this.displayCreateOwner = true;
        },
        cancelCreateOwner: function () {
            this.displayCreateOwner = false;
        },

        saveOwner: function (form, id, data, callback)
        {
            let fun, url;

            if (id === this.newOwnerId) {
                fun = 'POST';
                url = this.routeOwners.create;
            } else {
                fun = 'PUT';
                url = this.routeOwners.update.replace(':id:', id);
            }

            this.$http.request(url, {
                method:  fun,
                context: form,
                data:    JSON.stringify(Object.fromEntries(data)),
                headers: {'Content-Type': 'application/json;charset=UTF-8'}
            })
                .then((e) => {
                    callback(e.ok, e.data?.message);
                    this.emitter.emit('loadOwner');
                })
                .catch((e, res) => {
                    let msg = e.data?.message;
                    if (!msg) msg = e.data?.Message;
                    if (!msg) msg = e.data;
                    if (!msg) msg = e.status+e.statusText;
                    callback(false,  msg);
                });
        },

        deleteOwner: function (form, id, callback)
        {
            this.$http.delete(this.routeOwners.delete.replace(':id:', id), {context: form})
                .then((e) => {
                    callback(e.ok, e.data?.message);
                    this.emitter.emit('loadOwner');
                })
                .catch((e, res) => {
                    let msg = e.data?.message;
                    if (!msg) msg = e.data?.Message;
                    if (!msg) msg = e.data;
                    if (!msg) msg = e.status+e.statusText;
                    callback(false,  msg);
                });
        },

        loadOwner: function ()
        {
            const context = this.$el;
            let that = this;
            this.$http.get(this.routeOwners.list, {context: context})
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

                    let exp = [];
                    for (const item of e.data?.list) {
                        let typeName = typeof (item.type) !== 'undefined'?item.type:null;
                        typeName     = typeof (types[typeName]) === 'undefined'?'#'+typeName:types[typeName];
                        exp.push({
                            id:          item.id?item.id:null,
                            type:        typeof(item.type) !== 'undefined'?item.type:null,
                            typeName:    typeName,
                            original_id: typeof(item.original_id) !== 'undefined'?item.original_id:null,
                            name:        item.name?item.name:null,
                            created_at:  item.created_at?new Date(item.created_at).toLocaleWithoutToday():null,
                        });
                    }
                    that.ownersList = exp;
                })
                .catch((e, res) => {
                    let msg = e.data?.message;
                    if (!msg) msg = e.data;
                    that.alertStatus = false;
                    that.alertText   = msg;
                });
        },
        openInheritEdit: function (owner_id) {
            this.emitter.emit('selectComponent', 'inherit', owner_id);
        },
        openPermissionEdit: function (owner_id) {
            this.emitter.emit('selectComponent', 'permission', owner_id);
        },
    },
    beforeMount () {
        this.availableCreate  = !!this.routeOwners?.create;
        this.availableEdit    = !!this.routeOwners?.update;
        this.availableDelete  = !!this.routeOwners?.delete;
        this.emitter.on('saveOwner', this.saveOwner);
        this.emitter.on('deleteOwner', this.deleteOwner);
        this.emitter.on('loadOwner', this.loadOwner);
    },
    mounted () {
        this.emitter.emit('loadOwner');
    },
};
</script>
