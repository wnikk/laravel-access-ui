<template>
    <div class="accessUi">
        <breadcrumbs
            :available-rules="availableRules"
            :available-owners="availableOwners"
            :available-inherit="availableInherit"
            :available-permission="availablePermission"
            :opened-component="display"
        ></breadcrumbs>
        <component :is="startView" v-bind="attrs"></component>
    </div>
</template>
<script>
import * as Vue from 'vue';
import Breadcrumbs from '@/ukit/breadcrumbs.vue';
import Rules from '@/ukit/rules.vue';
import Owners from '@/ukit/owners.vue';
import Inherit from '@/ukit/inherits.vue';
import Permission from '@/ukit/permissions.vue';

export default {
    name: "Main",
    components: {
        Breadcrumbs
    },
    props: {
        csrfToken: String,
        selectedComponent: String,
        selectedOwner: Number,

        availableRules: Boolean,
        availableOwners: Boolean,
        availableInherit: Boolean,
        availablePermission: Boolean,

        routeRules: Object,
        routeOwners: Object,
        routeInherit: Object,
        routePermission: Object,
    },
    data() {
        return {
            VueVersion: Vue.version,
            display: '',
            components: {
                rules:  Rules,
                owners: Owners,
                inherit: Inherit,
                permission: Permission,
            },
            owner: 0,
            attrs: {},
        };
    },
    methods: {
        selectComponent(name, owner)
        {
            if (owner) {
                this.selectOwner(owner);
            }
            if (typeof (this.components[name]) !== 'undefined') {
                this.display = name;
            }
            let objName = 'route'
                + this.display.charAt(0).toUpperCase()
                + this.display.slice(1);
            let routes = Object.assign({}, this[objName]);
            if(this.owner && routes) for (const i in routes) {
                routes[i] = routes[i].replace(':owner:', this.owner);
            }
            this.attrs = {};
            this.attrs[objName] = routes;

            if (this.display === 'owners') {
                this.attrs.availableInherit    = this.availableInherit;
                this.attrs.availablePermission = this.availablePermission;
            }
        },
        selectOwner(id) {
            this.owner = id;
        }
    },
    computed: {
        startView()
        {
            return this.components[this.display];
        }
    },
    beforeMount () {
        this.emitter.on('selectOwner', this.selectOwner);
        this.emitter.on('selectComponent', this.selectComponent);

        this.emitter.emit('selectOwner', this.selectedOwner);
        this.emitter.emit('selectComponent', this.selectedComponent, this.selectedOwner);
    },
    created() {
        const headers =  {
            'Accept': 'application/json, text/javascript',
            'X-Requested-With': 'XMLHttpRequest',
        };
        if(this.csrfToken) headers['X-CSRF-TOKEN'] = this.csrfToken;
        this.$http.setOptions({ headers });
    },
}
</script>
