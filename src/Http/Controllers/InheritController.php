<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Wnikk\LaravelAccessRules\Contracts\Inheritance as InheritanceContract;

/**
 * Inheritance, asked from either end.
 *
 * There is no separate "assign a role" concept in access-rules: giving somebody a role *is* an
 * inheritance link, and the child keeps taking whatever the parent holds as the parent changes. So one
 * link answers two questions, and which one you are asking depends on where you are standing:
 *
 *   direction=children   who inherits from this owner. What the panel's inheritance screen shows:
 *                        pick a role on the left, see and change who has it.
 *   direction=parents    whom this owner inherits from. What the widget on somebody's page shows.
 *
 * Both run through the same three endpoints, because they are the same three writes against the same
 * table. Splitting them into six would have meant two names for every operation and a second place to
 * forget a check.
 *
 * `parents` additionally carries a count of what the owner ends up holding — the number only, never the
 * rules. That is what makes the widget honest about an account somebody hand-tuned: nothing assigned,
 * and still a non-zero number.
 */
class InheritController extends BaseController
{
    /** @var string */
    protected $screen = 'inherit';

    /**
     * The links on one side of an owner, and what could be added to that side.
     *
     * Indirect entries are included and marked. An owner inheriting from a role which itself inherits
     * from another holds both, and a screen showing only the first would be answering a question about
     * the table rather than about the owner. Each indirect row carries the direct link it arrived
     * through — that link is the one to remove.
     *
     * @param Request $request
     * @param int|string $owner
     * @return JsonResponse
     */
    public function index(Request $request, $owner): JsonResponse
    {
        $request->validate(['direction' => ['nullable', 'in:parents,children']]);

        $record    = $this->owner($owner);
        $direction = (string) $request->input('direction', 'parents');
        $ownerId   = (int) $record->getKey();

        $links = $direction === 'children'
            ? $record->inheritanceParent()->with('owner')->get()
            : $record->inheritance()->with('ownerParent')->get();

        $list    = [];
        $directs = [];

        foreach ($links as $link) {
            $other = $direction === 'children' ? $link->owner : $link->ownerParent;

            if ($other === null) {
                continue;
            }

            $directs[(int) $other->getKey()] = (int) $other->getKey();

            $list[] = $this->ui->presentOwner($other, [
                'inheritance_id' => (int) $link->id,
                'direct'         => true,
                'through'        => null,
                'linked_at'      => $link->created_at,
            ]);
        }

        // Whatever those links drag in behind them, walked in the same direction.
        $reached  = $this->relatives($ownerId, $direction);
        $indirect = array_diff_key($reached, $directs);

        if ($indirect !== []) {
            $others = $this->ui->allOwnerQuery()
                ->whereIn('id', array_keys($indirect))
                ->orderBy('name')
                ->get();

            foreach ($others as $other) {
                $through = $reached[(int) $other->getKey()];

                $list[] = $this->ui->presentOwner($other, [
                    'inheritance_id' => null,
                    'direct'         => false,
                    'through'        => isset($directs[$through]) ? $through : null,
                    'linked_at'      => null,
                ]);
            }
        }

        $payload = [
            'owner'     => $this->ui->presentOwner($record),
            'direction' => $direction,
            'list'      => $list,
            'available' => $this->available($direction, $ownerId, $directs),
            'write'     => $this->ui->screenWritable('inherit'),
        ];

        // Counts, not contents. The widget shows how much this owner ends up with; listing it is the
        // permissions screen's job and would make the card a page.
        if ($direction === 'parents') {
            $payload['permissions'] = $this->ui->permissionSummary($record);
        }

        return $this->ok('', $payload);
    }

    /**
     * Link another owner to this one, on the side the caller names.
     *
     * @param Request $request
     * @param int|string $owner
     * @return JsonResponse
     */
    public function store(Request $request, $owner): JsonResponse
    {
        $data = $request->validate([
            'direction' => ['nullable', 'in:parents,children'],
            'target'    => ['required', 'integer', 'min:1'],
        ]);

        $record    = $this->owner($owner);
        $direction = (string) ($data['direction'] ?? 'parents');
        $target    = $this->ui->findOwner($data['target']);

        if ($target === null) {
            return $this->err(__('No owner with that id.'), [
                'target' => [__('No owner with that id.')],
            ], 404);
        }

        // Which of the two is taking and which is giving.
        $child  = $direction === 'children' ? $target : $record;
        $parent = $direction === 'children' ? $record : $target;

        // A source has to be one the configuration allows to be handed out. Without this the widget's
        // own dropdown would be the only thing between a crafted request and inheriting from anything
        // in the table.
        if ($direction === 'parents' && !$this->isAssignable($parent)) {
            return $this->err(__('This kind of owner cannot be handed out as a source of rights.'), [
                'target' => [__('This kind of owner cannot be handed out as a source of rights.')],
            ], 422);
        }

        if ((int) $child->getKey() === (int) $parent->getKey()) {
            return $this->err(__('An owner cannot inherit from itself.'), [], 422);
        }

        if ($child->inheritance()->where('owner_parent_id', $parent->getKey())->exists()) {
            return $this->err(__('This is already assigned.'), [], 409);
        }

        // A loop would make resolving permissions depend on where the walk happened to start.
        if (array_key_exists((int) $child->getKey(), $this->relatives((int) $parent->getKey(), 'parents'))) {
            return $this->err(__('That would create a loop: it already inherits from this owner.'), [], 422);
        }

        if (!$child->addInheritance($parent)) {
            return $this->err(__('The assignment could not be saved.'), [], 500);
        }

        $this->ui->flushCache();

        return $this->ok(__(':name assigned', ['name' => $this->ui->presentOwner($parent)['title']]));
    }

