<template>
    <li v-if="rule" :data-id=rule.id :class=className>
        <RuleEdit v-if="displayEdit"
              :rule=rule
              @cancel="cancelPopup()"
        ></RuleEdit>
        <ruleDelete v-if="displayDelete"
                  :rule=rule
                  @cancel="cancelPopup()"
        ></ruleDelete>

        <details v-if="displayInfo" :open=rule.opened>
            <summary>{{ rule.title??rule.guard_name }}</summary>
            <div class="info">
                <fieldset v-if="availableEdit || availableDelete">
                    <button v-if=availableEdit class="icon icon-edit" @click="editRule($event)"></button>
                    <button v-if=availableDelete class="icon icon-delete" @click="deleteRule($event)"></button>
                </fieldset>
                <article>
                    <time>{{ rule.created_at }}</time>
                    <p class="description">{{ rule.description }}</p>
                    <p class="options">{{ rule.guard_name }}</p>
                    <p v-if="rule.options" class="options">{{ rule.options }}</p>
                </article>
            </div>
            <ul v-if="rule.children && rule.children.length" class="list-child">
                <rule v-for="child in rule.children"
                      :rule=child
                      :open=child.opened
                      :className=className
                      :availableEdit=availableEdit
                      :availableDelete=availableDelete
                ></rule>
            </ul>
        </details>
    </li>
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
        rule: Object,
    },
    data() {
        return {
            displayInfo: true,
            displayEdit: false,
            displayDelete: false,
            observer: null,
        };
    },
    watch: {
        'rule.opened'(val){
            if (val) {
                this.cancelPopup();
            }
        },
    },
    mounted () {
        let target = this.$el.querySelector("details");
        const callback = (mutationList, observer) => {
            const id = mutationList[0]?.target?.parentElement?.dataset.id;
            const open = mutationList[0]?.target?.hasAttribute('open');
            this.toggleInfoRule(id, open);
        };

        this.observer = new MutationObserver(callback);
        this.observer.observe(target, {
            attributeFilter: ['open'],
        });
    },
    unmounted () {
        this.observer?.disconnect();
        this.observer = null;
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
