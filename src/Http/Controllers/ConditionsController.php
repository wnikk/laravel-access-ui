<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Wnikk\LaravelAccessRules\Conditions\Cond;
use Wnikk\LaravelAccessUi\Support\Vocabulary;

/**
 * The editor of conditions: what it may name, and whether what was typed compiles.
 *
 * Not a screen of its own, so it is not gated as one: the editor sits inside the rules and the
 * permissions screens, and both are checked on their own routes. The check writes nothing and
 * shows nothing of the data, it is the compiler of the core answering "would this save".
 */
class ConditionsController extends BaseController
{
    protected string $screen = '';

    public function vocabulary(): JsonResponse
    {
        return $this->ok('', Vocabulary::build());
    }

    /**
     * The same compiler that saving uses; a refusal comes back as the core words it, with 422.
     * On success the text is printed back from the tree, which is what the stored condition will read as.
     */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'when'     => ['nullable', 'string', 'max:16384'],
            'resource' => ['nullable', 'string', 'max:64'],
        ]);

        $resource = ($data['resource'] ?? '') === '' ? null : $data['resource'];
        $tree     = Cond::compile(($data['when'] ?? '') === '' ? null : $data['when'], $resource);

        return $this->ok('', [
            'valid' => true,
            'text'  => Cond::describe($tree, $resource),
            // Aggregates cost a correlated subquery per row of a list. The editor says so next to them.
            'aggregates' => $tree === null ? false : self::hasAggregate($tree),
        ]);
    }

    private static function hasAggregate(array $node): bool
    {
        if (($node[0] ?? null) === 'agg') {
            return true;
        }

        foreach ($node as $child) {
            if (is_array($child) && self::hasAggregate($child)) {
                return true;
            }
        }

        return false;
    }
}
