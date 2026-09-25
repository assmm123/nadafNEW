@extends('errors.layout', [
    'code' => 413,
    'title' => app()->getLocale() === 'en' ? 'File too large' : 'الملف أكبر من الحد',
    'message' => (app()->getLocale() === 'en'
            ? 'The uploaded file exceeds the limit this server allows'
            : 'حجم الملف المرفوع أكبر من الحد الذي يسمح به السيرفر')
        .' ('.App\Support\UploadLimits::human(App\Support\UploadLimits::effectiveBytes()).' للفيديو الواحد). '
        .(app()->getLocale() === 'en'
            ? 'Compress the video before uploading, or upload a shorter clip.'
            : 'اضغط الفيديو قبل رفعه، أو ارفع مقطعًا أقصر.'),
])
