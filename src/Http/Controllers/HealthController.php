<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Wnikk\LaravelAccessRules\Administration\Doctor;
use Wnikk\LaravelAccessRules\Administration\Linter;
use Wnikk\LaravelAccessRules\Contracts\AccessManager;

/**
 * What acr:lint and acr:doctor find, as data, and the one cache button.
 *
 * Conditions are checked when they are saved, then a migration renames a column or a model leaves
 * config, and nothing says so until a user loses access. The linter reads every stored rule,
 * permission and link against the models of today; "fix" saves again what only changed its
 * column types. The doctor reads the four tables against each other: rows the core would never
 * have written, from SQL typed by hand or a partial restore; its fix deletes duplicates and
 * orphans and leaves a loop to a person.
 *
 * The cache follows every change made through the core by itself. The flush is for changes made
 * past it, straight in the tables.
 */
class HealthController extends BaseController
{
    protected string $screen = 'health';

    public function index(Linter $linter, Doctor $doctor): JsonResponse
    {
        return $this->ok('', $linter->run() + ['rows' => $doctor->run()['problems'], 'write' => $this->ui->screenWritable('health')]);
    }

    public function fix(Linter $linter): JsonResponse
    {
        $report = $linter->run(fix: true);

        return $this->ok(__(':count condition(s) saved again', ['count' => $report['fixed']]), $report);
    }

    public function doctor(Doctor $doctor): JsonResponse
    {
        $report = $doctor->run(fix: true);

        return $this->ok(__(':count row(s) deleted', ['count' => $report['fixed']]), $report);
    }

    public function flush(AccessManager $access): JsonResponse
    {
        $access->flush();

        return $this->ok(__('Cached permissions cleared'));
    }
}
