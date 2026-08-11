<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Experience\Experience;
use App\Support\Experience\ExperienceNotEnabled;
use App\Support\Experience\ExperiencePayload;
use App\Support\Experience\ExperienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExperienceController extends Controller
{
    public function __construct(
        private readonly ExperienceService $experiences,
        private readonly ExperiencePayload $payload,
    ) {}

    public function activate(Request $request, string $experience): JsonResponse
    {
        $required = $this->experience($experience);
        $user = $this->experiences->activate($request->user(), $required);
        $user = $this->experiences->switch($user, $required);

        return response()->json(['data' => $this->payload->for($user)]);
    }

    public function switch(Request $request, string $experience): JsonResponse
    {
        $required = $this->experience($experience);

        try {
            $user = $this->experiences->switch($request->user(), $required);
        } catch (ExperienceNotEnabled) {
            return response()->json([
                'error' => [
                    'code' => 'experience_not_enabled',
                    'message' => __('فعّل المسار المطلوب أولًا، ثم أعد المحاولة.'),
                    'required_experience' => $required->value,
                    'action' => 'activate_experience',
                ],
            ], 403);
        }

        return response()->json(['data' => $this->payload->for($user)]);
    }

    private function experience(string $value): Experience
    {
        $experience = Experience::tryFrom($value);
        abort_if($experience === null, 422, __('قيمة المسار غير صالحة.'));

        return $experience;
    }

}
