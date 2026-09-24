<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;
use Wnikk\LaravelAccessRules\Exceptions\AccessRulesException;
use Wnikk\LaravelAccessRules\Exceptions\InvalidConditionException;
use Wnikk\LaravelAccessRules\Exceptions\UntranslatableConditionException;
use Wnikk\LaravelAccessUi\AccessUi;
use Wnikk\LaravelAccessUi\Support\Errors;

/**
 * What every endpoint of the panel shares: one response shape, one way to name an owner, one
 * place that decides whether the request may change anything, and one translation of a refusal
 * of the core into that shape.
 *
 *   success  { ok: true,  message: string, data: mixed|null, reload: true|string|null }
 *   failure  { ok: false, message: string, code: string, errors: object }
 *
 * The 422 body of Laravel itself has the same shape, so $request->validate() is used as usual.
 */
abstract class BaseController extends Controller
{
    /** Screen this controller belongs to; drives the enabled and writable checks. */
    protected string $screen = '';

    /** Methods that read. Everything else needs the screen to be writable. */
    protected array $reading = ['index', 'show'];

    public function __construct(protected readonly AccessUi $ui) {}

    /**
     * Every action is refused when its screen is off, and every writing action when the screen is
     * read-only. Checked here so that adding a method cannot add an unguarded one. Refusals of the
     * core are translated here too, once, instead of a try in every writer.
     */
    public function callAction($method, $parameters): mixed
    {
        if ($this->screen !== '') {
            $this->ui->authorizeRead($this->screen);

            if (! in_array($method, $this->reading, true)) {
                $this->ui->authorizeWrite($this->screen);
            }
        }

        try {
            return parent::callAction($method, $parameters);
        } catch (AccessRulesException|InvalidConditionException|UntranslatableConditionException $e) {
            return $this->refused($e);
        }
    }

    protected function owner(int|string $id): OwnerContract
    {
        return $this->ui->findOwnerOrFail($id);
    }

    /**
     * Page, page size and search term of a paged list.
     *
     * @return array{page:int, per_page:int, search:string}
     */
    protected function pageParams(Request $request): array
    {
        $data = $request->validate([
            'page'   => ['nullable', 'integer', 'min:1'],
            'limit'  => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:128'],
        ]);

        $perPage = (int) $this->ui->config('picker.per_page', 15);

        return [
            'page'     => (int) ($data['page'] ?? 1),
            'per_page' => (int) ($data['limit'] ?? ($perPage > 0 ? $perPage : 15)),
            'search'   => trim((string) ($data['search'] ?? '')),
        ];
    }

    /**
     * @param bool|string|null $reload true reloads the page, a string goes there
     */
    protected function ok(string $message = '', mixed $data = null, bool|string|null $reload = null): JsonResponse
    {
        return response()->json(['ok' => true, 'message' => $message, 'data' => $data, 'reload' => $reload]);
    }

    protected function err(string $message, array $errors = [], int $status = 422, string $code = 'ui', mixed $data = null): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message, 'code' => $code, 'errors' => $errors, 'data' => $data], $status);
    }

    /**
     * A refusal of the core as the interface shows it: the translated meaning of its code first,
     * the words of the core second.
     */
    protected function refused(Throwable $e, mixed $data = null): JsonResponse
    {
        $error = Errors::describe($e);

        return $this->err(__('accessUi::errors.'.$error['code']), ['core' => [$error['message']]], $error['status'], $error['code'], $data);
    }
}
