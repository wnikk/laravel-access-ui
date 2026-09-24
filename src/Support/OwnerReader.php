<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Support;

use Illuminate\Database\Eloquent\Builder;
use Wnikk\LaravelAccessRules\Conditions\Cond;
use Wnikk\LaravelAccessRules\Contracts\Inheritance as InheritanceContract;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;
use Wnikk\LaravelAccessRules\Contracts\Permission as PermissionContract;
use Wnikk\LaravelAccessRules\Contracts\Rule as RuleContract;
use Wnikk\LaravelAccessUi\AccessUi;

/**
 * Reads the tables of the core for the screens: who inherits from whom, and what an owner holds
 * with the place each permission comes from.
 *
 * The core compiles the same facts for a check and keeps the compiled form to itself. This class
 * reads the rows instead, so what it reports is what the tables say and not what a check would
 * decide: a condition is shown as text, and whether it holds for a record is a question for
 * "explain". The five steps of priority are applied here for one purpose, the label of a rule
 * in the matrix; a decision is never taken from that label.
 */
final class OwnerReader
{
    /** Levels of inheritance the walk follows. A cycle in data cannot hang a request. */
    private const DEPTH = 100;

    public function __construct(private readonly AccessUi $ui) {}

    /**
     * Everything reachable from an owner in one direction, with the direct neighbour it came
     * through. Breadth first, so the recorded route is the shortest one.
     *
     * @param  string          $direction "parents" walks up to what the owner inherits from, "children" down to its heirs.
     * @return array<int, int> Reached owner id => the direct neighbour it came through.
     */
    public function relatives(int $ownerId, string $direction): array
    {
        $from = $direction === 'children' ? 'owner_parent_id' : 'owner_id';
        $to   = $direction === 'children' ? 'owner_id' : 'owner_parent_id';

        $reached = [];
        $border  = [];

        foreach ($this->links()->where($from, $ownerId)->pluck($to) as $id) {
            $reached[(int) $id] = (int) $id;
            $border[(int) $id]  = (int) $id;
        }

        for ($depth = self::DEPTH; $border !== [] && $depth > 0; $depth--) {
            $next   = $this->links()->whereIn($from, array_keys($border))->get([$from, $to]);
            $border = [];

            foreach ($next as $row) {
                $reachedId = (int) $row->{$to};

                if ($reachedId === $ownerId || isset($reached[$reachedId])) {
                    continue;
                }

                $reached[$reachedId] = $reached[(int) $row->{$from}];
                $border[$reachedId]  = $reachedId;
            }
        }

        return $reached;
    }

    /**
     * Every permission row that reaches an owner: its own and those of everything it inherits
     * from, grouped by rule id, strongest first inside a group.
     *
     * @return array<int, list<array{effect:string, option:?string, when:?string, own:bool, from:?string, from_id:int}>>
     */
    public function permissionsOf(OwnerContract $owner): array
    {
        $ownId   = (int) $owner->getKey();
        $sources = [$ownId => $ownId] + $this->relatives($ownId, 'parents');
        $titles  = [];

        foreach ($this->ui->ownerQuery()->whereIn('id', array_keys($sources))->get() as $source) {
            $titles[(int) $source->getKey()] = $this->ui->presentOwner($source)['title'];
        }

        $rows = app(PermissionContract::class)->newQuery()
            ->with('rule')
            ->whereIn('owner_id', array_keys($sources))
            ->get();

        $byRule = [];
        foreach ($rows as $row) {
            if ($row->rule === null) {
                continue;
            }

            $byRule[(int) $row->rule_id][] = [
                'effect'  => $row->permission ? 'allow' : 'deny',
                'option'  => $row->option,
                'when'    => Cond::describe($row->condition, $row->rule->resource),
                'own'     => (int) $row->owner_id === $ownId,
                'from'    => (int) $row->owner_id === $ownId ? null : ($titles[(int) $row->owner_id] ?? null),
                'from_id' => (int) $row->owner_id,
            ];
        }

        // With the option on, a row on a rule also reaches every rule below it in the tree, at the
        // same step. Such a row is shown under the rule it reaches, marked with the rule it sits on,
        // and is removed there: the matrix would otherwise say "not set" for a rule a check permits.
        if (config('access.rule_tree_inheritance')) {
            $parents = app(RuleContract::class)->newQuery()->pluck('parent_id', 'id')->map(static fn ($id): int => (int) $id)->all();
            $names   = app(RuleContract::class)->newQuery()->pluck('guard_name', 'id')->all();

            foreach (array_keys($parents) as $ruleId) {
                for ($above = $parents[$ruleId], $seen = [$ruleId => true]; $above > 0 && ! isset($seen[$above]); $above = $parents[$above] ?? 0) {
                    $seen[$above] = true;

                    foreach ($byRule[$above] ?? [] as $entry) {
                        if (! isset($entry['via'])) {
                            $byRule[$ruleId][] = ['via' => 'tree', 'via_rule' => $names[$above] ?? (string) $above] + $entry;
                        }
                    }
                }
            }
        }

        foreach ($byRule as &$entries) {
            usort($entries, static fn (array $a, array $b): int => self::strength($b) <=> self::strength($a));
        }

        return $byRule;
    }

