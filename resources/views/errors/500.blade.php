@extends('errors.layout', [
    'code' => 500,
    'title' => app()->getLocale() === 'en' ? 'Something went wrong' : 'حدث خطأ غير متوقع',
    'message' => app()->getLocale() === 'en'
        ? 'We are sorry for this temporary glitch — our team has been notified automatically and is working on it. Try refreshing the page or come back shortly.'
        : 'نعتذر عن هذا الخلل المؤقت — فريقنا أُبلغ تلقائيًا ويعمل على إصلاحه. جرّب تحديث الصفحة أو عد بعد قليل.',
])
