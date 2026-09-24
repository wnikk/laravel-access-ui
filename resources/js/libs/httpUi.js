/*!
 * httpUi.js v3.1.0, trimmed for this bundle
 * https://github.com/wnikk/httpui
 * (c) 2025 Released under the MIT License
 *
 * What is left out of the original: the jQuery transport and the jQuery plugin, the AMD and
 * CommonJS wrappers, the callable Proxy around the instance, and the verb shortcuts. The bundle calls request() through fetch and
 * nothing else, and an application on Laravel 13 is not expected to load jQuery for this.
 */
'use strict';

/**
 * The default settings for the UI.
 * @type {Object}
 */
let defaults = {
  errorsMap: {
    '0': 'An error occurred: Could not connect to the server, please check your internet connection.',
    '400': 'An error occurred: 400 - request failed',
    '404': 'An error occurred: 404 - page not found or address changed',
    '419': 'An error occurred: 419 - page expired, please reload the page',
    '500': 'The server encountered a 500 - unknown error',
    'parsererror': 'An error occurred: parser - the server returned an invalid JSON request',
    'timeout': 'An error occurred: timeout - the request timed out too long',
    'unknown': 'An error occurred: ',
  },
  icons: {
    success: '&#x2713;',
    danger: '&#9888;',
  }
};

/**
 * Class representing a query UI.
 */
class QueryUi {
  id = this.generateId();
  url = null;
  method = 'GET';
  headers = {
    'Accept': 'application/json, text/javascript',
    'X-Requested-With': 'XMLHttpRequest',
  };
  data = undefined;
  requestData = undefined;
  response = undefined;
  responseData = undefined;
  beforeRequest = null;
  onSuccess = null;
  onError = null;
  context = document.body;
  contextLock = undefined;
  contextStatus = undefined;
  ui = null;

  /**
   * Create a query UI.
   *
   * @param {Object|string} params - The parameters for the query.
   */
  constructor(params) {
    this.headers['X-Requested-Id'] = this.id;

    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (token) { this.headers['X-CSRF-TOKEN'] = token; }

    if (typeof params === 'string' || params instanceof String) {
      params = {
        url: params,
      };
    }
    if (!params) { params = {}; }
    Object.assign(this, params);
  }

  /**
   * Generate a unique ID/
   *
   * @returns {string}
   */
  generateId() {
    return (new Date().getTime()) + '-' + Math.floor(Math.random() * 999);
  }
  /**
   * Add response data to the query.
   *
   * @param {Object} responseObject - The response object.
   * @param {Object} responseData - The response data.
   * @returns {QueryUi} The updated query UI.
   */
  addResponse(responseObject, responseData) {
    this.requestData = this.data;
    this.response = responseObject;
    this.data = this.responseData = responseData;
    return this;
  }
}

/**
 * Class representing the UI.
 */
class Ui {
  id = null;
  context = null;
  contextLock = null;
  contextStatus = null;
  errorsMap = defaults.errorsMap;
  icons = defaults.icons;

  /**
   * Create a UI.
   *
   * @param {Object} params - The parameters for the UI.
   */
  constructor(params) {
    this.id = (params?.id?.replace(/[^a-z0-9]/gi, '')) ?? ('0' + (new Date().getTime()) + '' + (Math.random() * 999));
    this.context = params?.context ?? document.body;
    this.contextLock = params?.contextLock ?? this.context;
    this.contextStatus = params?.contextStatus ?? this.context;
  }

  /**
   * Display a loading spinner.
   *
   * @param {HTMLElement} context - The context element.
   * @param {string} ajaxId - The AJAX ID.
   * @returns {string} The AJAX ID.
   */
  displayLoader(context, ajaxId) {
    context = context || this.contextLock;
    ajaxId = ajaxId || this.id;

    // A request that asked for no lock draws no loader: a check that runs while somebody types
    // must not veil the field they are typing into.
    if (context === false) { return ajaxId; }

    const loader = document.createElement('div');
    loader.id = `aj-loader-${ajaxId}`;
    loader.className = `aj-loader-${ajaxId} aj-loader-parent`;
    loader.innerHTML = `
              <div class="aj-loader-element">
                  <svg class="aj-circular" viewBox="25 25 50 50">
                      <circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/>
                      <circle class="path" cx="50" cy="50" r="15" fill="none" stroke-width="2" stroke-miterlimit="10"/>
                      <circle class="path" cx="50" cy="50" r="10" fill="none" stroke-width="2" stroke-miterlimit="10"/>
                  </svg>
              </div>`;
    loader.style.width = `${context.offsetWidth + 20}px`;
    loader.style.height = `${context.offsetHeight + 20}px`;
    context.prepend(loader);
    return ajaxId;
  }

