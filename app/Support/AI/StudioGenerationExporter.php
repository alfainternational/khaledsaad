<?php

namespace App\Support\AI;

use App\Domain\AI\Models\AIGeneration;
use Illuminate\Support\Str;

/**
 * Markdown / HTML / PDF helpers for studio generation downloads.
 */
final class StudioGenerationExporter
{
    public static function markdownBody(AIGeneration $generation): string
    {
        $lines = [
            '# '.($generation->template?->name ?? 'ملف الاستوديو'),
            '',
            '- المشروع: '.($generation->project?->name ?? '—'),
            '- التاريخ: '.($generation->created_at?->format('Y-m-d H:i') ?? '—'),
            '',
            '---',
            '',
            trim((string) ($generation->output ?? '')),
        ];

        return implode("\n", $lines);
    }

    public static function printableHtml(AIGeneration $generation): string
    {
        $title = e($generation->template?->name ?? 'ملف الاستوديو');
        $sections = StudioMarkdownSections::split($generation->output ?? '');
        $outlineLinks = '';
        $blocks = '';

        foreach ($sections as $index => $section) {
            $sectionId = 'section-'.($index + 1);
            $sectionTitle = trim((string) ($section['title'] ?? ''));
            $body = StudioContentRenderer::render((string) ($section['body'] ?? ''));

            if ($sectionTitle !== '') {
                $outlineLinks .= '<a href="#'.$sectionId.'">'.e($sectionTitle).'</a>';
            }

            $blocks .= '<section class="studio-export-section" id="'.$sectionId.'">';
            if ($sectionTitle !== '') {
                $blocks .= '<div class="studio-export-section-head"><span>'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'</span><h2>'.e($sectionTitle).'</h2></div>';
            }
            $blocks .= '<div class="studio-rich-text">'.$body.'</div></section>';
        }

        $outline = $outlineLinks !== ''
            ? '<nav class="studio-export-outline"><h3>محتويات الملف</h3><div class="studio-export-outline-links">'.$outlineLinks.'</div></nav>'
            : '';

        return '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>'.$title.'</title>'
            .'<style>'
            .self::printableStyles()
            .'</style></head><body>'
            .'<main class="studio-export-page">'
            .'<section class="studio-export-hero">'
            .'<div><p class="studio-export-kicker">ملف من الاستوديو الذكي</p><h1>'.$title.'</h1><p class="studio-export-lead">ملف مرتب جاهز للمشاركة والمراجعة والطباعة.</p></div>'
            .'<div class="studio-export-meta">'
            .'<div><strong>المشروع</strong><span>'.e($generation->project?->name ?? 'بدون مشروع').'</span></div>'
            .'<div><strong>التاريخ</strong><span>'.e($generation->created_at?->format('Y-m-d H:i') ?? '—').'</span></div>'
            .'<div><strong>نوع الملف</strong><span>'.e($generation->template?->name ?? 'ملف الاستوديو').'</span></div>'
            .'</div></section>'
            .$outline
            .'<section class="studio-export-content">'.$blocks.'</section>'
            .'</main>'
            .'</body></html>';
    }

    public static function suggestedFilename(AIGeneration $generation, string $extension): string
    {
        $code = $generation->template?->code ?? 'output';
        $slug = Str::slug($code);
        if ($slug === '') {
            $slug = 'output';
        }

        return 'studio-'.$generation->public_id.'-'.$slug.'.'.$extension;
    }

    private static function printableStyles(): string
    {
        return <<<'CSS'
body{margin:0;background:#f3f4f6;color:#111827;font-family:DejaVu Sans Condensed,sans-serif;line-height:1.85}
.studio-export-page{max-width:960px;margin:0 auto;padding:28px}
.studio-export-hero{background:linear-gradient(135deg,#111827,#1f2937 60%,#374151);color:#fff;border-radius:24px;padding:28px 30px;display:flex;gap:24px;justify-content:space-between;align-items:flex-start;margin-bottom:20px}
.studio-export-kicker{margin:0 0 8px;font-size:12px;letter-spacing:.18em;text-transform:uppercase;opacity:.75}
.studio-export-hero h1{margin:0 0 10px;font-size:28px;line-height:1.3}
.studio-export-lead{margin:0;color:rgba(255,255,255,.8);font-size:14px}
.studio-export-meta{min-width:240px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:18px;padding:12px 14px}
.studio-export-meta div{padding:8px 0;border-bottom:1px solid rgba(255,255,255,.1)}
.studio-export-meta div:last-child{border-bottom:none}
.studio-export-meta strong{display:block;font-size:11px;letter-spacing:.08em;text-transform:uppercase;opacity:.7;margin-bottom:4px}
.studio-export-meta span{font-size:14px}
.studio-export-outline{background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:18px 20px;margin-bottom:20px}
.studio-export-outline h3{margin:0 0 12px;font-size:16px}
.studio-export-outline-links a{display:inline-block;margin:0 0 8px 8px;padding:8px 12px;border-radius:999px;background:#eef2ff;color:#3730a3;text-decoration:none;font-size:13px}
.studio-export-section{background:#fff;border:1px solid #e5e7eb;border-radius:22px;padding:20px 22px;margin-bottom:16px;box-shadow:0 10px 24px rgba(17,24,39,.04)}
.studio-export-section-head{display:flex;align-items:center;gap:12px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #e5e7eb}
.studio-export-section-head span{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:999px;background:#111827;color:#fff;font-size:12px;font-weight:700}
.studio-export-section-head h2{margin:0;font-size:19px;line-height:1.5}
.studio-rich-text p{margin:0 0 12px;font-size:14px;color:#1f2937}
.studio-rich-text p:last-child{margin-bottom:0}
.studio-rich-subheading{margin:18px 0 10px;font-size:15px;color:#111827}
.studio-rich-list{margin:0 0 14px;padding:0 22px 0 0}
.studio-rich-list li{margin-bottom:8px}
.studio-rich-quote{margin:0 0 14px;padding:14px 16px;border-right:4px solid #4f46e5;background:#eef2ff;color:#312e81;border-radius:14px}
.studio-rich-table-wrap{overflow:hidden;border:1px solid #e5e7eb;border-radius:16px;margin:0 0 14px}
.studio-rich-table{width:100%;border-collapse:collapse;font-size:13px}
.studio-rich-table th{background:#f9fafb;color:#111827;text-align:right;padding:10px 12px;border-bottom:1px solid #e5e7eb}
.studio-rich-table td{padding:10px 12px;border-bottom:1px solid #f1f5f9;vertical-align:top}
.studio-rich-table tr:last-child td{border-bottom:none}
.studio-rich-text code{background:#f3f4f6;border-radius:8px;padding:2px 6px;font-size:12px}
CSS;
    }
}
