<?php

namespace Wnikk\LaravelAccessUi\Support;

use Wnikk\LaravelAccessRules\Contracts\Rule as RuleContract;

/**
 * Questions about the shape of the rule tree that the database cannot answer in one statement.
 *
 * Rules form a tree through `parent_id`, and the column defaults to 0 rather than null — a root rule
 * has `parent_id` 0. Both values turn up in practice, which is why every check here treats them the
 * same.
 */
class RuleTree
{
    /**
     * Normalise a parent reference to what the column accepts.
     *
     * The column is a non-nullable integer defaulting to 0, so a "no parent" choice has to be
     * written as 0. Sending null would fail on any database that means it.
     *
     * @param mixed $parentId
     * @return int
     */
    public static function normaliseParent($parentId): int
    {
        return ($parentId === null || $parentId === '' || $parentId === false) ? 0 : (int) $parentId;
    }

    /**
     * Would moving $id under $parentId put a rule inside its own subtree?
     *
     * Walking upwards from the proposed parent is enough: if the rule itself is met on the way to a
     * root, the move closes a loop. The seen-set guards against a cycle that already exists in the
     * data, which would otherwise make this walk the last thing the request ever does.
     *
     * @param int $id
     * @param int $parentId
     * @return bool
     */
    public static function wouldCycle(int $id, int $parentId): bool
    {
        $seen = [];
        $walk = $parentId;

        while ($walk > 0) {
            if ($walk === $id || isset($seen[$walk])) {
                return true;
            }

            $seen[$walk] = true;

            // Deactivated rules stay in the tree, so the walk has to see them; stopping at one would
            // report no cycle across a branch that has a soft-deleted container in the middle.
            $walk = (int) app(RuleContract::class)->newQuery()
                ->withTrashed()
                ->where('id', $walk)
                ->value('parent_id');
        }

        return false;
    }
}