  /**
   * Hide the loading spinner.
   *
   * @param {string} ajaxId - The AJAX ID.
   */
  hideLoader(ajaxId) {
    ajaxId = ajaxId || this.id;
    const loader = document.querySelector(`.aj-loader-${ajaxId}`);
    if (loader) {
      loader.style.opacity = 0;
      setTimeout(() => loader.remove(), 300);
    }
  }

  /**
   * Get error text based on status.
   *
   * @param {Object} response - The response object.
   * @returns {string} The error text.
   */
  #getErrorText(response) {
    return this.errorsMap[response?.status]
      ?? this.errorsMap[response?.statusText]
      ?? this.errorsMap[response?.message]
      ?? response?.message
      ?? `${this.errorsMap.unknown}${response?.status} ${response?.statusText}`;
  }

  /**
   * Escape HTML to prevent injection attacks.
   *
   * @param {string} unsafe - The unsafe HTML string.
   * @returns {string} The escaped HTML string.
   */
  escapeHtml(unsafe) {
    if (!unsafe) { return ''; }
    return (unsafe + '')
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  /**
   * Display an alert box.
   *
   * @param {string} html - The HTML content of the alert.
   * @param {string} type - The type of the alert.
   * @param {string} icon - The icon for the alert.
   * @returns {HTMLElement} The alert element.
   */
  showAlert(html, type, icon = null) {
    const context = this.contextStatus;
    const ajaxId = this.id;

    if (typeof type === 'undefined') { type = 'info'; }
    const alert = document.createElement('div');
    alert.id = `aj-status-${ajaxId}`;
    alert.className = `aj-status-${ajaxId} aj-notification`;

    alert.innerHTML = `
              <div id="alert-${ajaxId}" class="alert alert-dismissible alert-${type}" role="alert">
                  <button type="button" class="btn btn-close" style="float: right;"></button>
                  <div>${icon ? icon : ''} ${html}</div>
              </div>`;
    context.prepend(alert);
    alert.querySelector('.btn-close').addEventListener(
      'click',
      () => this.hideAlert(alert)
    );
    return alert;
  }

  /**
   * Display a success message.
   *
   * @param {string} html - The HTML content of the success message.
   * @param {string} icon - The icon for the success message.
   * @returns {HTMLElement} The alert element.
   */
  showSuccess(text, icon = null) {
    if (icon === null) { icon = this.icons.success; }
    return this.showAlert(
      this.escapeHtml(text),
      'success',
      icon
    );
  }

  /**
   * Display an extended alert box.
   *
   * @param {Object} json - The JSON object containing the alert message and errors.
   * @returns {HTMLElement} The alert element.
   */
  showAlertExtended(json) {
    // message/errors приходят с сервера и могут нести чужой текст (ответ внешнего
    // шлюза, пользовательский ввод) — только как текст, не как разметка.
    return this.showAlert(
      '<strong>' + this.escapeHtml(json?.message) + '</strong>' +
      (json?.errors ? this.errorsToUlTree(json.errors) : ''),
      'danger',
      this.icons.danger
    );
  }

  /**
   * Display an alert box for a page expired error.
   *
   * @returns {HTMLElement} The alert element.
   */
  showAlertPageExpired() {
    const data = {
      message: defaults.errorsMap[419],
    };
    return this.showAlertExtended(data);
  }

  /**
   * Display an alert box for an unexpected error.
   *
   * @param {Object} jqXHR - The jQuery XMLHttpRequest object.
   * @param {boolean} showDetailed - Whether to show detailed error information.
   * @returns {HTMLElement} The alert element.
   */
  showAlertUnexpected(jqXHR, showDetailed = false) {
    const errorText = this.#getErrorText(jqXHR);
    let html = this.escapeHtml(errorText);
    if (showDetailed) {
      html += '<button type="button" class="btn btn-detailed">Detailed...</button>';
    }
    const alert = this.showAlert(
      html,
      'danger',
      this.icons.danger
    );
    if (!showDetailed) {
      return alert;
    }

    const report = document.createElement('div');
    report.className = 'aj-error-report';
    report.style.zIndex = '999999';
    report.style.display = 'none';
    report.innerHTML = `
              <button type="button" class="btn-detailed">html</button>
              <strong>Status code</strong>: ${this.escapeHtml(jqXHR?.status?.toString())}<br/>
              <strong>Status text</strong>: ${this.escapeHtml(jqXHR?.statusText)}<br/>
              <strong>Response body</strong>:
              <div class="aj-error-response">
                  <textarea>${this.escapeHtml(jqXHR?.responseText)}</textarea>
              </div>
          `;
    alert.append(report);

    alert.querySelector('.alert .btn-detailed').addEventListener('click', (e) => {
      alert.querySelector('.aj-error-report').style.display = 'block';
      e.target.style.display = 'none';
    });
    report.querySelector('.btn-detailed').addEventListener('click', (e) => {
      const html = report.querySelector('textarea').value;
      const iframe = document.createElement('iframe');
      iframe.className = 'aj-error-response-iframe';
      report.querySelector('.aj-error-response').innerHTML = '';
      report.querySelector('.aj-error-response').appendChild(iframe);
      setTimeout(() => iframe.contentDocument.body.innerHTML = html, 100);
      e.target.style.display = 'none';
    });
    return alert;
  }

  /**
   * Remove all alert boxes.
   */
  remAlerts() {
    const context = this.contextStatus;
    context.querySelectorAll('.aj-notification').forEach(e => e.remove());
  }

  /**
   * Hide an alert box.
   *
   * @param {HTMLElement} alert - The alert element.
   */
  hideAlert(alert) {
    alert.style.transition = "all 300ms ease-in";
    alert.style.overflow = "hidden";
    alert.style.height = `${alert.getBoundingClientRect().height}px`;

    setTimeout(() => { alert.style.height = '0px'; }, 100);
    setTimeout(() => { alert.remove(); }, 400);
  }

  /**
   * Convert an array of errors to a nested list.
   *
   * @param {Object} errors - The errors object.
   * @returns {string} The HTML string of the nested list.
   */
  errorsToUlTree(errors) {
    if (!Object.keys(errors).length) { return ''; }
    return '<ul><li>' + this.flatDeep(errors).map((e) => this.escapeHtml(e)).join('</li><li>') + '</li></ul>';
  }

  /**
   * Flatten a nested array.
   *
   * @param {Object} array - The nested array.
   * @returns {Array} The flattened array.
   */
  flatDeep(array) {
    let flattend = [];
    (function flat(array) {
      for (const [key, val] of Object.entries(array)) {
        if (typeof val === 'object') { flat(val); }
        else { flattend.push(val); }
      }
    })(array);
    return flattend;
  }
}

