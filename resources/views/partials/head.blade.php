<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>
    {{ filled($title ?? null) ? $title.' - '.\App\Models\Setting::get('center_name') : \App\Models\Setting::get('center_name') }}
</title>

<link rel="icon" href="/favicon.png" type="image/png">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])

@auth
    @php($fontKey = auth()->user()->fontKey())
    @if ($fontKey !== config('radiology.default_font'))
        {{-- The user's chosen interface font (Settings > Appearance) --}}
        <style data-user-font>:root { --font-sans: {!! config("radiology.fonts.$fontKey.stack") !!}, ui-sans-serif, system-ui, sans-serif; --font-display: var(--font-sans); }</style>
    @endif
@endauth
