@extends('interpresso::layouts.app')
@section('page')
<section class="max-w-md mx-auto px-6 py-10">
    <div class="card card-body bg-base-100 shadow-md gap-4">
        <h1 class="card-title">{{ __('interpresso::passwords.' . ($invitation ? 'invite_action' : 'reset_action')) }}</h1>
        <form method="POST" action="{{ route('interpresso.password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            @include('interpresso::component.error', ['field' => 'token'])
            <label for="email" class="label">{{ __('interpresso::login.email') }}</label>
            <input id="email" name="email" type="email" autocomplete="email" required maxlength="255" class="input input-bordered w-full" value="{{ old('email', $email) }}">
            @include('interpresso::component.error', ['field' => 'email'])
            <label for="password" class="label">{{ __('interpresso::passwords.new_password') }}</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8" maxlength="255" class="input input-bordered w-full">
            @include('interpresso::component.error', ['field' => 'password'])
            <label for="password_confirmation" class="label">{{ __('interpresso::passwords.confirm_password') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="input input-bordered w-full">
            @include('interpresso::component.error', ['field' => 'password_confirmation'])
            @include('interpresso::component.button', ['text' => __('interpresso::passwords.' . ($invitation ? 'invite_action' : 'reset_action'))])
        </form>
        <a href="{{ route('interpresso.password.request') }}" class="link">{{ __('interpresso::passwords.request_another') }}</a>
    </div>
</section>
@endsection
