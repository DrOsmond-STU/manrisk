<?php
/** Halaman masuk. Variabel $csrf dan $expired disiapkan oleh index.php. */
declare(strict_types=1);
if (!isset($csrf)) {
    http_response_code(404);
    exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>Masuk · ManRisk ERM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap">
<style>
:root {
  --bg: #f1f5fb; --surface: #fff; --surface-2: #e3ebf6; --line: #dce6f2; --line-2: #b9cce4;
  --fg: #0b1b2e; --fg-2: #27405c; --muted: #526883; --accent: #1268c4; --accent-2: #34a8e0;
  --brand-300: #8cc7f2; --grad-from: #062b63; --grad-to: #1a7bd4; --bad: #b00d21; --bad-bg: #fde8ea;
  --lv-l: #0ca30c; --lv-m: #fab219; --lv-h: #ec835a; --lv-vh: #d03b3b;
  --f-display: "Plus Jakarta Sans", "Segoe UI", system-ui, sans-serif;
  --f-body: "IBM Plex Sans", "Segoe UI", system-ui, sans-serif;
  --f-mono: "IBM Plex Mono", ui-monospace, Menlo, monospace;
  --shadow: 0 1px 2px rgba(4,35,79,.06), 0 8px 20px rgba(4,35,79,.07);
  --float: 0 2px 4px rgba(4,35,79,.12), 0 8px 16px -6px rgba(18,104,196,.7);
}
@media (prefers-color-scheme: dark) {
  :root { --bg: #071220; --surface: #0b1724; --surface-2: #12212f; --line: #1d2e3f; --line-2: #2f4762;
    --fg: #eaf2fb; --fg-2: #c3d6e8; --muted: #93a9c0; --accent: #4a9be4; --brand-300: #a6d6f7;
    --grad-from: #041b3f; --grad-to: #0b4da2; --bad: #ff7a85; --bad-bg: #3b1216; color-scheme: dark;
    --shadow: 0 1px 2px rgba(0,0,0,.5), 0 8px 20px rgba(0,0,0,.38); }
}
* { box-sizing: border-box; }
html, body { height: 100%; }
body { margin: 0; background: var(--bg); color: var(--fg); font: 14px/1.5 var(--f-body); -webkit-font-smoothing: antialiased; }
.login { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); min-height: 100%; }
.brandside { position: relative; overflow: hidden; color: #fff; padding: 44px 40px; display: flex; flex-direction: column; gap: 32px;
  background: linear-gradient(150deg, var(--grad-from), var(--grad-to)); }
.brandside::after { content: ""; position: absolute; right: -80px; top: -90px; width: 360px; height: 360px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,.16), rgba(255,255,255,0) 70%); pointer-events: none; }
.brand { display: flex; align-items: center; gap: 12px; position: relative; z-index: 1; }
.brand-mark { width: 40px; height: 40px; border-radius: 10px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px; padding: 6px;
  background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); }
