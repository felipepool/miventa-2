<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f1b2d">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <meta name="format-detection" content="telephone=no">

    <title>{{ config('app.name') }}</title>

    @fonts
    @viteReactRefresh
    @vite(['resources/scss/app.scss', 'resources/js/main.tsx'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
