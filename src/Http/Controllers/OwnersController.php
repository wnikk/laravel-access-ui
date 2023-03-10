<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;

class OwnersController extends Controller
{
    /**
     * Return all or selected type owner
     *
     * @param Request $request
     * @param OwnerContract $owner
     * @return array
     */
    public function index(Request $request, OwnerContract $owner)
    {
        $type = $request->type;
        $type = (!$type||$type==='all')?null:$owner->getTypeID($type);

        return [
            'success' => true,
            'list'    => $owner::where([$type?['type', $type]:['id', '>', '0']])->get([
                'id', 'type', 'created_at',
                'name', 'original_id',
            ])->toArray(),
            'types' => array_map('basename', $owner->getListTypes()),
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
        if (!in_array($method, ['index']) && !config('accessUi.grid_owners')){
            abort(403);
        }

        return parent::callAction($method, $parameters);
    }

    /**
     *  Save new owner
     *
     * @param Request $request
     * @param OwnerContract $owner
     * @return array
     */
    public function store(Request $request, OwnerContract $owner)
    {
        $request->validate([
            'type'        => 'required|integer|in:'.implode(',', array_keys($owner->getListTypes())),
            'original_id' => 'nullable',
            'name'        => 'nullable|string',
        ]);
        $type = $owner->getTypeID($request->type);

        $check = $owner->where('type', $type)
            ->where('original_id', $request->original_id)
            ->first();

        if ($check) return [
            'success' => false,
            'message' => 'This owner already exists.',
        ];

        $owner = $owner::create(
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
     * @param OwnerContract $owner
     * @return array
     */
    public function update(Request $request, OwnerContract $owner)
    {
        $request->validate([
            'id'   => 'required|integer',
            'name' => 'nullable|string',
            'original_id' => 'nullable',
        ]);

        $owner = $owner::findOrFail($request->id);

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
     * @param $id
     * @param OwnerContract $owner
     * @return array
     */
    public function destroy($id, OwnerContract $owner)
    {
        $owner = $owner::findOrFail($id);

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
