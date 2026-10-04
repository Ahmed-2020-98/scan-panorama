<x-layouts::auth title="استعادة كلمة المرور">
    <div class="flex flex-col gap-6">
        <x-auth-header title="استعادة كلمة المرور" description="أدخل بريدك الإلكتروني وسنرسل لك رابطًا لتعيين كلمة مرور جديدة" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input name="email" label="البريد الإلكتروني" type="email" required autofocus placeholder="email@example.com" dir="ltr" />

            <flux:button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
                إرسال رابط الاستعادة
            </flux:button>
        </form>

        <flux:callout icon="information-circle" color="zinc" inline>
            <flux:callout.text>إذا لم يكن لحسابك بريد إلكتروني، تواصل مع إدارة المركز لإعادة تعيين كلمة المرور.</flux:callout.text>
        </flux:callout>

        <div class="text-center text-sm text-zinc-500">
            <flux:link :href="route('login')" wire:navigate>العودة لتسجيل الدخول</flux:link>
        </div>
    </div>
</x-layouts::auth>
