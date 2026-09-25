<?php

namespace Tests\Feature;

// الصفحة الرئيسية تستعلم جدول التصنيفات عبر وسيط SetLocale،
// لذا يجب تهيئة قاعدة البيانات قبل أي طلب HTTP.
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
