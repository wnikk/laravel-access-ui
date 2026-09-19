<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Searching the owner table when a list is too long to send inline.
 *
 * Three scopes, because the dialogs that open it ask three different questions:
 *
 *   assignable  what may be handed out as a source of rights — the configured `assignable` entities.
 *               This is the widget's dropdown, grown up.
 *   all         every owner row. What may *receive* rights is not a decision the configuration makes,
 *               so choosing who inherits from a role draws from the whole table.
 *   listed      what the owners and inheritance screens show: the configured types, plus anything
 *               holding a permission whatever its type.
 *
 * Nothing here reads a model belonging to the host application. The owner table is the only source, and
 * a row exists in it because the application already put one there.
 */
class PickerController extends BaseController
{
    /**
     * Left empty on purpose: this controller only reads, and each scope is limited to what the
     * configuration already exposes. Gating it as one screen would tie the assignment dialog to whether
     * the owners screen happens to be switched on.
     *
     * @var string
     */
    protected $screen = '';

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'scope'   => ['nullable', 'in:assignable,all,listed'],
            'exclude' => ['nullable', 'string', 'max:512'],
        ]);

        $params = $this->pageParams($request);
        $scope  = (string) $request->input('scope', 'assignable');

        if ($scope === 'all') {
            $query = $this->ui->allOwnerQuery();
        } elseif ($scope === 'listed') {
            $query = $this->ui->listedOwnerQuery();
        } else {
            $query = $this->ui->assignableOwnerQuery();
        }

        // Ids already linked, so a dialog does not offer what is on screen behind it.
        $exclude = $this->excluded($request);

        if ($exclude !== []) {
            $query->whereNotIn('id', $exclude);
        }

        return $this->ok('', $this->ui->searchOwners(
            $query,
            $params['search'],
            $params['page'],
            $params['per_page']
        ));
    }

    /**
     * Owner ids to leave out, given as a comma-separated list.
     *
     * Filtered to positive integers rather than trusted: this reaches a `whereNotIn`, and a list that
     * long has no business carrying anything else.
     *
     * @param Request $request
     * @return array<int, int>
     */
    protected function excluded(Request $request): array
    {
        $raw = (string) $request->input('exclude', '');

        if ($raw === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $raw) as $piece) {
            $piece = trim($piece);

            if ($piece !== '' && ctype_digit($piece) && (int) $piece > 0) {
                $ids[] = (int) $piece;
            }
        }

        return array_values(array_unique($ids));
    }
}
