<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode OTP Verifikasi Email</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .otp-box {
            background-color: #ffffff;
            border: 2px dashed #3b82f6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 25px 0;
        }
        .otp-code {
            font-size: 36px;
            font-weight: bold;
            color: #3b82f6;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
        }
        .warning {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px;
            margin: 20px 0;
            font-size: 14px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            @if (isset($message) && method_exists($message, 'embed') && file_exists(public_path('images/inovindo-logo.png')))
                <img src="{{ $message->embed(public_path('images/inovindo-logo.png')) }}" alt="Logo Inovindo" style="max-height: 44px; width: auto; margin-bottom: 10px; display: inline-block;">
            @endif
            <h2 style="color: #3b82f6; margin: 0;">Sistem Giliran WFO</h2>
            <p style="color: #6b7280; margin: 5px 0 0 0;">PT Inovindo Digital Media</p>
        </div>

        <h3 style="color: #111827; margin-bottom: 15px;">Halo, {{ $username }}!</h3>
        
        <p>Anda telah meminta untuk menambahkan email ke akun Anda. Gunakan kode OTP berikut untuk memverifikasi email Anda:</p>

        <div class="otp-box">
            <p style="margin: 0 0 10px 0; font-size: 14px; color: #6b7280;">Kode OTP Anda</p>
            <div class="otp-code">{{ $otp }}</div>
            <p style="margin: 10px 0 0 0; font-size: 12px; color: #6b7280;">Kode berlaku selama {{ $expiresInMinutes }} menit</p>
        </div>

        <p>Masukkan kode ini pada halaman verifikasi untuk melanjutkan proses penambahan email.</p>

        <div class="warning">
            ⚠️ <strong>Penting:</strong> Jangan bagikan kode OTP ini kepada siapa pun, termasuk staf Inovindo. Kami tidak akan pernah meminta kode OTP Anda.
        </div>

        <p style="font-size: 14px; color: #6b7280;">Jika Anda tidak meminta kode ini, abaikan email ini atau hubungi administrator sistem.</p>

        <div class="footer">
            <p>Email ini dikirim secara otomatis oleh Sistem Giliran WFO.<br>
            PT Inovindo Digital Media</p>
        </div>
    </div>
</body>
</html>
