<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', Password::min(8)],
            'device_name' => 'required|string|max:120',
        ]);

        $user = User::create($data);

        return response()->json($this->payload($user, $data['device_name']), 201);
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
            throw ValidationException::withMessages(['email' => 'بيانات الدخول غير صحيحة.']);
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

        return response()->json(['data' => ['message' => 'تم تسجيل الخروج.']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user, string $deviceName): array
    {
        // مفتاح المزود لا يغادر الخادم إطلاقًا. التطبيق يحمل رمز مستخدم فقط.
        return [
            'data' => [
                'user' => $this->user($user),
                'token' => $user->createToken($deviceName)->plainTextToken,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function user(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
    }
}
