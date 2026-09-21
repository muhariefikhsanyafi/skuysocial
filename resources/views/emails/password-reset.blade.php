@php
    $appUrl = rtrim((string) config('app.url'), '/');
    $appUrl = $appUrl !== '' ? $appUrl : url('/');
    $appHost = parse_url($appUrl, PHP_URL_HOST) ?: $appUrl;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atur Ulang Kata Sandi SkuySocial</title>
</head>
<body style="margin:0; padding:0; background-color:#FFF9F2; color:#20262E; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; margin:0; padding:0; background-color:#FFF9F2;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; max-width:600px; background-color:#FFFFFF; border:1px solid #D6DCE1; border-radius:12px;">
                    <tr>
                        <td style="padding:30px 32px 12px;">
                            <p style="margin:0; color:#0D6B65; font-size:16px; line-height:1.4; font-weight:700;">SkuySocial</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 32px;">
                            <h1 style="margin:0 0 20px; color:#20262E; font-size:28px; line-height:1.25; font-weight:700;">Atur Ulang Kata Sandi</h1>
                            <p style="margin:0 0 18px; color:#20262E; font-size:16px; line-height:1.6;">Kami menerima permintaan untuk mengatur ulang kata sandi akun SkuySocial Anda.</p>
                            <p style="margin:0 0 24px; color:#20262E; font-size:16px; line-height:1.6;">Gunakan tombol berikut untuk melanjutkan:</p>
                            <p style="margin:0 0 26px;">
                                <a href="{{ $url }}" style="display:inline-block; padding:13px 20px; background-color:#0D6B65; color:#FFFFFF; border-radius:8px; font-size:15px; line-height:1.3; font-weight:700; text-decoration:none;">ATUR ULANG KATA SANDI</a>
                            </p>
                            <p style="margin:0 0 18px; color:#4A5563; font-size:14px; line-height:1.6;">Tautan ini berlaku selama {{ $expiration }} menit.</p>
                            <p style="margin:0 0 20px; color:#4A5563; font-size:14px; line-height:1.6;">Jika Anda tidak meminta pengaturan ulang kata sandi, abaikan email ini.</p>
                            <p style="margin:0 0 8px; color:#5F6B78; font-size:13px; line-height:1.5;">Jika tombol tidak berfungsi, salin dan buka tautan berikut:</p>
                            <p style="margin:0; font-size:13px; line-height:1.5; word-break:break-all;">
                                <a href="{{ $url }}" style="color:#0D6B65; text-decoration:underline;">{{ $url }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px; border-top:1px solid #D6DCE1; color:#5F6B78; font-size:13px; line-height:1.5;">
                            <p style="margin:0;">SkuySocial · <a href="{{ $appUrl }}" style="color:#0D6B65; text-decoration:underline;">{{ $appHost }}</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
