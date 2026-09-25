@extends('errors.layout', [
    'code' => 403,
    'title' => app()->getLocale() === 'en' ? 'Access denied' : 'لا تملك صلاحية الوصول',
    'message' => app()->getLocale() === 'en'
        ? 'This account is a customer account, so it cannot open the admin panel. If you are a store staff member, sign out and sign in with your admin account.'
        : 'هذا الحساب حساب عميل، فلا يفتح لوحة الإدارة. إن كنت من فريق المتجر فسجّل الخروج ثم ادخل بحساب الإدارة — وإن كنت تظن أن صلاحيتك سُحبت، فتواصل مع إدارة المتجر.',
    'logout' => true,
])
