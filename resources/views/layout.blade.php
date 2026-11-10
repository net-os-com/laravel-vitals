<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · Vitals</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 28 28'%3E%3Crect width='28' height='28' rx='9' fill='%230a2d51'/%3E%3Cpath d='M7 14.5h3l2-5 3.5 9 2-4H21' fill='none' stroke='%23fff' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
    <script>try{var t=localStorage.getItem('vitals-theme');if(t==='light'||t==='dark')document.documentElement.dataset.theme=t}catch(e){}</script>
    <style>{!! \NetOs\Vitals\Support\Assets::css() !!}</style>
</head>
<body>
<div class="shell">
    <header class="bar">
        <a class="brand" href="{{ route('vitals.overview') }}">
            <span class="mark" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16"><path d="M1 8.5h3l2-5 3.5 9 2-4H15" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            Vitals <span class="sub">{{ config('app.name') }}</span>
        </a>
        <div class="nav">
            <nav class="tabs" aria-label="Vitals">
                <a href="{{ route('vitals.overview') }}" @if(request()->routeIs('vitals.overview')) aria-current="page" @endif>Overview</a>
                <a href="{{ route('vitals.status') }}">status.json</a>
            </nav>
            <button class="theme" type="button" data-theme-toggle title="Switch between system, light and dark">
                <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8 1.75a6.25 6.25 0 0 1 0 12.5z" fill="currentColor"/></svg>
                <span data-theme-label>System</span>
            </button>
        </div>
    </header>
    <main class="view" id="vitals-main" data-refresh="{{ (int) config('vitals.dashboard.refresh_seconds', 30) }}">
        @yield('content')
    </main>
</div>
@include('vitals::partials.mute-dialog')
<script>{!! \NetOs\Vitals\Support\Assets::js() !!}</script>
</body>
</html>
