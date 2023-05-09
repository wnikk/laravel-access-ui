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
import Breadcrumbs from '@/bt/breadcrumbs.vue';
import Rules from '@/bt/rules.vue';
import Owners from '@/bt/owners.vue';
import Inherit from '@/bt/inherits.vue';
import Permission from '@/bt/permissions.vue';

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
            display: 'Transition',
            components: {
                rules:  Rules,
                owners: Owners,
                inherit: Inherit,
                permission: Permission,
            },
            owner: 0,
            usePath: false,
            thatPath: null,
            attrs: {},
        };
    },
    methods: {
        selectComponent(name, owner)
        {
            if (owner) {
                this.selectOwner(owner);
            }
            if (typeof (this.components[name]) === 'undefined') {
                console.error('Wrong component "'+name+'" name!');
                return;
            }
            let objName = 'route'
                + name.charAt(0).toUpperCase()
                + name.slice(1);
            let routes = Object.assign({}, this[objName]);
            if(this.owner && routes) for (const i in routes) {
                routes[i] = routes[i].replace(':owner:', this.owner);
            }
            this.attrs = {};
            this.attrs[objName] = routes;

            if (name === 'owners') {
                this.attrs.availableInherit    = this.availableInherit;
                this.attrs.availablePermission = this.availablePermission;
            }
            this.display = name;
            this.fixedLinkPage();
        },
        selectOwner(id) {
            this.owner = id;
        },
        fixedLinkPage() {
            if (!this.usePath) return;
            let path = this.display;
            if (this.display === 'inherit' || this.display === 'permission') {
                path += ':' + this.owner;
            }
            this.thatPath = path;
            path = '!'+path;
            if (history.pushState) {
                window.history.pushState(null, null, '#' + path);
            } else {
                window.location.hash = '#' + path;
            }
        },
        goLinkPage(path) {
            if (!this.usePath || path === this.thatPath || path.charAt(0) !== '!') return;
            path          = path.slice(1).split(':', 2);
            let component = path[0];
            let owner     = path[1]??null;
            path = component.charAt(0).toUpperCase() + component.slice(1);

            if (!this['available'+path]) return;

            this.display = 'Transition';
            setTimeout(()=>{
                this.selectComponent(component, owner);
            }, 100);
        },
    },
    computed: {
        startView()
        {
            return this.components[this.display]??this.display;
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

        if (
            this.availableRules + this.availableOwners +
            this.availableInherit + this.availablePermission
            > 1
        ) {
            this.usePath = true;
            window.addEventListener("hashchange", (event) => {
                var currentPath = window.location.hash.slice(1);
                this.goLinkPage(currentPath);
            }, false);
        }
    },
}
</script>
