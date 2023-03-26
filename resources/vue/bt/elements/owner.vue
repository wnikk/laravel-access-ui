<template>
    <tr v-if="owner" :data-id=owner.id :class=className>
        <th class="lbl-id text-center">
            {{ owner.id }}
        </th>
        <td class="lbl-created_at text-secondary">
            {{ owner.created_at }}
        </td>
        <th class="lbl-owner_type">
            {{ owner.typeName }}
        </th>
        <td class="lbl-name">
            <label>{{ owner.name }}</label>
            <OwnerEdit v-if="displayEdit"
                       :owner=owner
                       @cancel="cancelPopup()"
            ></OwnerEdit>
            <ownerDelete v-if="displayDelete"
                         :owner=owner
                         @cancel="cancelPopup()"
            ></ownerDelete>
        </td>
        <td class="lbl-original_id text-secondary">
            <label>{{ owner.original_id }}</label>
        </td>
        <td class="lbl-btn text-center">

            <button v-if=availableInherit class="btn btn-icon btn-outline-info btn-sm" @click="$emit('editInherit', owner.id)">
                <i class="icon icon-inherit"></i>
            </button>
            <button v-if=availablePermission class="btn btn-icon btn-outline-info btn-sm" @click="$emit('editPermission', owner.id)">
                <i class="icon icon-permission"></i>
            </button>

            <button v-if=availableEdit class="btn btn-icon btn-outline-primary btn-sm" @click="editOwner($event)">
                <i class="icon icon-edit"></i>
            </button>
            <button v-if=availableDelete class="btn btn-icon btn-outline-danger btn-sm" @click="deleteOwner($event)">
                <i class="icon icon-delete"></i>
            </button>

        </td>
    </tr>
</template>

<script>
import OwnerEdit from './ownerEdit.vue'
import OwnerDelete from './ownerDelete.vue'

export default {
    name: "Owner",
    components: {
        OwnerEdit,
        OwnerDelete
    },
    props: {
        className: {
            type: String,
            default: 'owner-item'
        },
        availableEdit: {
            type: Boolean,
            default: true
        },
        availableDelete: {
            type: Boolean,
            default: true
        },
        availableInherit: Boolean,
        availablePermission: Boolean,
        owner: Object,
    },
    emits: ['editInherit', 'editPermission'],
    data() {
        return {
            displayInfo: true,
            displayEdit: false,
            displayDelete: false,
        };
    },
    methods: {
        cancelPopup: function () {
            this.displayInfo = true;
            this.displayEdit = false;
            this.displayDelete = false;
        },
        editOwner: function (e) {
            this.displayInfo = false;
            this.displayEdit = true;
            this.displayDelete = false;
        },
        deleteOwner: function (e) {
            this.displayInfo = false;
            this.displayEdit = false;
            this.displayDelete = true;
        },
    }
};
</script>
