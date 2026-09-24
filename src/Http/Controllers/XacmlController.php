<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Wnikk\LaravelAccessRules\Xacml\Xacml;

/**
 * XACML 3.0 through the browser: download the export, look at what a document would change,
 * then import it. The entry class of the core takes an upload and a stream, so each action is a
 * few lines; the size of an upload is the one thing bounded here, the core refuses DOCTYPE and
 * never reads the network by itself.
 */
class XacmlController extends BaseController
{
    protected string $screen = 'xacml';

    protected array $reading = ['export', 'check'];

    /**
     * Written as it is produced, one owner at a time, so a large export does not become a large memory.
     */
    public function export(Xacml $xacml): StreamedResponse
    {
        return response()->streamDownload(
            static fn () => $xacml->export('php://output'),
            'access-rules-'.now()->format('Y-m-d-His').'.xml',
            ['Content-Type' => 'application/xml'],
        );
    }

    /**
     * The plan: every rule, owner, permission and link the document speaks about, marked create,
     * same, differs or only_in_database. Nothing is written.
     */
    public function check(Request $request, Xacml $xacml): JsonResponse
    {
        return $this->ok('', $xacml->check($this->upload($request), options: $this->options($request)));
    }

    /**
     * The same plan, executed. "replace" also brings what differs to the document, "partial"
     * writes what converts although something does not: both are choices the screen asks for
     * with their warnings, and both stay false unless the request says otherwise.
     */
    public function import(Request $request, Xacml $xacml): JsonResponse
    {
        $report = $xacml->import($this->upload($request), options: $this->options($request));

        return $this->ok($report['written'] ? __('Imported') : __('Nothing was written'), $report);
    }

    private function upload(Request $request): UploadedFile
    {
        $request->validate([
            'policy' => ['required', 'file', 'max:'.max(1, (int) $this->ui->config('xacml.max_upload_kb', 10240))],
        ]);

        return $request->file('policy');
    }

    /**
     * @return array{replace:bool, partial:bool, subject_type?:string, role_type?:string, everyone?:string}
     */
    private function options(Request $request): array
    {
        $data = $request->validate([
            'replace'      => ['nullable', 'boolean'],
            'partial'      => ['nullable', 'boolean'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'role_type'    => ['nullable', 'string', 'max:255'],
            'everyone'     => ['nullable', 'string', 'max:255'],
        ]);

        return array_filter([
            'replace'      => (bool) ($data['replace'] ?? false),
            'partial'      => (bool) ($data['partial'] ?? false),
            'subject_type' => $data['subject_type'] ?? null,
            'role_type'    => $data['role_type'] ?? null,
            'everyone'     => $data['everyone'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