/**
 * The transport. A plain class: the original wrapped its instance in a Proxy so that
 * `httpUi(params)` and `$(form).httpUi(...)` worked, and a Proxy cannot reach private methods,
 * which is what request() is made of. The bundle only ever calls request().
 */
class HttpUi {
  /**
   * Set the default options.
   *
   * @param {Object} newDefaults - The new default options.
   */
  setDefaults(newDefaults) {
    defaults = this.extend(defaults, newDefaults);
  }

  /**
   * Check if a variable is an object.
   *
   * @param {*} x - The variable to check.
   * @returns {boolean} True if the variable is an object, false otherwise.
   */
  isObject(x) {
    return typeof x !== 'function' && Object(x) === x;
  }

  /**
   * Merge defaults with user options.
   *
   * @param {Object} defaults - The default options.
   * @param {Object} options - The user options.
   * @returns {Object} The merged options.
   */
  extend(defaults = {}, options = {}) {
    return Object.
      entries(options).
      reduce((acc, [k, v]) =>
        this.isObject(v) && this.isObject(defaults[k])
          ? { ...acc, [k]: this.extend(defaults[k], v) }
          : { ...acc, [k]: v }
        , defaults
      );
  }

  /**
   * Serialize form data into an object.
   *
   * @param {HTMLFormElement} form - The form element.
   * @returns {FormData|Object} The serialized form data.
   */
  serializeForm(form) {
    if (typeof form == 'object' && form.nodeName === "FORM") {
      //    const formEntries = new FormData(html).entries();
      //    return Object.assign(...Array.from(formEntries, ([x,y]) => ({[x]:y})));
      return new FormData(form);
    }
    const inputs = form.querySelectorAll('input, select, textarea');
    const data = {};
    inputs.forEach(input => {
      if (input.type === "checkbox") {
        data[input.name] = input.checked ? input.value : "";
      } else if (input.type === "radio") {
        if (!data[input.name]) data[input.name] = "";
        if (input.checked) data[input.name] = input.value;
      } else if (input.tagName === "SELECT" && input.multiple && input.name.endsWith("[]")) {
        const sname = input.name.slice(0, -2);
        input.querySelectorAll("option:checked").forEach((option, j) => {
          data[`${sname}[${j}]`] = option.value;
        });
      } else {
        data[input.name] = input.value;
      }
    });
    return data;
  }

  /**
   * Display an error message box.
   *
   * @param {QueryUi} query - The query object.
   */
  displayAlert(query) {
    if (!(Object(query) instanceof QueryUi)) {
      return console.error('Invalid query object:', query);
    }
    const jqXHR = query.response;
    if (typeof (jqXHR.data) !== 'object' &&
      jqXHR.status === 419 &&
      jqXHR.responseText.indexOf('Page Expired') > 0
    ) {
      return query.ui.showAlertPageExpired();
    }
    if (typeof (jqXHR.data) === 'object' && jqXHR.data?.message) {
      return query.ui.showAlertExtended(jqXHR.data);
    }
    return query.ui.showAlertUnexpected(jqXHR, jqXHR?.readyState);
  }

