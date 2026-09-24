<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule as ValidationRule;
use Wnikk\LaravelAccessRules\Administration\RuleCatalog;
use Wnikk\LaravelAccessRules\Conditions\Cond;
use Wnikk\LaravelAccessRules\Contracts\AccessManager;
use Wnikk\LaravelAccessRules\Contracts\Permission as PermissionContract;
use Wnikk\LaravelAccessRules\Contracts\Rule as RuleContract;
use Wnikk\LaravelAccessRules\Exceptions\AccessRulesException;
use Wnikk\LaravelAccessRules\Models\RuleOrigin;
use Wnikk\LaravelAccessUi\Support\RuleTree;

/**
 * Rules: the names the application checks against.
 *
 * A rule has an origin. One that comes with code is created and removed by a migration, and the
 * panel may change its title, description, place in the tree and options, nothing else; the
 * core refuses the rest with RULE_MANAGED_BY_CODE. A rule the panel creates is "custom", for abilities whose names code
 * builds at run time. Deleting is for good and is refused while somebody holds the rule.
 */
class RulesController extends BaseController
{
    protected string $screen = 'rules';

    protected array $reading = ['index', 'holders'];

    /**
     * Every rule as a flat list. The tree is a presentation concern: building it here would mean
     * choosing an order and a depth on behalf of every screen at once.
     */
    public function index(RuleContract $rule): JsonResponse
    {
        $list = $rule->newQuery()
            ->withCount('permission as holders_count')
            ->orderBy('parent_id')
            ->orderBy('guard_name')
            ->get()
            ->map(fn ($row): array => $this->present($row))
            ->values();

        return $this->ok('', [
            'list'      => $list,
            'resources' => array_keys((array) config('access.resources', [])),
            'write'     => $this->ui->screenWritable('rules'),
        ]);
    }

    public function store(Request $request, AccessManager $access): JsonResponse
    {
        $data = $this->validated($request, null);

        $id = $access->newRule(
            $data['guard_name'],
            $data['title'],
            $data['description'],
            $data['parent_id'] ?: null,
            $data['options'],
            $data['resource'],
            $data['when'],
            RuleOrigin::Custom,
        );

        if (! $id) {
            return $this->err(__('The rule could not be created.'), [], 500);
        }

        return $this->ok(__('Rule created'), ['id' => $id]);
    }

    /**
     * Only the fields that changed reach the core. A rule of code accepts title, description and
     * options; sending its unchanged name along would make the core refuse the whole edit.
     */
    public function update(Request $request, RuleContract $rule, RuleCatalog $catalog, int $id): JsonResponse
    {
        $target = $rule->newQuery()->findOrFail($id);
        $data   = $this->validated($request, $id);

        if ($data['parent_id'] > 0 && RuleTree::wouldCycle($id, $data['parent_id'])) {
            return $this->err(__('A rule cannot be placed inside its own branch.'), ['parent_id' => [__('A rule cannot be placed inside its own branch.')]]);
        }

        $current = [
            'guard_name'  => $target->guard_name,
            'title'       => $target->title,
            'description' => $target->description,
            'options'     => $target->options,
            'resource'    => $target->resource,
            'parent_id'   => (int) $target->parent_id,
            'when'        => Cond::describe($target->condition, $target->resource),
        ];

        $changed = array_filter($data, static fn (mixed $value, string $field): bool => $value !== $current[$field], ARRAY_FILTER_USE_BOTH);

        // The catalogue of the core decides what a rule of code may change: title, description,
        // options and, while the tree does not inherit, the place in the tree. Its refusal names the
        // field and comes back by code.
        if ($changed !== []) {
            $catalog->edit($target->guard_name, $changed);
        }

        return $this->ok(__('Rule updated'));
    }

