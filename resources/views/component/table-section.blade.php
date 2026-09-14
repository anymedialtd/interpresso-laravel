@extends('interpresso::layouts.app')
@section('page')
<section class="pb-8">
    <div @class(['mx-auto px-4 lg:px-12', 'max-w-[1400px]' => ($maxWidth ?? 1920) === 1400, 'max-w-[1920px]' => ($maxWidth ?? 1920) !== 1400])>
        @yield('content')
    </div>
</section>
@endsection
