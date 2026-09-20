<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        @php $seoProps = $page['props'] ?? []; @endphp

        @if (($seoProps['hasActiveDiscovery'] ?? false) === true)
            <meta name="robots" content="noindex,follow">
        @endif
        @if (($seoProps['canonicalUrl'] ?? null) !== null)
            <link rel="canonical" href="{{ $seoProps['canonicalUrl'] }}">
            <meta property="og:url" content="{{ $seoProps['canonicalUrl'] }}">
        @endif

        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>