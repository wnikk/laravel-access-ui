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
                'title', 'description',
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
        if (!config('accessUi.grid_rules')) {
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
            'title'       => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $rule = $rule::create(
            $request->only(['parent_id', 'guard_name', 'options', 'title', 'description'])
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
        $request->merge(['id' => $request->route('id')]);
        $request->validate([
            'id'          => 'required|integer',
            'guard_name'  => 'required|string|unique:'.$rule->getTable().',guard_name,'.$request->id.',id',
            'parent_id'   => 'nullable|integer',
            'options'     => 'nullable|string',
            'title'       => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $rule = $rule::findOrFail($request->id);

        $rule->update(
            $request->only(['parent_id', 'guard_name', 'options', 'title', 'description'])
        );

        return [
            'success' => !!$rule->save(),
        ];
    }

    /**
     * Soft-delete rule
     *
     * @param Request $request
     * @param RuleContract $rule
     * @return array
     */
    public function destroy(Request $request, RuleContract $rule)
    {
        $request->merge(['id' => $request->route('id')]);
        $request->validate([
            'id' => 'required|integer',
        ]);

        $rule = $rule::findOrFail($request->id);

        return [
            'success' => !!$rule->delete(),
        ];
    }
}
