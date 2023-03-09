<template>
    <li v-if="owner" :data-id=owner.id :class=className>

        <OwnerEdit v-if="displayEdit"
              :owner=owner
              @cancel="cancelPopup()"
        ></OwnerEdit>
        <ownerDelete v-if="displayDelete"
                  :owner=owner
                  @cancel="cancelPopup()"
        ></ownerDelete>

        <article v-if="displayInfo" class="info">

            <h4>
                {{ owner.name }}
                <span v-if="owner.original_id" class="original_id">#{{ owner.original_id }}</span>
            </h4>

            <fieldset v-if="availableEdit || availableDelete">
                <button v-if=availableEdit class="icon icon-edit" @click="editOwner($event)"></button>
                <button v-if=availableDelete class="icon icon-delete" @click="deleteOwner($event)"></button>
            </fieldset>

            <label class="type">{{ owner.typeName }}</label>
            <time>{{ owner.created_at }}</time>

        </article>
    </li>
</template>

<script>
import OwnerEdit from '@/elements/ownerEdit.vue'
import ownerDelete from '@/elements/ownerDelete.vue'

export default {
    name: "Owner",
    components: {
        OwnerEdit,
        ownerDelete
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
        owner: Object,
    },
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
