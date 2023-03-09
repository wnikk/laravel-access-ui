
import * as Vue from 'vue';
import emitter from 'tiny-emitter/instance'
import helper from './libs/helper'
import http from './libs/http'
import i18n from './libs/vue-i18n'
import localeEng from './lang/en.json'

import Rules from '@/rules.vue';
import Owners from '@/owners.vue';
import './../css/icon.css';
import './../css/main.css';
import 'vue-multiselect/dist/vue3-multiselect.css';


(function( window, document, undefined ) {

    const accessUi = new function()
    {
        this.locale = {
            'en': localeEng
        };

        let config = {
            csrfToken: null,
            routeRules: {
                list:   null,// '/rules.php?list',
                create: null,// '/rules.php?add',
                update: null,// '/rules.php?save=%id%',
                delete: null,// '/rules.php?delete=%id%',
            },
            routeOwners: {
                list:   null,// '/owners.php?list',
                create: null,// '/owners.php?add',
                update: null,// '/owners.php?save=%id%',
                delete: null,// '/owners.php?delete=%id%',
            },
            components: {
                rules:  Rules,
                owners: Owners,
            },
        };

        let selectedComponentName = 'rules';
        let app;

        this.getLocale = (lang, group) => {
            if (typeof(this.locale[lang]) === 'undefined' || typeof(this.locale[lang][group]) === 'undefined') return undefined;
            return this.locale[lang][group];
        };

        this.setComponentName = (componentName) => {
            if (typeof (config.components[componentName]) === 'undefined') return;
            selectedComponentName = componentName;
        };

        this.setOptions = (options) => {
            config = helper.extend(config, options);
        };

        this.makeApp = () => {
            const localApp = Vue.createApp(
                config.components[selectedComponentName],
                { ...config }
            );
            localApp.use(i18n);
            localApp.config.globalProperties.$http = http;
            localApp.config.globalProperties.emitter = emitter;
            return localApp;
        };

        this.init = (domElement, componentName, options) => {
            if (options) this.setOptions(options);
            if (componentName) this.setComponentName(componentName);
            app = this.makeApp();
            app.mount(domElement);
        };
    };

    if( typeof( window.accessUi ) === 'undefined' )
    {
        window.accessUi = accessUi;
    }
})( window, document );
