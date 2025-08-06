<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Wnikk\LaravelAccessRules\Contracts\AccessRules as AccessRulesContract;

class BaseController extends Controller
{
    /**
     * Check access to Controller action
     *
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public function callAction($method, $parameters)
    {
        $response = parent::callAction($method, $parameters);

        // Clear all cached permissions after edition action
        if ($method !== 'index') {
            $accessRules = app(AccessRulesContract::class);
            $accessRules->clearAllCachedPermissions();
        }

        return $response;
    }
}
