<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Guests\GuestSessionManager;
use App\Support\Experience\Experience;
use App\Support\Experience\ExperienceService;
use App\Support\Experience\ExperiencePayload;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly GuestSessionManager $guests,
        private readonly ExperienceService $experiences,
        private readonly ExperiencePayload $experiencePayload,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', PasswordRule::min(8)],
            'device_name' => 'required|string|max:120',
            'guest_token' => 'nullable|string|size:48',
            'experience' => ['nullable', Rule::enum(Experience::class)],
        ]);

        $guest = $this->guests->currentForApi($data['guest_token'] ?? null);
        $claimedRun = $guest?->runs()->latest('id')->first();
        $user = User::create($data);
        $experience = Experience::from($data['experience'] ?? Experience::BUSINESS->value);
        $user = $this->experiences->selectInitial($user, $experience);

        event(new Registered($user));

        if ($guest !== null) {
            $this->guests->claim($guest, $user);
        }

        $user->primaryWorkspace();

        return response()->json(
            $this->payload($user, $data['device_name'], $claimedRun?->uuid),
            201,
        );
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'required|string|max:120',
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('بيانات الدخول غير صحيحة.')]);
        }

        return response()->json($this->payload($user, $data['device_name']));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->user($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['message' => __('تم تسجيل الخروج.')]]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email']);

        Password::sendResetLink(['email' => $data['email']]);

        return response()->json([
            'data' => [
                'message' => __('إذا كان البريد مسجلًا، فستصلك رسالة تحتوي على رابط لتغيير كلمة المرور.'),
            ],
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __('انتهت صلاحية الرابط أو استُخدم من قبل. اطلب رابطًا جديدًا.'),
            ]);
        }

        return response()->json([
            'data' => [
                'message' => __('تغيّرت كلمة المرور. يمكنك تسجيل الدخول الآن.'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user, string $deviceName, ?string $claimedRunUuid = null): array
    {
        // مفتاح المزود لا يغادر الخادم إطلاقًا. التطبيق يحمل رمز مستخدم فقط.
        return [
            'data' => [
                'user' => $this->user($user),
                'token' => $user->createToken($deviceName)->plainTextToken,
                'claimed_run_uuid' => $claimedRunUuid,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->isAdmin(),
            'locale' => app()->getLocale(),
            ...$this->experiencePayload->for($user),
        ];
    }
}
