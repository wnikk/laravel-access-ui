<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Support\Facades\View;

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
    public function inherit($owner)
    {
        $accept = config('accessUi.grid_inherit');

        return View::make('accessUi::inherit', ['routes' => [
            'list'   => route('accessUi.owner.inherit-data.index', ['owner' => $owner]),
            'create' => $accept?route('accessUi.owner.inherit-data.store', ['owner' => $owner]):null,
            'delete' => $accept?route('accessUi.owner.inherit-data.destroy', ['id' => ':id:', 'owner' => $owner]):null,
        ]]);
    }
}
