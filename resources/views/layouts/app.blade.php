<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('wf.title') }}@if (config('revoco.brand_name')) · {{ config('revoco.brand_name') }}@endif</title>
    @vite('resources/css/app.css')
</head>
<body class="wf-page">
    @yield('content')
</body>
</html>
