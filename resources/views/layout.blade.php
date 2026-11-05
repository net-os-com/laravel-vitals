<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · Vitals</title>
    <style>{!! \NetOs\Vitals\Support\Assets::css() !!}</style>
</head>
<body>
<div class="shell">
    <header class="bar">
        <a class="brand" href="{{ route('vitals.overview') }}">
            <span class="mark" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16"><path d="M1 8.5h3l2-5 3.5 9 2-4H15" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            Vitals <span class="sub">{{ config('app.name') }}</span>
        </a>
        <nav class="tabs" aria-label="Vitals">
            <a href="{{ route('vitals.overview') }}" @if(request()->routeIs('vitals.overview')) aria-current="page" @endif>Overview</a>
            <a href="{{ route('vitals.status') }}">status.json</a>
        </nav>
    </header>
    <main class="view" id="vitals-main" data-refresh="{{ (int) config('vitals.dashboard.refresh_seconds', 30) }}">
        @yield('content')
    </main>
</div>
<script>{!! \NetOs\Vitals\Support\Assets::js() !!}</script>
</body>
</html>
