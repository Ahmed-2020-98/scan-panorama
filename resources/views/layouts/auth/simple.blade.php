<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-paper text-zinc-800 antialiased">
        <div class="grid min-h-svh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
            {{-- Form side --}}
            <div class="relative flex flex-col px-6 py-8 sm:px-10">
                <x-center-brand class="lg:hidden" />

                <div class="flex flex-1 items-center justify-center py-10">
                    <div class="w-full max-w-sm animate-reveal">
                        {{ $slot }}
                    </div>
                </div>

                <div class="ruler mb-3"></div>
                <p class="text-xs text-zinc-400">
                    الوصول مخصص لطاقم المركز والأطباء المسجلين فقط.
                </p>
            </div>

            {{-- Lightbox side --}}
            <aside class="film hidden flex-col justify-between overflow-hidden p-10 lg:flex xl:p-14">
                <x-center-brand on-film />

                <div class="-mx-4 xl:-mx-8">
                    <x-radiograph class="drop-shadow-[0_0_40px_rgb(98_240_223_/_0.08)]" />
                </div>

                <div class="max-w-lg">
                    <div class="eyebrow mb-4">نظام إدارة حالات الأشعة</div>
                    <p class="font-display text-3xl leading-snug font-bold text-white xl:text-4xl">
                        كل حالة، بملفاتها،
                        <span class="text-phosphor">في مكانها.</span>
                    </p>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-zinc-400">
                        سجّل المريض، اربط الحالة بالطبيب والفرع، ارفع التقارير وملفات DICOM، وأرسل للطبيب رابطًا آمنًا في ثوانٍ.
                    </p>
                    <div class="ruler mt-8"></div>
                    <div class="mt-3 flex gap-6 text-xs text-zinc-400">
                        <span class="ltr-nums">PANORAMA</span>
                        <span class="ltr-nums">CBCT</span>
                        <span class="ltr-nums">CEPH</span>
                        <span class="ltr-nums">ENDO</span>
                    </div>
                </div>
            </aside>
        </div>

        @persist('toast')
            <flux:toast.group position="bottom start">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
