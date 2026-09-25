// Alpine تبدأ تلقائيًا مع Livewire (المنطق التفاعلي للقوالب يعمل عبر Alpine المدمجة)

/**
 * تبديل الوضع الداكن / الفاتح.
 *
 * يُحفظ في كوكي (سنة كاملة) لا في localStorage، والسبب: الخادم يقرأ الكوكي
 * عند أول رسم فيضع data-theme الصحيح على <html> — فلا وميض فاتح ثم انقلاب
 * إلى داكن عند كل تحميل صفحة.
 *
 * كل قيم الألوان تأتي من رموز app.css، فهذه الدالة لا تعرف شيئًا عن الألوان.
 */
window.nadSetTheme = function (mode) {
    var next = mode === 'light' ? 'light' : 'dark';

    document.documentElement.dataset.theme = next;
    document.cookie = 'nad_theme=' + next + ';path=/;max-age=31536000;SameSite=Lax';

    // Livewire يحدّث الصفحة جزئيًا — أبلغه ليعيد رسم ما يعتمد على الوضع
    document.dispatchEvent(new CustomEvent('nad:theme', { detail: { theme: next } }));
};

// تسجيل عامل الخدمة لتطبيق الويب (PWA) — في وضع الإنتاج فقط
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
