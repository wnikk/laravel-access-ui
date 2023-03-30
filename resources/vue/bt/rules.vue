<template>

    <section class="accessUi-rules-list">

        <alert :status="alertStatus" :message="alertText" />

        <div class="section-body custom-style">

            <div v-if="displayCreateRule" data-id="0">
                <RuleEdit
                    :rule="{ id: newRuleId, form_title: $t('rule.create.title') }"
                    @cancel="cancelCreateRule()"
                ></RuleEdit>
            </div>

            <table class="table table-striped list">
                <thead class="text-center">
                    <tr>
                        <th class="th-tree">
                            <button v-if="rulesTree && rulesTree.length" class="btn btn-icon btn-outline-secondary btn-sm" @click="expandAllTree($event)">
                                <i class="icon icon-expand"></i>
                            </button>
                            <button v-if="rulesTree && rulesTree.length" class="btn btn-icon btn-outline-secondary btn-sm" @click="collapseAllTree($event)">
                                <i class="icon icon-collapse"></i>
                            </button>
                        </th>
                        <th class="th-created_at">&nbsp;</th>
                        <th class="th-guard_name">{{ $t('rule.edit.guard_name') }}</th>
                        <th class="th-title th-lg">{{ $t('rule.edit.title') }}</th>
                        <th class="th-description th-lg">{{ $t('rule.edit.description') }}</th>
                        <th class="th-options">{{ $t('rule.edit.options') }}</th>
                        <th class="th-btn">
                            <button v-if=availableCreate class="btn btn-icon btn-outline-primary btn-sm" @click="openCreateRule($event)">
                                <i class="icon icon-create"></i>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody v-if="(rulesTree && rulesTree.length)">
                    <rule v-for="child in rulesTree"
                          :level=0
                          :rule="child"
                          :availableEdit=availableEdit
                          :availableDelete=availableDelete
                    ></rule>
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
import Rule from './elements/rule.vue'
import RuleEdit from './elements/ruleEdit.vue'
import Alert from './elements/alert.vue'
import helper from './../../js/libs/helper'

export default {
    name: "RulesList",
    components: {
        Rule,
        RuleEdit,
        Alert
    },
    props: {
        routeRules: {
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

            displayCreateRule: false,
            newRuleId: ':new:',

            alertStatus: true,
            alertText: null,

            rulesList: [],
            rulesTree: [],
        };
    },
    methods: {

        openCreateRule: function () {
            this.displayCreateRule = true;
        },
        cancelCreateRule: function () {
            this.displayCreateRule = false;
        },

        saveRule: function (form, id, data, callback)
        {
            let fun, url;

            if (id === this.newRuleId) {
                fun = 'POST';
                url = this.routeRules.create;
            } else {
                fun = 'PUT';
                url = this.routeRules.update.replace(':id:', id);
            }

            this.$http.request(url, {
                method: fun,
                context: form,
                data: JSON.stringify(Object.fromEntries(data)),
                headers: {'Content-Type': 'application/json;charset=UTF-8'}
            })
                .then((e) => {
                    callback(e.ok, e.data?.message);
                    this.emitter.emit('loadRule');
                })
                .catch((e, res) => {
                    let msg = e.data?.message;
                    if (!msg) msg = e.data?.Message;
                    if (!msg) msg = e.data;
                    if (!msg) msg = e.status+e.statusText;
                    callback(false,  msg);
                });
        },
        deleteRule: function (form, id, callback)
        {
            this.$http.delete(this.routeRules.delete.replace(':id:', id), {context: form})
                .then((e) => {
                    callback(e.ok, e.data?.message);
                    this.emitter.emit('loadRule');
                })
                .catch((e, res) => {
                    let msg = e.data?.message;
                    if (!msg) msg = e.data?.Message;
                    if (!msg) msg = e.data;
                    if (!msg) msg = e.status+e.statusText;
                    callback(false,  msg);
                });
        },

        loadRule: function ()
        {
            const context = this.$el;
            let that = this;
            this.$http.get(this.routeRules.list, {context: context})
                .then((e) => {
                    if (!e.data.list) {
                        let msg = e.data?.message;
                        if (!msg) msg = e.data;
                        that.alertStatus = false;
                        that.alertText = 'EMPTY json prop "list"! '+msg;
                        return;
                    }
                    let exp = [];
                    for (const item of e.data?.list) {
                        exp.push({
                            id:          item.id?item.id:null,
                            parent_id:   item.parent_id?item.parent_id:0,
                            guard_name:  item.guard_name?item.guard_name:null,
                            options:     item.options?item.options:null,
                            title:       item.title?item.title:null,
                            description: item.description?item.description:null,
                            created_at:  item.created_at?new Date(item.created_at).toLocaleWithoutToday():null,
                        });
                    }
                    that.rulesList = exp;
                    that.expandFirstTree();
                })
                .catch((e, res) => {
                    let msg = e.data?.message;
                    if (!msg) msg = e.data;
                    that.alertStatus = false;
                    that.alertText = msg;
                });
        },


        toggleInfoRule: function (id, open) {
            for (const i in this.rulesList) {
                if (this.rulesList[i].id === id) {
                    this.rulesList[i].openedChild = Boolean(open);
                }
                if (this.rulesList[i].parent_id === id) {
                    this.rulesList[i].opened = Boolean(open);
                }
            }
            this.updateListToTree();
        },

        expandAllTree: function (e) {
            for(const i in this.rulesList){
                this.rulesList[i].opened = true;
                this.rulesList[i].openedChild = true;
            }
            this.updateListToTree();
        },
        collapseAllTree: function (e) {
            for(const i in this.rulesList){
                this.rulesList[i].opened = !(this.rulesList[i].parent_id);
                this.rulesList[i].openedChild = false;
            }
            this.updateListToTree();
        },
        expandFirstTree: function (e) {
            for(const i in this.rulesList){
                this.rulesList[i].opened      = true;
                this.rulesList[i].openedChild = true;
            }
            this.updateListToTree();
        },
        updateListToTree: function () {
            this.rulesTree = helper.createDataTree(this.rulesList);
        },
    },
    beforeMount () {
        this.availableCreate  = !!this.routeRules?.create;
        this.availableEdit    = !!this.routeRules?.update;
        this.availableDelete  = !!this.routeRules?.delete;
        this.emitter.on('toggleInfoRule', this.toggleInfoRule);
        this.emitter.on('saveRule', this.saveRule);
        this.emitter.on('deleteRule', this.deleteRule);
        this.emitter.on('loadRule', this.loadRule);
    },
    mounted () {
        this.emitter.emit('loadRule');
    },
};
</script>
