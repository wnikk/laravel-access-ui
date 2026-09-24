<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Wnikk\LaravelAccessRules\Contracts\AccessManager;
use Wnikk\LaravelAccessUi\Support\OwnerReader;

/**
 * Owners: the rows that hold permissions.
 *
 * Which types are listed by default is configuration ("entities"). Two things happen regardless
 * of it: any owner holding a permission is listed whatever its type, so a grant made straight to
 * one account can be seen and revoked; and any listed row can be renamed or deleted, because the
 * screen works on the owner table and nothing else. Deleting a user's owner row takes away the
 * permissions and the links of that account; the user of the application stays.
 */
class OwnersController extends BaseController
{
    protected string $screen = 'owners';

    protected array $reading = ['index', 'heirs'];

    /**
     * Paged, searched, with enough counts to tell which rows matter.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['entity' => ['nullable', 'string', 'max:64']]);
        $params = $this->pageParams($request);

        $entityKey = (string) $request->input('entity', '');
        $query     = $entityKey !== '' && $entityKey !== 'all'
            ? $this->ui->ownerQuery()->where('type', $this->ui->entity($entityKey)['type_id'])
            : $this->ui->listedOwnerQuery();

        $query->withCount([
            'permission as permissions_count',
            'inheritance as sources_count',
            'inheritanceParent as inheritors_count',
        ]);

        $page = $this->ui->searchOwners($query, $params['search'], $params['page'], $params['per_page']);

        return $this->ok('', $page + ['write' => $this->ui->screenWritable('owners')]);
    }

    /**
     * The identifier and the name are asked for apart: the identifier is the stable machine name
     * migrations and seeders refer to, the name is what administrators read and may change.
     */
    public function store(Request $request, AccessManager $access): JsonResponse
    {
        $data = $request->validate([
            'entity'      => ['required', 'string', 'max:64'],
            'original_id' => ['required', 'string', 'max:64'],
            'name'        => ['nullable', 'string', 'max:128'],
        ]);

        $entity = $this->ui->entity($data['entity']);

        if (! $entity['create']) {
            return $this->err(__(':entity records appear on their own; the panel does not create them.', ['entity' => __($entity['label'])]), [], 403);
        }

        $owner = $access->for($entity['type'], $data['original_id']);

        if ($owner->record() !== null) {
            return $this->err(__('This owner already exists.'), ['original_id' => [__('This owner already exists.')]], 409);
        }

        $created = $owner->create(($data['name'] ?? '') === '' ? $data['original_id'] : $data['name']);

        return $this->ok(__(':entity created', ['entity' => __($entity['single'])]), ['id' => (int) $created->getKey()]);
    }

    /**
     * The name only. The identifier is what code refers to, and the name decides nothing about
     * access, which is why this is the one write of the panel that goes to the row directly.
     */
    public function update(Request $request, int $owner): JsonResponse
    {
        $record = $this->owner($owner);
        $data   = $request->validate(['name' => ['required', 'string', 'max:128']]);

        $record->forceFill(['name' => $data['name']])->save();

        return $this->ok(__('Owner renamed'));
    }

    /**
     * Through the core, so its permissions and links go in both directions, the cache turns over
     * and the event fires. Everyone who inherited from it loses what it held. Not reversible.
     */
    public function destroy(AccessManager $access, int $owner): JsonResponse
    {
        $record = $this->owner($owner);
        $title  = $this->ui->presentOwner($record)['title'];

        $access->for($record)->delete();

        return $this->ok(__(':name deleted', ['name' => $title]));
    }

    /**
     * Who is affected by a change to this owner: everyone that inherits from it, at any depth.
     */
    public function heirs(Request $request, OwnerReader $reader, int $owner): JsonResponse
    {
        $record = $this->owner($owner);
        $params = $this->pageParams($request);
        $ids    = array_keys($reader->relatives((int) $record->getKey(), 'children'));

        $query = $this->ui->ownerQuery()->whereIn('id', $ids ?: [-1]);

        return $this->ok('', $this->ui->searchOwners($query, $params['search'], $params['page'], $params['per_page']) + ['owner' => $this->ui->presentOwner($record)]);
    }
}
