<?php
/**
 * Diagnostic LLM Handler (Z.AI Integrated)
 * مسئول عن معالجة النصوص الطبيعية وسرد التقارير باستخدام Z.AI (GLM-4)
 */

class DiagnosticLLM {
    
    private $apiKey;
    private $model = 'GLM-4.5-air'; // موديل متوازن وسريع جداً
    private $apiEndpoint = 'https://api.z.ai/api/coding/paas/v4/chat/completions';

    public function __construct($apiKey = null) {
        // نستخدم المفتاح الممرر أو جلب المفتاح من الثوابت (يُفضل وضعه في config.php لاحقاً)
        $this->apiKey = $apiKey ?: (defined('ZAI_API_KEY') ? ZAI_API_KEY : '');
    }

    /**
     * توليد سرد نصي للتقرير (Narrative Report)
     */
    public function generateNarrative($analysisData, $clientProfile, $customPrompt = null) {
        if (empty($this->apiKey)) {
            return $this->fallbackNarrative($analysisData);
        }

        $prompt = $customPrompt ?: $this->buildPrompt($analysisData, $clientProfile);
        
        try {
            $response = $this->callZAI($prompt);
            if (strpos($response, 'Insufficient balance') !== false) {
                 return "خطأ: المعالج الفني غير متوفر حالياً. يرجى مراجعة الإعدادات.";
            }
            return $response;
        } catch (Exception $e) {
            error_log("Z.AI Support Error: " . $e->getMessage());
            return $this->fallbackNarrative($analysisData);
        }
    }

    private function buildPrompt($data, $profile) {
        $name = $profile['full_name'] ?? 'عميل';
        $company = $profile['company_name'] ?? 'منشأة';
        $industry = $profile['industry'] ?? 'غير محدد';
        $size = $profile['company_size'] ?? 'غير محدد';
        $score = $data['overall'] ?? 0;
        
        // تجهيز ملخص الإجابات
        $answersSummary = "";
        if (isset($profile['answers']) && is_array($profile['answers'])) {
            foreach ($profile['answers'] as $ans) {
                $answersSummary .= "- {$ans['q']}: الدرجة {$ans['score']}%\n";
            }
        }

        return "
        أنت استشاري نمو أعمال وخبير استراتيجي متخصص في السوق العربي والخليجي 2026.
        المطلوب: كتابة خلاصة استراتيجية (Executive Narrative) لعميل بناءً على بياناته التالية:
        الاسم: {$name}
        المنشأة: {$company}
        القطاع: {$industry}
        حجم الفريق: {$size}
        الدرجة الكلية للنضج: {$score}%
        
        ملخص التقييم التفصيلي:
        {$answersSummary}
        
        التعليمات:
        1. اكتب سردًا نصيًا ملهمًا ومحترفًا باللغة العربية.
        2. اربط النتائج بواقع السوق العربي (تحديات المنافسة، التحول الرقمي، مواسم التسوق، الأنظمة المحلية).
        3. كن دقيقاً في التوصية بناءً على الدرجة؛ إذا كانت منخفضة كن حازماً، وإذا كانت عالية كن طموحاً.
        4. الخلاصة يجب أن تكون فقرة واحدة مكثفة مركزة على 'الخطوة القادمة' (The Next Big Move).
        ";
    }

    private function callZAI($prompt) {
        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'أنت استشاري نمو أعمال خبير. تتحدث بمهنية واحترافية عالية باللغة العربية.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7
        ];

        $ch = curl_init($this->apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        // ضبط مهلة زمنية (Timeout)
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception("CURL Error: " . $error_msg);
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Z.AI API Response Error: HTTP $httpCode - " . $response);
            return $this->fallbackNarrative($payload);
        }

        $result = json_decode($response, true);
        
        return $result['choices'][0]['message']['content'] ?? $this->fallbackNarrative([]);
    }

    private function fallbackNarrative($data) {
        // نص افتراضي في حال فشل الاتصال بالذكاء الاصطناعي
        return "بناءً على تحليل بيانات منشأتك، نوصي بالتركيز الفوري على سد فجوات الأتمتة وتعزيز قنوات النمو الرقمي لضمان استمرارية الربحية وتجنب فرص الهدر المكتشفة.";
    }
}
