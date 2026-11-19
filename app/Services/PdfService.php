<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PlanAndReport;
use App\Models\Setting;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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

    public function executive(array $data): string
    {
        return $this->render('pdf.executive', array_merge($data, [
            'institution' => Setting::institutionName(),
        ]));
    }

    private function render(string $view, array $data): string
    {
        $temp = storage_path('app/mpdf');
        File::ensureDirectoryExists($temp);

        $defaultConfig = (new \Mpdf\Config\ConfigVariables)->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables)->getDefaults();
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
