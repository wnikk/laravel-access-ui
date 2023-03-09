
import helper from './helper.js'

const xhr = (() => {

    let mainOptions = {
        method: 'GET',
        baseUrl: '',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        makeId: () => helper.uuidv4(),
        progress: (percent, id, e) => null,
        validateStatus: (status) => (status >= 200 && status < 400),
    };

    let listXhr = {};

    return class {

        /**
         * constructor
         *
         * @param options
         */
        constructor (options) {
            this.setOptions(options);
        }

        setOptions (options) {
            mainOptions = helper.extend(mainOptions, options);
        }

        formatResponseHeaders (headers) {
            // Convert the header string into an array
            // of individual headers
            const arr = headers.trim().split(/[\r\n]+/);

            // Create a map of header names to values
            const headerMap = {};
            arr.forEach((line) => {
                const parts = line.split(': ');
                const header = parts.shift();
                headerMap[header] = parts.join(': ');
            });
            return headerMap;
        };

        serializeElement (html) {
            if (typeof html !== 'object' || !(html instanceof HTMLElement)) return [];
            if (typeof html == 'object' && html instanceof HTMLFormElement) {
                //    const formEntries = new FormData(html).entries();
                //    return Object.assign(...Array.from(formEntries, ([x,y]) => ({[x]:y})));
                return new FormData(html);
            }
            const inp = html.querySelectorAll('input, select, textarea');
            let data = new FormData();
            for (const i in inp) {
                const el = inp[i];
                if (el instanceof HTMLElement) {} else {
                    continue;
                }
                if (el.type === "checkbox"){
                    data.append(el.name, el.checked ? el.value : '');
                }else if (el.type === "radio") {
                    if (!data.has(el.name)) data.set(el.name, '');
                    if (el.checked) data.set(el.name, el.value);
                } else if (el.tagName === "SELECT" && el.multiple && el.name.substring(-2) === "[]"
                ) {
                    const sname = el.name.substring(0, el.name.length-2);
                    const ssel = inp.querySelectorAll("option:checked");
                    for (const j in ssel) {
                        data.append(sname+"["+j+"]", ssel.value);
                    }
                } else {
                    data.append(el.name, el.value);
                }
            }
            return data;
        };

        async request (url, params)
        {
            if (params.data && (params.data instanceof HTMLElement)) {
                params.data = this.serializeElement(params.data);
            }

            let query = {
                id: null,
                url: url,
                method: 'GET',
                headers: null,
                data: null,
                context:  undefined,
                datatype: 'text',
                success:  () => null,
                error:    () => null,
                loadstart:() => null,
                progress: () => null,
                loadend:  () => null,
                xhr: null,
            };
            const updProgress = (e, rate, plus) => {
                if (typeof e !== 'number') {
                    e = e.total?(e.loaded / e.total):0;
                }
                if (!rate) rate = 1;
                if (!plus) plus = 0;
                return query.progress(
                    ((e) * rate) + plus,
                    query.id,
                    query
                );
            }

            query = helper.extend(query, mainOptions);
            query = helper.extend(query, params);

            if (!query.id)  query.id = query.makeId();
            if (!query.xhr) query.xhr = new XMLHttpRequest();
            query.xhr.responseType = query.datatype;
            listXhr[query.id] = query;

            return new Promise((resolve, reject) => {
                let xhr = query.xhr;
                let url = query.url;
                if (url && query.baseUrl && url.substring(0, 4).toLowerCase() !== 'http') {
                    url = query.baseUrl+url;
                }
                let getResponse = () => {
                    let data = xhr.response;
                    if (
                        typeof(data) === "string"
                        && xhr.getResponseHeader("Content-Type")
                        && xhr.getResponseHeader("Content-Type").indexOf('application/json') === 0
                    ) {
                        data = JSON && JSON.parse(data);
                    }
                    return {
                        ok: query.validateStatus(xhr.status),
                        id: query.id,
                        url: url,
                        data: data,
                        status: xhr.status,
                        statusText: xhr.statusText,
                        headers: this.formatResponseHeaders(xhr.getAllResponseHeaders()),
                        context: query.context,
                        request: query,
                    };
                };

                // upload
                xhr.upload.addEventListener('progress', e => updProgress(e, 49));
                // download
                xhr.addEventListener('progress', e => updProgress(e, 50, 49));
                //xhr.addEventListener('load', () => {});
                //xhr.addEventListener('error', () => {});
                //xhr.addEventListener('abort', () => {});
                // final
                xhr.addEventListener('loadend', () => {
                    updProgress(100);
                    query.loadend(query.id, query);
                    delete listXhr[query.id];
                    let response = getResponse();
                    if (response.ok) {
                        if (query.success) query.success(response);
                        return resolve(response);
                    } else {
                        if (query.error) query.error(response);
                        return reject(response);
                    }
                });
                // send
                query.loadstart(query.id, query);
                updProgress(0);
                xhr.open(query.method, url, true);
                if (query.headers) for (const key in query.headers) {
                    xhr.setRequestHeader(key, query.headers[key]);
                }
                xhr.send(query.data??null);
            })
        }


        async get     (url, params) { return this.request(url, helper.extend({method: 'GET'}, params)); }
        async delete  (url, params) { return this.request(url, helper.extend({method: 'DELETE'}, params)); }
        async head    (url, params) { return this.request(url, helper.extend({method: 'HEAD'}, params)); }
        async options (url, params) { return this.request(url, helper.extend({method: 'OPTIONS'}, params)); }
        async post  (url, data, params) { return this.request(url, helper.extend({method: 'POST', data: data}, params)); }
        async put   (url, data, params) { return this.request(url, helper.extend({method: 'PUT', data: data}, params)); }
        async patch (url, data, params) { return this.request(url, helper.extend({method: 'PATCH', data: data}, params)); }
    }
})();

export default xhr;