<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Wnikk\LaravelAccessRules\Contracts\AccessRules as AccessRulesContract;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;
use Wnikk\LaravelAccessRules\Contracts\Rule as RuleContract;
use Wnikk\LaravelAccessUi\Support\RuleSpec;

/**
 * What one owner may and may not do, rule by rule.
 *
 * Two facts about permissions decide most of what this screen shows:
 *
 *   - a prohibition beats an allowance, including one that arrived through inheritance. That is what
 *     makes it possible to hand somebody a role and still hold them back from one part of it;
 *   - a rule may declare an option spec, and then each option value is granted separately. A rule
 *     with the spec `in:read,write` is not one permission but as many as there are values granted.
 */
class PermissionsController extends BaseController
{
    /** @var string */
    protected $screen = 'permissions';

    /**
     * Every rule, annotated with what this owner has been given.
     *
     * The interesting distinction is between `direct` and `effective`. `effective` is reported only
     * when there is no direct grant, so a filled `effective` always means "this comes from somewhere
     * else" — which is the question an administrator is actually asking when they open this screen.
     *
     * @param int|string $owner
     * @param RuleContract $rule
     * @param AccessRulesContract $accessRules
     * @return JsonResponse
     */
    public function index($owner, RuleContract $rule, AccessRulesContract $accessRules): JsonResponse
    {
        $record = $this->owner($owner);

        $accessRules->setOwner($record);
        $map = $accessRules->getThisPermitMap();

        $inheritedAllow = [];
        foreach ($map['allow'] as $item) {
            $inheritedAllow[(int) $item['rule_id']] = true;
        }

        $inheritedDeny = [];
        foreach ($map['disallow'] as $item) {
            $inheritedDeny[(int) $item['rule_id']] = true;
        }

        // Direct grants, grouped by rule; one entry per (rule, option) pair.
        $direct = [];
        foreach ($record->permission()->get(['rule_id', 'option', 'permission']) as $row) {
            $direct[(int) $row->rule_id][] = [
                'option'     => $row->option,
                'permission' => $row->permission ? 'allow' : 'deny',
            ];
        }

        $list = $rule->newQuery()
            ->withTrashed()
            ->orderBy('parent_id')
            ->orderBy('guard_name')
            ->get(['id', 'parent_id', 'guard_name', 'options', 'title', 'description', 'deleted_at'])
            ->map(static function ($row) use ($direct, $inheritedAllow, $inheritedDeny) {
                $id     = (int) $row->id;
                $grants = isset($direct[$id]) ? $direct[$id] : [];

                // The option-less grant, which is what the plain Allow/Forbid buttons act on.
                $directVal = null;
                foreach ($grants as $grant) {
                    if ($grant['option'] === null || $grant['option'] === '') {
                        $directVal = $grant['permission'];
                        break;
                    }
                }

                // Reported only when the owner holds nothing of its own on this rule — not merely
                // no option-less grant. The permit map counts option-specific grants too, and
                // calling one of those "inherited" would point the finger at the wrong place.
                $effectiveVal = null;
                if ($grants === []) {
                    if (isset($inheritedDeny[$id])) {
                        $effectiveVal = 'deny';
                    } elseif (isset($inheritedAllow[$id])) {
                        $effectiveVal = 'allow';
                    }
                }

                // Only option-bearing rules list their values; for the rest the single grant above
                // already says everything.
                $options = [];
                if ($row->options) {
                    foreach ($grants as $grant) {
                        $options[] = $grant;
                    }
                }

                return [
                    'id'                 => $id,
                    'parent_id'          => (int) $row->parent_id,
                    'guard_name'         => $row->guard_name,
                    'options'            => $row->options,
                    'title'              => $row->title ? __($row->title) : null,
                    'description'        => $row->description ? __($row->description) : null,
                    'deleted_at'         => $row->deleted_at,
                    'direct'             => $directVal,
                    'effective'          => $effectiveVal,
                    'option_permissions' => $options,
                ];
            })
            ->values();

        return $this->ok('', [
            'owner' => $this->ui->presentOwner($record),
            'list'  => $list,
            'write' => $this->ui->screenWritable('permissions'),
        ]);
    }

    /**
     * Grant, forbid, or clear one permission on one owner.
     *
     *   allow  — grant it outright, dropping a prohibition if one was there;
     *   deny   — forbid it outright, dropping a grant if one was there;
     *   remove — take away the direct entry, letting inheritance decide again.
     *
     * @param Request $request
     * @param int|string $owner
     * @param int|string $rule
     * @param RuleContract $rules
     * @return JsonResponse
     */
    public function update(Request $request, $owner, $rule, RuleContract $rules): JsonResponse
    {
        $data = $request->validate([
            'permission' => ['required', 'in:allow,deny,remove'],
            'option'     => ['nullable', 'string', 'max:255'],
        ]);

        $record = $this->owner($owner);
        $target = $rules->newQuery()->withTrashed()->findOrFail((int) $rule);

        $action = $data['permission'];
        $option = (($data['option'] ?? '') === '') ? null : $data['option'];

        // The rule's own spec is the authority on what values it accepts. Checked before writing so
        // a bad value is a message on the screen rather than an exception out of the model cast.
        if ($action !== 'remove') {
            $problem = RuleSpec::check($target->options, $option);

            if ($problem !== null) {
                return $this->err($problem, ['option' => [$problem]]);
            }
        }

        $current = $record->permission()
            ->where('rule_id', $target->getKey())
            ->where('option', $option)
            ->first();

        try {
            if ($action === 'allow') {
                $this->grant($record, $target, $option, $current);
            } elseif ($action === 'deny') {
                $this->forbid($record, $target, $option, $current);
            } else {
                $this->revoke($record, $target, $option, $current);
            }
        } catch (LogicException $e) {
            return $this->err($e->getMessage(), [], 409);
        }

        $this->ui->flushCache();

        return $this->ok(__('Permission updated'));
    }

    // -----------------------------------------------------------------
    // Writers
    //
    // access-rules stores the allow/deny flag without casting it, so it arrives as 0/1 and is
    // compared loosely on purpose. It also refuses to add a permission that already exists, which is
    // why each of these clears the opposite entry before adding its own.
    // -----------------------------------------------------------------

    /**
     * @param OwnerContract $owner
     * @param RuleContract $rule
     * @param string|null $option
     * @param mixed $current
     * @return void
     */
    protected function grant($owner, $rule, $option, $current)
    {
        if ($current && !$current->permission) {
            $owner->remProhibition($rule, $option);
            $current = null;
        }

        if ($current === null) {
            $owner->addPermission($rule, $option, true);
        }
    }

    /**
     * @param OwnerContract $owner
     * @param RuleContract $rule
     * @param string|null $option
     * @param mixed $current
     * @return void
     */
    protected function forbid($owner, $rule, $option, $current)
    {
        if ($current && $current->permission) {
            $owner->remPermission($rule, $option, true);
            $current = null;
        }

        if ($current === null) {
            $owner->addProhibition($rule, $option);
        }
    }

    /**
     * @param OwnerContract $owner
     * @param RuleContract $rule
     * @param string|null $option
     * @param mixed $current
     * @return void
     */
    protected function revoke($owner, $rule, $option, $current)
    {
        if (!$current) {
            return;
        }

        if ($current->permission) {
            $owner->remPermission($rule, $option, true);
        } else {
            $owner->remProhibition($rule, $option);
        }
    }
}
