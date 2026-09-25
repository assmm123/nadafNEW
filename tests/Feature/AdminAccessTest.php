<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * من يدخل لوحة الإدارة — وماذا يرى من لا يدخلها.
 *
 * ── العلّة التي يمنعها هذا الملف ──
 * كان العميل الذي يفتح `/admin` يرى صفحة **403 Forbidden** الإنجليزية الجافّة:
 * لا سبب ولا مخرج. والسلوك نفسه صحيح (العميل لا يدخل اللوحة)، لكن الصفحة كانت
 * تُوهم بعطل — وقد ظُنّت عطلًا فعلًا. ومجلد `errors` كان يحوي 404 و413 و419
 * و500 و503 **ولا يحوي 403**، فسقطت على صفحة Laravel الافتراضية.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_open_the_panel(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get('/admin')
            ->assertOk();
    }

    public function test_a_customer_is_refused_the_panel(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get('/admin')
            ->assertForbidden();
    }

    /**
     * والمهمّ: الصفحة تشرح السبب وتعطي مخرجًا — لا «Forbidden» مجرّدة.
     */
    public function test_the_refusal_page_explains_the_reason_and_offers_a_way_out(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'customer']))->get('/admin');

        $response->assertForbidden()
            ->assertSee('403', escape: false)
            ->assertSee('حساب عميل', escape: false)
            ->assertSee('تسجيل الخروج', escape: false)
            ->assertDontSee('Forbidden', escape: false);
    }

    /** والمخرج حقيقي: زرّ الخروج يشير إلى مسار الخروج فعلًا */
    public function test_the_way_out_is_a_real_logout_route(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get('/admin')
            ->assertSee(route('logout'), escape: false);
    }

    public function test_a_blocked_staff_member_is_refused(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager', 'status' => 'blocked']))
            ->get('/admin')
            ->assertForbidden();
    }
}
