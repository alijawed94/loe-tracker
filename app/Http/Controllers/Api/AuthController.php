<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\Auth\RoleAwareResetPasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AuthController extends Controller
{
    public function employeeLogin(LoginRequest $request): JsonResponse
    {
        return $this->login($request, 'employee', '/app/dashboard');
    }

    public function adminLogin(LoginRequest $request): JsonResponse
    {
        return $this->login($request, 'admin', '/admin/dashboard');
    }

    public function employeeForgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        return $this->sendResetLink($request, 'employee');
    }

    public function adminForgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        return $this->sendResetLink($request, 'admin');
    }

    public function employeeResetPassword(ResetPasswordRequest $request): JsonResponse
    {
        return $this->resetPassword($request, 'employee');
    }

    public function adminResetPassword(ResetPasswordRequest $request): JsonResponse
    {
        return $this->resetPassword($request, 'admin');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()?->load('roles');

        return response()->json([
            'user' => $user ? $this->serializeUser($user) : null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($user) {
            activity('auth')
                ->causedBy($user)
                ->performedOn($user)
                ->withProperties(['action' => 'logout'])
                ->event('logout')
                ->log('User logged out');
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    protected function login(LoginRequest $request, string $expectedRole, string $redirectPath): JsonResponse
    {
        $credentials = $request->validated();
        $remember = (bool) ($credentials['remember'] ?? false);

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'status' => true,
        ], $remember)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect or the account is inactive.',
            ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user = $request->user()->load('roles');

        if (! $user->hasRole($expectedRole)) {
            activity('auth')
                ->causedBy($user)
                ->performedOn($user)
                ->withProperties([
                    'requested_area' => $expectedRole,
                    'redirect_to' => $user->hasRole('admin') ? '/admin/dashboard' : '/app/dashboard',
                ])
                ->event('redirected')
                ->log('User attempted login in unauthorized portal');

            return response()->json([
                'message' => 'This account does not have access to the requested area.',
                'redirect_to' => $user->hasRole('admin') ? '/admin/dashboard' : '/app/dashboard',
                'user' => $this->serializeUser($user),
            ], HttpResponse::HTTP_CONFLICT);
        }

        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(['area' => $expectedRole])
            ->event('login')
            ->log('User logged in');

        return response()->json([
            'message' => 'Login successful.',
            'redirect_to' => $redirectPath,
            'user' => $this->serializeUser($user),
        ]);
    }

    protected function sendResetLink(ForgotPasswordRequest $request, string $role): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->validated('email'))
            ->where('status', true)
            ->with('roles')
            ->first();

        if ($user && $user->hasRole($role)) {
            $token = Password::broker()->createToken($user);
            $user->notify(new RoleAwareResetPasswordNotification($token, $role));

            activity('auth')
                ->causedBy($user)
                ->performedOn($user)
                ->withProperties(['area' => $role])
                ->event('password_reset_requested')
                ->log('User requested a password reset link');
        }

        return response()->json([
            'message' => 'If the account exists in this area, a password reset link has been sent.',
        ]);
    }

    protected function resetPassword(ResetPasswordRequest $request, string $role): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::query()
            ->where('email', $credentials['email'])
            ->where('status', true)
            ->with('roles')
            ->first();

        if (! $user || ! $user->hasRole($role)) {
            return response()->json([
                'message' => 'This password reset link is invalid or has expired.',
            ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = Password::broker()->reset(
            $credentials,
            function (User $user, string $password) use ($role) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                activity('auth')
                    ->causedBy($user)
                    ->performedOn($user)
                    ->withProperties(['area' => $role])
                    ->event('password_reset_completed')
                    ->log('User reset their password');
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'This password reset link is invalid or has expired.',
            ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Your password has been reset successfully. You can now sign in.',
        ]);
    }

    protected function serializeUser($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'employee_code' => $user->employee_code,
            'designation' => $user->designation,
            'stream' => $user->stream,
            'timezone' => $user->timezone,
            'status' => $user->status,
            'roles' => $user->roles->pluck('name')->values(),
        ];
    }
}
