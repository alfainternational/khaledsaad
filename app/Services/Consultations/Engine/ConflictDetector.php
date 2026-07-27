<?php

namespace App\Services\Consultations\Engine;

use App\Models\ConsultationConflict;
use App\Models\ConsultationSession;
use App\Models\ProjectAnswer;

class ConflictDetector
{
    public function refresh(ConsultationSession $session): void
    {
        $known = ProjectAnswer::where('project_id', $session->project_id)
            ->get()
            ->mapWithKeys(fn (ProjectAnswer $answer) => [$answer->field_key => data_get($answer->value_json, 'value')]);

        $this->sync($session, 'stage_vs_sales',
            in_array($known->get('actual_stage'), ['فكرة', 'idea', 'قبل الإطلاق', 'launch'], true)
                && (float) ($known->get('monthly_sales') ?? $known->get('sales_count') ?? 0) > 0,
            'المرحلة المسجلة لا تتوافق مع وجود مبيعات فعلية. حدّد إن كانت المبيعات تجريبية أو صحّح المرحلة.',
            ['actual_stage', 'monthly_sales'], 'high');

        $tracking = $known->get('tracking_maturity') ?? $known->get('analytics_setup');
        $cac = $known->get('cac') ?? $known->get('customer_acquisition_cost');
        $this->sync($session, 'cac_without_tracking',
            filled($cac) && in_array($tracking, [null, '', 'لا يوجد', 'none', 'غير مفعل'], true),
            'ذُكرت تكلفة اكتساب عميل دون نظام قياس يسمح بإثباتها.',
            ['tracking_maturity', 'cac'], 'high');

        $revenue = $known->get('revenue') ?? $known->get('monthly_revenue');
        $profit = $known->get('profit') ?? $known->get('monthly_profit');
        $this->sync($session, 'profit_exceeds_revenue',
            is_numeric($revenue) && is_numeric($profit) && (float) $profit > (float) $revenue,
            'الربح المسجل أكبر من الإيراد؛ راجع تعريف الرقمين والفترة الزمنية.',
            ['revenue', 'profit'], 'high');
    }

    /** @param array<int,string> $subject */
    private function sync(ConsultationSession $session, string $key, bool $active, string $message, array $subject, string $severity): void
    {
        $conflict = ConsultationConflict::firstOrNew([
            'consultation_session_id' => $session->id,
            'key' => $key,
        ]);

        if ($active) {
            if ($conflict->status !== 'resolved') {
                $conflict->fill(compact('message', 'subject', 'severity') + ['status' => 'open'])->save();
            }
        } elseif ($conflict->exists && $conflict->status === 'open') {
            $conflict->delete();
        }
    }
}
