<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Report\MonthlyReportService;
use ProWay\Domain\Report\ReportPdfRenderer;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class ReportController
{
    public function __construct(
        private readonly MonthlyReportService $reports,
        private readonly ReportPdfRenderer    $renderer,
        private readonly AuthMiddleware       $middleware,
    ) {}

    /**
     * GET /api/v1/admin/reports — list all generated reports.
     */
    public function listReports(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $reports = $this->reports->listAll();
        Response::success(['reports' => $reports]);
    }

    /**
     * POST /api/v1/admin/reports/generate
     * Body: { client_id, year, month, recommendations? }
     */
    public function generate(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $clientId = (int) $request->input('client_id', 0);
        $year     = (int) $request->input('year', 0);
        $month    = (int) $request->input('month', 0);
        $recs     = $request->input('recommendations', '');

        if ($clientId <= 0) {
            Response::error('VALIDATION', 'client_id es requerido', 422);
        }
        if ($year < 2020 || $year > 2099) {
            Response::error('VALIDATION', 'year debe ser un a\u{00f1}o v\u{00e1}lido', 422);
        }
        if ($month < 1 || $month > 12) {
            Response::error('VALIDATION', 'month debe estar entre 1 y 12', 422);
        }

        try {
            // Gather report data
            $reportData = $this->reports->generateForClient($clientId, $year, $month);
            $reportData['recommendations'] = $recs;

            // Generate HTML and save to file
            $html = $this->renderer->render($reportData);

            $dir = $this->getReportsDir();
            $filename = sprintf('report_%d_%04d_%02d.html', $clientId, $year, $month);
            $filePath = $dir . '/' . $filename;
            file_put_contents($filePath, $html);

            $pdfPath = '/data/reports/' . $filename;

            // Save report record
            $id = $this->reports->save($clientId, $year, $month, $recs ?: null, $pdfPath);

            Response::success([
                'report_id' => $id,
                'pdf_path'  => $pdfPath,
                'message'   => 'Reporte generado exitosamente',
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            error_log('[ReportController] generate failed: ' . $e->getMessage());
            Response::error('SERVER_ERROR', 'Error al generar el reporte', 500);
        }
    }

    /**
     * GET /api/v1/reports/{id}/pdf — download/view the report PDF.
     * Both admin and client can access (client ownership checked).
     */
    public function downloadPdf(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);
        $reportId = (int) ($vars['id'] ?? 0);

        // Admin can view any report; client can only view their own
        if ($user->type === 'admin') {
            $report = $this->reports->getById($reportId);
        } else {
            $report = $this->reports->getForClient($user->id, $reportId);
        }

        if ($report === null) {
            Response::error('NOT_FOUND', 'Reporte no encontrado', 404);
        }

        $pdfPath = $report['pdf_path'] ?? '';
        if (empty($pdfPath)) {
            Response::error('NOT_FOUND', 'El archivo del reporte no existe', 404);
        }

        // Resolve the full file path
        $basePath = dirname(__DIR__, 4); // api/ root
        $fullPath = $basePath . $pdfPath;

        if (!file_exists($fullPath)) {
            // Try to regenerate on the fly
            try {
                $clientId = (int) $report['client_id'];
                $year     = (int) $report['year'];
                $month    = (int) $report['month'];
                $recs     = $report['recommendations'] ?? '';

                $reportData = $this->reports->generateForClient($clientId, $year, $month);
                $reportData['recommendations'] = $recs;
                $html = $this->renderer->render($reportData);
            } catch (\Throwable $e) {
                Response::error('NOT_FOUND', 'Archivo de reporte no encontrado', 404);
            }
        } else {
            $html = file_get_contents($fullPath);
        }

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: no-store');
        echo $html;
        exit;
    }

    /**
     * GET /api/v1/clients/me/reports — client sees their own reports.
     */
    public function clientReports(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);

        $reports = $this->reports->listForClient($user->id);
        Response::success(['reports' => $reports]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function getReportsDir(): string
    {
        $dir = dirname(__DIR__, 4) . '/data/reports';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
}
