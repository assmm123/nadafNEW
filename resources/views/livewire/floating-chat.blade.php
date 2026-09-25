<div wire:key="floating-chat">
    {{-- زر الشات العائم — دائرة نحاسية ناعمة بأيقونة كلام فحمية + هالة (نمط nad.html) --}}
    @if (! $open)
        <button wire:click="toggle" aria-label="{{ __('chat.open') }}"
                class="nad-fab">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
            </svg>
            <span class="nad-fab-dot"></span>
        </button>
    @endif

    {{-- نافذة المحادثة — سطح فحمي بحافة نحاسية خافتة وزوايا 18px --}}
    @if ($open)
        <div class="nad-chat">
            <div class="nad-chat-h">
                <span class="nad-chat-av">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                    </svg>
                </span>
                <div class="nad-chat-tt">
                    <b>{{ __('chat.title') }}</b>
                    <small><i class="nad-chat-ondot"></i>{{ __('chat.online') }}</small>
                </div>
                <button wire:click="toggle" class="nad-chat-x" aria-label="{{ __('common.close') }}">
                    <svg viewBox="0 0 24 24" class="h-4 w-4"><path d="m9 9 6 6M15 9l-6 6"/></svg>
                </button>
            </div>

            {{-- الرسائل + تمرير تلقائي لآخر رسالة (تبعية تفاعلية على طول المصفوفة) --}}
            <div x-effect="$wire.messages.length && $nextTick(() => { $el.scrollTop = $el.scrollHeight })"
                 id="chat-scroll" class="nad-chat-m" wire:key="chat-msgs">
                @foreach ($messages as $i => $msg)
                    <div class="{{ $msg['from'] === 'user' ? 'nad-chat-row me' : 'nad-chat-row bot' }}" wire:key="msg-{{ $i }}">
                        <div class="{{ $msg['from'] === 'user' ? 'nad-msg nad-msg-me' : 'nad-msg nad-msg-bot' }}">
                            {{ $msg['text'] }}

                            @if (isset($msg['suggestions']))
                                <div class="nad-msg-sug">
                                    @foreach ($msg['suggestions'] as $s)
                                        <button wire:click="ask(@js($s))" class="nad-qchip">
                                            {{ $s }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if (isset($msg['show_contact']) && $msg['show_contact'])
                                {{-- وسائل التواصل كروت دائرية كبيرة بنمط nad-cbtn — تظهر عند تكرار السؤال أو غياب الجواب --}}
                                <div class="nad-cgrid">
                                    @foreach (\App\Models\CommunicationMethod::where('is_active', true)->orderBy('sort_order')->get() as $cm)
                                        <a href="{{ $cm->link() }}" target="_blank" rel="noopener" class="nad-cbtn">
                                            <span class="nad-cbtn-ico">
                                                <x-shop-icon name="{{ \App\Models\CommunicationMethod::TYPES[$cm->type]['icon'] ?? 'globe' }}" class="h-5 w-5" />
                                            </span>
                                            <b>{{ $cm->label ?? $cm->typeLabel() }}</b>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- الإدخال --}}
            <form wire:submit="ask" class="nad-chat-c">
                <input type="text" wire:model="message" placeholder="{{ __('chat.placeholder') }}"
                       class="nad-chat-in" maxlength="300" autocomplete="off">
                <button type="submit" wire:loading.attr="disabled" class="nad-chat-send" aria-label="{{ __('chat.send') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 rtl:-scale-x-100">
                        <path d="M3.478 2.405a.75.75 0 0 0-.926.94l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.405Z" />
                    </svg>
                </button>
            </form>
        </div>
    @endif
</div>
