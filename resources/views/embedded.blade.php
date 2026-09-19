{{--
    The panel inside the host's layout, used when `layout.view` names one.

    The layout has to yield the configured section and nothing else. Everything the panel needs comes
    with it: the stylesheet and script are emitted here, and the CSRF token is read from a
    `<meta name="csrf-token">` when the layout has one — a Laravel layout almost always does, and
    when it does not the token travels in the bootstrap payload instead.

    Both `@extends` and `@section` take the configured names, so nothing here is hard-coded to a
    particular application's conventions. `$title` reaches the layout as an ordinary variable, so a
    layout that wants it can print `{{ $title ?? '' }}`.
--}}
@extends($layout)

@section($section)

    @include('accessUi::assets')

    @include('accessUi::panel', ['bootstrap' => $bootstrap])

@endsection
