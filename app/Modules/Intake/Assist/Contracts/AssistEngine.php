<?php

namespace App\Modules\Intake\Assist\Contracts;

use App\Modules\Intake\Assist\AssistDraft;
use App\Modules\Intake\Assist\QuestionDescriptor;

/**
 * توليد دليل ومقترحات لسؤال واحد في سياق مشروع واحد.
 *
 * خلف عقد كبقية المزوّدات (§٨)، ولسبب إضافي: جودة المقترح العربي تتفاوت بشدّة
 * بين النماذج، وتبديل المزوّد قرار جودة يُتخذ بعد القياس. وأهم من ذلك أن العقد
 * يجعل الاختبار يمرّ بلا شبكة — ولو كان الاستدعاء مباشرًا لصار كل اختبار للواجهة
 * اختبارًا لمزوّد خارجي.
 */
interface AssistEngine
{
    /**
     * @param  array<string, mixed>  $context  سياق المشروع كما بناه `AssistContextBuilder`.
     */
    public function compose(QuestionDescriptor $question, array $context): AssistDraft;

    public function name(): string;
}
