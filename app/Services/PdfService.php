<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PlanAndReport;
use App\Models\Setting;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

class PdfService
{
    public function report(PlanAndReport $plan): string
    {
        $plan->loadMissing(['user', 'department', 'kpiIndicator']);
        $url = $plan->verification_hash ? route('documents.verify', $plan->verification_hash) : null;

        return $this->render('pdf.report', [
            'plan' => $plan,
            'qr' => $url ? $this->qrDataUri($url) : null,
            'verifyUrl' => $url,
            'institution' => Setting::institutionName(),
        ]);
    }

    public function payslip(Payroll $payroll): string
    {
        $payroll->loadMissing(['user', 'department', 'payer']);
        $url = $payroll->verification_hash ? route('documents.verify', $payroll->verification_hash) : null;

        return $this->render('pdf.payslip', [
            'payroll' => $payroll,
            'qr' => $url ? $this->qrDataUri($url) : null,
            'verifyUrl' => $url,
            'institution' => Setting::institutionName(),
        ]);
    }

    /**
     * @return array{hash: string, snapshot: array{budget_year: int, paid_total: float, rows: array<int, array{department: string, score: float, payroll: float}>}}
     */
    public function executiveDocument(array $data): array
    {
        $rows = collect($data['rows'] ?? [])->map(function ($row) {
            $row = (array) $row;

            return [
                'department' => (string) ($row['department'] ?? ''),
                'score' => round((float) ($row['score'] ?? 0), 2),
                'payroll' => round((float) ($row['payroll'] ?? 0), 2),
            ];
        })->values()->all();

        $snapshot = [
            'budget_year' => (int) ($data['budgetYear'] ?? 0),
            'paid_total' => round((float) ($data['paidTotal'] ?? 0), 2),
            'rows' => $rows,
        ];

        return [
            'hash' => $this->verificationHash(
                'executive',
                $snapshot['budget_year'],
                json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            ),
            'snapshot' => $snapshot,
        ];
    }

    public function executive(array $data, ?string $hash = null): string
    {
        $hash ??= $this->executiveDocument($data)['hash'];
        $url = route('documents.verify', $hash);

        return $this->render('pdf.executive', array_merge($data, [
            'institution' => Setting::institutionName(),
            'qr' => $this->qrDataUri($url),
            'verifyUrl' => $url,
            'verificationHash' => $hash,
        ]));
    }

    private function render(string $view, array $data): string
    {
        $temp = storage_path('app/mpdf');
        File::ensureDirectoryExists($temp);

        $defaultConfig = (new ConfigVariables)->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new FontVariables)->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $temp,
            'fontDir' => array_merge($fontDirs, [resource_path('fonts')]),
            'fontdata' => $fontData + [
                'notosansethiopic' => [
                    'R' => 'NotoSansEthiopic-Regular.ttf',
                    'B' => 'NotoSansEthiopic-Bold.ttf',
                ],
            ],
            'default_font' => 'notosansethiopic',
        ]);

        $mpdf->WriteHTML(view($view, $data)->render());

        return $mpdf->Output('', 'S');
    }

    public function qrDataUri(string $contents): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'outputBase64' => true,
            'scale' => 4,
        ]);

        return (new QRCode($options))->render($contents);
    }

    public function verificationHash(string $type, int $id, string $discriminator): string
    {
        return hash_hmac('sha256', $type.'|'.$id.'|'.$discriminator, (string) config('app.key'));
    }

    public function storeQrFile(string $contents): string
    {
        $temp = storage_path('app/mpdf');
        File::ensureDirectoryExists($temp);
        $binary = (new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'outputBase64' => false,
            'scale' => 4,
        ])))->render($contents);
        $path = $temp.'/qr-'.Str::uuid().'.png';
        file_put_contents($path, $binary);

        return $path;
    }
}
