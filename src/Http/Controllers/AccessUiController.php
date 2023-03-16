<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Support\Facades\View;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;

class AccessUiController
{
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function rules()
    {
        $accept = config('accessUi.grid_rules');

        return View::make('accessUi::rules', ['routes' => [
            'list'   => route('accessUi.rules-data.index'),
            'create' => $accept?route('accessUi.rules-data.store'):null,
            'update' => $accept?route('accessUi.rules-data.update', ':id:'):null,
            'delete' => $accept?route('accessUi.rules-data.destroy', ':id:'):null,
        ]]);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function owners($type)
    {
        $accept = config('accessUi.grid_owners');

        $type = is_numeric($type)?$type:'all';

        return View::make('accessUi::owners', ['routes' => [
            'list'   => route('accessUi.owners-data.index', ['type' => $type]),
            'create' => $accept?route('accessUi.owners-data.store'):null,
            'update' => $accept?route('accessUi.owners-data.update', ':id:'):null,
            'delete' => $accept?route('accessUi.owners-data.destroy', ':id:'):null,
        ]]);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function management(OwnerContract $owners)
    {
        if (!config('accessUi.grid_owners')) abort(403, 'Config "accessUi.grid_owners" does not allow access');

        $types = array_map('basename', $owners->getListTypes());
        $list  = $owners::all();
        $groups = [];
        foreach ($list as $item) {
            if (empty($groups[$item->type])) {
                $groups[$item->type] = [
                    'name' => $types[$item->type]??'#ID:'.$item->type,
                    'list' => [],
                ];
            }
            $groups[$item->type]['list'][] = $item->toArray();
        }

        return View::make('accessUi::management', [
            'routes' => [
                'inherit'    => config('accessUi.grid_inherit')?route('accessUi.inherit', ':owner:'):null,
                'permission' => config('accessUi.grid_permission')?route('accessUi.permission', ':owner:'):null,
            ],
            'owners' => $groups,
        ]);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function inherit(int $owner)
    {
        $accept = config('accessUi.grid_inherit');

        return View::make('accessUi::inherit', ['routes' => [
            'list'   => route('accessUi.owner.inherit-data.index', ['owner' => $owner]),
            'create' => $accept?route('accessUi.owner.inherit-data.store', ['owner' => $owner]):null,
            'delete' => $accept?route('accessUi.owner.inherit-data.destroy', ['owner' => $owner, 'id' => ':id:']):null,
        ]]);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function permission(int $owner)
    {
        $accept = config('accessUi.grid_permission');

        return View::make('accessUi::permission', ['routes' => [
            'list'   => route('accessUi.owner.permission-data.index', ['owner' => $owner]),
            'update' => $accept?route('accessUi.owner.permission-data.update', ['owner' => $owner, 'id' => ':id:']):null,
        ]]);
    }
}
