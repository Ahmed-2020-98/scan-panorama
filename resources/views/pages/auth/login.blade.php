<x-layouts::auth title="تسجيل الدخول">
    <div class="flex flex-col gap-6">
        <x-auth-header title="تسجيل الدخول" description="أدخل البريد الإلكتروني أو رقم الهاتف وكلمة المرور" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="email"
                label="البريد الإلكتروني أو رقم الهاتف"
                :value="old('email')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="01xxxxxxxxx"
                dir="ltr"
            />

            <div class="relative">
                <flux:input
                    name="password"
                    label="كلمة المرور"
                    type="password"
                    required
                    autocomplete="current-password"
                    viewable
                    dir="ltr"
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        نسيت كلمة المرور؟
                    </flux:link>
                @endif
            </div>

            <flux:checkbox name="remember" label="تذكرني على هذا الجهاز" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                دخول
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
