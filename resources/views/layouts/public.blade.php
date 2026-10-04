<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        @include('partials.head')
        <meta name="robots" content="noindex, nofollow">
    </head>
    <body class="workspace flex min-h-screen flex-col text-zinc-800 antialiased">
        <header class="border-b border-zinc-200/80 bg-white/80 backdrop-blur">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <x-center-brand />
                @auth
                    <flux:button size="sm" variant="ghost" :href="auth()->user()->homeUrl()">حسابي</flux:button>
                @endauth
            </div>
        </header>

        <main class="page-enter mx-auto w-full max-w-5xl flex-1 px-4 py-6 sm:px-6">
            {{ $slot }}
        </main>

        @include('partials.center-footer')

        @fluxScripts
    </body>
</html>
