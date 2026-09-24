<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Throwable;
use Wnikk\LaravelAccessRules\AccessRules;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;

/**
 * Configuration, turned into the questions the screens ask.
 *
 * The class never loads, queries or names a model of the host application. The core keeps every
 * owner, users, roles and groups alike, as a row of one table, and that table is the whole world
 * of this panel. An entity in config is a label and two decisions about it, nothing more.
 *
 * Owners are therefore addressed by the id of their row and by nothing else. A host page that
 * wants the assignment widget for a user has that id already: $user->getOwner()->id, from the
 * trait the core asks to put on the model. Resolving it here instead would tie the panel to how
 * the application stores people.
 *
 * Nothing about access is decided here. The core answers can(), explain() and every write; this
 * class knows which screens are on, which owner types the panel lists, and where the bundle is.
 */
class AccessUi
{
    /** @var array<string, array{key:string, type:string, type_id:int, label:string, single:string, create:bool, assignable:bool}>|null */
    private ?array $entities = null;

    /** @var array<string, string> Entity keys that are configured but unusable, with the reason. */
    private array $problems = [];

    private bool $assetsEmitted = false;

    public const SCREENS = ['rules', 'owners', 'permissions', 'inherit', 'explain', 'health', 'xacml'];

    public function __construct(
        private readonly Repository $config,
        private readonly Gate $gate,
    ) {}

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config->get('accessUi.'.$key, $default);
    }

    // =================================================================
    // Routes
    // =================================================================

    /**
     * Both a prefix and a middleware stack are required before anything is registered. These
     * endpoints hand out permissions, so registering them without a guard would be worse than
     * not registering them; requiring the prefix too keeps an install that was never configured
     * from exposing something at a path nobody chose.
     */
    public function routesEnabled(): bool
    {
        return $this->routePrefix() !== null && $this->routeMiddleware() !== [];
    }

    public function routePrefix(): ?string
    {
        $prefix = $this->config('routes.prefix');

        if (! is_string($prefix)) {
            return null;
        }

        $prefix = trim($prefix, "/ \t\n\r\0\x0B");

        return $prefix === '' ? null : $prefix;
    }

    /**
     * @return list<string>
     */
    public function routeMiddleware(): array
    {
        $middleware = $this->config('routes.middleware', []);

        if (is_string($middleware)) {
            $middleware = [$middleware];
        }

        return is_array($middleware) ? array_values(array_filter($middleware)) : [];
    }

    /**
     * Route-name prefix, always ending in a dot.
     */
    public function routeName(): string
    {
        $name = (string) $this->config('routes.as', 'accessUi.');
        $name = $name === '' ? 'accessUi.' : $name;

        return str_ends_with($name, '.') ? $name : $name.'.';
    }

    // =================================================================
    // Screens
    // =================================================================

    public function screenEnabled(string $screen): bool
    {
        return in_array($screen, self::SCREENS, true) && (bool) $this->config('screens.'.$screen.'.enabled', true);
    }

    /**
     * May the current request change anything on this screen? Two gates, both have to open: the
     * "write" switch in config, and the Gate ability named next to it when there is one. A screen
     * may be readable and not writable, which is the useful setting for rules.
     */
    public function screenWritable(string $screen): bool
    {
        if (! $this->screenEnabled($screen) || ! $this->config('screens.'.$screen.'.write', true)) {
            return false;
        }

        $ability = $this->config('screens.'.$screen.'.ability');

        return $ability === null || $this->gate->allows($ability);
    }

    public function authorizeRead(string $screen): void
    {
        abort_unless($this->screenEnabled($screen), 403, 'This screen is disabled in config/accessUi.php.');
    }

    public function authorizeWrite(string $screen): void
    {
        $this->authorizeRead($screen);

        abort_unless($this->screenWritable($screen), 403, 'Writing is not allowed on this screen.');
    }

    // =================================================================
    // Entities
    // =================================================================

    /**
     * The class the application calls a user, read as a string and used as a string. The core
     * derives an owner type from the label; the class is never loaded.
     */
    public function userType(): ?string
    {
        $model = $this->config->get('auth.providers.users.model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    /**
     * Every usable entity, keyed by entity key, in configuration order.
     *
     * An entity whose type is not listed in config/access.php is dropped and the reason is kept
     * for the panel to report. A single mistyped label costs that entity, not the whole screen.
     *
     * @return array<string, array{key:string, type:string, type_id:int, label:string, single:string, create:bool, assignable:bool}>
     */
    public function entities(): array
    {
        if ($this->entities !== null) {
            return $this->entities;
        }

        $this->entities = [];
        $configured     = $this->config('entities', []);

        foreach ((is_array($configured) ? $configured : []) as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                continue;
            }

            try {
                $this->entities[$key] = $this->normalise($key, $definition);
            } catch (Throwable $e) {
                $this->problems[$key] = $e->getMessage();
            }
        }

        return $this->entities;
    }

    /**
     * @return array<string, string>
     */
    public function problems(): array
    {
        $this->entities();

        return $this->problems;
    }

    /**
     * @return array{key:string, type:string, type_id:int, label:string, single:string, create:bool, assignable:bool}
     */
    private function normalise(string $key, array $definition): array
    {
        $type = $definition['type'] ?? $this->userType()
            ?? throw new LogicException('Entity "'.$key.'" has no type and auth.providers.users.model is not set.');

        return [
            'key'  => $key,
            'type' => (string) $type,
            // Throws when the label is absent from config/access.php, owner_types.
            'type_id'    => AccessRules::getTypeID((string) $type),
            'label'      => (string) ($definition['label'] ?? $key),
            'single'     => (string) ($definition['single'] ?? ($definition['label'] ?? $key)),
            'create'     => (bool) ($definition['create'] ?? false),
            'assignable' => (bool) ($definition['assignable'] ?? false),
        ];
    }

    /**
     * One entity, or a 404 when the key is not one the panel knows.
     */
    public function entity(string $key): array
    {
        $entities = $this->entities();

        abort_unless(isset($entities[$key]), 404, 'Unknown entity "'.$key.'".');

        return $entities[$key];
    }

    /**
     * Entities carrying a flag: "create" or "assignable".
     *
     * @return array<string, array>
     */
    public function entitiesWith(string $flag): array
    {
        return array_filter($this->entities(), static fn (array $entity): bool => ! empty($entity[$flag]));
    }

    /**
     * @return list<int>
     */
    public function configuredTypeIds(): array
    {
        return array_values(array_column($this->entities(), 'type_id'));
    }

    public function entityForType(?int $typeId): ?array
    {
        if ($typeId === null) {
            return null;
        }

        foreach ($this->entities() as $entity) {
            if ($entity['type_id'] === $typeId) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * A readable name for any owner type, configured or not.
     *
     * Owners of unconfigured types show up whenever they hold a permission, so they need a label
     * too. "Type#12345" is the honest answer when the core has no label either: the type left
     * config/access.php while rows still point at it.
     */
    public function typeLabel(?int $typeId): string
    {
        if ($entity = $this->entityForType($typeId)) {
            return __($entity['single']);
        }

        if ($typeId === null) {
            return 'Unknown';
        }

        $known = AccessRules::getListTypes();

        return isset($known[$typeId]) ? class_basename($known[$typeId]) : 'Type#'.$typeId;
    }

    /**
     * Owner types that the core treats specially, so a list can say so next to the name.
     *
     * @return array{tenant:list<int>, guest:?array{type:int, id:string}}
     */
    public function specialTypes(): array
    {
        $tenants = [];
        foreach ((array) $this->config->get('access.tenant_types', []) as $type) {
            try {
                $tenants[] = AccessRules::getTypeID((string) $type);
            } catch (Throwable) {
            }
        }

        $guest = $this->config->get('access.guest');
        try {
            $guest = is_array($guest) && isset($guest['type'], $guest['id'])
                ? ['type' => AccessRules::getTypeID((string) $guest['type']), 'id' => (string) $guest['id']]
                : null;
        } catch (Throwable) {
            $guest = null;
        }

        return ['tenant' => $tenants, 'guest' => $guest];
    }

    // =================================================================
    // Owners
    // =================================================================

    /**
     * An owner by the id of its row, and by nothing else.
     */
    public function findOwner(int|string $id): ?OwnerContract
    {
        $id = (int) $id;

        return $id > 0 ? $this->ownerQuery()->find($id) : null;
    }

    public function findOwnerOrFail(int|string $id): OwnerContract
    {
        return $this->findOwner($id) ?? abort(404, 'No owner with id "'.$id.'".');
    }

    public function ownerQuery(): Builder
    {
        return app(OwnerContract::class)->newQuery();
    }

    /**
     * Owners the screens list: the configured types, plus anything holding a permission or a
     * prohibition whatever its type. A permission granted straight to one account is invisible
     * on a screen that lists only roles, and an administrator who cannot see it cannot revoke it.
     */
    public function listedOwnerQuery(): Builder
    {
        $typeIds = $this->configuredTypeIds();
        $query   = $this->ownerQuery();

        // No entities configured is a usable setup: the panel then shows exactly the owners that
        // hold something, which is the whole truth about a system whose permissions went to accounts.
        if ($typeIds === []) {
            return $query->has('permission');
        }

        return $query->where(static function (Builder $inner) use ($typeIds): void {
            $inner->whereIn('type', $typeIds)->orHas('permission');
        });
    }

    /**
     * Owners that may be handed out as a source of rights: the configured "assignable" types only.
     * Holding a permission does not widen this set; what may be assigned is a decision.
     */
    public function assignableOwnerQuery(): Builder
    {
        $typeIds = array_values(array_column($this->entitiesWith('assignable'), 'type_id'));

        return $this->ownerQuery()->whereIn('type', $typeIds ?: [-1]);
    }

    /**
     * One owner as the interface wants it.
     *
     * @param OwnerContract&Model $owner
     */
    public function presentOwner(OwnerContract $owner, array $extra = []): array
    {
        $entity  = $this->entityForType((int) $owner->type);
        $special = $this->specialTypes();
        $name    = $owner->name;

        return $extra + [
            'id'          => (int) $owner->getKey(),
            'entity'      => $entity['key'] ?? null,
            'type_label'  => $this->typeLabel((int) $owner->type),
            'original_id' => $owner->original_id,
            'name'        => $name,
            'title'       => ($name !== null && $name !== '') ? $name : (string) $owner->original_id,
            // False means "not in config, listed because it holds something". The screens mark those
            // instead of hiding them: an unlisted owner with permissions is what an administrator looks for.
            'managed'    => $entity !== null,
            'tenant'     => in_array((int) $owner->type, $special['tenant'], true),
            'guest'      => $special['guest'] !== null && $special['guest']['type'] === (int) $owner->type && $special['guest']['id'] === (string) $owner->original_id,
            'created_at' => $owner->created_at,
        ];
    }

    /**
     * @return array{rows:list<array>, meta:array{current_page:int, last_page:int, per_page:int, total:int}}
     */
    public function searchOwners(Builder $query, string $search, int $page, int $perPage): array
    {
        if ($search !== '') {
            // PostgreSQL compares LIKE by case; MySQL and SQLite do not. Lowering both sides gives one answer everywhere.
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(static function (Builder $inner) use ($needle): void {
                $inner->whereRaw('lower(name) like ?', [$needle])->orWhereRaw('lower(original_id) like ?', [$needle]);
            });
        }

        $paginator = $query->orderBy('name')->orderBy('id')->paginate($perPage, ['*'], 'page', $page);

        return [
            // Counts a caller asked for with withCount() travel with the row.
            'rows' => array_map(fn (OwnerContract $owner): array => $this->presentOwner($owner, array_map('intval', array_filter($owner->getAttributes(), static fn (string $key): bool => str_ends_with($key, '_count'), ARRAY_FILTER_USE_KEY))), $paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ];
    }

    // =================================================================
    // Front-end handover
    // =================================================================

    /**
     * @return array{css:?string, js:?string}
     */
    public function assetUrls(): array
    {
        if (! $this->config('assets.inject', true)) {
            return ['css' => null, 'js' => null];
        }

        $base    = rtrim((string) $this->config('assets.base', '/vendor/accessui'), '/');
        $version = $this->config('assets.version');
        $suffix  = ($version === null || $version === '') ? '' : '?v='.rawurlencode((string) $version);

        return ['css' => $base.'/accessUi.css'.$suffix, 'js' => $base.'/accessUi.js'.$suffix];
    }

    /**
     * True the first time it is asked, false afterwards. The bundle tags may be reached from two
     * directions on one page, a layout that calls @accessUiAssets and a widget that includes them
     * itself; loading the bundle twice would mount two copies of everything.
     */
    public function markAssetsEmitted(): bool
    {
        if ($this->assetsEmitted) {
            return false;
        }

        return $this->assetsEmitted = true;
    }

    /**
     * Every endpoint as a URL template. The __OWNER__, __ID__ and __LINK__ placeholders are filled
     * client-side. Going through route() means the host can move or rename the group and nothing
     * outside this method hears about it.
     *
     * @return array<string, string>
     */
    public function routeUrls(): array
    {
        if (! $this->routesEnabled()) {
            return [];
        }

        $n = $this->routeName();

        return [
            'rules'          => route($n.'rules.index'),
            'ruleCreate'     => route($n.'rules.store'),
            'ruleUpdate'     => route($n.'rules.update', ['id' => '__ID__']),
            'ruleDelete'     => route($n.'rules.destroy', ['id' => '__ID__']),
            'ruleHolders'    => route($n.'rules.holders', ['id' => '__ID__']),
            'owners'         => route($n.'owners.index'),
            'ownerCreate'    => route($n.'owners.store'),
            'ownerUpdate'    => route($n.'owners.update', ['owner' => '__OWNER__']),
            'ownerDelete'    => route($n.'owners.destroy', ['owner' => '__OWNER__']),
            'ownerHeirs'     => route($n.'owners.heirs', ['owner' => '__OWNER__']),
            'permissions'    => route($n.'permissions.index', ['owner' => '__OWNER__']),
            'permissionSet'  => route($n.'permissions.store', ['owner' => '__OWNER__']),
            'permissionDrop' => route($n.'permissions.destroy', ['owner' => '__OWNER__']),
            'inherit'        => route($n.'inherit.index', ['owner' => '__OWNER__']),
            'inheritAdd'     => route($n.'inherit.store', ['owner' => '__OWNER__']),
            'inheritRemove'  => route($n.'inherit.destroy', ['owner' => '__OWNER__', 'link' => '__LINK__']),
            'pick'           => route($n.'pick'),
            'vocabulary'     => route($n.'conditions.vocabulary'),
            'conditionCheck' => route($n.'conditions.check'),
            'explain'        => route($n.'explain'),
            'health'         => route($n.'health.index'),
            'healthFix'      => route($n.'health.fix'),
            'cacheFlush'     => route($n.'cache.flush'),
            'xacmlExport'    => route($n.'xacml.export'),
            'xacmlCheck'     => route($n.'xacml.check'),
            'xacmlImport'    => route($n.'xacml.import'),
        ];
    }

    /**
     * Everything the interface needs to start: where to talk, what it may show, what it may change.
     * Shared by the panel page and by the assignment widget.
     */
    public function bootstrapPayload(): array
    {
        $entities = [];
        foreach ($this->entities() as $key => $entity) {
            $entities[] = [
                'key'        => $key,
                'label'      => __($entity['label']),
                'single'     => __($entity['single']),
                'create'     => $entity['create'],
                'assignable' => $entity['assignable'],
            ];
        }

        $screens = [];
        foreach (self::SCREENS as $screen) {
            $screens[$screen] = ['enabled' => $this->screenEnabled($screen), 'write' => $this->screenWritable($screen)];
        }

        $theme = $this->config('theme', 'auto');

        return [
            'locale'    => str_replace('_', '-', app()->getLocale()),
            'theme'     => in_array($theme, ['light', 'dark'], true) ? $theme : 'auto',
            'csrfToken' => csrf_token(),
            'screens'   => $screens,
            'entities'  => $entities,
            'picker'    => [
                'perPage'     => (int) $this->config('picker.per_page', 15),
                'inlineLimit' => (int) $this->config('picker.inline_limit', 100),
            ],
            // With the option on, a row on "reports" also covers "reports.sales" inside the core. The matrix
            // reads rows and cannot show that per rule, so the screen says it once.
            'ruleTree' => (bool) $this->config->get('access.rule_tree_inheritance', false),
            // Configured entities that could not be used, reported on screen: a role nobody can see
            // is indistinguishable from a role nobody created.
            'problems' => $this->problems(),
            'routes'   => $this->routeUrls(),
        ];
    }

    /**
     * Which owner a widget is about. Three ways to say it:
     *
     *     @accessUiWidget(['owner' => $user])        anything with getOwner(), the trait of the core
     *     @accessUiWidget(['owner' => $user->getOwner()->id])
     *     @accessUiWidget(['owner_id' => 17])
     *
     * The first is duck-typed on purpose: it costs this package no knowledge of the classes of the
     * application. getOwner() creates the row of the owner when it is absent, which is right here:
     * a user has none until something is granted, and mounting the card is the moment somebody is about to.
     */
    public function widgetOwnerId(array $options): ?int
    {
        $given = $options['owner'] ?? $options['owner_id'] ?? null;

        if (is_object($given)) {
            if ($given instanceof OwnerContract) {
                return (int) $given->getKey();
            }

            if (method_exists($given, 'getOwner')) {
                return (int) ($given->getOwner()?->getKey() ?? 0) ?: null;
            }

            return null;
        }

        return is_numeric($given) && (int) $given > 0 ? (int) $given : null;
    }

    /**
     * The widget needs the routes and the inheritance screen. When either is missing it renders
     * nothing at all, and not a card whose buttons answer 403.
     */
    public function widgetAvailable(): bool
    {
        return $this->routesEnabled() && $this->screenEnabled('inherit');
    }

    /**
     * May the current request change what this owner inherits from, through the card? The
     * inheritance screen has to be writable, then the card's own switch, then its ability, which
     * receives the owner: the answer may depend on who asks and about whom. The server checks
     * this on the routes the card uses, so a page that shows a read-only card is not the guard.
     */
    public function widgetWritable(OwnerContract $owner): bool
    {
        if (! $this->screenWritable('inherit') || ! $this->config('widget.write', true)) {
            return false;
        }

        $ability = $this->config('widget.ability');

        return $ability === null || $this->gate->allows($ability, [$owner]);
    }

    /**
     * What the card needs and nothing else: the four routes it calls, the entities it may hand
     * out, the locale, the theme, the token. The payload of the panel carries every route and
     * every screen, and a page of the application that shows an account is not the place to
     * print them. `write` is the answer of widgetWritable() for this owner; `readOnly` is the
     * choice of the page.
     *
     * @param array{title?:?string, compact?:bool, write?:bool} $options
     */
    public function widgetPayload(OwnerContract $owner, array $options = []): array
    {
        $n     = $this->routeName();
        $theme = $this->config('theme', 'auto');

        $entities = [];
        foreach ($this->entitiesWith('assignable') as $key => $entity) {
            $entities[] = ['key' => $key, 'label' => __($entity['label']), 'single' => __($entity['single']), 'assignable' => true];
        }

        return [
            'locale'    => str_replace('_', '-', app()->getLocale()),
            'theme'     => in_array($theme, ['light', 'dark'], true) ? $theme : 'auto',
            'csrfToken' => csrf_token(),
            'picker'    => ['perPage' => (int) $this->config('picker.per_page', 15)],
            'entities'  => $entities,
            'routes'    => [
                'inherit'       => route($n.'inherit.index', ['owner' => '__OWNER__']),
                'inheritAdd'    => route($n.'inherit.store', ['owner' => '__OWNER__']),
                'inheritRemove' => route($n.'inherit.destroy', ['owner' => '__OWNER__', 'link' => '__LINK__']),
                'pick'          => route($n.'pick'),
            ],
            'owner'    => (int) $owner->getKey(),
            'title'    => $options['title'] ?? null,
            'compact'  => (bool) ($options['compact'] ?? false),
            'readOnly' => array_key_exists('write', $options) && ! $options['write'],
            'write'    => $this->widgetWritable($owner),
        ];
    }
}
