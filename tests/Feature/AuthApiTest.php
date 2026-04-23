<?php

namespace Tests\Feature;

use App\Notifications\Auth\RoleAwareResetPasswordNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use InteractsWithRoles;
    use RefreshDatabase;

    public function test_employee_can_login_through_employee_route(): void
    {
        $user = $this->createUserWithRoles(['employee'], [
            'email' => 'employee@example.com',
            'password' => 'Password@123',
        ]);

        $response = $this->postJson('/api/auth/employee/login', [
            'email' => 'employee@example.com',
            'password' => 'Password@123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('redirect_to', '/app/dashboard')
            ->assertJsonPath('user.id', $user->id);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'description' => 'User logged in',
            'event' => 'login',
            'causer_id' => $user->id,
        ]);
    }

    public function test_admin_can_login_through_admin_route(): void
    {
        $user = $this->createUserWithRoles(['admin'], [
            'email' => 'admin@example.com',
            'password' => 'Password@123',
        ]);

        $response = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'Password@123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('redirect_to', '/admin/dashboard')
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_user_is_redirected_to_the_area_matching_their_role_when_logging_into_the_wrong_portal(): void
    {
        $user = $this->createUserWithRoles(['admin'], [
            'email' => 'admin-only@example.com',
            'password' => 'Password@123',
        ]);

        $response = $this->postJson('/api/auth/employee/login', [
            'email' => 'admin-only@example.com',
            'password' => 'Password@123',
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('redirect_to', '/admin/dashboard')
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_authenticated_user_can_fetch_profile_and_logout(): void
    {
        $user = $this->createUserWithRoles(['admin', 'employee']);

        $this->actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('user.id', $user->id)
                ->has('user.roles', 2)
                ->where('user.roles.0', fn (string $role) => in_array($role, ['admin', 'employee'], true))
            );

        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'description' => 'User logged out',
            'event' => 'logout',
            'causer_id' => $user->id,
        ]);
    }

    public function test_employee_can_request_and_complete_password_reset(): void
    {
        Notification::fake();

        $user = $this->createUserWithRoles(['employee'], [
            'email' => 'employee-reset@example.com',
        ]);

        $this->postJson('/api/auth/employee/forgot-password', [
            'email' => $user->email,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'If the account exists in this area, a password reset link has been sent.');

        Notification::assertSentTo($user, RoleAwareResetPasswordNotification::class);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/employee/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword@123',
            'password_confirmation' => 'NewPassword@123',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Your password has been reset successfully. You can now sign in.');

        $this->postJson('/api/auth/employee/login', [
            'email' => $user->email,
            'password' => 'NewPassword@123',
        ])->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'password_reset_requested',
            'causer_id' => $user->id,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'password_reset_completed',
            'causer_id' => $user->id,
        ]);
    }

    public function test_admin_can_request_and_complete_password_reset(): void
    {
        Notification::fake();

        $user = $this->createUserWithRoles(['admin'], [
            'email' => 'admin-reset@example.com',
        ]);

        $this->postJson('/api/auth/admin/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertSentTo($user, RoleAwareResetPasswordNotification::class);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/admin/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'AdminPassword@123',
            'password_confirmation' => 'AdminPassword@123',
        ])
            ->assertOk();

        $this->postJson('/api/auth/admin/login', [
            'email' => $user->email,
            'password' => 'AdminPassword@123',
        ])->assertOk();
    }

    public function test_password_reset_is_restricted_to_the_requested_area(): void
    {
        Notification::fake();

        $admin = $this->createUserWithRoles(['admin'], [
            'email' => 'only-admin@example.com',
        ]);

        $this->postJson('/api/auth/employee/forgot-password', [
            'email' => $admin->email,
        ])->assertOk();

        Notification::assertNothingSent();

        $token = Password::broker()->createToken($admin);

        $this->postJson('/api/auth/employee/reset-password', [
            'email' => $admin->email,
            'token' => $token,
            'password' => 'WrongArea@123',
            'password_confirmation' => 'WrongArea@123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This password reset link is invalid or has expired.');
    }

    public function test_authenticated_user_can_view_and_mark_notifications_as_read(): void
    {
        $user = $this->createUserWithRoles(['employee']);

        DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\LoeReminderNotification',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => [
                'title' => 'Reminder',
                'message' => 'Submit your LOE before month end.',
            ],
        ]);

        DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\LoeSubmissionConfirmationNotification',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => [
                'title' => 'Saved',
                'message' => 'Your LOE was saved.',
            ],
            'read_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(2, 'notifications');

        $notificationId = $response->json('notifications.0.id');

        $this->postJson("/api/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('message', 'Notification marked as read.')
            ->assertJsonPath('unread_count', 0);

        $this->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('message', 'All notifications marked as read.')
            ->assertJsonPath('unread_count', 0);
    }
}
