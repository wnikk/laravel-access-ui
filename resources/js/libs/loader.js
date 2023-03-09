
import './../../css/loader.css';

const loader = new ((() => {

    const mainProgress = {
        percent: 0,
        lastValue: -1,
        node: null,
        timer: null,
    };

    let listLoaders = {};

    let listProgress = {};

    return class {
        progress (percent, id) {
            if (percent > 100) percent = 100;
            if (percent === 100) {
                return this.destroyedProgress(id);
            }
            if (typeof (listProgress[id]) === "undefined") this.declareNewProgress(id);
            listProgress[id] = percent;
            if (!mainProgress.node || mainProgress.node === true) return;

            let abs = Object.values(listProgress);
            mainProgress.percent = abs = abs.reduce((a, b) => (a + b)) / abs.length;

            if (mainProgress.lastValue === abs) return;
            mainProgress.node.querySelector('.ajax-progress-container').style.setProperty('--progress', abs+'%');
            mainProgress.node.querySelector('.ajax-progress-container progress').value = abs;
            mainProgress.lastValue = mainProgress.percent;
        }

        declareNewProgress (key) {
            if (!mainProgress.node) {
                mainProgress.node = true;
                const parent = Object.assign(document.createElement('div'), {
                    id: 'ajax-progress-base',
                    className: 'ajax-progress-parent',
                    innerHTML: this.getHtmlProgress(0),
                });
                document.body.insertBefore(parent, document.body.firstChild);
                mainProgress.node = parent;
            }
            listProgress[key] = 0;
        }

        destroyedProgress (key) {
            if (typeof (listProgress[key]) === "undefined") return;
            delete listProgress[key];

            if (listProgress.length) return;
            if (mainProgress.timer) {
                clearTimeout(mainProgress.timer);
            }
            mainProgress.timer = setTimeout(() => {
                if (listProgress.length) return;
                mainProgress.node = mainProgress.node.remove();
            },  500);
        }

        declareLoader (key, context) {
            if (typeof (listLoaders[key]) !== "undefined") return;

            context.insertAdjacentHTML('afterbegin', this.getHtmlLoader(key));
            const loader = document.querySelector('#'+this.makeIdLoader(key));

            const posInfo = getComputedStyle(context);

            loader.style.width = 'calc('+posInfo.width+' + '+posInfo.paddingLeft+' + '+posInfo.paddingRight+' + 4px)';
            loader.style.height = 'calc('+posInfo.height+' + '+posInfo.paddingTop+' + '+posInfo.paddingBottom+' + 4px)';

            loader.style.marginTop = 'calc(0px - ('+posInfo.paddingTop+' + 2px))';
            loader.style.marginLeft = 'calc(0px - ('+posInfo.paddingLeft+' + 2px))';

            listLoaders[key] = loader;
        }

        destroyedLoader (key) {
            if (typeof (listLoaders[key]) === "undefined") return;
            listLoaders[key].remove();
            delete listLoaders[key];
        }

        makeId () {
            return parseInt(new Date().getTime() / 1000, 10).toString(16) +
                '-' +
                Math.floor(Math.random() * 0x75bcd15).toString(16);
        }
        getHtmlProgress (id) {
            if (!id) id = this.makeId();
            return `<div id="aj-progress-`+id+`" class="ajax-loader ajax-progress-element">
                <div class="ajax-progress-container">
                    <progress max="100"/>
                </div>
            </div>`;
        }

        makeIdLoader (id) {
            if (!id) id = this.makeId();
            return 'ajax-loader-'+id;
        };

        getHtmlLoader (id) {
            id = this.makeIdLoader(id);
            return `<div id="`+id+`" class="ajax-loader ajax-loader-element"><div class="ajax-loader-container">
                    <svg class="ajax-circular" viewBox="25 25 50 50" xmlns="http://www.w3.org/2000/svg">
                        <circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/>
                        <circle class="path" cx="50" cy="50" r="15" fill="none" stroke-width="2" stroke-miterlimit="10"/>
                        <circle class="path" cx="50" cy="50" r="10" fill="none" stroke-width="2" stroke-miterlimit="10"/>
                    </svg>
                </div><!--<progress/>--></div>`;
        }

    };

})());

export default loader;

