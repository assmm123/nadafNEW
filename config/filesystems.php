<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            // جذر الويب لا storage — ليُخدَم الملف المرفوع مباشرةً من Apache
            // بلا رابط رمزي. الاستضافات المجانية (FTP) لا تنشئ symlinks،
            // فقرص storage/app/public يبقى معزولاً عن الويب وتنكسر كل الصور.
            // المجلد يحوي: products · categories · slides · branding · payment-proofs
            'root' => public_path('storage'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | لم نعد نستخدم روابط رمزية: قرص public يكتب الآن داخل public/storage
    | مباشرةً (انظر أعلاه)، فيُخدَم الملف من جذر الويب بلا وسيط.
    | أبقينا المصفوفة فارغة عمدًا — تشغيل `storage:link` كان سينشئ رابطًا
    | رمزيًا يصطدم بمجلد حقيقي يحمل الاسم نفسه.
    |
    */

    'links' => [],

];
