<!doctype html>
<html lang="en" @if($colorTheme) data-theme="{{ $colorTheme }}" @endif @class(['dark' => $colorTheme === 'dark']) data-theme-cookie-path="{{ $themeCookiePath }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('interpresso::global.app_name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/interpresso/css/app.css') }}">
    <script type="module" src="{{ asset('vendor/interpresso/js/app.js') }}"></script>
    @stack('styles')
</head>
<body class="min-h-screen bg-base-200 text-base-content lg:px-6 py-2.5">
@include('interpresso::partials.toast')
<nav class="navbar bg-base-100 rounded-box shadow-sm px-2 sm:px-4 mx-4 w-auto">
    <div class="container flex flex-wrap gap-4 items-center justify-between mx-auto">
        <a href="{{ route('interpresso.languages') }}" class="btn btn-ghost text-xl">{{ __('interpresso::navbar.brand') }}</a>
        <span class="badge badge-ghost">v {{ \AnyMedia\Interpresso\InterpressoServiceProvider::$version }}</span>
        <button id="theme-toggle" type="button" class="btn btn-ghost btn-sm" aria-label="Toggle dark mode">Light / Dark</button>
        @if(auth(config('interpresso.translator_guard'))->check())
            <button type="button" data-toggle="mobile-menu" aria-controls="mobile-menu" aria-expanded="false" class="btn btn-ghost md:hidden">Open main menu</button>
            <div id="mobile-menu" class="hidden md:flex w-full md:w-auto">
                <ul class="menu md:menu-horizontal w-full md:w-auto">
                    <li><a href="{{ route('interpresso.languages') }}">{{ __('interpresso::navbar.languages') }}</a></li>
                    <li><a href="{{ route('interpresso.manual') }}">{{ __('interpresso::navbar.manual') }}</a></li>
                    @if($isAdministrator)
                        <li><a href="{{ route('interpresso.translators') }}">{{ __('interpresso::navbar.translators') }}</a></li>
                        <li><a href="{{ route('interpresso.settings') }}">{{ __('interpresso::navbar.settings') }}</a></li>
                    @endif
                    <li><form method="POST" action="{{ route('interpresso.logout') }}" class="p-0">@csrf<button type="submit" class="btn btn-ghost btn-sm w-full justify-start">{{ __('interpresso::navbar.logout') }}</button></form></li>
                </ul>
            </div>
        @endif
    </div>
</nav>
@if(auth(config('interpresso.translator_guard'))->check())
    @include('interpresso::partials.batch-progress')
    @include('interpresso::partials.notifications')
@endif
@yield('page')
@stack('scripts')
</body>
</html>
