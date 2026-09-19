<?php

namespace Wnikk\LaravelAccessUi;

use Illuminate\Support\Facades\Gate;
use LogicException;
use Throwable;
use Wnikk\LaravelAccessRules\Contracts\AccessRules as AccessRulesContract;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;

/**
 * Configuration, turned into the questions the screens actually ask.
 *
 * The one thing worth knowing about this class is what it does *not* do: it never loads, queries or
 * names a model belonging to the host application. access-rules keeps every owner — users, roles,
 * groups — as a row in one table, and that table is the whole of this panel's world. An entity in
 * config is a label and two decisions about it, nothing more.
 *
 * Which is why owners are addressed by their owner id and by nothing else. A host page that wants the
 * assignment widget for a user already has a way to get that id: `$user->getOwner()->id`, from the
 * trait access-rules asks you to put on the model anyway. Asking for it there rather than resolving it
 * here keeps the panel independent of how the application stores anything, and keeps the integration
 * to one expression.
 */
class AccessUi
{
    /** Normalised entity definitions, keyed by entity key. Built once per request. */
    protected $entities;

    /** Entity keys that are configured but unusable, with the reason. @var array<string, string> */
    protected $problems = [];

    /** Whether the bundle tags have already gone out on this page. @var bool */
    protected $assetsEmitted = false;

    // =================================================================
    // Configuration
    // =================================================================

    /**
     * Read a value out of config/accessUi.php.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function config(string $key, $default = null)
    {
        return config('accessUi.'.$key, $default);
    }

    /**
     * Whether the route group should be registered at all.
     *
     * Both a prefix and a middleware stack are required. The middleware is the part that matters:
     * these endpoints hand out permissions, so registering them without a guard would be worse than
     * not registering them. Requiring the prefix too means an install that was never configured
     * exposes nothing, rather than exposing something at a path nobody chose.
     *
     * @return bool
     */
    public function routesEnabled(): bool
    {
        return $this->routePrefix() !== null && $this->routeMiddleware() !== [];
    }

    /**
     * @return string|null
     */
    public function routePrefix()
    {
        $prefix = $this->config('routes.prefix');

        if (!is_string($prefix)) {
            return null;
        }

        $prefix = trim($prefix, "/ \t\n\r\0\x0B");

        return $prefix === '' ? null : $prefix;
    }

    /**
     * @return array<int, string>
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
     *
     * @return string
     */
    public function routeName(): string
    {
        $name = (string) $this->config('routes.as', 'accessUi.');
        $name = $name === '' ? 'accessUi.' : $name;

        return substr($name, -1) === '.' ? $name : $name.'.';
    }

    // =================================================================
    // Screens
    // =================================================================

    /**
     * @param string $screen
     * @return bool
     */
    public function screenEnabled(string $screen): bool
    {
        return (bool) $this->config('screens.'.$screen.'.enabled', false);
    }

    /**
     * May the current request change anything on this screen?
     *
     * Two gates, both of which have to open: the `write` switch in config, and the Gate ability named
     * next to it when there is one. A screen may therefore be readable and not writable, which is the
     * useful setting for rules.
     *
     * @param string $screen
     * @return bool
     */
    public function screenWritable(string $screen): bool
    {
        if (!$this->screenEnabled($screen) || !$this->config('screens.'.$screen.'.write', false)) {
            return false;
        }

        $ability = $this->config('screens.'.$screen.'.ability');

        return $ability === null || Gate::allows($ability);
    }

    /**
     * @param string $screen
     * @return void
     */
    public function authorizeRead(string $screen)
    {
        abort_unless($this->screenEnabled($screen), 403, 'This screen is disabled in config/accessUi.php.');
    }

    /**
     * @param string $screen
     * @return void
     */
    public function authorizeWrite(string $screen)
    {
        $this->authorizeRead($screen);

        abort_unless($this->screenWritable($screen), 403, 'Writing is not allowed on this screen.');
    }

    // =================================================================
    // Entities
    // =================================================================

