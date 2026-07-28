# Diagnosis — حساب المحاور ودرجة النضج

**العقد:** يقرأ من قاعدة البيانات فقط. **ممنوع أي اتصال شبكي هنا** — يحرسه
`ArchitectureBoundariesTest::test_diagnosis_and_measurement_do_not_reach_the_network`.

السبب: الدرجة يجب أن تكون قابلة لإعادة الحساب من لقطة `brain_snapshots`.
استدعاء شبكي واحد يجعل درجتين بنفس المدخلات مختلفتين، فتنهار المقارنة الزمنية
وتصير التنبيهات ضوضاء — والتنبيه هو المخرج المتكرر الوحيد.

**المخرجات:** `axis_score` · `axis_coverage` · `maturity_score` (بأسماء
`App\Modules\Shared\Metrics\MetricKey` حرفيًا).

**يُبنى في المرحلة ١.** الجمع الخارجي مسؤولية `AiReadiness` و`OwnedAssets`،
وهذه الوحدة تقرأ ما كتبوه في `Brain`.
