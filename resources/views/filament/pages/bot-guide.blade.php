<x-filament-panels::page>
    @php
        // صفوف الأوامر تُرسم من مصفوفة واحدة — لا تكرار ماركب
        $botCommands = [
            'stock' => [
                'title' => '📦 أوامر المخزون والجرد',
                'rows' => [
                    ['cmd' => 'جرد', 'desc' => 'قائمة المخزون كاملة مرتبة من الأقل للكثير (15 صنفًا بالصفحة)'],
                    ['cmd' => 'جرد 2', 'desc' => 'الصفحة الثانية من الجرد'],
                    ['cmd' => 'منخفض', 'desc' => 'الأصناف التي بلغت حد التنبيه — مع اقتراح الشراء'],
                    ['cmd' => 'نفد', 'desc' => 'الأصناف التي رصيدها صفر'],
                ],
            ],
            'sales' => [
                'title' => '💰 أوامر المبيعات والطلبات',
                'rows' => [
                    ['cmd' => 'مبيعات', 'desc' => 'مبيعات اليوم'],
                    ['cmd' => 'مبيعات أسبوع', 'desc' => 'أو «مبيعات شهر» — تقرير الفترة'],
                    ['cmd' => 'طلبات', 'desc' => 'آخر 5 طلبات بحالاتها'],
                ],
            ],
            'edit' => [
                'title' => '✏️ التعديل الآمن — بنظام المسودة',
                'accent' => true,
                'note' => 'كل تعديل يمر بمسودة تأكيد، وتعديل الكمية يُوثق في سجل حركات المخزون تلقائيًا',
                'rows' => [
                    ['cmd' => 'كمية 25 LEA05-BRN-L', 'desc' => 'مسودة: «كمية هذا المتغير من 4 إلى 25» — ينتظر تأكيدك'],
                    ['cmd' => 'سعر 12.5 CRV-01', 'desc' => 'مسودة تعديل سعر المنتج — ⚠️ السعر للمنتج فيُطبَّق على كل متغيراته'],
                    ['cmd' => 'تأكيد', 'desc' => 'يطبق التعديل — أو «إلغاء» للتراجع'],
                ],
                'footnote' => '⏱ المسودة تنتهي بعد 10 دقائق دون تأكيد — ومحفوظة في قاعدة البيانات فلا تضيع بإغلاق المحادثة',
            ],
            'search' => [
                'title' => '🔍 البحث',
                'rows' => [
                    ['cmd' => 'كرافات', 'desc' => 'ابحث بالاسم — يعرض المخزون والسعر والSKU لكل متغير'],
                    ['cmd' => 'LEA05', 'desc' => 'أو ابحث بالكود الداخلي / SKU'],
                ],
            ],
            'slash' => [
                'title' => '⌨️ أوامر الشرطة (بديل إنجليزي)',
                'rows' => [
                    ['cmd' => '/help', 'desc' => 'قائمة الأوامر — أو «مساعدة» أو «بدء»'],
                    ['cmd' => '/stock', 'desc' => 'الجرد — بديل «جرد»'],
                    ['cmd' => '/low · /out', 'desc' => 'المنخفض والنافد'],
                    ['cmd' => '/sales', 'desc' => 'المبيعات — بديل «مبيعات»'],
                    ['cmd' => '/find كود', 'desc' => 'البحث — بديل كتابة الكود مباشرة'],
                    ['cmd' => '/orders', 'desc' => 'آخر الطلبات — بديل «طلبات»'],
                ],
            ],
        ];

        $chatRules = [
            'يُرحَّب بالزائر برسالة ترحيب + حتى 4 اقتراحات من الأسئلة المفعلة (حسب الترتيب).',
            'المطابقة أولًا بالكلمات المفتاحية: أطول كلمة مطابقة تفوز — ثم تشابه نص السؤال بعتبة 68%.',
            'سؤال بلا جواب → تظهر وسائل التواصل فورًا.',
            'نية صريحة (تواصل / مدير / أسعار) → تظهر وسائل التواصل بعد الجواب الجاهز.',
            'تكرار السؤال وحده لا يُظهر وسائل التواصل — فالتكرار ليس دليل حاجة إلى بشر.',
            'كل رسالة تُسجل في «سجل المحادثات» مع الجواب المقدم وهل وجد جوابًا.',
            'الرسائل المُجاب عنها تختفي من قائمة العمل افتراضيًا — وتظهر عند اختيار «تمت الإجابة» أو «الكل».',
        ];
    @endphp

    <div class="mx-auto max-w-3xl space-y-6" wire:key="bot-guide">

        {{-- الحالة التشغيلية --}}
        <div class="rounded-2xl border p-5" style="background:var(--adm-surface);border-color:var(--adm-border)">
            <h2 class="mb-3 font-extrabold" style="color:var(--adm-txt)">🔌 حالة الاتصال</h2>
            <div class="grid gap-2.5 text-sm">
                <div class="flex items-center justify-between rounded-lg px-3 py-2" style="background:var(--adm-surface2)">
                    <span style="color:var(--adm-txt2)">توكن البوت التفاعلي (الأوامر)</span>
                    <span class="font-bold" style="color:{{ $tokenExists ? 'var(--adm-green)' : 'var(--adm-red)' }}">{{ $tokenExists ? '✓ موجود' : '✗ غير موجود — أضفه من الإعدادات' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-lg px-3 py-2" style="background:var(--adm-surface2)">
                    <span style="color:var(--adm-txt2)">توكن الإشعارات (الطلبات والمخزون)</span>
                    <span class="font-bold" style="color:{{ $notifyTokenExists ? 'var(--adm-green)' : 'var(--adm-yellow)' }}">{{ $notifyTokenExists ? '✓ موجود' : 'غير موجود' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-lg px-3 py-2" style="background:var(--adm-surface2)">
                    <span style="color:var(--adm-txt2)">الاستقصاء (استقبال أوامرك)</span>
                    <span class="font-bold" style="color:{{ $pollingEnabled ? 'var(--adm-green)' : 'var(--adm-yellow)' }}">{{ $pollingEnabled ? '✓ مفعّل' : 'معطّل — فعّله من الإعدادات' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-lg px-3 py-2" style="background:var(--adm-surface2)">
                    <span style="color:var(--adm-txt2)">chat_id المدير (حماية الأوامر)</span>
                    <span class="font-bold" style="color:{{ $adminChatId ? 'var(--adm-green)' : 'var(--adm-yellow)' }}">{{ $adminChatId ?: 'غير محدد — الأوامر مرفوضة حتى تُضبط' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-lg px-3 py-2" style="background:var(--adm-surface2)">
                    <span style="color:var(--adm-txt2)">التحقق من شهادة SSL</span>
                    <span class="font-bold" style="color:var(--adm-txt2)">{{ $verifySsl ? 'مفعّل (آمن)' : 'معطّل — للشبكات التي تعترض الشهادات فقط' }}</span>
                </div>
                <div class="flex items-center justify-between rounded-lg px-3 py-2" style="background:var(--adm-surface2)">
                    <span style="color:var(--adm-txt2)">التقارير المجدولة (9:00 و 21:00)</span>
                    <span class="font-bold" style="color:{{ $scheduledReports ? 'var(--adm-green)' : 'var(--adm-txt3)' }}">{{ $scheduledReports ? '✓ مفعّلة' : 'معطّلة' }}</span>
                </div>
                @if ($botUsername)
                    <div class="flex items-center justify-between rounded-lg px-3 py-2" style="background:var(--adm-accent-bg);border:1px solid var(--adm-accent-bd)">
                        <span style="color:var(--adm-txt2)">افتح البوت في تيليجرام:</span>
                        <a href="https://t.me/{{ $botUsername }}" target="_blank" rel="noopener" class="font-extrabold hover:underline" style="color:var(--adm-accent-hover)">@{{ $botUsername }} ←</a>
                    </div>
                @endif
            </div>
        </div>

        {{-- النبض الحي — يربط الدليل بقسم الدردشة الموحد --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border p-4" style="background:var(--adm-surface);border-color:var(--adm-border)">
                <div class="text-2xl font-bold" style="color:var(--adm-accent-hover)">{{ $messagesToday }}</div>
                <div class="text-xs" style="color:var(--adm-txt3)">أوامر اليوم</div>
            </div>
            <div class="rounded-2xl border p-4" style="background:var(--adm-surface);border-color:var(--adm-border)">
                <div class="text-2xl font-bold" style="color:var(--adm-accent-hover)">{{ $pendingEdits }}</div>
                <div class="text-xs" style="color:var(--adm-txt3)">مسودات تعديل معلقة</div>
            </div>
            <div class="rounded-2xl border p-4" style="background:var(--adm-surface);border-color:var(--adm-border)">
                <div class="text-2xl font-bold" style="color:var(--adm-accent-hover)">{{ $chatQuestionsCount }}</div>
                <div class="text-xs" style="color:var(--adm-txt3)">أسئلة شات مفعلة</div>
            </div>
            <a href="{{ \App\Filament\Resources\ChatLogResource::getUrl('index') }}" class="rounded-2xl border p-4 transition hover:brightness-125" style="background:var(--adm-surface);border-color:{{ $chatPendingCount > 0 ? 'var(--adm-accent-bd)' : 'var(--adm-border)' }}">
                <div class="text-2xl font-bold" style="color:{{ $chatPendingCount > 0 ? 'var(--adm-red)' : 'var(--adm-accent-hover)' }}">{{ $chatPendingCount }}</div>
                <div class="text-xs" style="color:var(--adm-txt3)">رسائل شات بانتظار إجابة ←</div>
            </a>
        </div>

        @foreach ($botCommands as $group)
            <div class="rounded-2xl border p-5" style="background:var(--adm-surface);border-color:{{ ($group['accent'] ?? false) ? 'var(--adm-accent-bd)' : 'var(--adm-border)' }}">
                <h2 class="mb-1 font-extrabold" style="color:var(--adm-txt)">{{ $group['title'] }}</h2>
                @if ($group['note'] ?? false)
                    <p class="mb-3 text-xs" style="color:var(--adm-txt2)">{{ $group['note'] }}</p>
                @endif
                <div class="space-y-2 text-sm">
                    @foreach ($group['rows'] as $row)
                        <div class="flex flex-wrap items-baseline gap-2 rounded-lg px-3 py-2" style="background:var(--adm-surface2)">
                            <code class="rounded px-2 py-0.5 text-xs font-bold" style="background:var(--adm-bg);color:var(--adm-accent-hover);border:1px solid var(--adm-accent-bd)">{{ $row['cmd'] }}</code>
                            <span style="color:var(--adm-txt2)">{{ $row['desc'] }}</span>
                        </div>
                    @endforeach
                </div>
                @if ($group['footnote'] ?? false)
                    <p class="mt-2 text-xs" style="color:var(--adm-txt3)">{{ $group['footnote'] }}</p>
                @endif
            </div>
        @endforeach

        {{-- دليل الشات العائم — الجزء الثاني من القسم الموحد --}}
        <div class="rounded-2xl border p-5" style="background:var(--adm-surface);border-color:var(--adm-accent-bd)">
            <div class="mb-2 flex items-center justify-between gap-3">
                <h2 class="font-extrabold" style="color:var(--adm-txt)">💬 دليل الشات العائم — كيف يعمل؟</h2>
                <a href="{{ \App\Filament\Resources\ChatQuestionResource::getUrl('index') }}" class="text-xs font-bold hover:underline" style="color:var(--adm-accent-hover)">إدارة الأسئلة والأجوبة ←</a>
            </div>
            <p class="mb-3 text-xs" style="color:var(--adm-txt2)">شات بأجوبة جاهزة تديرها أنت 100% — بلا ذكاء اصطناعي. أضف سؤالًا وكلماته المفتاحية ويعرض الجواب فورًا لأي زائر.</p>
            <ul class="space-y-1.5 text-sm leading-7" style="color:var(--adm-txt2)">
                @foreach ($chatRules as $rule)
                    <li>• {{ $rule }}</li>
                @endforeach
            </ul>
            <p class="mt-3 rounded-lg px-3 py-2 text-xs" style="background:var(--adm-surface2);color:var(--adm-txt3)">
                حلقة التعلم: كل رسالة زائر بلا جواب تظهر في «سجل المحادثات» — اضغط «علّم الشات» فتتحول لسؤالًا جديدًا ويختفي من قائمة العمل فورًا.
            </p>
        </div>

        {{-- نصائح --}}
        <div class="rounded-2xl border p-5" style="background:var(--adm-surface);border-color:var(--adm-accent-bd)">
            <h2 class="mb-2 font-extrabold" style="color:var(--adm-accent-hover)">💡 نصائح مهمة</h2>
            <ul class="space-y-1.5 text-sm leading-7" style="color:var(--adm-txt2)">
                <li>• الأوامر مقصورة عليك وحدك — أي شخص آخر يرسل للبوت يرى رفضًا مع chat_id الخاص به.</li>
                <li>• تعديل الكمية عبر البوت يوثق كحركة «تعديل» في سجل المخزون، ويمر عبر نفس خدمة المخزون الذرّية.</li>
                <li>• التقارير الصباحية تصلك 9:00 صباحًا والمسائية 9:00 مساءً تلقائيًا (إن فعلتها من الإعدادات).</li>
                <li>• إن لم يرد البوت: تأكد أن «استقبال الأوامر» مفعل، وأن المجدول يعمل، وأن التوكن هو توكن الأوامر لا الإشعارات.</li>
            </ul>
        </div>
    </div>
</x-filament-panels::page>