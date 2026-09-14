<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app('translator')->getLocale()) }}">
<head><meta charset="UTF-8"><title>{{ $subject }}</title></head>
<body>
    <p>{{ $greeting }}</p>
    @foreach($introLines as $line)<p>{{ $line }}</p>@endforeach
    <p><a href="{{ $actionUrl }}">{{ $actionText }}</a></p>
    @foreach($outroLines as $line)<p>{{ $line }}</p>@endforeach
    <p>{{ $salutation }}</p>
</body>
</html>