.brand-mark i { border-radius: 2px; display: block; }
.brand b { font-family: var(--f-display); font-size: 19px; display: block; line-height: 1.1; }
.brand small { color: var(--brand-300); font-size: 11px; letter-spacing: .06em; font-weight: 600; }
.claim { margin-top: auto; position: relative; z-index: 1; max-width: 34ch; }
.claim .num { font-family: var(--f-display); font-size: 58px; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }
.claim h2 { font-family: var(--f-display); font-size: 24px; line-height: 1.25; margin: 8px 0 4px; }
.claim p { margin: 0; color: #d6e8fb; }
.claim .stack { display: flex; gap: 6px; margin-top: 22px; }
.claim .stack span { flex: 1; padding: 8px 10px; border-radius: 8px; background: rgba(255,255,255,.12); font-size: 12px; }
.claim .stack b { display: block; font-family: var(--f-display); font-size: 20px; }
.claim .stack i { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 6px; }
.claim blockquote { margin: 24px 0 0; padding-left: 14px; border-left: 3px solid var(--brand-300); color: #d6e8fb; font-size: 14px; }
.formside { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 32px 24px 48px; }
.card { width: 100%; max-width: 420px; background: var(--surface); border: 1px solid var(--line); border-radius: 20px; box-shadow: var(--shadow); padding: 32px; }
h1 { font-family: var(--f-display); font-size: 24px; margin: 0; }
.sub { color: var(--muted); font-size: 13px; margin: 4px 0 22px; }
.field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
label { font-size: 12.5px; font-weight: 600; color: var(--fg-2); }
input { font: inherit; color: inherit; width: 100%; border: 1px solid var(--line); background: var(--surface); border-radius: 10px; padding: 11px 12px; min-height: 44px; box-shadow: 0 1px 2px rgba(4,35,79,.06); }
input:focus { outline: 2px solid color-mix(in oklab, var(--accent) 50%, transparent); border-color: var(--accent); }
.pass { position: relative; }
.pass input { padding-right: 46px; }
.eye { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px; border: 0; background: none; color: var(--muted); border-radius: 8px; cursor: pointer; display: grid; place-items: center; }
.eye:hover { background: var(--surface-2); color: var(--fg); }
.err { display: none; gap: 8px; align-items: flex-start; background: var(--bad-bg); color: var(--bad); border-left: 3px solid currentColor; border-radius: 8px; padding: 10px 12px; font-size: 13px; margin-bottom: 16px; }
.err.show { display: flex; }
.btn { width: 100%; border: 0; cursor: pointer; font: inherit; font-weight: 700; font-size: 15px; color: #fff; min-height: 46px; border-radius: 10px;
  background: linear-gradient(135deg, var(--accent), var(--accent-2)); box-shadow: var(--float); transition: transform .18s ease, box-shadow .18s ease; }
.btn:hover { transform: translateY(-2px); }
.btn[disabled] { opacity: .55; cursor: wait; transform: none; }
.foot { margin-top: 22px; padding-top: 16px; border-top: 1px solid var(--line); font-size: 12px; color: var(--muted); line-height: 1.6; }
.foot code { font-family: var(--f-mono); font-size: 11.5px; }
@media (max-width: 900px) {
  .login { grid-template-columns: minmax(0, 1fr); }
  .brandside { padding: 28px 20px; gap: 22px; }
  .claim { margin-top: 0; max-width: none; }
  .claim .num { font-size: 40px; }
  .claim h2 { font-size: 20px; }
  .card { padding: 24px 20px; }
}
@media (prefers-reduced-motion: reduce) { .btn { transition: none; } .btn:hover { transform: none; } }
</style>
</head>
<body>
<div class="login">
  <aside class="brandside">
    <div class="brand">
      <span class="brand-mark" aria-hidden="true"><i style="background:var(--lv-l)"></i><i style="background:var(--lv-m)"></i><i style="background:var(--lv-h)"></i><i style="background:var(--lv-m)"></i><i style="background:var(--lv-h)"></i><i style="background:var(--lv-vh)"></i><i style="background:var(--lv-h)"></i><i style="background:var(--lv-vh)"></i><i style="background:var(--lv-vh)"></i></span>
      <span><b>ManRisk</b><small>ERM · ISO 31000:2018</small></span>
    </div>
    <div class="claim">
      <div class="num">127</div>
      <h2>Risiko organisasi dikelola dalam satu platform</h2>
      <p>Dari penetapan konteks sampai pemantauan, insiden, dan perbaikan berkelanjutan.</p>
      <div class="stack" aria-label="Profil risiko residual">
        <span><i style="background:var(--lv-vh)"></i>Sangat Tinggi<b>8</b></span>
        <span><i style="background:var(--lv-h)"></i>Tinggi<b>23</b></span>
        <span><i style="background:var(--lv-m)"></i>Sedang<b>61</b></span>
        <span><i style="background:var(--lv-l)"></i>Rendah<b>35</b></span>
      </div>
      <blockquote>Manajemen risiko adalah bagian dari setiap keputusan, bukan kegiatan terpisah.</blockquote>
    </div>
  </aside>
  <main class="formside">
    <form class="card" id="f" novalidate>
      <h1>Masuk ke ManRisk</h1>
      <p class="sub">Integrated Enterprise Risk Management Platform</p>
      <div class="err <?= $expired ? 'show' : '' ?>" id="err" role="alert"><?= $expired ? 'Sesi Anda berakhir karena tidak ada aktivitas. Silakan masuk kembali.' : '' ?></div>
      <div class="field"><label for="email">Alamat email</label><input id="email" name="email" type="email" autocomplete="username" inputmode="email" required autofocus></div>
      <div class="field"><label for="pass">Kata sandi</label><div class="pass"><input id="pass" name="password" type="password" autocomplete="current-password" required>
        <button type="button" class="eye" id="eye" aria-label="Tampilkan kata sandi"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.8"/></svg></button></div></div>
      <button class="btn" id="go" type="submit">Masuk</button>
      <p class="foot">Akses hanya untuk pengguna terdaftar. Lupa kata sandi? Hubungi administrator ManRisk di organisasi Anda. Setiap percobaan masuk dicatat.</p>
    </form>
  </main>
</div>
<script>
(function () {
  var csrf = <?= json_encode($csrf) ?>;
  var f = document.getElementById('f'), err = document.getElementById('err'), go = document.getElementById('go'), pass = document.getElementById('pass');
  var timer = null;
  function show(msg) { err.textContent = msg; err.classList.toggle('show', !!msg); }
  document.getElementById('eye').addEventListener('click', function () {
    var t = pass.type === 'password'; pass.type = t ? 'text' : 'password'; this.setAttribute('aria-label', t ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'); pass.focus();
  });
  function lock(sec) {
    clearInterval(timer); go.disabled = true;
    function tick() { if (sec <= 0) { clearInterval(timer); go.disabled = false; go.textContent = 'Masuk'; show(''); return; }
      go.textContent = 'Coba lagi dalam ' + Math.floor(sec / 60) + ':' + String(sec % 60).padStart(2, '0'); sec--; }
    tick(); timer = setInterval(tick, 1000);
  }
  f.addEventListener('submit', function (e) {
    e.preventDefault(); show('');
    var email = f.email.value.trim(), password = f.password.value;
    if (!email || !password) { show('Isi alamat email dan kata sandi.'); return; }
    go.disabled = true; go.textContent = 'Memeriksa…';
    fetch('api/login.php', { method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ email: email, password: password }) })
      .then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }); })
      .then(function (x) {
        if (x.s === 200 && x.j.ok) { go.textContent = 'Membuka aplikasi…'; location.replace('./'); return; }
        if (x.s === 429 && x.j.retryAfter) { show(x.j.error); lock(x.j.retryAfter); return; }
        if (x.s === 403) { show(x.j.error || 'Sesi formulir kedaluwarsa. Muat ulang halaman.'); }
        else show(x.j.error || 'Tidak dapat masuk. Coba lagi.');
        go.disabled = false; go.textContent = 'Masuk'; pass.select();
      })
      .catch(function () { show('Server tidak dapat dihubungi. Periksa koneksi lalu coba lagi.'); go.disabled = false; go.textContent = 'Masuk'; });
  });
})();
</script>
</body>
</html>
