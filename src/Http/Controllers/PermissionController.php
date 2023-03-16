<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Wnikk\LaravelAccessRules\Contracts\AccessRules as AccessRulesContract;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;
use Wnikk\LaravelAccessRules\Contracts\Permission as PermissionContract;
use Wnikk\LaravelAccessRules\Contracts\Rule as RuleContract;

class PermissionController extends Controller
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
     * @param array &$rules
     * @param array $permit
     * @param bool $inherit
     * @param bool|null $allow
     * @return void
     */
    private function addPermitToRules(array &$rules, array $permit, ?bool $inherit = false, ?bool $allow = null)
    {
        foreach ($permit as $item)
        {
            $key = crc32($item['option']);

            $rules[$item['rule_id']]['permission'][$key] = [
                'option'     => $item['option'],
                'permission' => !!($allow??$item['permission']??0),
                'inherit'    => $inherit,
            ];
        }
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
        if (!config('accessUi.grid_permission')){
            abort(403);
        }

        if (!$this->owner) {
            $this->setOwner(app(OwnerContract::class));
        }

        return parent::callAction($method, $parameters);
    }

    /**
     * Return all rule with permission
     *
     * @param Request $request
     * @param RuleContract $rule
     * @param AccessRulesContract $accessRules
     * @return array
     */
    public function index(Request $request, RuleContract $rule, AccessRulesContract $accessRules)
    {
        if (!$this->owner->getKey()) $this->setOwnerFromRequest($request);

        $accessRules->setOwner($this->owner);

        $permit  = $accessRules->getThisPermitMap();

        /** @var PermissionContract */
        $permission = $this->owner
            ->permission()
            ->get([
                'rule_id', 'permission', 'option'
            ])->toArray();

        $rules = $rule::all([
            'id', 'parent_id',
            'guard_name', 'options',
            'title', 'description'
        ])->keyBy('id')->toArray();

        foreach ($rules as &$item) $item['permission'] = [];
        unset($item);

        // Add inherit allow
        $this->addPermitToRules($rules, $permit['allow'], true, true);
        // Add inherit disallow
        $this->addPermitToRules($rules, $permit['disallow'], true, false);
        // Add direct assigned
        $this->addPermitToRules($rules, $permission, false);

        return [
            'success' => true,
            'list'    => array_values($rules),
        ];
    }

    /**
     * Update permission
     *
     * @param Request $request
     * @param RuleContract $rule
     * @param AccessRulesContract $accessRules
     * @return false[]
     */
    public function update(Request $request, RuleContract $rule, AccessRulesContract $accessRules)
    {
        $request->validate([
            'id'         => 'required|integer',
            'permission' => 'required|boolean',
            'option'     => 'nullable',
        ]);
        if (!$this->owner->getKey()) $this->setOwnerFromRequest($request);

        /** @var RuleContract */
        $rule = $rule::findOrFail($request->id);

        /** @var PermissionContract */
        $permission = $this->owner
            ->permission()
            ->where('rule_id', $request->id)
            ->where('option', $request->option)
            ->first();

        // Set with what owner we work
        $accessRules->setOwner($this->owner);

        $result = false;
        $cmd = '';
        // This owner already has a direct permission for him
        if (!is_null($permission)) {
            // The owner is allowed, but you need to prohibit

            if ($permission->permission === 1 && $request->permission === '0') {
                // By default, lack of permission is already a bans
                $cmd = 'remPermission';
                $result = $accessRules->remPermission($rule->guard_name, $request->option);
            }
            // With the prohibition, need remove and after permission is required add.
            if ($permission->permission === 0 && $request->permission === '1') {
                $cmd = 'remProhibition,addPermission';
                $result = $accessRules->remProhibition($rule->guard_name, $request->option);
                if ($result) $result = $accessRules->addPermission($rule->guard_name, $request->option);
            }
        } else {
            // Regardless of inheritance, installation and permission and prohibition is allowed.s
            if ($request->permission === '1') {
                $cmd = 'addPermission';
                $result = $accessRules->addPermission($rule->guard_name, $request->option);
            } else {
                $cmd = 'addProhibition';
                $result = $accessRules->addProhibition($rule->guard_name, $request->option);
            }
        }

        return [
            'success' => $result,
            'cmd'     => $cmd,
        ];
    }
}
