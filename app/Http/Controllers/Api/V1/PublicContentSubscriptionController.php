<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Content\ContentSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicContentSubscriptionController extends Controller
{
    public function __construct(private readonly ContentSubscriptionService $subscriptions) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'consent' => ['accepted'],
        ]);

        $result = $this->subscriptions->subscribe($data['email'], true);

        return response()->json(['data' => [
            'email' => $result['subscriber']->email,
            'access_token' => $result['token'],
        ]], 201);
    }
}
