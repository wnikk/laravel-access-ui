<template>
    <tr v-if="rule && rule.opened" :data-id=rule.id :class=className>
        <th class="lbl-tree">
            <label
                :class="[
                    'btn-tree',
                    rule.children && rule.children.length ? 'have-children':''
                    ]"
                @click="() => {
                    rule.children.length?toggleInfoRule(rule.id, !rule.openedChild):null
                }"
            >
                <i class="level" v-if="level" v-for="num in level">&middot;</i>
                <i
                   :class="['triangle',rule.openedChild ? 'open':'']"
                   v-if="rule.children && rule.children.length">&#9656;</i>
            </label>
        </th>
        <td class="lbl-created_at text-secondary">{{ rule.created_at }}</td>
        <th class="lbl-guard_name">
            <label>{{ rule.guard_name }}</label>
            <RuleEdit v-if="displayEdit"
                      :rule=rule
                      @cancel="cancelPopup()"
            ></RuleEdit>
            <ruleDelete v-if="displayDelete"
                        :rule=rule
                        @cancel="cancelPopup()"
            ></ruleDelete>
        </th>
        <td class="lbl-title">{{ rule.title }}</td>
        <td class="lbl-description">
            <blockquote :title="rule.description">
            {{ rule.description }}
            </blockquote>
        </td>
        <td class="lbl-options">
            <div class="text-secondary text-break" :title="rule.options">{{ rule.options }}</div>
        </td>
        <td class="lbl-btn text-center">
            <button v-if=availableEdit class="btn btn-icon btn-outline-primary btn-sm" @click="editRule($event)">
                <i class="icon icon-edit"></i>
            </button>
            <button v-if=availableDelete class="btn btn-icon btn-outline-danger btn-sm" @click="deleteRule($event)">
                <i class="icon icon-delete"></i>
            </button>
        </td>
    </tr>
    <rule v-if="rule.opened && rule.children && rule.children.length" v-for="child in rule.children"
          :level="level+1"
          :rule=child
          :open=child.opened
          :className=className
          :availableEdit=availableEdit
          :availableDelete=availableDelete
    ></rule>
</template>

<script>
import RuleEdit from './ruleEdit.vue'
import ruleDelete from './ruleDelete.vue'

export default {
    name: "Rule",
    components: {
        RuleEdit,
        ruleDelete
    },
    props: {
        className: {
            type: String,
            default: 'rule-item'
        },
        availableEdit: {
            type: Boolean,
            default: true
        },
        availableDelete: {
            type: Boolean,
            default: true
        },
        level: Number,
        rule: Object,
    },
    data() {
        return {
            displayInfo: true,
            displayEdit: false,
            displayDelete: false,
        };
    },
    methods: {
        toggleInfoRule: function (id, open) {
            if (open) {
                this.cancelPopup();
            }
            this.emitter.emit('toggleInfoRule', id, open);
        },
        cancelPopup: function () {
            this.displayInfo = true;
            this.displayEdit = false;
            this.displayDelete = false;
        },
        editRule: function (e) {
            this.displayInfo = false;
            this.displayEdit = true;
            this.displayDelete = false;
        },
        deleteRule: function (e) {
            this.displayInfo = false;
            this.displayEdit = false;
            this.displayDelete = true;
        },
    }
};
</script>
