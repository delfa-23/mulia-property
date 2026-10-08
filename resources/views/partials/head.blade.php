<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@livewireStyles

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="{{ asset('images/mulia-property-logo.png') }}" type="image/png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
