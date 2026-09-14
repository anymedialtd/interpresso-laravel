@php
    // Keep complete class names here so Tailwind includes every supported option.
    $variantClass = match ($variant ?? 'primary') {
        'success' => 'btn-success',
        'error' => 'btn-error',
        'warning' => 'btn-warning',
        'info' => 'btn-info',
        'ghost' => 'btn-ghost',
        'outline' => 'btn-outline',
        'neutral' => 'btn-neutral',
        default => 'btn-primary',
    };
    $sizeClass = match ($size ?? 'md') {
        'xs' => 'btn-xs',
        'sm' => 'btn-sm',
        'lg' => 'btn-lg',
        'xl' => 'btn-xl',
        default => 'btn-md',
    };
@endphp
<button type="{{ $type ?? 'submit' }}" class="btn {{ $variantClass }} {{ $sizeClass }}">{{ $text }}</button>
