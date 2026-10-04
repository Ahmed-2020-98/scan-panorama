<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>@yield('title')</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-zinc-50 p-6 text-zinc-800 antialiased">
        <div class="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-sm">
            <div class="ltr-nums text-5xl font-bold text-brand-700">@yield('code')</div>
            <h1 class="mt-3 text-xl font-bold text-zinc-900">@yield('title')</h1>
            <p class="mt-2 text-sm text-zinc-500">@yield('message')</p>
            <a href="{{ url('/') }}" class="mt-6 inline-flex items-center justify-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">الصفحة الرئيسية</a>
        </div>
    </body>
</html>
