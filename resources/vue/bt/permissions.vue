<template>

    <section class="accessUi-permissions-list">

        <alert :status="alertStatus" :message="alertText" />

        <div class="section-body custom-style">

            <table class="table table-striped list">
                <thead class="text-center">
                <tr>
                    <th class="th-tree">&nbsp;</th>
                    <th class="th-guard_name">{{ $t('rule.edit.guard_name') }}</th>
                    <th class="th-title th-lg">{{ $t('rule.edit.title') }}</th>
                    <th class="th-description">{{ $t('rule.edit.description') }}</th>
                    <th class="th-btn">&nbsp;</th>
                </tr>
                </thead>
                <tbody v-if="(permissionTree && permissionTree.length)">
                    <permission v-for="child in permissionTree"
                        :level=0
                        :rule=child
                        :simplePermission=child.permission.simple
                        :complexPermission=child.permission.complex
                        :availableUpdate=availableUpdate
                        :availableDelete=availableUpdate
                    ></permission>
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
import Permission from './elements/permission.vue'
import Alert from './elements/alert.vue'
import helper from './../../js/libs/helper'

export default {
    name: "PermissionsList",
    components: {
        Permission,
        Alert
    },
    props: {
        routePermission: {
            type: Object,
            default: {
                list:   null,
                update: null,
            },
        },
    },
    data () {
        return {
            availableUpdate: true,

            alertStatus: true,
            alertText: null,

            permissionList: [],
            permissionTree: [],
        };
    },
    methods: {

        savePermission: function (form, rule_id, data, callback)
        {
            let fun, url;

            fun = 'PUT';
            url = this.routePermission.update.replace(':id:', rule_id);
            data.append('id', rule_id);

            this.$http.request(url, {
                method:  fun,
                context: form,
                data:    JSON.stringify(Object.fromEntries(data)),
                headers: {'Content-Type': 'application/json;charset=UTF-8'}
            })
                .then((e) => {
                    callback(e.ok, e.data?.message);
                    this.emitter.emit('loadPermission');
                })
                .catch((e, res) => {
                    let msg = e.data?.message;
                    if (!msg) msg = e.data?.Message;
                    if (!msg) msg = e.data;
                    if (!msg) msg = e.status+e.statusText;
                    callback(false,  msg);
                });
        },

        loadPermission: function ()
        {
            const context = this.$el;
            let that = this;
            this.$http.get(this.routePermission.list, {context: context})
                .then((e) => {
                    if (!e.data.list) {
                        let msg = e.data?.message;
                        if (!msg) msg = e.data;
                        that.alertStatus = false;
                        that.alertText   = 'EMPTY json prop "list"! '+msg;
                        return;
                    }
                    let exp = [];
                    for (const item of e.data.list) {
                        let permission = {
                            simple:  [],
                            complex: [],
                        };
                        if (item.permission) for (const i in item.permission) {
                            const perm   = item.permission[i];
                            const option = perm.option?perm.option:null;
                            permission[option?'complex':'simple'].push({
                                option:     option,
                                permission: perm.permission?perm.permission:false,
                                inherit:    perm.inherit?perm.inherit:false,
                            });
                        }
                        exp.push({
                            id:          item.id?item.id:null,
                            parent_id:   item.parent_id?item.parent_id:0,
                            guard_name:  item.guard_name?item.guard_name:null,
                            options:     item.options?item.options:null,
                            title:       item.title?item.title:null,
                            description: item.description?item.description:null,
                            permission:  permission,
                            opened:      false,
                        });
                    }
                    that.permissionList = exp;
                    that.updateListToTree();
                })
                .catch((e, res) => {
                    let msg = e.data?.message;
                    if (!msg) msg = e.data;
                    that.alertStatus = false;
                    that.alertText   = msg;
                });
        },
        updateListToTree: function () {
            this.permissionTree = helper.createDataTree(this.permissionList);
        },

    },
    beforeMount () {
        this.availableUpdate  = !!this.routePermission?.update;
        this.emitter.on('savePermission', this.savePermission);
        this.emitter.on('loadPermission', this.loadPermission);
    },
    mounted () {
        this.emitter.emit('loadPermission');
    },
};
</script>
