<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Dibagikan - Smarthom</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f8; margin: 0; padding: 0; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #1e3a5f 0%, #0f6fad 100%); padding: 36px 40px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; letter-spacing: 0.5px; }
        .header p  { color: rgba(255,255,255,0.75); margin: 6px 0 0; font-size: 13px; }
        .body { padding: 36px 40px; }
        .body p  { color: #374151; font-size: 15px; line-height: 1.7; margin: 0 0 16px; }
        .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px 24px; margin: 24px 0; }
        .card .label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 4px; }
        .card .value { font-size: 17px; font-weight: 600; color: #1e293b; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-control { background: #dbeafe; color: #1d4ed8; }
        .badge-view    { background: #dcfce7; color: #166534; }
        .btn { display: block; width: fit-content; margin: 28px auto 0; padding: 14px 36px; background: #0f6fad; color: #ffffff; text-decoration: none; border-radius: 8px; font-size: 15px; font-weight: 600; }
        .footer { background: #f8fafc; padding: 20px 40px; text-align: center; }
        .footer p  { color: #94a3b8; font-size: 12px; margin: 0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>⚡ Smarthom</h1>
        <p>Smart Device Management Platform</p>
    </div>
    <div class="body">
        <p>Hai <strong>{{ $sharedWith->name }}</strong>,</p>
        <p>
            <strong>{{ $sharedBy->name }}</strong> telah berbagi akses sebuah device dengan kamu di platform Smarthom.
            Kamu sekarang dapat mengaksesnya dari dashboard kamu.
        </p>

        <div class="card">
            <div class="label">Nama Device</div>
            <div class="value">{{ $device->name }}</div>
        </div>

        <div class="card">
            <div class="label">Level Akses</div>
            <div class="value">
                <span class="badge badge-{{ $permission }}">
                    @if($permission === 'control')
                        🎮 Control — Bisa melihat & mengendalikan device
                    @else
                        👁 View — Hanya bisa melihat data device
                    @endif
                </span>
            </div>
        </div>

        <p style="color: #64748b; font-size: 13px;">
            Kamu tidak bisa menghapus, mengedit nama, atau mengubah konfigurasi device ini — hanya pemilik yang bisa melakukannya.
        </p>

        <a href="{{ url('/dashboard') }}" class="btn">Buka Dashboard</a>
    </div>
    <div class="footer">
        <p>Email ini dikirim otomatis oleh Smarthom. Jika kamu tidak mengenali pengirim, abaikan saja email ini.</p>
    </div>
</div>
</body>
</html>
