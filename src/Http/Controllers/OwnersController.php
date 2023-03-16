<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;

class OwnersController extends Controller
{
    /**
     * Object of OwnerContract
     *
     * @var OwnerContract
     */
    protected $owner;

    /**
     * List of all accompanied types of owners
     *
     * @var array <int, string>
     */
    protected $supportTypes = [];

    /**
     * Establishes a list of all accompanied types of owners
     *
     * @return void
     */
    public function setSupportTypes(array $types)
    {
        $this->supportTypes = $types?array_map('strval', $types):[];
    }

    /**
     * Return all or selected type owner
     *
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $type = $request->type;
        $type = (!$type||$type==='all')?null:$this->owner->getTypeID($type);

        if (!$this->supportTypes){
            abort(403, 'Empty supportTypes');
        }

        if (!$type) {
            $select = $this->owner::whereIn('type', array_keys($this->supportTypes));
        } else {
            if (!in_array($type, array_keys($this->supportTypes))) {
                abort(403, 'Type of owner not find in supportTypes');
            }
            $select = $this->owner::where('type', $type);
        }

        return [
            'success' => true,
            'list'    => $select
                ->get([
                    'id', 'type', 'created_at',
                    'name', 'original_id',
                ])->toArray(),
            'types' => array_map('basename', $this->supportTypes),
        ];
    }

    /**
     * Check access to Controller action
     *
     * @param $method
     * @param $parameters
     * @return void
     */
    public function callAction($method, $parameters)
    {
        if (!config('accessUi.grid_owners')) {
            abort(403);
        }

        $this->owner = app(OwnerContract::class);
        if (!$this->supportTypes) {
            $this->setSupportTypes($this->owner->getListTypes());
        }

        return parent::callAction($method, $parameters);
    }

    /**
     *  Save new owner
     *
     * @param Request $request
     * @return array
     */
    public function store(Request $request)
    {
        $request->validate([
            'type'        => 'required|integer|in:'.implode(',', array_keys($this->supportTypes)),
            'original_id' => 'nullable',
            'name'        => 'nullable|string',
        ]);
        $type = $this->owner->getTypeID($request->type);

        $check = $this->owner->where('type', $type)
            ->where('original_id', $request->original_id)
            ->first();

        if ($check) return [
            'success' => false,
            'message' => 'This owner already exists.',
        ];

        $owner = $this->owner::create(
            $request->only(['type', 'original_id', 'name'])
        );

        return [
            'success' => !!$owner->save(),
        ];
    }

    /**
     * Update owner name
     *
     * @param Request $request
     * @return array
     */
    public function update(Request $request)
    {
        $request->validate([
            'id'   => 'required|integer',
            'name' => 'nullable|string',
            'original_id' => 'nullable',
        ]);

        $owner = $this->owner::findOrFail($request->id);

        $owner->update(
            $request->only(['name', 'original_id'])
        );

        return [
            'success' => !!$owner->save(),
        ];
    }

    /**
     * Delete owner
     *
     * @param Request $request
     * @return array
     */
    public function destroy(Request $request)
    {
        $request->merge(['id' => $request->route('id')]);
        $request->validate([
            'id' => 'required|integer',
        ]);

        $owner = $this->owner::findOrFail($request->id);


        $relation = [
            'inheritance' => $owner->inheritance()->delete(),
            'parent'      => $owner->inheritanceParent()->delete(),
            'permission' => $owner->permission()->delete(),
        ];
        return [
            'success' => !!$owner->delete(),
            'count'   => $relation,
        ];
    }
}