    /**
     * The label of a rule in the matrix, read from the rows alone.
     *
     * The strongest step decides. When an entry of that step has no condition, the rule is
     * "allowed" or "forbidden" whatever the weaker steps say; when every entry of that step
     * carries a condition, the answer depends on the record and the label is "conditional", even
     * when a weaker step holds a plain row, because the stronger row wins for the records it is
     * true for. "options" when every entry names an option value, so the rule is granted value
     * by value; null when nothing is said. "explain" is where the answer for one record lives.
     *
     * @param  list<array{effect:string, option:?string, when:?string, own:bool}> $entries Sorted strongest first.
     * @return array{state:?string, own:bool}
     */
    public static function summary(array $entries): array
    {
        $plain = array_values(array_filter($entries, static fn (array $e): bool => $e['option'] === null));

        if ($plain === []) {
            return $entries === []
                ? ['state' => null, 'own' => false]
                : ['state' => 'options', 'own' => array_any($entries, static fn (array $e): bool => $e['own'])];
        }

        // Entries of one step share "own" and the effect. Only the strongest step is looked at.
        $top = $plain[0];
        foreach ($plain as $entry) {
            if ($entry['own'] !== $top['own'] || $entry['effect'] !== $top['effect']) {
                break;
            }
            if ($entry['when'] === null) {
                return ['state' => $entry['effect'] === 'allow' ? 'allowed' : 'forbidden', 'own' => $entry['own']];
            }
        }

        return ['state' => 'conditional', 'own' => $top['own']];
    }

    /**
     * Counts for the widget: what the owner holds itself, what reaches it from others, and how
     * much of that depends on a record.
     *
     * @return array{own:int, inherited:int, conditional:int, forbidden:int}
     */
    public function counts(OwnerContract $owner): array
    {
        $counts = ['own' => 0, 'inherited' => 0, 'conditional' => 0, 'forbidden' => 0];

        foreach ($this->permissionsOf($owner) as $entries) {
            foreach ($entries as $entry) {
                // A row that reaches a rule through the tree is counted once, on the rule it sits on.
                if (isset($entry['via'])) {
                    continue;
                }

                $counts[$entry['own'] ? 'own' : 'inherited']++;

                if ($entry['when'] !== null) {
                    $counts['conditional']++;
                }
                if ($entry['effect'] === 'deny') {
                    $counts['forbidden']++;
                }
            }
        }

        return $counts;
    }

    /**
     * The five steps of the core as a number: inherited permit < inherited prohibition < own permit < own prohibition.
     */
    private static function strength(array $entry): int
    {
        return ($entry['own'] ? 2 : 0) + ($entry['effect'] === 'deny' ? 1 : 0);
    }

    private function links(): Builder
    {
        return app(InheritanceContract::class)->newQuery();
    }
}
