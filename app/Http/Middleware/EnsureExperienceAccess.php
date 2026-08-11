<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Experience\Experience;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExperienceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $required = $this->requiredFor($request->route()?->getName());
        $user = $request->user();

        if ($required === null || ! $user instanceof User || $user->isAdmin() || $this->enabled($user, $required)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->apiDenied($required);
        }

        return redirect()->guest(route('app.experience.activate', $required->value));
    }

    private function requiredFor(?string $routeName): ?Experience
    {
        if ($routeName === null) {
            return null;
        }

        foreach (config('experiences.route_prefixes', []) as $value => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($routeName, $prefix)) {
                    return Experience::from($value);
                }
            }
        }

        return null;
    }

    private function enabled(User $user, Experience $experience): bool
    {
        return match ($experience) {
            Experience::BUSINESS => $user->hasBusinessExperience(),
            Experience::LEARNING => $user->hasLearningExperience(),
        };
    }

    private function apiDenied(Experience $required): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'experience_not_enabled',
                'message' => __('فعّل المسار المطلوب أولًا، ثم أعد المحاولة.'),
                'required_experience' => $required->value,
                'activation_url' => route('app.experience.activate', $required->value),
                'action' => 'activate_experience',
            ],
        ], 403);
    }
}
