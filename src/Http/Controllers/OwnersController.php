<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Wnikk\LaravelAccessRules\Contracts\AccessRules as AccessRulesContract;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;

/**
 * Owners: the rows that can hold permissions.
 *
 * Which types are listed by default is configuration (`entities`), but two things happen regardless of
 * it. Any owner holding a permission or a prohibition appears here whatever its type — a grant made
 * straight to one account is otherwise invisible on a screen listing only roles, and an administrator
 * who cannot see it cannot revoke it. And any listed row can be renamed or deleted, because this screen
 * works on the owner table and nothing else: deleting a user's owner row takes away their permissions
 * and assignments, not the user.
 *
 * Creating is the one thing `entities` gates, and only because the two cases differ. A role exists
 * because somebody made it here. A user's owner row appears the first time the application touches
 * them, so typing one by hand would invite a typo that holds permissions and belongs to nobody.
 */
class OwnersController extends BaseController
{
    /** @var string */
    protected $screen = 'owners';

    /**
     * Owners, with enough counts to tell which ones matter.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['entity' => ['nullable', 'string', 'max:64']]);

        $entityKey = (string) $request->input('entity', '');
        $query     = $this->ui->listedOwnerQuery();

        // Filtering by entity narrows to that type exactly, unlisted permission holders included or
        // not according to whether their type is the one asked for.
        if ($entityKey !== '' && $entityKey !== 'all') {
            $query = $this->ui->allOwnerQuery()->where('type', $this->ui->entity($entityKey)['type_id']);
        }

        $rows = $query
            ->withCount([
                'permission as permissions_count',
                'inheritance as sources_count',
                'inheritanceParent as inheritors_count',
            ])
            ->orderBy('name')
            ->get();

        $list = $rows->map(function ($row) {
            return $this->ui->presentOwner($row, [
                'permissions_count' => (int) $row->permissions_count,
                'sources_count'     => (int) $row->sources_count,
                'inheritors_count'  => (int) $row->inheritors_count,
            ]);
        })->values();

        return $this->ok('', [
            'list'  => $list,
            'write' => $this->ui->screenWritable('owners'),
        ]);
    }

    /**
     * Create an owner row of a type the configuration lets the panel create.
     *
     * The identifier is asked for separately from the name because they are different things: the
     * identifier is the stable machine name migrations and seeders refer to, the name is what
     * administrators read and may change freely.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity'      => ['required', 'string', 'max:64'],
            'original_id' => ['required', 'string', 'max:64'],
            'name'        => ['nullable', 'string', 'max:128'],
        ]);

        $entity = $this->ui->entity($data['entity']);

        if (!$entity['create']) {
            return $this->err(
                __(':entity records appear on their own; the panel does not create them.', [
                    'entity' => __($entity['label']),
                ]),
                [],
                403
            );
        }

        $existing = app(OwnerContract::class)->findOwner($entity['type_id'], $data['original_id']);

        if ($existing) {
            return $this->err(__('This owner already exists.'), [
                'original_id' => [__('This owner already exists.')],
            ], 409);
        }

        $created = app(AccessRulesContract::class)->newOwner(
            $entity['type'],
            $data['original_id'],
            ($data['name'] ?? '') === '' ? $data['original_id'] : $data['name']
        );

        if (!$created) {
            return $this->err(__('The owner could not be created.'), [], 500);
        }

        $this->ui->flushCache();

        return $this->ok(__(':entity created', ['entity' => __($entity['single'])]));
    }

    /**
     * Rename an owner.
     *
     * The name only. `original_id` is what seeders, migrations and application code refer to, so
     * changing it here would quietly break whatever points at it.
     *
     * @param Request $request
     * @param int|string $owner
     * @return JsonResponse
     */
    public function update(Request $request, $owner): JsonResponse
    {
        $record = $this->owner($owner);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
        ]);

        $record->update(['name' => $data['name']]);
        $this->ui->flushCache();

        return $this->ok(__('Owner renamed'));
    }

    /**
     * Delete an owner row, and with it everything it held.
     *
     * access-rules cascades: the owner's permissions go, its inheritance links go in both directions,
     * and everyone who inherited from it loses what it held. The row in your own tables — the user, the
     * team, whatever it stood for — is untouched; this is the access record, not the thing itself.
     *
     * Not reversible, which is why the interface asks first.
     *
     * @param int|string $owner
     * @return JsonResponse
     */
    public function destroy($owner): JsonResponse
    {
        $record = $this->owner($owner);
        $title  = $this->ui->presentOwner($record)['title'];

        $record->delete();
        $this->ui->flushCache();

        return $this->ok(__(':name deleted', ['name' => $title]));
    }
}
