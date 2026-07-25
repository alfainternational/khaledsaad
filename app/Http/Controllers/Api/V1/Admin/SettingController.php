<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Settings\SettingsConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $overrides = Setting::all()->keyBy('key');

        $groups = collect(SettingsConfig::catalog())->map(fn (array $group) => [
            'group' => $group['group'],
            'fields' => collect($group['fields'])->map(function (array $field) use ($overrides) {
                $override = $overrides->get($field['key']);
                $secret = $field['type'] === 'secret';
                $effective = $secret
                    ? null
                    : (Setting::get($field['key']) ?? (str_contains($field['key'], '.') ? config($field['key']) : null));

                return [
                    ...$field,
                    'is_overridden' => $override !== null && $override->value !== null,
                    'value' => $secret ? null : $effective,
                    'saved' => $secret && $override !== null && filled($override->value),
                ];
            })->all(),
        ])->values()->all();

        return response()->json(['data' => $groups]);
    }

    public function update(Request $request): JsonResponse
    {
        foreach (SettingsConfig::fields() as $field) {
            $key = $field['key'];
            $inputName = str_replace('.', '__', $key);

            if (! $request->exists($inputName)) {
                continue;
            }

            $input = $request->input($inputName);

            if ($field['type'] === 'bool') {
                Setting::put($key, $request->boolean($inputName), 'admin', 'bool');
            } elseif ($field['type'] === 'int') {
                blank($input)
                    ? Setting::where('key', $key)->delete()
                    : Setting::put($key, (int) $input, 'admin', 'int');
            } elseif ($field['type'] === 'secret') {
                if (filled($input)) {
                    Setting::put($key, $input, 'admin', 'secret');
                }
            } elseif (blank($input)) {
                Setting::where('key', $key)->delete();
            } else {
                Setting::put($key, $input, 'admin', 'string');
            }
        }

        return $this->index();
    }
}
