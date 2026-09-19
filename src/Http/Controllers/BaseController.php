<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;
use Wnikk\LaravelAccessUi\AccessUi;

/**
 * What every endpoint in the panel shares: one response shape, one way to name an owner, and one
 * place that decides whether the request is allowed to change anything.
 *
 * The response envelope exists so the bundled JavaScript never has to know which endpoint it called:
 *
 *   success → { ok: true,  message: string, data: mixed|null, reload: true|string|null }
 *   failure → { ok: false, message: string, errors: object }
 *
 * Laravel's own 422 body ({ message, errors }) is the same shape as the failure case, which is why
 * `$request->validate()` is used as usual and needs no wrapping.
 */
abstract class BaseController extends Controller
{
    /** @var AccessUi */
    protected $ui;

    /** Screen this controller belongs to; drives the enabled/writable checks. @var string */
    protected $screen = '';

    /**
     * @param AccessUi $ui
     */
    public function __construct(AccessUi $ui)
    {
        $this->ui = $ui;
    }

    /**
     * Every action on a controller is refused when its screen is switched off, and every action
     * other than reading is refused when the screen is read-only.
     *
     * Checked here rather than in each method so that adding a method cannot accidentally add an
     * unguarded one. The markup hides the same controls, but the endpoint is what has to be sure.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function callAction($method, $parameters)
    {
        if ($this->screen !== '') {
            $this->ui->authorizeRead($this->screen);

            if ($method !== 'index') {
                $this->ui->authorizeWrite($this->screen);
            }
        }

        return parent::callAction($method, $parameters);
    }

    // =================================================================
    // Owners
    // =================================================================

    /**
     * The owner a route is about, by its own id.
     *
     * Owner ids and nothing else. A host page that wants the widget for one of its users gets the id
     * from `$user->getOwner()->id` — the trait access-rules asks you to add anyway — which keeps this
     * package free of any knowledge about how the application stores people.
     *
     * @param int|string $id
     * @return OwnerContract|\Illuminate\Database\Eloquent\Model
     */
    protected function owner($id)
    {
        return $this->ui->findOwnerOrFail($id);
    }

    // =================================================================
    // Request helpers
    // =================================================================

    /**
     * Page, page size and search term for a paged list.
     *
     * @param Request $request
     * @return array{page: int, per_page: int, search: string}
     */
    protected function pageParams(Request $request): array
    {
        $perPage = (int) $this->ui->config('picker.per_page', 15);

        $data = $request->validate([
            'page'   => ['nullable', 'integer', 'min:1'],
            'limit'  => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:128'],
        ]);

        return [
            'page'     => isset($data['page']) ? (int) $data['page'] : 1,
            'per_page' => isset($data['limit']) ? (int) $data['limit'] : ($perPage > 0 ? $perPage : 15),
            'search'   => isset($data['search']) ? trim($data['search']) : '',
        ];
    }

    // =================================================================
    // Response envelope
    // =================================================================

    /**
     * @param string $message
     * @param mixed $data
     * @param bool|string|null $reload true = reload the page, string = go there
     * @return JsonResponse
     */
    protected function ok(string $message = '', $data = null, $reload = null): JsonResponse
    {
        return response()->json([
            'ok'      => true,
            'message' => $message,
            'data'    => $data,
            'reload'  => $reload,
        ]);
    }

    /**
     * @param string $message
     * @param array $errors
     * @param int $status
     * @return JsonResponse
     */
    protected function err(string $message, array $errors = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'ok'      => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}
