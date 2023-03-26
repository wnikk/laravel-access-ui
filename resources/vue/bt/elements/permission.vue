<template>
    <tr v-if="rule" :data-id=rule.id :class=className>
        <th class="lbl-tree">
            <label class="btn-tree">
                <i class="level" v-if="level" v-for="num in level">&middot;</i>
            </label>
        </th>
        <td class="lbl-guard_name">

            <permissionUpdate v-if="displayUpdate"
                :rule=rule
                :permission=displayUpdatePermission
                @cancel="cancelPopup()"
            ></permissionUpdate>

            <PermissionStatus v-if="simplePermission.length" v-for="item in simplePermission" :permission=item.permission :inherit=item.inherit>
                &nbsp;
            </PermissionStatus>
            <strong>{{ rule.guard_name }}</strong>

            <p v-if="complexPermission.length" class="guard_option_name">
                <PermissionStatus v-for="item in complexPermission" :permission=item.permission :inherit=item.inherit>
                    {{ item.option }}
                </PermissionStatus>
            </p>

        </td>
        <td class="lbl-title">{{ rule.title }}</td>
        <td class="lbl-description">
            <blockquote :title="rule.description">
                {{ rule.description }}
            </blockquote>
        </td>

        <td class="lbl-btn text-center">
            <button v-if=availableUpdate class="btn btn-icon btn-outline-primary btn-sm" @click="addPermission($event)">
                <i class="icon icon-create"></i>
            </button>
            <button v-if=availableDelete class="btn btn-icon btn-outline-danger btn-sm" @click="deletePermission($event)">
                <i class="icon icon-delete"></i>
            </button>
        </td>
    </tr>

    <permission v-if="rule.children && rule.children.length" v-for="child in rule.children"
        :level="level+1"
        :rule=child
        :simplePermission=child.permission.simple
        :complexPermission=child.permission.complex
        :className=className
        :availableUpdate=availableUpdate
        :availableDelete=availableDelete
    ></permission>

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
        level: Number,
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
