@php
    $setting = \Helper::getSetting();

    $resolveStorageAsset = function (?string $path) {
        if (blank($path)) {
            return null;
        }

        $path = ltrim((string) $path, '/');

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        if (str_starts_with($path, 'uploads/')) {
            return asset('storage/' . $path);
        }

        return asset('storage/' . $path);
    };

    $logoWhite = $resolveStorageAsset($setting?->software_logo_white ?? null);
    $logoBlack = $resolveStorageAsset($setting?->software_logo_black ?? null);
@endphp

@if($setting)
    <div>
        @if($logoWhite)
            <img src="{{ $logoWhite }}" alt="" class="show-in-dark h-8">
        @endif

        @if($logoBlack)
            <img src="{{ $logoBlack }}" alt="" class="show-in-light h-8">
        @endif
    </div>
@endif