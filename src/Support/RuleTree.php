<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Support;

use Wnikk\LaravelAccessRules\Contracts\Rule as RuleContract;

/**
 * The shape of the rule tree. The column parent_id defaults to 0 and not null, so a root rule
 * has parent_id 0; both values turn up in practice and both mean "no parent" here.
 */
final class RuleTree
{
    public static function normaliseParent(mixed $parentId): int
    {
        return ($parentId === null || $parentId === '' || $parentId === false) ? 0 : (int) $parentId;
    }

    /**
     * Would moving $id under $parentId put a rule inside its own subtree? Walking up from the
     * proposed parent is enough: meeting the rule itself on the way to a root closes a loop. The
     * seen set stops on a cycle that already exists in the data.
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
            $walk        = (int) app(RuleContract::class)->newQuery()->whereKey($walk)->value('parent_id');
        }

        return false;
    }
}
