{{--
    A complete page, used when `layout.view` is not configured.

    Deliberately bare: no CSS framework, no fonts, nothing from a CDN. The panel styles itself and reads
    well on its own, so this file has no opinions to impose and nothing to load.

    Name your own layout in config/accessUi.php → layout.view and this file goes unused.
--}}
@php
    $accessUiTheme = $bootstrap['theme'] ?? 'auto';
    $accessUiTheme = in_array($accessUiTheme, ['light', 'dark'], true) ? ' wacu-'.$accessUiTheme : '';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }}</title>

    @include('accessUi::assets')
</head>
{{-- The theme class sits on the body so the page background matches the panel it is behind. --}}
<body class="wacu-page{{ $accessUiTheme }}">

@include('accessUi::panel', ['bootstrap' => $bootstrap])

</body>
</html>
