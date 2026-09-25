<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * اختبارات استعادة كلمة المرور.
 *
 * مسارا التنفيذ (POST) لا يعتمدان على أي قالب واجهة، لذا يُختبر المنطق كاملًا
 * هنا. مسارا العرض (GET) يُختبران بحسب وجود القالب — وهما من نطاق الفرونت إند.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'customer@nadaf.store',
            'role' => 'customer',
        ]);
    }

    private function validResetRequest(string $token): array
    {
        return [
            'token' => $token,
            'email' => 'customer@nadaf.store',
            'password' => 'new-secret-1',
            'password_confirmation' => 'new-secret-1',
        ];
    }

    // ---------- طلب الرابط ----------

    public function test_reset_link_is_sent_to_a_registered_email(): void
    {
        Notification::fake();

        $user = $this->user();

        $this->post(route('password.email'), ['email' => 'customer@nadaf.store'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_unknown_email_gets_the_same_response_without_leaking(): void
    {
        Notification::fake();

        $this->user();

        $response = $this->post(route('password.email'), ['email' => 'nobody@nowhere.test']);

        // نفس الرد ونفس غياب الأخطاء — وإلا صار النموذج أداة لتعداد الحسابات
        $response->assertSessionHas('status');
        $response->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_email_is_normalised_before_lookup(): void
    {
        Notification::fake();

        $user = $this->user();

        $this->post(route('password.email'), ['email' => '  CUSTOMER@NADAF.STORE  ']);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_invalid_email_format_is_rejected(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    // ---------- تعيين كلمة المرور ----------

    public function test_valid_token_changes_the_password(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);

        $response = $this->post(route('password.store'), $this->validResetRequest($token));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $this->assertTrue(
            Hash::check('new-secret-1', $user->fresh()->password),
            'كلمة المرور الجديدة مخزّنة ومُشفّرة صحيحًا'
        );
    }

    public function test_new_password_works_for_login(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);

        $this->post(route('password.store'), $this->validResetRequest($token));

        $this->assertTrue(auth()->attempt([
            'email' => 'customer@nadaf.store',
            'password' => 'new-secret-1',
        ]));
    }

    public function test_invalid_token_is_rejected_and_password_unchanged(): void
    {
        $user = $this->user();
        $original = $user->password;

        $this->post(route('password.store'), $this->validResetRequest('not-a-real-token'))
            ->assertSessionHasErrors('email');

        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_token_cannot_be_reused(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);

        $this->post(route('password.store'), $this->validResetRequest($token))
            ->assertSessionHas('status');

        $this->post(route('password.store'), $this->validResetRequest($token))
            ->assertSessionHasErrors('email');
    }

    public function test_password_must_be_confirmed(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'customer@nadaf.store',
            'password' => 'new-secret-1',
            'password_confirmation' => 'something-else',
        ])->assertSessionHasErrors('password');
    }

    public function test_short_password_is_rejected(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'customer@nadaf.store',
            'password' => '12345',
            'password_confirmation' => '12345',
        ])->assertSessionHasErrors('password');
    }

    public function test_reset_does_not_work_for_another_users_email(): void
    {
        $victim = $this->user();
        $attacker = User::factory()->create(['email' => 'attacker@nadaf.store']);

        $token = Password::createToken($victim);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'attacker@nadaf.store',
            'password' => 'new-secret-1',
            'password_confirmation' => 'new-secret-1',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('new-secret-1', $victim->fresh()->password));
        $this->assertFalse(Hash::check('new-secret-1', $attacker->fresh()->password));
    }

    // ---------- المسارات ----------

    public function test_authenticated_user_is_redirected_away(): void
    {
        $this->actingAs($this->user())
            ->get(route('password.request'))
            ->assertRedirect();
    }

    public function test_request_form_degrades_gracefully_while_view_is_missing(): void
    {
        // القالب من نطاق الواجهة الأمامية: قبل وجوده يجب ألا يظهر خطأ 500
        $response = $this->get(route('password.request'));

        if (view()->exists('auth.forgot-password')) {
            $response->assertOk();

            return;
        }

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_reset_form_degrades_gracefully_while_view_is_missing(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);

        $response = $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]));

        if (view()->exists('auth.reset-password')) {
            $response->assertOk();

            return;
        }

        $response->assertRedirect(route('login'));
    }
}
