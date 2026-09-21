@php
    $appUrl = rtrim((string) config('app.url'), '/');
    $appUrl = $appUrl !== '' ? $appUrl : url('/');
    $appHost = parse_url($appUrl, PHP_URL_HOST) ?: $appUrl;
@endphp
Atur Ulang Kata Sandi SkuySocial

Atur Ulang Kata Sandi

Kami menerima permintaan untuk mengatur ulang kata sandi akun SkuySocial Anda.

Gunakan tautan berikut untuk melanjutkan:
{{ $url }}

Tautan ini berlaku selama {{ $expiration }} menit.

Jika Anda tidak meminta pengaturan ulang kata sandi, abaikan email ini.

SkuySocial
{{ $appHost }}
