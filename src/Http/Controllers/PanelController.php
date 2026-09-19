<?php

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View;
use Wnikk\LaravelAccessUi\AccessUi;

/**
 * The one HTML response in the package.
 *
 * Renders the shell the interface mounts into, either inside the host's layout or as a standalone
 * page when no layout is named. Everything after that is JSON: the page carries no data beyond the
 * bootstrap payload, so it is dull by design and cheap to cache.
 */
class PanelController extends Controller
{
    /** @var AccessUi */
    protected $ui;

    /**
     * @param AccessUi $ui
     */
    public function __construct(AccessUi $ui)
    {
        $this->ui = $ui;
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        $layout = $this->ui->config('layout.view');
        $inside = is_string($layout) && $layout !== '';

        return View::make($inside ? 'accessUi::embedded' : 'accessUi::standalone', [
            'layout'    => $layout,
            'section'   => (string) $this->ui->config('layout.section', 'content'),
            'title'     => __((string) $this->ui->config('layout.title', 'Access control')),
            'assets'    => $this->ui->assetUrls(),
            'bootstrap' => $this->ui->bootstrapPayload(),
        ]);
    }
}
