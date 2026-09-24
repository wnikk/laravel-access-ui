<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Support;

use Wnikk\LaravelAccessRules\Contracts\AccessManager;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;

/**
 * What the core says an owner holds, shaped for the screens.
 *
 * The core answers permissions(), sources() and heirs() as data since 3.3; this class regroups
 * the rows by rule and turns the five steps of priority into the label of a rule in the matrix.
 * A decision is never taken from that label: whether a condition holds for a record is a
 * question for "explain". Nothing here reads a table.
 */
final class OwnerReader
{
    public function __construct(private readonly AccessManager $access) {}

    /**
     * Everyone reachable from an owner in one direction, direct ones first.
     *
     * @param  string                                                                                              $direction "parents" is what the owner inherits from, "children" who inherits from it.
     * @return list<array{type:string, id:string, name:?string, record:int, direct:bool, link:?int, through:?int}>
     */
    public function related(OwnerContract $owner, string $direction): array
    {
        $rows = $direction === 'children' ? $this->access->for($owner)->heirs() : $this->access->for($owner)->sources();

        usort($rows, static fn (array $a, array $b): int => ($b['direct'] <=> $a['direct']) ?: strcmp((string) ($a['name'] ?? $a['id']), (string) ($b['name'] ?? $b['id'])));

        return $rows;
    }

    /**
     * Every row that reaches an owner, grouped by rule id, strongest first inside a group, in the
     * shape the matrix draws: the source as a title, and the id of its record to key a chip by.
     *
     * @return array<int, list<array{effect:string, option:?string, when:?string, own:bool, from:?string, from_id:int, via:?string, via_rule:?string}>>
     */
    public function permissionsOf(OwnerContract $owner): array
    {
        $byRule = [];
        foreach ($this->access->for($owner)->permissions() as $row) {
            $byRule[$row['rule_id']][] = [
                'effect'   => $row['effect'],
                'option'   => $row['option'],
                'when'     => $row['when'],
                'own'      => $row['own'],
                'from'     => $row['own'] ? null : (($row['from']['name'] ?? '') !== '' ? $row['from']['name'] : $row['from']['id']),
                'from_id'  => $row['from']['record'],
                'via'      => $row['via'],
                'via_rule' => $row['via_rule'],
            ];
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
     * many rows carry a condition or take a permission away. A row that reaches a rule through
     * the tree is counted once, on the rule it sits on.
     *
     * @return array{own:int, inherited:int, conditional:int, forbidden:int}
     */
    public function counts(OwnerContract $owner): array
    {
        $counts = ['own' => 0, 'inherited' => 0, 'conditional' => 0, 'forbidden' => 0];

        foreach ($this->access->for($owner)->permissions() as $row) {
            if ($row['via'] !== null) {
                continue;
            }

            $counts[$row['own'] ? 'own' : 'inherited']++;

            if ($row['when'] !== null) {
                $counts['conditional']++;
            }
            if ($row['effect'] === 'deny') {
                $counts['forbidden']++;
            }
        }

        return $counts;
    }
}
