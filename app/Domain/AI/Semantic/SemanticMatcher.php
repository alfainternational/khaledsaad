<?php

namespace App\Domain\AI\Semantic;

/**
 * عقد الفهم الدلالي: هل يعبّر نصّ عن مفهوم؟ وما قوّة التعبير؟ وما تشابه نصّين؟
 *
 * التطبيق الحالي محلي (معجمي + صرفي)، لكن العقد يسمح باستبداله لاحقاً بمحرّك
 * تضمينات (embeddings) عصبي دون لمس المستهلكين — طبقة الفهم قابلة للترقية.
 */
interface SemanticMatcher
{
    /** هل يعبّر النص عن المفهوم؟ (عتبة قوّة افتراضية). */
    public function expresses(string $text, string $conceptKey): bool;

    /** قوّة تعبير النص عن المفهوم: 0.0 (لا شيء) … 1.0 (صريح). */
    public function strength(string $text, string $conceptKey): float;

    /** تشابه دلالي بين نصّين: 0.0 … 1.0. */
    public function similarity(string $textA, string $textB): float;
}
