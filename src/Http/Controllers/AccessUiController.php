<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Support\Facades\View;

class AccessUiController
{
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function main()
    {
        $accept1 = config('accessUi.grid_rules');
        $accept2 = config('accessUi.grid_owners');
        $accept3 = config('accessUi.grid_inherit');
        $accept4 = config('accessUi.grid_permission');
        $type = 'all';

        return View::make('accessUi::template', [
            'available' => [
                'rules' => $accept1,
                'owners' => $accept2,
                'inherit' => $accept3,
                'permission' => $accept4,
            ],
            'routes' => [
                'rules' => [
                    'list'   => route('accessUi.rules-data.index'),
                    'create' => $accept1?route('accessUi.rules-data.store'):null,
                    'update' => $accept1?route('accessUi.rules-data.update', ':id:'):null,
                    'delete' => $accept1?route('accessUi.rules-data.destroy', ':id:'):null,
                ],
                'owners' => [
                    'list'   => route('accessUi.owners-data.index', ['type' => $type]),
                    'create' => $accept2?route('accessUi.owners-data.store'):null,
                    'update' => $accept2?route('accessUi.owners-data.update', ':id:'):null,
                    'delete' => $accept2?route('accessUi.owners-data.destroy', ':id:'):null,
                ],
                'inherit' => [
                    'list'   => route('accessUi.owner.inherit-data.index', ['owner' => ':owner:']),
                    'create' => $accept3?route('accessUi.owner.inherit-data.store', ['owner' => ':owner:']):null,
                    'delete' => $accept3?route('accessUi.owner.inherit-data.destroy', ['owner' => ':owner:', 'id' => ':id:']):null,
                ],
                'permission' => [
                    'list'   => route('accessUi.owner.permission-data.index', ['owner' => ':owner:']),
                    'update' => $accept4?route('accessUi.owner.permission-data.update', ['owner' => ':owner:', 'id' => ':id:']):null,
                ]
            ],
        ]);
    }
}
