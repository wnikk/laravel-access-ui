{{--
    The bundle: one stylesheet, one script, no CDN.

    Reachable from two directions on the same page — a layout that calls `@accessUiAssets`, and a
    widget that includes this itself so it also works on a page that does not. Whichever arrives
    first emits the tags; the second call renders nothing.

    Resolves its own data so the Blade directive can stay a one-liner.
--}}
@php
    /** @var \Wnikk\LaravelAccessUi\AccessUi $accessUiService */
    $accessUiService = app(\Wnikk\LaravelAccessUi\AccessUi::class);
    $accessUiFirst   = $accessUiService->markAssetsEmitted();
    $accessUiAssets  = $accessUiService->assetUrls();
@endphp

@if ($accessUiFirst)
    @if (!empty($accessUiAssets['css']))
        <link rel="stylesheet" href="{{ $accessUiAssets['css'] }}">
    @endif
    @if (!empty($accessUiAssets['js']))
        <script src="{{ $accessUiAssets['js'] }}"></script>
    @endif
@endif
