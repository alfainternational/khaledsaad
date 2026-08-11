<?php

namespace App\Modules\Insights\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * صف تجميع يومي واحد: (يوم × بُعد × قيمة).
 *
 * البُعد نصّ لا جدول: «قناة» و«صفحة» و«جهاز» و«بلد» أبعاد تُضاف بلا
 * هجرة. وكل صف هنا قابل لإعادة البناء من الصفوف الخام بأمر واحد.
 */
class VisitorDailyStat extends Model
{
    protected $fillable = [
        'stat_date', 'dimension', 'value',
        'visitors', 'sessions', 'page_views', 'bounces', 'conversions', 'active_seconds',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'visitors' => 'integer',
            'sessions' => 'integer',
            'page_views' => 'integer',
            'bounces' => 'integer',
            'conversions' => 'integer',
            'active_seconds' => 'integer',
        ];
    }

    /** أسماء الأبعاد المجمَّعة — تُقرأ من مكان واحد لا تُكتب نصًّا في كل استعلام. */
    public const DIMENSIONS = [
        'total' => 'الإجمالي',
        'channel' => 'القناة',
        'platform' => 'المنصة',
        'path' => 'الصفحة',
        'device' => 'الجهاز',
        'country' => 'البلد',
        'campaign' => 'الحملة',
        'referrer' => 'المُحيل',
    ];
}
