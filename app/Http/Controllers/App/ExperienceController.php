<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Support\Experience\Experience;
use App\Support\Experience\ExperienceNotEnabled;
use App\Support\Experience\ExperienceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExperienceController extends Controller
{
    public function __construct(private readonly ExperienceService $experiences) {}

    public function choose(Request $request): View
    {
        return view('app.experience.choose', [
            'user' => $request->user(),
            'enabledExperiences' => $this->experiences->enabled($request->user()),
        ]);
    }

    public function select(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'experience' => ['required', Rule::enum(Experience::class)],
        ]);
        $experience = Experience::from($data['experience']);
        $user = $request->user();

        if ($user->initial_experience === null) {
            $this->experiences->selectInitial($user, $experience);
        }

        return redirect()->to($this->destination($request->user()->fresh()));
    }

    public function activation(Request $request, string $experience): View
    {
        $required = Experience::tryFrom($experience);
        abort_if($required === null, 404);

        return view('app.experience.activate', [
            'experience' => $required,
            'alreadyEnabled' => in_array($required->value, $this->experiences->enabled($request->user()), true),
        ]);
    }

    public function enable(Request $request, string $experience): RedirectResponse
    {
        $required = Experience::tryFrom($experience);
        abort_if($required === null, 404);

        $user = $this->experiences->activate($request->user(), $required);
        $this->experiences->switch($user, $required);

        return redirect()->intended($this->destination($user->fresh()))
            ->with('status', __('تم تفعيل المسار دون إنشاء حساب أو مساحة عمل جديدة.'));
    }

    public function switch(Request $request, string $experience): RedirectResponse
    {
        $required = Experience::tryFrom($experience);
        abort_if($required === null, 404);

        try {
            $user = $this->experiences->switch($request->user(), $required);
        } catch (ExperienceNotEnabled) {
            return redirect()->route('app.experience.activate', $required->value);
        }

        return redirect()->to($this->destination($user))
            ->with('status', __('غيّرنا ما تعمل عليه الآن، وكل بياناتك ما زالت في مكانها.'));
    }

    private function destination(\App\Models\User $user): string
    {
        if ($user->activeExperience() === Experience::LEARNING) {
            return route('app.learning.marketing.home');
        }

        return $user->workspaces()->whereHas('projects')->exists()
            ? route('app.dashboard')
            : route('app.projects.create');
    }
}
