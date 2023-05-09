<template>
    <li v-if="rule" :data-id=rule.id :class=className>

        <permissionUpdate v-if="displayUpdate"
            :rule=rule
            :permission=displayUpdatePermission
            @cancel="cancelPopup()"
        ></permissionUpdate>

        <details v-if="displayInfo" :open=rule.opened>
            <summary>
                {{ rule.title??rule.guard_name }}
                <span class="badges" v-if="simplePermission.length || complexPermission.length ">
                    <PermissionStatus v-for="item in simplePermission" :permission=item.permission :inherit=item.inherit />
                    <PermissionStatus v-for="item in complexPermission" :permission=item.permission :inherit=item.inherit />
                </span>
            </summary>
            <div class="info">
                <fieldset v-if="availableUpdate || availableDelete">
                    <button v-if=availableUpdate class="icon icon-create" @click="addPermission($event)"></button>
                    <button v-if=availableDelete class="icon icon-delete" @click="deletePermission($event)"></button>
                </fieldset>
                <article>
                    <time>{{ rule.created_at }}</time>
                    <p class="description">{{ rule.description }}</p>
                    <p class="guard_name">
                        <span class="badges" v-if="simplePermission.length">
                            <PermissionStatus  v-for="item in simplePermission" :permission=item.permission :inherit=item.inherit />
                        </span>
                        {{ rule.guard_name }}
                    </p>
                    <p v-if="complexPermission.length" class="guard_option_name">
                        <label v-for="item in complexPermission" class="badge">
                            <PermissionStatus :permission=item.permission :inherit=item.inherit />
                            {{ item.option }}
                        </label>
                    </p>
                    <p v-if="rule.options" class="options">{{ rule.options }}</p>
                </article>
            </div>
            <ul v-if="rule.children && rule.children.length" class="list-child">
                <permission v-for="child in rule.children"
                    :rule=child
                    :simplePermission=child.permission.simple
                    :complexPermission=child.permission.complex
                    :className=className
                    :availableUpdate=availableUpdate
                    :availableDelete=availableDelete
                ></permission>
            </ul>
        </details>
    </li>
</template>

<script>
import permissionUpdate from './permissionUpdate.vue'
import PermissionStatus from './permissionStatus.vue'

export default {
    name: "Permission",
    components: {
        permissionUpdate,
        PermissionStatus
    },
    props: {
        className: {
            type: String,
            default: 'permission-item'
        },
        availableUpdate: {
            type: Boolean,
            default: true
        },
        availableDelete: {
            type: Boolean,
            default: true
        },
        rule: Object,
        simplePermission: Object,
        complexPermission: Object,
    },
    data() {
        return {
            displayInfo: true,
            displayUpdate: false,
            displayUpdatePermission: false,
        };
    },
    methods: {
        cancelPopup: function () {
            this.displayInfo = true;
            this.displayUpdate = false;
        },
        addPermission: function (e) {
            this.displayInfo = false;
            this.displayUpdatePermission = true;
            this.displayUpdate = true;
        },
        deletePermission: function (e) {
            this.displayInfo = false;
            this.displayUpdatePermission = false;
            this.displayUpdate = true;
        },
    }
};
</script>
