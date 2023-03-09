<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Wnikk\LaravelAccessRules\Contracts\Rule as RuleContract;

class RulesController extends Controller
{
    /**
     * Return all rules
     *
     * @param RuleContract $rule
     * @return array
     */
    public function index(RuleContract $rule)
    {
        return [
            'success' => true,
            'list'    => $rule::all([
                'id', 'parent_id',
                'guard_name', 'options',
                'description',
                'created_at', 'deleted_at',
            ])->toArray(),
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
        if (!in_array($method, ['index']) && !config('accessUi.grid_rules')){
            abort(403);
        }

        return parent::callAction($method, $parameters);
    }

    /**
     * Save new rule
     *
     * @param Request $request
     * @param RuleContract $rule
     * @return array
     */
    public function store(Request $request, RuleContract $rule)
    {
        $request->validate([
            'guard_name'  => 'required|string|unique:'.$rule->getTable().',guard_name',
            'parent_id'   => 'nullable|integer',
            'options'     => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $rule = $rule::create(
            $request->only(['parent_id', 'guard_name', 'options', 'description'])
        );

        return [
            'success' => !!$rule->save(),
        ];
    }

    /**
     * Update rule info
     *
     * @param Request $request
     * @param RuleContract $rule
     * @return array
     */
    public function update(Request $request, RuleContract $rule)
    {
        $request->validate([
            'id'          => 'required|integer',
            'guard_name'  => 'required|string|unique:'.$rule->getTable().',guard_name,'.$request->id.',id',
            'parent_id'   => 'nullable|integer',
            'options'     => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $rule = $rule::findOrFail($request->id);

        $rule->update(
            $request->only(['parent_id', 'guard_name', 'options', 'description'])
        );

        return [
            'success' => !!$rule->save(),
        ];
    }

    /**
     * Soft-delete rule
     *
     * @param $id
     * @param RuleContract $rule
     * @return array
     */
    public function destroy($id, RuleContract $rule)
    {
        $rule = $rule::findOrFail($id);

        return [
            'success' => !!$rule->delete(),
        ];
    }
}
