<?php

namespace App\Modules\Reporting;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/**
 * محرك الطباعة العربية المشترك.
 *
 * mPDF لأنه الوحيد الذي يطبّق تشكيل الحروف العربية واتجاه RTL فعليًا؛ dompdf
 * يرسم المحارف منفصلة ومعكوسة.
 *
 * استُخرج من `ReportPdfGenerator` ليستعمله كل مخرج مطبوع. الإعداد أدناه كلّف
 * وقتًا حتى استقرّ — نسخُه في كل مولّد يعني أن يُصلح خللٌ في مكان ويبقى في
 * البقية، فيخرج مخرج بخط مختلف عن أخيه بلا سبب ظاهر.
 */
class ArabicPdfEngine
{
    /**
     * @param  string  $footerNote  وسم المخرج في التذييل («موجز وكالة»، «تقريرك الخاص»…)
     * @param  int  $marginTop  الهامش العلوي؛ يختلف باختلاف كثافة رأس القالب
     */
    public function make(string $footerNote = '', int $marginTop = 15): Mpdf
    {
        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new FontVariables)->getDefaults()['fontdata'];

        $tempDir = storage_path('app/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => 'rtl',
            'mirrorMargins' => true,
            /*
             * إيقاف التبديل التلقائي للخط حاسم: mPDF كان يستبدل خط المنصة
             * بخط عربي مدمج عنده (XBRiyaz) فور رؤيته نصًا عربيًا، فيخرج
             * الملف بخط لا يشبه الموقع. الخط الآن هو ملف الموقع نفسه.
             */
            'autoScriptToLang' => false,
            'autoLangToFont' => false,
            'tempDir' => $tempDir,
            'fontDir' => [...$fontDirs, public_path('assets/fonts')],
            'fontdata' => $fontData + [
                /*
                 * useOTL كامل ضروري لوصل الحروف العربية: بدونه تُرسم منفصلة.
                 * ملاحظة: هذا الملف لا يحمل جدول GPOS لتموضع الحركات، فعلامات
                 * التشكيل الاختيارية (ضمة/كسرة) قد تُزاح قليلًا — والمتصفح
                 * يخفيها بتموضع احتياطي لا يملكه mPDF. النص بلا تشكيل سليم تمامًا.
                 */
                'hacentunisia' => [
                    'R' => 'Hacen-Tunisia.ttf',
                    'useOTL' => 0xFF,
                ],
            ],
            'default_font' => 'hacentunisia',
            'margin_top' => $marginTop,
            'margin_bottom' => 18,
            'margin_left' => 13,
            'margin_right' => 13,
        ]);

        $mpdf->SetDirectionality('rtl');

        $logo = str_replace('\\', '/', public_path('assets/brand/khaled-saad-light.png'));
        $siteUrl = rtrim((string) config('app.url'), '/');

        /*
         * هذا الرأس هو إطار الهوية المشترك لكل ملف يولده الموقع. يوضع في
         * المحرك نفسه حتى لا يستطيع قالب تقرير جديد أن يخرج بلا الشعار أو
         * ألوان المنصة أو عنوانها، وحتى تبقى الهوية موحدة في كل الصفحات.
         */
        $header = '<div style="padding:5px 8px 7px;border-bottom:3px solid #2575ff;background:#071f5b;">'
            .'<table width="100%" cellspacing="0" cellpadding="0"><tr>'
            .'<td width="55%" style="color:#dce8ff;font-size:8.5pt;text-align:right;">'.e($footerNote ?: config('brand.tagline')).'</td>'
            .'<td width="45%" style="text-align:left;"><img src="'.e($logo).'" style="width:96px;"></td>'
            .'</tr></table></div>';

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLHeader($header, 'E');

        // فاصل واحد لكل المخرجات: كانت التقارير تستعمل شرطة والموجزات نقطة،
        // فيبدو ملفان من المنصة نفسها كأنهما من نظامين.
        $parts = array_filter([e(config('brand.name', 'خالد سعد')), e($footerNote), e($siteUrl)]);
        $parts[] = 'صفحة {PAGENO} من {nbpg}';

        $mpdf->SetHTMLFooter(
            '<div style="border-top:3px solid #2575ff;padding-top:6px;font-size:8pt;color:#5d6b82;text-align:center;">'
            .implode(' · ', $parts).'</div>'
        );

        return $mpdf;
    }
}
