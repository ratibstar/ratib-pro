{{-- EN: Base admin layout (head, admin assets, content slot).
     AR: القالب الأساسي لصفحات الإدارة (الرأس، ملفات الإدارة، ومكان المحتوى). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    {{-- EN: Head section for admin pages (title + global admin stylesheet).
         AR: قسم الرأس لصفحات الإدارة (العنوان + ملف التنسيقات العام). --}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Admin') . ' - ' . config('app.name'))</title>
    <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
</head>
<body>
    {{-- EN: Content slot injected by child admin views.
         AR: مكان عرض المحتوى القادم من الصفحات الفرعية. --}}
    @yield('content')
    {{-- EN: Global admin JavaScript bundle + per-page scripts stack.
         AR: حزمة JavaScript العامة للإدارة + سكربتات إضافية خاصة بكل صفحة. --}}
    <script src="{{ asset('assets/js/admin.js') }}"></script>
    @stack('scripts')
</body>
</html>
