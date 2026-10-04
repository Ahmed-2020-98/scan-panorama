<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-paper text-zinc-800 antialiased">
        <div class="grid min-h-svh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
            {{-- Form side --}}
            <div class="relative isolate flex flex-col overflow-hidden px-6 py-8 sm:px-10">
                {{-- Brand wave from the visual identity --}}
                <svg aria-hidden="true" class="pointer-events-none absolute -bottom-2 end-0 -z-10 w-72 sm:w-96 rtl:-scale-x-100" viewBox="0 0 420 220" fill="none">
                    <path d="M420 92C330 96 262 136 206 176C176 197 150 212 120 220H420Z" fill="var(--color-brand-600)" fill-opacity="0.09" />
                    <path d="M420 128C352 130 300 160 252 194C238 204 226 213 212 220H420Z" fill="var(--color-brand-600)" fill-opacity="0.14" />
                    <path d="M420 150C372 158 334 182 300 220" stroke="var(--color-marker)" stroke-width="6" stroke-linecap="round" />
                </svg>

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
                <x-center-brand on-film size="lg" />

                <div class="-mx-4 xl:-mx-8">
                    <x-radiograph class="drop-shadow-[0_0_40px_rgb(0_102_214_/_0.18)]" />
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
