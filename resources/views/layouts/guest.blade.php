<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'PlayDF') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/dark-toggle.js'])
    </head>
    <body class="font-sans text-gray-300 antialiased bg-playdf-dark">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            
            <div class="mb-6 flex justify-center">
                @include('partials.logo')
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-8 bg-playdf-card shadow-lg overflow-hidden sm:rounded-2xl border border-gray-800">
                {{ $slot }}
            </div>
        </div>
        <button onclick="toggleTheme()" class="theme-toggle-floating" title="Cambiar tema">
            <i class="fa-solid fa-moon icon-moon"></i>
            <i class="fa-solid fa-sun icon-sun"></i>
        </button>
    </body>
</html>