    /**
     * Remove one direct link that touches this owner on either side.
     *
     * Scoped to the owner in the URL rather than trusting the link id alone: otherwise any link in the
     * table could be removed by guessing a number. Either side counts, because the same screen removes
     * links it is looking at from both directions.
     *
     * @param int|string $owner
     * @param int|string $link
     * @return JsonResponse
     */
    public function destroy($owner, $link): JsonResponse
    {
        $record  = $this->owner($owner);
        $ownerId = (int) $record->getKey();

        $row = app(InheritanceContract::class)->newQuery()
            ->where('id', (int) $link)
            ->where(static function ($query) use ($ownerId) {
                $query->where('owner_id', $ownerId)->orWhere('owner_parent_id', $ownerId);
            })
            ->firstOrFail();

        $child  = $this->ui->findOwner($row->owner_id);
        $parent = $this->ui->findOwner($row->owner_parent_id);

        // Both ends present: ask access-rules to remove it, so its own bookkeeping runs. One end gone
        // already: delete the orphaned row directly, since there is nothing left to ask.
        if ($child !== null && $parent !== null) {
            $child->remInheritance($parent);
        } else {
            $row->delete();
        }

        $this->ui->flushCache();

        return $this->ok(__('Assignment removed'));
    }

    // =================================================================
    // Internals
    // =================================================================

    /**
     * Everything reachable from an owner in one direction, mapped to the direct link it came through.
     *
     * access-rules resolves the same chain internally but keeps it private, and the screens need it for
     * a different reason: to show that a role was not assigned here but reached the owner through
     * something else. Walked breadth-first so the recorded route is the shortest one, and bounded so a
     * cycle already in the data cannot hang the request.
     *
     * @param int $ownerId
     * @param string $direction 'parents' walks upwards, 'children' downwards
     * @return array<int, int> reached owner id => the direct neighbour it came through
     */
    protected function relatives(int $ownerId, string $direction): array
    {
        $from = $direction === 'children' ? 'owner_parent_id' : 'owner_id';
        $to   = $direction === 'children' ? 'owner_id' : 'owner_parent_id';

        $inheritance = app(InheritanceContract::class);

        $reached = [];
        $border  = [];

        foreach ($inheritance->newQuery()->where($from, $ownerId)->pluck($to) as $id) {
            $reached[(int) $id] = (int) $id;
            $border[(int) $id]  = (int) $id;
        }

        $depth = 100;

        while ($border !== [] && --$depth > 0) {
            $next = $inheritance->newQuery()
                ->whereIn($from, array_keys($border))
                ->get([$from, $to]);

            $border = [];

            foreach ($next as $row) {
                $reachedId = (int) $row->{$to};

                if ($reachedId === $ownerId || isset($reached[$reachedId])) {
                    continue;
                }

                // Attribute it to whichever direct link led here.
                $reached[$reachedId] = $reached[(int) $row->{$from}];
                $border[$reachedId]  = $reachedId;
            }
        }

        return $reached;
    }

    /**
     * Is this owner one the configuration allows to be handed out?
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @return bool
     */
    protected function isAssignable($owner): bool
    {
        $entity = $this->ui->entityForType($owner->type);

        return $entity !== null && $entity['assignable'];
    }

    /**
     * What could still be added to this side, minus this owner and whatever is already linked.
     *
     * The two sides draw from different pools, and the asymmetry is the point:
     *
     *   parents  — only entities marked `assignable`. What may be handed out is a decision.
     *   children — every owner in the table. Who may receive rights is not.
     *
     * Sent inline while the list is short enough to be a dropdown; past `picker.inline_limit` it comes
     * back as `truncated` with an empty list and the client searches instead.
     *
     * @param string $direction
     * @param int $ownerId
     * @param array<int, int> $exclude
     * @return array{list: array, truncated: bool, total: int, scope: string}
     */
    protected function available(string $direction, int $ownerId, array $exclude): array
    {
        $scope = $direction === 'children' ? 'all' : 'assignable';
        $query = $direction === 'children' ? $this->ui->allOwnerQuery() : $this->ui->assignableOwnerQuery();

        $exclude[$ownerId] = $ownerId;

        $query->whereNotIn('id', array_values($exclude));

        $limit = (int) $this->ui->config('picker.inline_limit', 100);
        $limit = $limit > 0 ? $limit : 100;
        $total = (clone $query)->count();

        if ($total > $limit) {
            return ['list' => [], 'truncated' => true, 'total' => $total, 'scope' => $scope];
        }

        $list = [];
        foreach ($query->orderBy('name')->get() as $candidate) {
            $list[] = $this->ui->presentOwner($candidate);
        }

        return ['list' => $list, 'truncated' => false, 'total' => $total, 'scope' => $scope];
    }
}
