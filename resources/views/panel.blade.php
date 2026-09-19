{{--
    The panel itself: a mount point and the call that fills it.

    Included by both the standalone page and the embedded one, so those two differ only in what
    surrounds this.
--}}
<div id="accessUiPanel" class="wacu-root">
    <noscript>
        <p class="wacu-empty">{{ __('This screen needs JavaScript.') }}</p>
    </noscript>
</div>

@include('accessUi::boot', [
    'mount'   => 'accessUiPanel',
    'method'  => 'init',
    'options' => $bootstrap,
])