  /**
   * Prepare AJAX query parameters.
   *
   * @param {Object} params - The parameters for the query.
   * @returns {QueryUi} The prepared query object.
   */
  #prepareQuery(params) {
    const query = new QueryUi(params);

    if (typeof query.data === 'undefined' && query.method !== 'GET' && query.context instanceof HTMLElement) {
      query.data = this.serializeForm(query.context);
      // if form have files query set correct content type for form data
      // Content-Type = multipart/form-data automatically set by browser
      // if form have no files and no enctype set, set content type to urlencoded
      // if form have enctype set, do nothing, user want custom content type
      if (
        query.data
        && typeof query.data === 'object'
        && query.data instanceof FormData
        && typeof query.headers['Content-Type'] === 'undefined'
        && query.context.nodeName === "FORM"
        && query.context.getAttribute('enctype') === null
        && query.context.querySelector('input[type="file"]') === null
      ) {
        const urlEncodedData = new URLSearchParams();
        for (const [key, value] of query.data.entries()) {
          urlEncodedData.append(key, value);
        }
        query.data = urlEncodedData.toString();
        query.headers['Content-Type'] = 'application/x-www-form-urlencoded';
      }
    }
    if (query.data && typeof query.data === 'object' && !(query.data instanceof FormData)) {
      query.data = JSON.stringify(query.data);
      query.headers['Content-Type'] = 'application/json';
    }
    if (query.contextLock === undefined || query.contextLock === null) { query.contextLock = query.context; }
    if (!query.contextStatus) { query.contextStatus = query.context; }

    return query;
  }

  /**
   * Send through fetch.
   *
   * @param {QueryUi} query - The query object.
   * @param {Function} resolve - The resolve function for the promise.
   * @param {Function} reject - The reject function for the promise.
   * @returns {Promise} The fetch promise.
   */
  #sendRequestFetch(query, resolve, reject) {
    query.ui.remAlerts();
    query.ui.displayLoader();
    return fetch(query.url, {
      //method: query.method,
      //headers: query.headers,
      body: query.data,
      ...query
    }).then((response) => {
      query.ui.hideLoader();
      return response.json().then((data) => {
        if (response.ok) {
          query.addResponse(response, data);
          if (query.onSuccess) { query.onSuccess.call(query.context, data, query); }
          resolve(query);
        } else {
          throw { data, status: response.status, statusText: response.statusText };
        }
      });
    }).catch((jqXHR) => {
      this.#requestFail(jqXHR, query, reject);
    });
  }

  /**
   * Handle AJAX request failure.
   *
   * @param {Object} jqXHR - The jQuery XMLHttpRequest object.
   * @param {QueryUi} query - The query object.
   * @param {Function} reject - The reject function for the promise.
   */
  #requestFail(jqXHR, query, reject) {
    query.ui.hideLoader();
    try {
      jqXHR.data = JSON.parse(jqXHR.responseText);
    } catch (e) { }
    query.addResponse(jqXHR, jqXHR.data);
    if (query.onError) { query.onError.call(query.context, jqXHR, query); }
    else { this.displayAlert(query); }
    reject(query);
  }

  /**
   * Load data via AJAX.
   *
   * @param {Object} params - The parameters for the request.
   * @param {string} params.url - The URL for the request.
   * @param {string} [params.method='GET'] - The HTTP method for the request.
   * @param {Object} [params.headers] - The headers for the request.
   * @param {Object} [params.data] - The data to be sent with the request.
   * @param {string} [params.id] - The unique ID of the request.
   * @param {HTMLElement} [params.context] - The context element for the request.
   * @param {Function} [params.beforeRequest] - Function to be called before the request is sent.
   * @param {Function} [params.onSuccess] - The callback function to be called on success.
   * @param {Function} [params.onError] - The callback function to be called on error.
   * @param {HTMLElement} [params.contextLock=context] - The context element to be locked during the request.
   * @param {HTMLElement} [params.contextStatus=context] - The context element for status messages.
   * @returns {Promise<Object>} The promise for the AJAX request.
   * @returns {Object} return - The QueryUi object of request.
   * @returns {Object} return.data - The data sent with the response.
   * @returns {Object} return.response - The response object.
   * @returns {Object} return.responseData - The response data.
   * @returns {Object} return.requestData - The request data.
   * @returns {Object} return.ui - The UI object.
   */
  async request(params) {
    const query = this.#prepareQuery(params);
    query.ui = new Ui(query);
    if (query.beforeRequest) { query.beforeRequest(query); }
    return new Promise((resolve, reject) => this.#sendRequestFetch(query, resolve, reject));
  }
}

// One instance for the bundle: the loader and the alerts keep no state between requests.
export default new HttpUi();
