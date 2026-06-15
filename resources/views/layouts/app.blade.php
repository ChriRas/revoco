<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('wf.title') }}@if (config('revoco.brand_name')) · {{ config('revoco.brand_name') }}@endif</title>
    {{-- Critical CSS inlined: the page is fully styled at first paint, with no
         external render-blocking stylesheet to wait for — this eliminates the FOUC.
         The public withdrawal pages use only the --wf-* styles (no Tailwind), so
         this ~8 KB block is the complete stylesheet for them. --}}
    <style>{!! file_get_contents(resource_path('css/withdrawal.css')) !!}</style>
</head>
<body class="wf-page">
    @yield('content')
</body>
</html>
