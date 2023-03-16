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
     * Object of OwnerContract
     *
     * @var OwnerContract
     */
    protected $owner;

    /**
     * Set owner
     *
     * @return void
     */
    public function setOwner(OwnerContract $owner)
    {
        $this->owner = $owner;
    }

    /**
     * Set owner form Request
     *
     * @return void
     */
    public function setOwnerFromRequest(Request $request)
    {
        $request->merge([
            'owner' => $request->route('owner')
        ]);

        $request->validate([
            'owner' => 'required|integer',
        ]);

        $this->setOwner(
            $this->owner::findOrFail($request->owner)
        );
    }

    /**
     * Return all inherit
     *
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        if (!$this->owner->getKey()) $this->setOwnerFromRequest($request);

        /** @var InheritanceContract */
        $inherit = $this->owner
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
                'type'        => $parent?$parent->type:null,
                'name'        => $parent?$parent->name:null,
                'original_id' => $parent?$parent->original_id:null,
            ];
        }

        $owners = $this->owner::where('id', '!=', $this->owner->getKey())->get([
            'id', 'type',
            'name', 'original_id',
        ])->toArray();

        return [
            'success' => true,
            'list'    => $list,
            'owners'  => $owners,
            'types'   => array_map('basename', $this->owner->getListTypes()),
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
        if (!config('accessUi.grid_inherit')){
            abort(403);
        }

        if (!$this->owner) {
            $this->setOwner(app(OwnerContract::class));
        }

        return parent::callAction($method, $parameters);
    }

    /**
     * Create inherit
     *
     * @param Request $request
     * @return array
     */
    public function store(Request $request)
    {
        if (!$this->owner->getKey()) $this->setOwnerFromRequest($request);

        $request->validate([
            'owner_id' => 'required|integer',
        ]);

        $parent = $this->owner::findOrFail($request->owner_id);

        return [
            'success' => $this->owner->addInheritance($parent),
        ];
    }

    /**
     * Delete inherit
     *
     * @param Request $request
     * @return array
     */
    public function destroy(Request $request)
    {
        if (!$this->owner->getKey()) $this->setOwnerFromRequest($request);

        $request->merge([
            'id' => $request->route('id')
        ]);
        $request->validate([
            'id' => 'required|integer',
        ]);

        $parent = $this->owner::findOrFail($request->id);

        return [
            'success' => $this->owner->remInheritance($parent),
        ];
    }
}
