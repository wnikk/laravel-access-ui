<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;
use Wnikk\LaravelAccessRules\Contracts\Inheritance as InheritanceContract;

class InheritController extends Controller
{
    /**
     * Return all inherit
     *
     * @param $owner
     * @param OwnerContract $ownerContract
     * @return array
     */
    public function index($owner, OwnerContract $ownerContract)
    {
        /** @var InheritanceContract */
        $inherit = $ownerContract::findOrFail($owner)
            ->inheritance()
            ->with('ownerParent')
            ->get();

        $list = [];
        foreach ($inherit as $item) {
            $parent = $item->ownerParent;
            $list[] = [
                'id'          => $item->id,
                'created_at'  => $item->created_at,
                'owner_id'    => $item->owner_parent_id,
                'type'        => $parent->type,
                'name'        => $parent->name,
                'original_id' => $parent->original_id,
            ];
        }

        $owners = $ownerContract::where('id', '!=', $owner)->get([
            'id', 'type',
            'name', 'original_id',
        ])->toArray();

        return [
            'success' => true,
            'list'    => $list,
            'owners'  => $owners,
            'types'   => array_map('basename', $ownerContract->getListTypes()),
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
        if (!in_array($method, ['index']) && !config('accessUi.grid_inherit')){
            abort(403);
        }
        return parent::callAction($method, $parameters);
    }

    /**
     * Create inherit
     *
     * @param int $owner
     * @param Request $request
     * @param OwnerContract $ownerContract
     * @return array
     */
    public function store($owner, Request $request, OwnerContract $ownerContract)
    {
        $request->validate([
            'owner_id' => 'required|integer',
        ]);

        $owner  = $ownerContract::findOrFail($owner);
        $parent = $ownerContract::findOrFail($request->owner_id);

        return [
            'success' => $owner->addInheritance($parent),
        ];
    }

    /**
     * Delete inherit
     *
     * @param $owner
     * @param $id
     * @param OwnerContract $ownerContract
     * @return array
     */
    public function destroy($owner, $id, OwnerContract $ownerContract)
    {

        $owner  = $ownerContract::findOrFail($owner);
        $parent = $ownerContract::findOrFail($id);

        return [
            'success' => $owner->remInheritance($parent),
        ];
    }
}
