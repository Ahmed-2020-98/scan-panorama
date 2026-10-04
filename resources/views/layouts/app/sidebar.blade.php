<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        @include('partials.head')
    </head>
    <body class="workspace min-h-screen text-zinc-800 antialiased">
        @php($user = auth()->user())

        <flux:sidebar sticky collapsible="mobile" class="dark nav-surface border-e border-white/10">
            <flux:sidebar.header class="shrink-0 pt-1">
                <x-app-logo :sidebar="true" href="{{ $user->homeUrl() }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @if ($user->isStaff())
                @if($user->hasPermission(\App\Enums\Permission::CreateCase))<div class="px-1">
                    <flux:button variant="primary" icon="plus" :href="route('cases.create')" class="w-full" wire:navigate>
                        حالة جديدة
                    </flux:button>
                </div>@endif

                <flux:sidebar.nav>
                    <flux:sidebar.group heading="التشغيل" class="grid">
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            لوحة التحكم
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="folder-open" :href="route('cases.index')" :current="request()->routeIs('cases.*', 'patients.*')" wire:navigate>
                            المرضى والحالات
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    @if($user->hasPermission(\App\Enums\Permission::ViewFinancials))
                        <flux:sidebar.item icon="banknotes" :href="route('accounts.index')" :current="request()->routeIs('accounts.*')" wire:navigate>الحسابات</flux:sidebar.item>
                    @endif
                    @if($user->hasPermission(\App\Enums\Permission::ManageVisits))
                        <flux:sidebar.item icon="calendar-days" :href="route('visits.index')" :current="request()->routeIs('visits.*')" wire:navigate>زيارات الأطباء</flux:sidebar.item>
                    @endif
                    @if ($user->isAdmin())
                        <flux:sidebar.group heading="الإدارة" class="grid">
                            <flux:sidebar.item icon="academic-cap" :href="route('doctors.index')" :current="request()->routeIs('doctors.*')" wire:navigate>
                                الأطباء
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="building-office-2" :href="route('branches.index')" :current="request()->routeIs('branches.*')" wire:navigate>
                                الفروع
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="queue-list" :href="route('exam-types.index')" :current="request()->routeIs('exam-types.*')" wire:navigate>
                                أنواع الفحوصات
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="identification" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>
                                المستخدمون والصلاحيات
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="clock" :href="route('activity.index')" :current="request()->routeIs('activity.*')" wire:navigate>
                                سجل النشاط
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="cog-6-tooth" :href="route('center-settings')" :current="request()->routeIs('center-settings')" wire:navigate>
                                إعدادات المركز
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="cloud" :href="route('drive.settings')" :current="request()->routeIs('drive.*')" wire:navigate>Google Drive والملفات</flux:sidebar.item>
                            <flux:sidebar.item icon="arrow-path" :href="route('recycle-bin')" :current="request()->routeIs('recycle-bin')" wire:navigate>المحذوفات</flux:sidebar.item>
                        </flux:sidebar.group>
                    @endif
                </flux:sidebar.nav>
            @endif

            @if($user->isTechnician())<flux:sidebar.nav><flux:sidebar.item icon="folder-open" :href="route('technician')" wire:navigate>الحالات المسندة إليّ</flux:sidebar.item></flux:sidebar.nav>@endif
            @if ($user->isDoctor())
                <flux:sidebar.nav>
                    <flux:sidebar.item icon="folder-open" :href="route('portal')" wire:navigate>حالاتي</flux:sidebar.item>
                </flux:sidebar.nav>
            @endif

            <flux:spacer />

            <div class="mx-1 mb-1 rounded-lg border border-white/5 bg-white/[3%] px-3 py-2.5">
                <div class="ruler ruler-light mb-2 opacity-60"></div>
                <div class="flex items-center justify-between text-[0.7rem] text-zinc-400">
                    <span>{{ now()->translatedFormat('l') }}</span>
                    <span class="ltr-nums text-phosphor/80">{{ now()->format('d.m.Y') }}</span>
                </div>
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="$user->name" />
        </flux:sidebar>

        <!-- Mobile header -->
        <flux:header class="dark nav-surface border-b border-white/10 lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <x-app-logo href="{{ $user->homeUrl() }}" class="ms-2" wire:navigate />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile :initials="$user->initials()" icon-trailing="chevron-down" />

                <flux:menu>
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar :name="$user->name" :initials="$user->initials()" />
                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ $user->name }}</flux:heading>
                            <flux:text class="truncate">{{ $user->role->label() }}</flux:text>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>الحساب</flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                            تسجيل الخروج
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group position="bottom start">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
