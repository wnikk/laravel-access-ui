<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule as ValidationRule;
use Wnikk\LaravelAccessRules\Contracts\AccessRules as AccessRulesContract;
use Wnikk\LaravelAccessRules\Contracts\Rule as RuleContract;
use Wnikk\LaravelAccessUi\Support\RuleSpec;
use Wnikk\LaravelAccessUi\Support\RuleTree;

/**
 * Rules: the vocabulary the rest of the application checks against.
 *
 * A rule is a guard name such as `content.posts.publish`. Rules form a tree through `parent_id`, but
 * the tree is presentation only — access-rules does not grant a parent because a child was granted.
 * Container rows exist to organise a long list.
 *
 * Deleting is two-stage on purpose. A soft delete deactivates the rule: the row and its permissions
 * stay, and every check against that guard name starts answering no. That is reversible, and it is
 * what you want when you are not certain the name is gone from the code. A forced delete is the
 * irreversible one — access-rules re-parents the children and drops the permissions with it.
 */
class RulesController extends BaseController
{
    /** @var string */
    protected $screen = 'rules';

    /**
     * Every rule as a flat list, deactivated ones included.
     *
     * Flat because the tree is a presentation concern: building it here would mean choosing an order
     * and a depth limit on behalf of a screen that may want neither. Deactivated rules are included
     * and marked, because a rule that still holds permissions should not vanish from the only screen
     * that could tell you so.
     *
     * @param RuleContract $rule
     * @return JsonResponse
     */
    public function index(RuleContract $rule): JsonResponse
    {
        $list = $rule->newQuery()
            ->withTrashed()
            ->orderBy('parent_id')
            ->orderBy('guard_name')
            ->get(['id', 'parent_id', 'guard_name', 'options', 'title', 'description', 'created_at', 'deleted_at'])
            ->map(static function ($row) {
                return [
                    'id'          => (int) $row->id,
                    'parent_id'   => (int) $row->parent_id,
                    'guard_name'  => $row->guard_name,
                    'options'     => $row->options,
                    'title'       => $row->title ? __($row->title) : null,
                    'description' => $row->description ? __($row->description) : null,
                    'created_at'  => $row->created_at,
                    'deleted_at'  => $row->deleted_at,
                ];
            })
            ->values();

        return $this->ok('', [
            'list'  => $list,
            'write' => $this->ui->screenWritable('rules'),
        ]);
    }

    /**
     * @param Request $request
     * @param RuleContract $rule
     * @return JsonResponse
     */
    public function store(Request $request, RuleContract $rule): JsonResponse
    {
        $data = $this->validated($request, $rule, null);

        $created = app(AccessRulesContract::class)::newRule(
            $data['guard_name'],
            $data['title'],
            $data['description'],
            $data['parent_id'] ?: null,
            $data['options']
        );

        if (!$created) {
            return $this->err(__('The rule could not be created.'), [], 500);
        }

        $this->ui->flushCache();

        return $this->ok(__('Rule created'));
    }

    /**
     * @param Request $request
     * @param RuleContract $rule
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, RuleContract $rule, $id): JsonResponse
    {
        $target = $rule->newQuery()->withTrashed()->findOrFail((int) $id);
        $data   = $this->validated($request, $rule, (int) $target->id);

        // A rule cannot be its own parent, nor sit under one of its own descendants.
        if ($data['parent_id'] > 0 && RuleTree::wouldCycle((int) $target->id, $data['parent_id'])) {
            return $this->err(__('A rule cannot be placed inside its own branch.'), [
                'parent_id' => [__('A rule cannot be placed inside its own branch.')],
            ]);
        }

        $target->update($data);
        $this->ui->flushCache();

        return $this->ok(__('Rule updated'));
    }

    /**
     * Deactivate a rule, or erase it when `force` is asked for.
     *
     * @param Request $request
     * @param RuleContract $rule
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy(Request $request, RuleContract $rule, $id): JsonResponse
    {
        $target = $rule->newQuery()->withTrashed()->findOrFail((int) $id);
        $force  = $request->boolean('force');

        if ($force) {
            $target->forceDelete();
        } else {
            $target->delete();
        }

        $this->ui->flushCache();

        return $this->ok($force ? __('Rule deleted') : __('Rule deactivated'));
    }

    /**
     * Bring a deactivated rule back, with the permissions it still holds.
     *
     * @param RuleContract $rule
     * @param int|string $id
     * @return JsonResponse
     */
    public function restore(RuleContract $rule, $id): JsonResponse
    {
        $target = $rule->newQuery()->withTrashed()->findOrFail((int) $id);
        $target->restore();

        $this->ui->flushCache();

        return $this->ok(__('Rule restored'));
    }

    /**
     * Shared validation, and the two normalisations the column types demand.
     *
     * `parent_id` is a non-nullable integer defaulting to 0, so "no parent" is 0 and not null.
     * `options` is a validation-rule string that access-rules applies to every option value granted
     * on this rule, so a spec that cannot compile is refused here rather than crashing later.
     *
     * @param Request $request
     * @param RuleContract $rule
     * @param int|null $ignoreId
     * @return array
     */
    protected function validated(Request $request, RuleContract $rule, $ignoreId): array
    {
        $table  = $rule->getTable();
        $unique = ValidationRule::unique($table, 'guard_name');

        if ($ignoreId !== null) {
            $unique->ignore($ignoreId);
        }

        // Not `exists:` — the parent may itself be deactivated, and moving a rule under a
        // deactivated container is a legitimate thing to do while tidying up.
        $parentExists = static function ($attribute, $value, $fail) use ($rule) {
            if (RuleTree::normaliseParent($value) > 0
                && !$rule->newQuery()->withTrashed()->whereKey((int) $value)->exists()
            ) {
                $fail(__('The selected parent rule does not exist.'));
            }
        };

        $data = $request->validate([
            'guard_name'  => ['required', 'string', 'max:128', $unique],
            'parent_id'   => ['nullable', 'integer', 'min:0', $parentExists],
            'options'     => ['nullable', 'string', 'max:255', RuleSpec::validator()],
            'title'       => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'guard_name'  => $data['guard_name'],
            'parent_id'   => RuleTree::normaliseParent($data['parent_id'] ?? null),
            'options'     => ($data['options'] ?? '') === '' ? null : $data['options'],
            'title'       => ($data['title'] ?? '') === '' ? null : $data['title'],
            'description' => ($data['description'] ?? '') === '' ? null : $data['description'],
        ];
    }
}
