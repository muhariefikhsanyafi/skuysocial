<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('auth.verify_email.mail_subject') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#FFF9F2; color:#20262E; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; margin:0; padding:0; background-color:#FFF9F2;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; max-width:600px; background-color:#FFFFFF; border:1px solid #D6DCE1; border-radius:14px;">
                    <tr>
                        <td style="padding:30px 32px 12px;">
                            <p style="margin:0; color:#0D6B65; font-size:16px; line-height:1.4; font-weight:700;">SkuySocial</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 32px;">
                            <h1 style="margin:0 0 20px; color:#20262E; font-size:26px; line-height:1.25; font-weight:700;">{{ __('auth.verify_email.mail_title') }}</h1>
                            <p style="margin:0 0 24px; color:#4A5563; font-size:16px; line-height:1.6;">{{ __('auth.verify_email.mail_intro') }}</p>
                            <p style="margin:0 0 24px; color:#0D6B65; font-size:32px; line-height:1.2; font-weight:700; letter-spacing:0.12em;">{{ $code }}</p>
                            <p style="margin:0 0 16px; color:#4A5563; font-size:14px; line-height:1.6;">{{ __('auth.verify_email.mail_expiration') }}</p>
                            <p style="margin:0; color:#4A5563; font-size:14px; line-height:1.6;">{{ __('auth.verify_email.mail_ignore') }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px; border-top:1px solid #D6DCE1; color:#5F6B78; font-size:13px; line-height:1.5;">
                            {{ __('auth.verify_email.mail_footer') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
