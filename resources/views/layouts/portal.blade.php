<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        @include('partials.head')
    </head>
    <body class="workspace flex min-h-screen flex-col text-zinc-800 antialiased">
        @php($user = auth()->user())

        <header class="border-b border-zinc-200/80 bg-white/80 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <a href="{{ route('portal') }}" wire:navigate>
                    <x-center-brand />
                </a>

                <flux:dropdown position="bottom" align="end">
                    <flux:profile :name="$user->name" :initials="$user->initials()" icon:trailing="chevron-down" class="max-sm:[&>span]:hidden" />
                    <flux:menu>
                        <flux:menu.item :href="route('portal')" icon="folder-open" wire:navigate>حالاتي</flux:menu.item>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>الحساب وكلمة المرور</flux:menu.item>
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">تسجيل الخروج</flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </header>

        <main class="page-enter mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6">
            {{ $slot }}
        </main>

        @include('partials.center-footer')

        @persist('toast')
            <flux:toast.group position="bottom start">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
