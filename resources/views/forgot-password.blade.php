@extends('interpresso::layouts.app')
@section('page')
<section class="max-w-md mx-auto px-6 py-10">
    <div class="card card-body bg-base-100 shadow-md gap-4">
        <h1 class="card-title">{{ __('interpresso::passwords.forgot') }}</h1>
        <p>{{ __('interpresso::passwords.request_intro') }}</p>
        @if(session('password_status'))
            <p role="status">{{ session('password_status') }}</p>
        @endif
        @isset($retryAfter)
            <p role="alert">{{ __('interpresso::passwords.throttled', ['seconds' => $retryAfter]) }}</p>
        @endisset
        <form method="POST" action="{{ route('interpresso.password.email') }}" class="space-y-4">
            @csrf
            <label for="email" class="label">{{ __('interpresso::login.email') }}</label>
            <input id="email" name="email" type="email" autocomplete="email" required maxlength="255" class="input input-bordered w-full" value="{{ old('email') }}">
            @include('interpresso::component.error', ['field' => 'email'])
            @include('interpresso::component.button', ['text' => __('interpresso::passwords.send_link')])
        </form>
        <a href="{{ route('interpresso.login') }}" class="link">{{ __('interpresso::passwords.back_to_login') }}</a>
    </div>
</section>
@endsection
