<?php

namespace Tests\Feature;

use App\Filament\Resources\ChatQuestionResource\Pages\ListChatQuestions;
use App\Models\ChatQuestion;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * إخفاء الأسئلة المُجابة من قسم «الشات العائم».
 *
 * المطلوب: أي سؤال له جواب — أو تمّت الإجابة عليه — يُخفى كليًا. والقسم يبقى
 * لإدخال سؤال جديد فقط.
 *
 * و`answer` إلزامي في النموذج، فكل سؤال محفوظ له جواب بالضرورة ⇒ القائمة تبقى
 * فارغة بحكم البناء. والاختبار يقيس القاعدة لا الحالة: سؤال بلا جواب **يظهر**،
 * وسؤال بجواب **يختفي** — فلو غُيّر النموذج لاحقًا وجُعل الجواب اختياريًا بقي
 * السلوك صحيحًا.
 */
class ChatQuestionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function listPage()
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return Livewire::test(ListChatQuestions::class);
    }

    public function test_a_question_with_an_answer_is_hidden_from_the_list(): void
    {
        ChatQuestion::create([
            'question' => 'كم التوصيل؟',
            'answer' => 'حسب المحافظة',
            'is_active' => true,
        ]);

        $this->listPage()
            ->assertOk()
            ->assertDontSee('كم التوصيل؟', escape: false)
            ->assertDontSee('حسب المحافظة', escape: false);
    }

    public function test_a_question_without_an_answer_still_appears(): void
    {
        ChatQuestion::create([
            'question' => 'سؤال بانتظار جواب',
            'answer' => '',
            'is_active' => true,
        ]);

        $this->listPage()
            ->assertOk()
            ->assertSee('سؤال بانتظار جواب', escape: false);
    }

    /** الأسئلة المُجابة لا تظهر حتى بالبحث المباشر عنها */
    public function test_answered_questions_do_not_leak_through_search(): void
    {
        ChatQuestion::create(['question' => 'كلمة سرّية', 'answer' => 'جوابها', 'is_active' => true]);

        // لا نفحص نصّ السؤال نفسه: يبقى في خانة البحث فيظهر في الصفحة.
        // نفحص **الجواب** — وهو لا يظهر إلا في صفّ جدول حقيقي.
        $this->listPage()
            ->searchTable('كلمة سرّية')
            ->assertSee('لا أسئلة بانتظار جواب', escape: false)
            ->assertDontSee('جوابها', escape: false);
    }

    public function test_the_empty_state_explains_the_rule(): void
    {
        ChatQuestion::create(['question' => 'سؤال', 'answer' => 'جواب', 'is_active' => true]);

        $this->listPage()
            ->assertOk()
            ->assertSee('لا أسئلة بانتظار جواب', escape: false);
    }
}
