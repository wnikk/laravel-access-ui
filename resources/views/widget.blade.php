{{--
    The assignment card, for a page that is already showing somebody.

    A user-edit screen gets one more card: where this account takes its rights from, a dropdown of
    everything that may be handed out, and a remove button per row. Plus how many permissions it ends up
    with — the number only, never the list. That number is the point of showing one at all: an account
    with no roles and a non-zero count is one somebody hand-tuned, and "no roles" and "no rights" are
    not the same statement.

    It talks to the same three endpoints as the panel's inheritance screen, asked from the other end.

    Usage, all equivalent:

        @accessUiWidget(['owner' => $user])
        @accessUiWidget(['owner' => $user->getOwner()->id])
        @accessUiWidget(['owner_id' => 17])

    The first form is duck-typed on `getOwner()`, which comes from the trait access-rules asks you to put
    on the model — so this package needs no knowledge of your classes, and the owner row is created on
    demand, which is right: a user has none until something is granted to them, and mounting this card is
    the moment somebody is about to.

    Options beyond the owner:

        title      heading; defaults to the name of the assignable entity, or a generic one
        compact    true for a denser card

    Renders nothing at all when the routes are switched off or the inheritance screen is disabled, so a
    page carrying this line stays valid in an installation where the panel is not in use.
--}}
@php
    /** @var \Wnikk\LaravelAccessUi\AccessUi $accessUiService */
    $accessUiService = app(\Wnikk\LaravelAccessUi\AccessUi::class);
    $accessUiOptions = isset($options) && is_array($options) ? $options : [];
    $accessUiOwner   = $accessUiService->widgetAvailable()
        ? $accessUiService->widgetOwnerId($accessUiOptions)
        : null;
@endphp

@if ($accessUiOwner !== null)
    @php
        // Unique per instance: a page may carry more than one card.
        $accessUiMount   = 'accessUiWidget'.$accessUiOwner.substr(md5($accessUiOwner.microtime(false)), 0, 6);
        $accessUiPayload = array_merge($accessUiService->bootstrapPayload(), [
            'owner'   => $accessUiOwner,
            'title'   => $accessUiOptions['title'] ?? null,
            'compact' => (bool) ($accessUiOptions['compact'] ?? false),
        ]);
    @endphp

    @include('accessUi::assets')

    <div id="{{ $accessUiMount }}" class="wacu-root wacu-widget-root"></div>

    @include('accessUi::boot', [
        'mount'   => $accessUiMount,
        'method'  => 'widget',
        'options' => $accessUiPayload,
    ])
@endif
