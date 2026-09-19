{{--
    Mount one instance of the interface.

    Params:
      mount    id of the element to mount into
      method   'init' for the full panel, 'widget' for the assignment card
      options  the bootstrap payload, encoded as-is

    Waits for the bundle rather than assuming it has run. The tags are emitted without `defer` so in
    the normal case `window.accessUi` is already there, but a host that moves the script into its own
    pipeline may well defer it, and a silent no-op would be a poor way to find that out.
--}}
<script>
(function () {
    var mountId = {!! json_encode($mount, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};
    var method  = {!! json_encode($method, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};
    var options = {!! json_encode($options, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!};

    function mount() {
        var element = document.getElementById(mountId);

        if (!element) {
            return console.error('[accessUi] mount point #' + mountId + ' is not on the page.');
        }

        window.accessUi[method](element, options);
    }

    function start() {
        if (window.accessUi) {
            return mount();
        }

        var waited = 0;
        var timer  = setInterval(function () {
            if (window.accessUi) {
                clearInterval(timer);
                mount();
            } else if ((waited += 25) > 5000) {
                clearInterval(timer);
                console.error('[accessUi] the bundle did not load; run vendor:publish --tag=accessUi-assets');
            }
        }, 25);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
</script>
