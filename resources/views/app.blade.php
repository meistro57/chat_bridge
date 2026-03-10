<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        @php($hasFrontendAssets = app(\App\Support\FrontendAssets::class)->hasViteAssets())

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @if ($hasFrontendAssets)
            @viteReactRefresh
            @vite(['resources/js/app.jsx'])
        @endif
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @if ($hasFrontendAssets)
            @inertia
        @else
            <main class="min-h-screen bg-zinc-950 px-6 py-16 text-zinc-100">
                <div class="mx-auto max-w-2xl rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl shadow-black/40 backdrop-blur">
                    <p class="text-sm uppercase tracking-[0.3em] text-zinc-400">Frontend assets unavailable</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white">Build output is missing.</h1>
                    <p class="mt-4 text-sm leading-6 text-zinc-300">
                        This release does not currently have a Vite manifest. Run the frontend build and redeploy the generated
                        <code class="rounded bg-black/30 px-1.5 py-0.5 text-xs text-zinc-100">public/build</code> assets.
                    </p>
                </div>
            </main>
        @endif
    </body>
</html>
