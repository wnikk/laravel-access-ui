
import { createI18n } from "vue-i18n";

let loaded = {};
const i18n = createI18n({
    locale: document.querySelector('html').getAttribute('lang') || 'en',
    fallbackLocale: 'en', // set fallback locale
    globalInjection: true,
    messages: {},
    missing: async (locale, key, type, groupId) => {
        let grp = key.match(/^([0-9a-z-_]{2,32})\..+$/i);
        grp = grp.length === 2?grp[1]:null;

        if (locale && grp && typeof (loaded[locale+'-'+grp]) === 'undefined') {

            loaded[locale+'-'+grp] = true;
            let messages = {};
            /*
            await fetch('/assets/lang/'+locale+'/'+grp+'.json')
                .then((res) => {
                    for (const key of Object.keys(res.data)) {
                        messages[grp+'.'+key] = res.data[key];
                    }
                })
                .catch();
            */
            let resData = accessUi.getLocale(locale, grp);
            if (resData) {
                for (const key of Object.keys(resData)) {
                    messages[grp+'.'+key] = resData[key];
                }
            }

            if (messages) i18n.global.mergeLocaleMessage(locale, messages);
        }
        return '[[e-i18n-'+locale+': '+key+']]';
    },
});
export default i18n;
