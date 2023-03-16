
const helper = {

    toInt: (num) => {
        num = parseInt(num);
        return isNaN(num)?0:num;
    },

    isObject: x => typeof x !== 'function' && Object (x) === x,

    /**
     * Merge defaults with user options
     *
     * Demo 1
     * const x = { a: { b: { c: 1, d: 1 } } }
     * const y = { a: { b: { c: 2, e: 2 } }, f: 2 }
     * console.log (merge (x, y))
     * // { a: { b: { c: 2, d: 1, e: 2 } }, f: 2 }
     *
     * Demo 2
     * const x = [ 1, 2, 3, 4, 5 ]
     * const y = [ 0, 0, 0 ]
     * const z = [ , , , , , 6 ]
     * console.log (merge (x, y)) // [ 0, 0, 0, 4, 5 ]
     * console.log (merge (y, z)) // [ 0, 0, 0, <2 empty items>, 6 ]
     * console.log (merge (x, z)) // [ 1, 2, 3, 4, 5, 6 ]
     *
     * @param {Object} defaults Default settings
     * @param {Object} options User options
     * @returns {Object} Merged values of defaults and options
     */
    extend: (defaults = {}, options = {}) =>
        Object.
        entries(options).
        reduce((acc, [ k, v ]) =>
                helper.isObject (v) && helper.isObject (defaults [k])
                    ? { ...acc, [k]: helper.extend(defaults [k], v) }
                    : { ...acc, [k]: v }
            , defaults
        ),

    /**
     * const x = { a: { b: { c: 1, d: 2 }, c: 3 } }}
     * console.log (flatDeep (x)) // [1, 2, 3]
     *
     * @param array
     * @returns {*[]}
     */
    flatDeep: (array) =>
    {
        let flattend = [];
        (function flat(array) {
            for (const [key, val] of Object.entries(array)) {
                if(typeof val === 'object')flat(val);
                else flattend.push(val);
            }
        })(array);
        return flattend;
    },

    /**
     * Generator uuid v4
     *
     * @returns {string}
     */
    uuidv4: () => {
        return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
            (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
        );
    },

    /**
     * Generator crc32 by string
     *
     * @param {string} r
     * @returns {number}
     */
    crc32: (r) => {
        for(let a,o=[],c=0;c<256;c++){
            a=c;
            for(const f=0;f<8;f++)a=1&a?3988292384^a>>>1:a>>>1;o[c]=a
        }
        for(let n=-1,t=0;t<r.length;t++)n=n>>>8^o[255&(n^r.charCodeAt(t))];
        return(-1^n)>>>0;
    },

    /**
     * Array list to tree
     *
     * @param {array} dataset
     * @param {object} options
     * @returns array
     */
    createDataTree: (dataset, options) => {
        options = options || {};
        let ID_KEY = options.idKey || 'id';
        let PARENT_KEY = options.parentKey || 'parent_id';
        let CHILDREN_KEY = options.childrenKey || 'children';
        const hashTable = Object.create(null);
        dataset.forEach(
            aData => {
                hashTable[aData[ID_KEY]] = {...aData};
                hashTable[aData[ID_KEY]][CHILDREN_KEY] = [];
            }
        );
        const dataTree = [];
        dataset.forEach(aData => {
            if(aData[PARENT_KEY] && hashTable[aData[PARENT_KEY]]){
                hashTable[aData[PARENT_KEY]][CHILDREN_KEY].push(hashTable[aData[ID_KEY]]);
            } else{
                dataTree.push(hashTable[aData[ID_KEY]]);
            }
        });
        return dataTree;
    },
};

export default helper;
