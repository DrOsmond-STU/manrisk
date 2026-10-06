<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title ?? 'ManRisk ERM' }}</title></head>
<body style="margin:0;padding:24px 12px;background:#eef3f9;font-family:Segoe UI,Arial,sans-serif;color:#13233a">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;background:#ffffff;border-radius:12px;border:1px solid #d6e2f0">
      <tr><td style="background:#0b4f9c;color:#ffffff;padding:16px 24px;border-radius:12px 12px 0 0;font-size:16px;font-weight:700">ManRisk ERM</td></tr>
      <tr><td style="padding:24px;font-size:14px;line-height:1.6">@yield('body')</td></tr>
      <tr><td style="padding:14px 24px;border-top:1px solid #e3ebf5;color:#526883;font-size:12px">Email otomatis dari {{ config('app.url') }} — jangan dibalas. Dikirim {{ now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB.</td></tr>
    </table>
  </td></tr></table>
</body>
</html>