    /**
     * The class name the application calls a user.
     *
     * Read as a string and used as a string: access-rules derives an owner type from the label, and a
     * label is all this is. The class is never loaded.
     *
     * @return string|null
     */
    public function userType()
    {
        $model = config('auth.providers.users.model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    /**
     * Every usable entity, keyed by entity key, in configuration order.
     *
     * An entity whose type is not listed in config/access.php is dropped rather than thrown, and the
     * reason is kept in {@see problems()} for the panel to report. A single mistyped label should cost
     * that one entity, not the whole screen.
     *
     * @return array<string, array>
     */
    public function entities(): array
    {
        if ($this->entities !== null) {
            return $this->entities;
        }

        $this->entities = [];
        $configured     = $this->config('entities', []);

        foreach ((is_array($configured) ? $configured : []) as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) {
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
     * Entity keys that were configured but could not be used, with the reason why.
     *
     * @return array<string, string>
     */
    public function problems(): array
    {
        $this->entities();

        return $this->problems;
    }

    /**
     * @param string $key
     * @param array $definition
     * @return array
     */
    protected function normalise(string $key, array $definition): array
    {
        $type = $definition['type'] ?? null;

        if ($type === null) {
            $type = $this->userType();

            if ($type === null) {
                throw new LogicException(
                    'Entity "'.$key.'" has no type and auth.providers.users.model is not set.'
                );
            }
        }

        // Throws when the label is absent from config/access.php → owner_types.
        $typeId = app(OwnerContract::class)->getTypeID($type);

        return [
            'key'        => $key,
            'type'       => (string) $type,
            'type_id'    => (int) $typeId,
            'label'      => (string) ($definition['label'] ?? $key),
            'single'     => (string) ($definition['single'] ?? ($definition['label'] ?? $key)),
            'create'     => (bool) ($definition['create'] ?? false),
            'assignable' => (bool) ($definition['assignable'] ?? false),
        ];
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasEntity(string $key): bool
    {
        return isset($this->entities()[$key]);
    }

    /**
     * One entity, or a 404 when the key is not one the panel knows.
     *
     * @param string $key
     * @return array
     */
    public function entity(string $key): array
    {
        $entities = $this->entities();

        abort_unless(isset($entities[$key]), 404, 'Unknown entity "'.$key.'".');

        return $entities[$key];
    }

    /**
     * Entities carrying a given flag: 'create' or 'assignable'.
     *
     * @param string $flag
     * @return array<string, array>
     */
    public function entitiesWith(string $flag): array
    {
        return array_filter($this->entities(), static function ($entity) use ($flag) {
            return !empty($entity[$flag]);
        });
    }

    /**
     * Numeric types of every configured entity.
     *
     * @return array<int, int>
     */
    public function configuredTypeIds(): array
    {
        return array_values(array_column($this->entities(), 'type_id'));
    }

    /**
     * The entity behind a numeric owner type, if there is one in config.
     *
     * @param int|null $typeId
     * @return array|null
     */
    public function entityForType($typeId)
    {
        if ($typeId === null) {
            return null;
        }

        foreach ($this->entities() as $entity) {
            if ($entity['type_id'] === (int) $typeId) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * A readable name for any owner type, configured or not.
     *
     * Owners of unconfigured types still show up whenever they hold a permission, so they need a label
     * too. `Type#12345` is the honest answer when access-rules has no label for it either — it means
     * the type was removed from config/access.php while rows still point at it.
     *
     * @param int|null $typeId
     * @return string
     */
    public function typeLabel($typeId): string
    {
        $entity = $this->entityForType($typeId);

        if ($entity) {
            return $entity['single'];
        }

        if ($typeId === null) {
            return 'Unknown';
        }

        $known = app(OwnerContract::class)->getListTypes();

        return isset($known[(int) $typeId]) ? class_basename($known[(int) $typeId]) : 'Type#'.$typeId;
    }

    // =================================================================
    // Owners
    // =================================================================

    /**
     * An owner by its own id, and by nothing else.
     *
     * @param int|string $id
     * @return OwnerContract|\Illuminate\Database\Eloquent\Model|null
     */
    public function findOwner($id)
    {
        $id = (int) $id;

        return $id > 0 ? app(OwnerContract::class)->newQuery()->find($id) : null;
    }

    /**
     * @param int|string $id
     * @return OwnerContract|\Illuminate\Database\Eloquent\Model
     */
    public function findOwnerOrFail($id)
    {
        $owner = $this->findOwner($id);

        abort_if($owner === null, 404, 'No owner with id "'.$id.'".');

        return $owner;
    }

    /**
     * One owner as the interface wants it.
     *
     * @param OwnerContract|\Illuminate\Database\Eloquent\Model $owner
     * @param array $extra
     * @return array
     */
    public function presentOwner($owner, array $extra = []): array
    {
        $entity = $this->entityForType($owner->type);
        $name   = $owner->name;

        return $extra + [
            'id'          => (int) $owner->getKey(),
            'entity'      => $entity ? $entity['key'] : null,
            'type_label'  => $this->typeLabel($owner->type),
            'original_id' => $owner->original_id,
            'name'        => $name,
            'title'       => ($name !== null && $name !== '') ? $name : (string) $owner->original_id,

            // False means "not in config, listed because it holds something". The screens mark those
            // rather than hiding them: an unlisted owner with permissions is exactly what an
            // administrator needs to see.
            'managed'     => $entity !== null,
            'created_at'  => $owner->created_at,
        ];
    }

    // =================================================================
    // Owner queries
    // =================================================================

    /**
     * Owners the screens list: the configured types, plus anything holding a permission or a
     * prohibition whatever its type.
     *
     * The second half is not a nicety. A permission granted straight to one account is invisible on a
     * screen that lists only roles, and an administrator who cannot see it cannot revoke it.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function listedOwnerQuery()
    {
        $typeIds = $this->configuredTypeIds();
        $query   = app(OwnerContract::class)->newQuery();

        // No entities configured at all is a usable setup, not a broken one: the panel then shows
        // exactly the owners that hold something, which is the whole truth about a system whose
        // permissions all went straight to accounts.
        if ($typeIds === []) {
            return $query->has('permission');
        }

        return $query->where(static function ($inner) use ($typeIds) {
            $inner->whereIn('type', $typeIds)->orHas('permission');
        });
    }

    /**
     * Owners that may be handed out as a source of rights — the widget's dropdown.
     *
     * Configured `assignable` types only. Unlike the listed set this does not widen to whoever happens
     * to hold a permission: what may be assigned is a decision, and holding a permission is not one.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function assignableOwnerQuery()
    {
        $typeIds = array_values(array_column($this->entitiesWith('assignable'), 'type_id'));

        return app(OwnerContract::class)->newQuery()->whereIn('type', $typeIds ?: [-1]);
    }

    /**
     * Every owner row.
     *
     * Used when choosing who should inherit from something. Who may *receive* rights is not a decision
     * the entity list makes — if a row exists, something in the application put it there, and it is a
     * legitimate target.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function allOwnerQuery()
    {
        return app(OwnerContract::class)->newQuery();
    }

    /**
     * Search one of the owner sets, paged.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @param int $page
     * @param int $perPage
     * @return array{rows: array, meta: array}
     */
    public function searchOwners($query, string $search, int $page, int $perPage): array
    {
        if ($search !== '') {
            $query->where(static function ($inner) use ($search) {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('original_id', 'like', '%'.$search.'%');
            });
        }

        $paginator = $query->orderBy('name')->paginate($perPage, ['*'], 'page', $page);

        $rows = [];
        foreach ($paginator->items() as $owner) {
            $rows[] = $this->presentOwner($owner);
        }

        return ['rows' => $rows, 'meta' => $this->paginationMeta($paginator)];
    }

    /**
     * Paginator reduced to what a table needs: the rows and where they sit.
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
     * @return array
     */
    public function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ];
    }

    // =================================================================
    // Permission counts
    // =================================================================

    /**
     * How much this owner ends up with, without listing any of it.
     *
     * The widget on a user's page shows these numbers and no rule names. Three separate facts, and the
     * gap between them is the useful part:
     *
     *   effective — what the owner can do once inheritance is resolved. The headline number.
     *   direct    — granted to this owner itself. Non-zero here with nothing assigned is the signature
     *               of an account someone hand-tuned, and the reason the widget shows a count at all:
     *               "no roles" and "no rights" are not the same statement.
     *   forbidden — prohibitions in force, which beat allowances including inherited ones.
     *
     * @param OwnerContract|\Illuminate\Database\Eloquent\Model $owner
     * @return array{effective: int, direct: int, forbidden: int}
     */
    public function permissionSummary($owner): array
    {
        $accessRules = app(AccessRulesContract::class);
        $accessRules->setOwner($owner);

        $map = $accessRules->getThisPermitMap();

        return [
            'effective' => count($map['allow']),
            'direct'    => (int) $owner->permission()->count(),
            'forbidden' => count($map['disallow']),
        ];
    }

    // =================================================================
    // Front-end handover
    // =================================================================

    /**
     * URLs of the published bundle, or nulls when the host wires the assets up itself.
     *
     * @return array{css: string|null, js: string|null}
     */
    public function assetUrls(): array
    {
        if (!$this->config('assets.inject', true)) {
            return ['css' => null, 'js' => null];
        }

        $base    = rtrim((string) $this->config('assets.base', '/vendor/accessui'), '/');
        $version = $this->config('assets.version');
        $suffix  = ($version === null || $version === '') ? '' : '?v='.rawurlencode((string) $version);

        return [
            'css' => $base.'/accessUi.css'.$suffix,
            'js'  => $base.'/accessUi.js'.$suffix,
        ];
    }

    /**
     * True the first time it is asked, false afterwards.
     *
     * The bundle tags may be reached from two directions on the same page — a layout that calls
     * `@accessUiAssets`, and a widget that includes them itself so it works on a page that does not.
     * Loading the bundle twice would mount two copies of everything, so whichever gets there first
     * wins and the other stays quiet.
     *
     * @return bool
     */
    public function markAssetsEmitted(): bool
    {
        if ($this->assetsEmitted) {
            return false;
        }

        return $this->assetsEmitted = true;
    }

    /**
     * Every endpoint as a URL template.
     *
     * The `__OWNER__`, `__ID__`, `__RULE__` and `__LINK__` placeholders are filled in client-side.
     * Going through `route()` rather than joining a prefix means the host can move or rename the group
     * and nothing outside this method has to hear about it.
     *
     * @return array<string, string>
     */
    public function routeUrls(): array
    {
        if (!$this->routesEnabled()) {
            return [];
        }

        $name = $this->routeName();

        return [
            'rules'         => route($name.'rules.index'),
            'ruleCreate'    => route($name.'rules.store'),
            'ruleUpdate'    => route($name.'rules.update', ['id' => '__ID__']),
            'ruleDelete'    => route($name.'rules.destroy', ['id' => '__ID__']),
            'ruleRestore'   => route($name.'rules.restore', ['id' => '__ID__']),
            'owners'        => route($name.'owners.index'),
            'ownerCreate'   => route($name.'owners.store'),
            'ownerUpdate'   => route($name.'owners.update', ['owner' => '__OWNER__']),
            'ownerDelete'   => route($name.'owners.destroy', ['owner' => '__OWNER__']),
            'permissions'   => route($name.'permissions.index', ['owner' => '__OWNER__']),
            'permissionSet' => route($name.'permissions.update', [
                'owner' => '__OWNER__',
                'rule'  => '__RULE__',
            ]),
            'inherit'       => route($name.'inherit.index', ['owner' => '__OWNER__']),
            'inheritAdd'    => route($name.'inherit.store', ['owner' => '__OWNER__']),
            'inheritRemove' => route($name.'inherit.destroy', [
                'owner' => '__OWNER__',
                'link'  => '__LINK__',
            ]),
            'pick'          => route($name.'pick'),
        ];
    }

    /**
     * Everything the interface needs to start: where to talk, what it may show, what it may change.
     *
     * Shared by the panel page and by the assignment widget.
     *
     * @return array
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
        foreach (['rules', 'owners', 'permissions', 'inherit'] as $screen) {
            $screens[$screen] = [
                'enabled' => $this->screenEnabled($screen),
                'write'   => $this->screenWritable($screen),
            ];
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
            // Configured entities that could not be used. Reported on screen rather than swallowed:
            // a role nobody can see is indistinguishable from a role nobody created.
            'problems'  => $this->problems(),
            'routes'    => $this->routeUrls(),
        ];
    }

    /**
     * Which owner a widget is about.
     *
     * Three ways to say it, and the first is the one to use:
     *
     *     @accessUiWidget(['owner' => $user->getOwner()->id])
     *     @accessUiWidget(['owner' => $user])        // anything with getOwner(), i.e. the access-rules trait
     *     @accessUiWidget(['owner_id' => 17])
     *
     * The second is duck-typed on purpose. `getOwner()` comes from the trait access-rules asks you to
     * put on the model, so accepting any object that has it costs this package no knowledge of your
     * classes — and it creates the owner row on demand, which is right: a user has none until
     * something is granted to them, and mounting the widget is the moment somebody is about to.
     *
     * @param array $options
     * @return int|null
     */
    public function widgetOwnerId(array $options)
    {
        $given = $options['owner'] ?? ($options['owner_id'] ?? null);

        if (is_object($given)) {
            if ($given instanceof OwnerContract) {
                return (int) $given->getKey();
            }

            if (method_exists($given, 'getOwner')) {
                $owner = $given->getOwner();

                return $owner ? (int) $owner->getKey() : null;
            }

            return null;
        }

        if (is_numeric($given) && (int) $given > 0) {
            return (int) $given;
        }

        return null;
    }

    /**
     * Can the assignment widget be put on a page right now?
     *
     * It needs the routes to exist and the inheritance screen to be on. When either is missing the
     * widget renders nothing at all, rather than a card whose buttons answer 403.
     *
     * @return bool
     */
    public function widgetAvailable(): bool
    {
        return $this->routesEnabled() && $this->screenEnabled('inherit');
    }

    // =================================================================
    // Misc
    // =================================================================

    /**
     * Drop every cached permission.
     *
     * Called after each write. Not surgical — the package caches per owner, and a change to a role
     * reaches everyone who inherits from it, so working out the exact set costs more than
     * re-resolving does.
     *
     * @return void
     */
    public function flushCache()
    {
        app(AccessRulesContract::class)->clearAllCachedPermissions();
    }
}
