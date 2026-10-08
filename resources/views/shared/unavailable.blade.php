<x-layouts::public title="الرابط غير متاح">
    <div class="mx-auto max-w-lg">
        <x-panel>
            <x-empty-state icon="link-slash" :title="$expired ? 'انتهت صلاحية هذا الرابط' : 'الرابط غير صحيح'" :text="($forPatient ?? false) ? 'تواصل مع المركز للحصول على رابط جديد.' : 'تواصل مع المركز للحصول على رابط جديد، أو ادخل على حسابك لعرض كل حالاتك.'">
                @unless ($forPatient ?? false)<flux:button variant="primary" :href="route('login')" icon="arrow-right-end-on-rectangle">دخول الأطباء</flux:button>@endunless
            </x-empty-state>
        </x-panel>
    </div>
</x-layouts::public>
