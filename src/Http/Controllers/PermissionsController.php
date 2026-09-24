<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Wnikk\LaravelAccessRules\Contracts\AccessManager;
use Wnikk\LaravelAccessRules\Contracts\Rule as RuleContract;
use Wnikk\LaravelAccessUi\Support\OwnerReader;

/**
 * What one owner holds, rule by rule.
 *
 * A permit and a prohibition are two rows and can exist together, each with a condition of its
 * own: "may see orders of its team, may not see locked ones" is one rule and two rows. So the
 * screen writes one row at a time and never turns one into the other. The label of a rule comes
 * from the rows by the five steps of the core; whether a condition holds for a record is a
 * question for the explain screen.
 */
class PermissionsController extends BaseController
{
    protected string $screen = 'permissions';

    public function index(RuleContract $rule, OwnerReader $reader, int $owner): JsonResponse
    {
        $record = $this->owner($owner);
        $held   = $reader->permissionsOf($record);

        $list = $rule->newQuery()
            ->orderBy('parent_id')
            ->orderBy('guard_name')
            ->get()
            ->map(static function (RuleContract $row) use ($held): array {
                $entries = $held[(int) $row->getKey()] ?? [];

                return [
                    'id'          => (int) $row->getKey(),
                    'parent_id'   => (int) $row->parent_id,
                    'guard_name'  => $row->guard_name,
                    'options'     => $row->options,
                    'resource'    => $row->resource,
                    'origin'      => $row->origin->value,
                    'title'       => $row->title ? __($row->title) : null,
                    'description' => $row->description ? __($row->description) : null,
                    'entries'     => $entries,
                    'summary'     => OwnerReader::summary($entries),
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
     * One row: a permit or a prohibition of a rule, with an option and a condition. A row that
     * exists already for the same rule, option and effect is replaced, inside one transaction so
     * no request sees the owner without it in between.
     */
    public function store(Request $request, RuleContract $rules, AccessManager $access, int $owner): JsonResponse
    {
        $data = $request->validate([
            'rule'   => ['required', 'integer', 'exists:'.$rules->getTable().',id'],
            'effect' => ['required', 'in:allow,deny'],
            'option' => ['nullable', 'string', 'max:255'],
            'when'   => ['nullable', 'string', 'max:16384'],
        ]);

        $record = $this->owner($owner);
        $rule   = $rules->newQuery()->findOrFail((int) $data['rule']);
        $option = ($data['option'] ?? '') === '' ? null : $data['option'];
        $when   = ($data['when'] ?? '') === '' ? null : $data['when'];
        $permit = $data['effect'] === 'allow';

        $exists = $record->permission()
            ->where('rule_id', $rule->getKey())
            ->where('option', $option)
            ->where('permission', $permit)
            ->exists();

        $write = function () use ($access, $record, $rule, $option, $when, $permit, $exists): void {
            $access->batch(function () use ($access, $record, $rule, $option, $when, $permit, $exists): void {
                $target = $access->for($record);

                if ($exists) {
                    $permit ? $target->removeAllow($rule->guard_name, $option) : $target->removeDeny($rule->guard_name, $option);
                }

                $permit ? $target->allow($rule->guard_name, $option, $when) : $target->deny($rule->guard_name, $option, $when);
            });
        };

        // A transaction only around a replacement: that is where a refused condition would otherwise
        // leave the owner without the row it had.
        $exists ? DB::transaction($write) : $write();

        return $this->ok(__('Permission saved'));
    }

    public function destroy(Request $request, RuleContract $rules, AccessManager $access, int $owner): JsonResponse
    {
        $data = $request->validate([
            'rule'   => ['required', 'integer', 'exists:'.$rules->getTable().',id'],
            'effect' => ['required', 'in:allow,deny'],
            'option' => ['nullable', 'string', 'max:255'],
        ]);

        $record = $this->owner($owner);
        $rule   = $rules->newQuery()->findOrFail((int) $data['rule']);
        $option = ($data['option'] ?? '') === '' ? null : $data['option'];
        $target = $access->for($record);

        $removed = $data['effect'] === 'allow'
            ? $target->removeAllow($rule->guard_name, $option)
            : $target->removeDeny($rule->guard_name, $option);

        if (! $removed) {
            return $this->err(__('There was no such permission.'), [], 404);
        }

        return $this->ok(__('Permission removed'));
    }
}
