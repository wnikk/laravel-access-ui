
import Xhr from './xhr.js';
import loader from './loader.js';

const http = new Xhr({
    loadstart:(id, q) => {
        if (q.context) {
            loader.declareLoader(id, q.context);
        }
    },
    loadend:  (id, q) => {
        if (q.context) {
            loader.destroyedLoader(id);
        }
    },
    progress: (percent, id, q) => {
        loader.progress(percent, id);
    },
});

export default http;
