<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Ulang Kata Sandi - Sistem Giliran WFO</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f3f4f6;
        }
        .container {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 32px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }
        .logo {
            text-align: center;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f3f4f6;
        }
        .logo-title {
            color: #2563eb;
            font-size: 22px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .logo-sub {
            color: #6b7280;
            font-size: 13px;
            margin: 4px 0 0 0;
        }
        .action-box {
            text-align: center;
            margin: 30px 0;
        }
        .btn-reset {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            padding: 12px 32px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        .btn-reset:hover {
            background-color: #1d4ed8;
        }
        .warning {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 14px 16px;
            border-radius: 0 8px 8px 0;
            margin: 24px 0;
            font-size: 13.5px;
            color: #92400e;
        }
        .subcopy {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #f3f4f6;
            font-size: 12.5px;
            color: #6b7280;
            word-break: break-all;
        }
        .subcopy a {
            color: #2563eb;
            text-decoration: underline;
        }
        .footer {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #9ca3af;
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
            <h2 class="logo-title">Sistem Giliran WFO</h2>
            <p class="logo-sub">PT Inovindo Digital Media</p>
        </div>

        <h3 style="color: #111827; margin-bottom: 12px; font-size: 18px;">Halo, {{ $username }}!</h3>
        
        <p style="font-size: 14.5px; color: #4b5563; margin-top: 0;">
            Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda di Sistem Giliran WFO. Klik tombol di bawah ini untuk membuat kata sandi baru:
        </p>

        <div class="action-box">
            <a href="{{ $url }}" class="btn-reset" target="_blank" rel="noopener">
                Atur Ulang Kata Sandi
            </a>
            <p style="margin: 12px 0 0 0; font-size: 12.5px; color: #6b7280;">
                Tautan ini hanya berlaku selama <strong>{{ $expiresInMinutes }} menit</strong>.
            </p>
        </div>

        <div class="warning">
            ⚠️ <strong>Perhatian:</strong> Jika Anda tidak merasa meminta pengaturan ulang kata sandi, abaikan email ini. Kata sandi akun Anda akan tetap aman dan tidak akan diubah.
        </div>

        <div class="subcopy">
            Jika tombol di atas tidak dapat diklik, salin dan tempel tautan berikut ke browser web Anda:<br>
            <a href="{{ $url }}">{{ $url }}</a>
        </div>

        <div class="footer">
            <p style="margin: 0;">Email ini dikirim secara otomatis oleh Sistem Giliran WFO.<br>
            © {{ date('Y') }} PT Inovindo Digital Media. Hak Cipta Dilindungi.</p>
        </div>
    </div>
</body>
</html>
