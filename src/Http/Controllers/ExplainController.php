<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Wnikk\LaravelAccessRules\AccessRules;
use Wnikk\LaravelAccessRules\Contracts\AccessManager;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;

/**
 * "Why?": the explanation of one check, the way acr:explain prints it.
 *
 * The record is named as "alias:id", as "alias" for records of that kind in general, or left out.
 * The alias comes from config access.resources, so the class of the record is the core's to
 * name; this controller loads one row by id and passes it on.
 *
 * The owner is the subject of the check. When its type is a model class, that model is loaded
 * for the check, because conditions read "user." attributes from it. That is the one place the
 * panel touches a model of the application, and it is the row the owner stands for.
 */
class ExplainController extends BaseController
{
    protected string $screen = 'explain';

    public function index(Request $request, AccessManager $access): JsonResponse
    {
        $data = $request->validate([
            'owner'   => ['required', 'integer', 'min:1'],
            'ability' => ['required', 'string', 'max:255'],
            'record'  => ['nullable', 'string', 'max:255'],
        ]);

        $record  = $this->owner($data['owner']);
        $subject = $this->subjectOf($record) ?? $record;

        return $this->ok('', $access->for($subject)->explain($data['ability'], $this->recordOf($data['record'] ?? null)));
    }

    /**
     * @return Model|class-string<Model>|null
     */
    private function recordOf(?string $named): Model|string|null
    {
        if ($named === null || $named === '') {
            return null;
        }

        [$alias, $id] = array_pad(explode(':', $named, 2), 2, null);
        $definition   = config('access.resources.'.$alias);
        $class        = is_array($definition) ? ($definition['model'] ?? null) : $definition;

        abort_unless(is_string($class) && is_subclass_of($class, Model::class), 422, 'Unknown resource "'.$alias.'". List it in config access.resources.');

        if ($id === null || $id === '') {
            return $class;
        }

        return $class::query()->findOrFail($id);
    }

    private function subjectOf(OwnerContract $owner): ?Model
    {
        $class = AccessRules::getListTypes()[(int) $owner->type] ?? null;

        return is_string($class) && is_subclass_of($class, Model::class) ? $class::query()->find($owner->original_id) : null;
    }
}
