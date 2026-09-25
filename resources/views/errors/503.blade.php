@extends('errors.layout', [
    'code' => 503,
    'title' => app()->getLocale() === 'en' ? 'Under maintenance' : 'الموقع تحت الصيانة',
    'message' => app()->getLocale() === 'en'
        ? 'We are making a few updates to serve you better — back very soon with a nicer experience. Thanks for your patience 💛'
        : 'نقوم ببعض التحديثات لخدمتك بشكل أفضل — نعود قريبًا جدًا بتجربة أجمل. شكرًا لصبرك 💛',
])
