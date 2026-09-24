<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Searching the owner table when a list is too long to send inline. Three scopes for the three
 * questions the dialogs ask: "assignable" is what may be handed out as a source of rights,
 * "all" is every owner (who may receive rights is not a decision of the configuration),
 * "listed" is what the owners screen shows.
 */
class PickerController extends BaseController
{
    /**
     * Left empty on purpose: this controller only reads, and each scope is limited to what the
     * configuration exposes already. Tying it to one screen would tie the assignment dialog to
     * whether the owners screen happens to be on.
     */
    protected string $screen = '';

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'scope'   => ['nullable', 'in:assignable,all,listed'],
            'exclude' => ['nullable', 'string', 'max:512'],
        ]);

        $params = $this->pageParams($request);

        $query = match ((string) $request->input('scope', 'assignable')) {
            'all'    => $this->ui->ownerQuery(),
            'listed' => $this->ui->listedOwnerQuery(),
            default  => $this->ui->assignableOwnerQuery(),
        };

        // Ids already on the screen behind the dialog, given as "1,2,3". Only positive integers reach the query.
        $exclude = array_values(array_unique(array_map('intval', array_filter(
            explode(',', (string) $request->input('exclude', '')),
            static fn (string $piece): bool => ctype_digit(trim($piece)) && (int) $piece > 0,
        ))));

        if ($exclude !== []) {
            $query->whereNotIn('id', $exclude);
        }

        return $this->ok('', $this->ui->searchOwners($query, $params['search'], $params['page'], $params['per_page']));
    }
}
