<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Core\View;

/**
 * تصدير المستندات إلى PDF عبر مكتبة mPDF (تبعية composer تُثبَّت في حاوية الويب).
 *
 * القوالب تُبنى من `app/Views` كما في الشاشات (`View::capture`)، فلا يُكرَّر منطق البيانات.
 * العربية مدعومة بالخط المدمج `xbriyaz` مع الاتجاه RTL والإعدادات autoScriptToLang/autoLangToFont.
 */
final class PdfService
{
    /** @param array<string,mixed> $data */
    public static function download(string $template, array $data, string $filename): never
    {
        $autoload = BASE_PATH . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new \RuntimeException('مكتبة PDF غير مثبّتة على الخادم (نفّذ composer install).');
        }
        require_once $autoload;

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'directionality' => 'rtl',
            'default_font' => 'xbriyaz',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 16,
            'tempDir' => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle((string) ($data['title'] ?? ''));
        $mpdf->SetHTMLFooter('<div style="text-align:center; font-size:8pt; color:#666666;">صفحة {PAGENO} من {nbpg}</div>');
        $mpdf->WriteHTML(View::capture($template, $data));
        $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);

        exit;
    }
}