    /**
     * Deleted for good, through the catalogue, so a rule of code and a rule somebody holds are
     * refused. The second refusal carries who holds it: the screen shows them instead of a dead end.
     */
    public function destroy(RuleContract $rule, RuleCatalog $catalog, int $id): JsonResponse
    {
        $target = $rule->newQuery()->findOrFail($id);

        try {
            $catalog->discard($target->guard_name);
        } catch (AccessRulesException $e) {
            return $this->refused($e, $e->getCode() === AccessRulesException::RULE_IN_USE ? $this->holdersOf($target, 1, 10) : null);
        }

        return $this->ok(__('Rule deleted'));
    }

    /**
     * Who holds a rule, with the condition of each grant. Paged: a rule of a menu is held by everyone.
     */
    public function holders(Request $request, RuleContract $rule, int $id): JsonResponse
    {
        $params = $this->pageParams($request);

        return $this->ok('', $this->holdersOf($rule->newQuery()->findOrFail($id), $params['page'], $params['per_page']));
    }

    private function holdersOf(RuleContract $rule, int $page, int $perPage): array
    {
        $paginator = app(PermissionContract::class)->newQuery()
            ->with('owner')
            ->where('rule_id', $rule->getKey())
            ->orderBy('owner_id')
            ->paginate($perPage, ['*'], 'page', $page);

        $rows = [];
        foreach ($paginator->items() as $permission) {
            if ($permission->owner === null) {
                continue;
            }

            $rows[] = $this->ui->presentOwner($permission->owner, [
                'effect' => $permission->permission ? 'allow' : 'deny',
                'option' => $permission->option,
                'when'   => Cond::describe($permission->condition, $rule->resource),
            ]);
        }

        return [
            'rule' => $rule->guard_name,
            'rows' => $rows,
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        ];
    }

    private function present(RuleContract $row): array
    {
        return [
            'id'            => (int) $row->getKey(),
            'parent_id'     => (int) $row->parent_id,
            'guard_name'    => $row->guard_name,
            'options'       => $row->options,
            'resource'      => $row->resource,
            'when'          => Cond::describe($row->condition, $row->resource),
            'origin'        => $row->origin->value,
            'managed'       => $row->origin->isManagedByCode(),
            'title'         => $row->title ? __($row->title) : null,
            'description'   => $row->description ? __($row->description) : null,
            'holders_count' => (int) $row->holders_count,
            'created_at'    => $row->created_at,
        ];
    }

    /**
     * The option spec is a Laravel validation string that the core applies to every option value
     * granted on the rule. One that cannot compile is refused here, where it was typed, and not
     * later on somebody else's screen. Whether the condition compiles the core decides when it saves.
     *
     * @return array{guard_name:string, parent_id:int, options:?string, resource:?string, when:?string, title:?string, description:?string}
     */
    private function validated(Request $request, ?int $ignoreId): array
    {
        $table  = app(RuleContract::class)->getTable();
        $unique = ValidationRule::unique($table, 'guard_name');
        if ($ignoreId !== null) {
            $unique->ignore($ignoreId);
        }

        $data = $request->validate([
            'guard_name' => ['required', 'string', 'max:128', $unique],
            'parent_id'  => ['nullable', 'integer', 'min:0', ValidationRule::when(RuleTree::normaliseParent($request->input('parent_id')) > 0, ['exists:'.$table.',id'])],
            'options'    => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                try {
                    validator(['option' => '1'], ['option' => $value])->passes();
                } catch (\Throwable) {
                    $fail(__('The option spec must be a valid Laravel validation string.'));
                }
            }],
            'resource'    => ['nullable', 'string', 'max:64', ValidationRule::in(array_keys((array) config('access.resources', [])))],
            'when'        => ['nullable', 'string', 'max:16384'],
            'title'       => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $blank = static fn (?string $value): ?string => ($value ?? '') === '' ? null : $value;

        return [
            'guard_name'  => $data['guard_name'],
            'parent_id'   => RuleTree::normaliseParent($data['parent_id'] ?? null),
            'options'     => $blank($data['options'] ?? null),
            'resource'    => $blank($data['resource'] ?? null),
            'when'        => $blank($data['when'] ?? null),
            'title'       => $blank($data['title'] ?? null),
            'description' => $blank($data['description'] ?? null),
        ];
    }
}
