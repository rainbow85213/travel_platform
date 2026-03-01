<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        return $this->success($request->user());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
        ]);

        $request->user()->update($data);

        return $this->success($request->user()->fresh(), '프로필이 수정되었습니다.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($request->current_password, $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['현재 비밀번호가 올바르지 않습니다.'],
            ]);
        }

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return $this->success(message: '비밀번호가 변경되었습니다.');
    }

    public function showPreferences(Request $request): JsonResponse
    {
        $preference = $request->user()->preference
            ?? $request->user()->preference()->create([]);

        return $this->success($preference);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'language'             => ['sometimes', 'string', 'max:10'],
            'currency'             => ['sometimes', 'string', 'max:10'],
            'timezone'             => ['sometimes', 'string', 'max:50'],
            'notification_enabled' => ['sometimes', 'boolean'],
        ]);

        $preference = $request->user()->preference()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        return $this->success($preference, '설정이 저장되었습니다.');
    }
}
