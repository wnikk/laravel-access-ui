<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Wnikk\LaravelAccessUi\AccessUi;

/**
 * The one HTML response of the package: the shell the interface mounts into, inside the layout of
 * the host or as a standalone page. The page carries no data beyond the bootstrap payload, so it
 * is dull by design and cheap to cache. Everything after it is JSON.
 */
class PanelController extends Controller
{
    public function __construct(private readonly AccessUi $ui) {}

    public function index(): View
    {
        $layout = $this->ui->config('layout.view');
        $inside = is_string($layout) && $layout !== '';

        return view($inside ? 'accessUi::embedded' : 'accessUi::standalone', [
            'layout'    => $layout,
            'section'   => (string) $this->ui->config('layout.section', 'content'),
            'title'     => __((string) $this->ui->config('layout.title', 'Access control')),
            'assets'    => $this->ui->assetUrls(),
            'bootstrap' => $this->ui->bootstrapPayload(),
        ]);
    }
}
