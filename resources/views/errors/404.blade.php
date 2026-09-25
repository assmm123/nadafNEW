@extends('errors.layout', [
    'code' => 404,
    'title' => app()->getLocale() === 'en' ? 'Page not found' : 'الصفحة غير موجودة',
    'message' => app()->getLocale() === 'en'
        ? 'The page you are looking for has moved or is no longer available — but our scarves are still right here! Browse the store and pick what suits your style.'
        : 'يبدو أن الصفحة التي تبحث عنها انتقلت أو لم تعد متوفرة — لكن كرافاتنا ما زالت في مكانها! تصفّح المتجر واختر ما يناسب أناقتك.',
])
