<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $title }}</title>
<style>
  @page { margin: 18mm 14mm; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0b1b2e; }
  h1 { font-size: 17px; margin: 0 0 2px; } h2 { font-size: 12.5px; margin: 14px 0 6px; border-bottom: 1px solid #b9cce4; padding-bottom: 3px; }
  .meta { color: #526883; font-size: 9px; margin-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 8px; } th, td { border: 1px solid #c9d6e6; padding: 4px 5px; vertical-align: top; } th { background: #e3ebf6; text-align: left; font-size: 9px; text-transform: uppercase; }
  td.num, th.num { text-align: right; } .lv { display: inline-block; padding: 1px 6px; border-radius: 3px; color: #fff; font-size: 9px; }
  .low { background: #0ca30c; } .medium { background: #d99a00; } .high { background: #ec835a; } .very_high { background: #d03b3b; }
  .kpis { width: 100%; margin-bottom: 8px; } .kpis td { border: 1px solid #c9d6e6; text-align: center; padding: 6px; } .kpis b { font-size: 16px; display: block; }
  .foot { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 8px; color: #526883; text-align: center; }
  .brand { color: #0b4da2; font-weight: bold; }
</style></head><body>
<div class="foot">ManRisk ERM · {{ $org }} · dibuat {{ $generated_at->format('d/m/Y H:i') }} oleh {{ $by }} · Dokumen internal</div>
<h1><span class="brand">ManRisk</span> · {{ $title }}</h1>
<div class="meta">Nomor: MR/{{ strtoupper(substr(md5($title . $generated_at), 0, 6)) }}/{{ $generated_at->format('m/Y') }}</div>
<div class="meta">{{ $org }} · dibuat {{ $generated_at->translatedFormat('d F Y H:i') }} oleh {{ $by }}@if(!empty($params['unit_id'])) · unit #{{ $params['unit_id'] }}@endif</div>
@yield('content')
</body></html>
