<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Wnikk\LaravelAccessRules\Contracts\AccessManager;
use Wnikk\LaravelAccessRules\Contracts\Inheritance as InheritanceContract;
use Wnikk\LaravelAccessUi\AccessUi;
use Wnikk\LaravelAccessUi\Support\OwnerReader;

/**
 * Inheritance, asked from either end.
 *
 * Giving somebody a role is an inheritance link, and the child keeps taking whatever the parent
 * holds as the parent changes. One link answers two questions, and which one depends on where
 * you stand: direction=children is the screen of the panel (who inherits from this owner),
 * direction=parents is the widget on somebody's page (whom this owner inherits from). Both run
 * through the same three endpoints, because they are the same writes against the same table.
 */
class InheritController extends BaseController
{
    protected string $screen = 'inherit';

    public function __construct(AccessUi $ui, private readonly OwnerReader $reader)
    {
        parent::__construct($ui);
    }

    /**
     * The links on one side of an owner, indirect ones included and marked with the direct link
     * they arrived through, and what could still be added to that side.
     */
    public function index(Request $request, int $owner): JsonResponse
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
            $list[]                          = $this->ui->presentOwner($other, [
                'inheritance_id' => (int) $link->getKey(),
                'direct'         => true,
                'through'        => null,
                'linked_at'      => $link->created_at,
            ]);
        }

        $reached  = $this->reader->relatives($ownerId, $direction);
        $indirect = array_diff_key($reached, $directs);

        if ($indirect !== []) {
            foreach ($this->ui->ownerQuery()->whereIn('id', array_keys($indirect))->orderBy('name')->get() as $other) {
                $through = $reached[(int) $other->getKey()];
                $list[]  = $this->ui->presentOwner($other, [
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
            // "parents" is the direction of the card, and the card has a policy of its own.
            'write' => $direction === 'parents' ? $this->ui->widgetWritable($record) : $this->ui->screenWritable('inherit'),
        ];

        // Counts, not contents: how much this owner ends up with is what a card on a user page
        // shows; listing it is the job of the permissions screen.
        if ($direction === 'parents') {
            $payload['permissions'] = $this->reader->counts($record);
        }

        return $this->ok('', $payload);
    }

    /**
     * A source has to be one the configuration allows to be handed out; without that check the
     * dropdown of the widget would be the only thing between a crafted request and inheriting
     * from anything in the table. A loop is refused by the core with INHERITANCE_LOOP.
     */
    public function store(Request $request, AccessManager $access, int $owner): JsonResponse
    {
        $data = $request->validate([
            'direction' => ['nullable', 'in:parents,children'],
            'target'    => ['required', 'integer', 'min:1'],
        ]);

        $record    = $this->owner($owner);
        $direction = (string) ($data['direction'] ?? 'parents');
        $target    = $this->ui->findOwner($data['target']);

        if ($target === null) {
            return $this->err(__('No owner with that id.'), ['target' => [__('No owner with that id.')]], 404);
        }

        [$child, $parent] = $direction === 'children' ? [$target, $record] : [$record, $target];

        // Giving this owner a source is what the card does, whichever page sent the request.
        if ($direction === 'parents') {
            abort_unless($this->ui->widgetWritable($record), 403, 'Assigning to this owner is not allowed here.');
        }

        if ($direction === 'parents' && ! ($this->ui->entityForType((int) $parent->type)['assignable'] ?? false)) {
            return $this->err(__('This kind of owner cannot be handed out as a source of rights.'), ['target' => [__('This kind of owner cannot be handed out as a source of rights.')]]);
        }

        if ((int) $child->getKey() === (int) $parent->getKey()) {
            return $this->err(__('An owner cannot inherit from itself.'));
        }

        if ($child->inheritance()->where('owner_parent_id', $parent->getKey())->exists()) {
            return $this->err(__('This is already assigned.'), [], 409);
        }

        $access->for($child)->inheritFrom($parent);

        return $this->ok(__(':name assigned', ['name' => $this->ui->presentOwner($parent)['title']]));
    }

    /**
     * One direct link that touches this owner on either side. Scoped to the owner of the URL,
     * otherwise any link of the table could be removed by guessing a number.
     */
    public function destroy(AccessManager $access, int $owner, int $link): JsonResponse
    {
        $record  = $this->owner($owner);
        $ownerId = (int) $record->getKey();

        $row = app(InheritanceContract::class)->newQuery()
            ->whereKey($link)
            ->where(static fn ($query) => $query->where('owner_id', $ownerId)->orWhere('owner_parent_id', $ownerId))
            ->firstOrFail();

        // A link where this owner is the child is one of its sources: removing it is what the
        // card does, so the policy of the card applies. Removing an heir is the panel's side.
        if ((int) $row->owner_id === $ownerId) {
            abort_unless($this->ui->widgetWritable($record), 403, 'Assigning to this owner is not allowed here.');
        }

        $child  = $this->ui->findOwner($row->owner_id);
        $parent = $this->ui->findOwner($row->owner_parent_id);

        // Both ends present: the core removes it, with its cache and event. One end gone already:
        // the row is an orphan and goes directly.
        if ($child !== null && $parent !== null) {
            $access->for($child)->stopInheritingFrom($parent);
        } else {
            $row->delete();
        }

        return $this->ok(__('Assignment removed'));
    }

    /**
     * What could still be added to one side. The two sides draw from different pools: parents
     * from the entities marked assignable, children from every owner of the table. Sent inline
     * while short enough for a dropdown; past picker.inline_limit the client searches instead.
     *
     * @param array<int, int> $exclude
     */
    private function available(string $direction, int $ownerId, array $exclude): array
    {
        $scope = $direction === 'children' ? 'all' : 'assignable';
        $query = $direction === 'children' ? $this->ui->ownerQuery() : $this->ui->assignableOwnerQuery();

        $exclude[$ownerId] = $ownerId;
        $query->whereNotIn('id', array_values($exclude));

        $limit = max(1, (int) $this->ui->config('picker.inline_limit', 100));
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
