@extends('errors.layout', [
    'code' => 419,
    'title' => app()->getLocale() === 'en' ? 'Page expired' : 'انتهت صلاحية الصفحة',
    'message' => app()->getLocale() === 'en'
        ? 'For security reasons your session expired — refresh the page and continue where you left off. Your data is safe.'
        : 'لأسباب أمنية انتهت صلاحية جلستك — حدّث الصفحة وأكمل من حيث توقفت. بياناتك آمنة.',
])
