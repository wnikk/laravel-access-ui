import * as Vue from 'vue';
import Emitter from 'tiny-emitter'
import helper from './libs/helper'
import http from './libs/http'
import i18n from './libs/vue-i18n'
import localeEng from './lang/en.json'

import Main from '@/main.ukit.vue';

import './../css/icon.css';
import './../css/main.ukit.css';
import 'vue-multiselect/dist/vue3-multiselect.css';

/*!
 * Laravel-access-ui (theme:ukit) v1.3.4
 * (c) 2023 Nikolya May
 * Released under the MIT License.
 */

(function( window, document, undefined ) {

    const accessUi = new function()
    {
        this.locale = {
            'en': localeEng
        };

        let config = {
            csrfToken: null,

            availableRules: false,
            availableOwners: false,
            availableInherit: false,
            availablePermission: false,

            routeRules: {
                list:   null,// '/rules.php?list',
                create: null,// '/rules.php?add',
                update: null,// '/rules.php?save=:id:',
                delete: null,// '/rules.php?delete=:id:',
            },
            routeOwners: {
                list:   null,// '/owners.php?list',
                create: null,// '/owners.php?add',
                update: null,// '/owners.php?save=:id:',
                delete: null,// '/owners.php?delete=:id:',
            },
            routeInherit: {
                list:   null,// '/inherit.php?list',
                create: null,// '/inherit.php?add',
                delete: null,// '/inherit.php?delete=:id:',
            },
            routePermission: {
                list:   null,// '/permission.php?list',
                update: null,// '/permission.php?save=:id:',
            },
            selectedComponent: 'rules',
        };

        this.getLocale = (lang, group) => {
            if (typeof(this.locale[lang]) === 'undefined' || typeof(this.locale[lang][group]) === 'undefined') return undefined;
            return this.locale[lang][group];
        };

        this.setOptions = (options) => {
            return helper.extend(config, options);
        };

        this.makeApp = (options) => {
            const localApp = Vue.createApp(
                Main,
                { ...options }
            );
            localApp.use(i18n);
            localApp.config.globalProperties.$http = http;
            localApp.config.globalProperties.emitter = new Emitter();
            return localApp;
        };

        this.init = (domElement, componentName, options) => {
            options = this.setOptions(options);
            if (componentName) options.selectedComponent = componentName;
            let app = this.makeApp(options);
            app.mount(domElement);
            return app;
        };
    };

    if( typeof( window.accessUi ) === 'undefined' )
    {
        window.accessUi = accessUi;
    }
})( window, document );
