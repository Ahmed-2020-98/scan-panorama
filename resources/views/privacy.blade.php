@php($center = \App\Models\Setting::get('center_name'))
<x-layouts::public title="سياسة الخصوصية">
    <article class="mx-auto max-w-3xl">
        <x-panel>
            <div class="space-y-5 text-[0.95rem] leading-relaxed text-zinc-700">
                <div>
                    <h1 class="font-display text-2xl font-bold text-zinc-900">سياسة الخصوصية</h1>
                    <p class="mt-1 text-sm text-zinc-500">{{ $center }} — نظام إدارة حالات الأشعة</p>
                </div>

                <section>
                    <h2 class="font-display text-lg font-semibold text-zinc-900">من يستخدم النظام</h2>
                    <p>النظام مخصص لطاقم المركز والأطباء المسجلين فقط. لا يتاح التسجيل للعامة، وكل حساب يرى ما تسمح به صلاحياته وفرعه.</p>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-zinc-900">البيانات التي نحفظها</h2>
                    <p>بيانات المريض الأساسية (الاسم والهاتف والعمر)، وبيانات الحالة (الفحص والطبيب والفرع والتاريخ)، وملفات الأشعة والتقارير، وسجل العمليات داخل النظام. تُستخدم هذه البيانات لتقديم خدمة الأشعة ومشاركة النتائج مع الطبيب المعالج فقط.</p>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-zinc-900">استخدام Google Drive</h2>
                    <p>يربط المدير حساب Google Drive الخاص بالمركز لحفظ ملفات الحالات. يطلب النظام صلاحية <span dir="ltr">drive.file</span> فقط، أي أنه يصل إلى الملفات والمجلدات التي ينشئها النظام نفسه، ولا يقرأ أي ملفات أخرى في Drive. لا يُنشئ النظام روابط مشاركة عامة على Drive، والوصول للملفات يمر دائمًا عبر صلاحيات النظام. يُحفظ رمز الوصول مشفرًا على خادم المركز، ويمكن للمدير إلغاء الربط في أي وقت من النظام أو من إعدادات حساب Google.</p>
                    <p>استخدام البيانات المستلمة من Google APIs يلتزم بـ <a class="text-brand-700 underline" dir="ltr" href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>، بما فيها متطلبات الاستخدام المحدود.</p>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-zinc-900">المشاركة مع أطراف أخرى</h2>
                    <p>لا نبيع البيانات ولا نشاركها لأغراض إعلانية. تُعرض نتائج الحالة للطبيب المعالج عبر حسابه أو رابط خاص بالحالة يمكن للمركز إلغاؤه.</p>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-zinc-900">التواصل</h2>
                    <p>لأي استفسار عن بياناتك تواصل مع المركز@if (\App\Models\Setting::get('contact_phone')) على <span class="ltr-nums">{{ \App\Models\Setting::get('contact_phone') }}</span>@endif.</p>
                </section>
            </div>
        </x-panel>
    </article>
</x-layouts::public>
