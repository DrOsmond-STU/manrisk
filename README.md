# ManRisk ERM — Purwarupa UI/UX

Purwarupa klik-able untuk **Integrated Enterprise Risk Management Platform berbasis ISO 31000:2018**.
Alur yang didemonstrasikan:

```
STRATEGY → OBJECTIVE → RISK → CONTROL → TREATMENT → KRI → MONITORING → INCIDENT → IMPROVEMENT
```

> Seluruh organisasi, nama, dan angka di dalam purwarupa adalah **data contoh fiktif**
> (organisasi contoh: *Badan Layanan Digital Nusantara*). Fitur AI, ekspor laporan, dan
> notifikasi adalah **simulasi** UI.

## Cara menjalankan

Tidak perlu build atau server. Buka `index.html` langsung di browser, atau jalankan server statis:

```bash
python3 -m http.server 8080
# buka http://localhost:8080
```

Bisa juga diunggah apa adanya ke GitHub Pages, Netlify, atau hosting statis lain.

## Struktur berkas

| Berkas | Isi |
|---|---|
| `index.html` | Kerangka aplikasi + seluruh CSS (design token terang/gelap, layout responsif) |
| `assets/data.js` | Data inti: kriteria, taksonomi, 20 risiko unggulan beserta kontrol, KRI, insiden, pengguna, peran |
| `assets/demo-data.js` | Data demo lengkap (127 risiko, action plan, kontrol, KRI, insiden, dokumen, audit trail) dan perhitungan ulang semua agregat dashboard |
| `assets/app.js` | Router berbasis hash, komponen (heatmap, grafik, sparkline, wizard), dan semua layar |

## Deploy ke server

Situs dipasang di https://manrisk.semestateknologiutama.com lewat Git Deploy (cPanel) dari branch `main`.
Setiap kali `assets/*.js` berubah, naikkan penanda `?v=` pada tag `<script>` di `index.html`
agar browser dan proxy server tidak memakai salinan lama. `.htaccess` membuat `index.html` selalu
dicek ulang ke server dan menyembunyikan folder `.git`.

## Isi data demo

Semua angka di dashboard dihitung dari data di bawah ini, sehingga heatmap, profil risiko, grafik
per unit/kategori/sasaran, dan status mitigasi selalu konsisten satu sama lain.

| Data | Jumlah | Keterangan |
|---|---|---|
| Risiko | 127 | 8 unit kerja, 10 kategori, 4 sasaran strategis; residual 8 Sangat Tinggi · 23 Tinggi · 61 Sedang · 35 Rendah |
| Action plan | 216 | PIC, anggaran, tenggat, progres, bukti; status Selesai/Berjalan/Belum Mulai/Terlambat/Dibatalkan |
| Kontrol | 36 | preventif/detektif/korektif, manual/otomatis, efektivitas desain & operasi |
| KRI | 18 | tren 12 bulan dengan ambang Normal/Waspada/Kritis |
| Insiden | 14 | kronologi, penyebab, dampak, kerugian, tindakan korektif; loss event 2024–2026 |
| Dokumen | 26 | SOP, kebijakan, kontrak, sertifikat, hasil uji, foto & screenshot bukti |
| Pengguna | 24 | seluruh peran RBAC, Risk Officer di setiap unit |
| Persetujuan | 13 | risiko baru, perubahan skor, rencana mitigasi, penutupan risiko |
| Audit trail | 73 | 4 minggu aktivitas dengan nilai sebelum → sesudah |
| Reviu, perbaikan, laporan | 46 · 11 · 8 | hasil reviu triwulan, improvement plan, riwayat laporan |

Data dibangkitkan secara deterministik, jadi demo selalu menampilkan isi yang sama setiap kali dibuka.

## Peta layar ↔ rancangan fitur

| Menu | Layar | Fitur dalam rancangan |
|---|---|---|
| Dashboard | Executive Dashboard | #1, #7, #12, #24, #32 — profil risiko, heatmap inheren/residual, top 10, tren, mitigasi, perjalanan risiko 25 → 16 → 9 → 4, ringkasan AI |
| | Risk Dashboard | #1 — risiko baru/ditangani/terlambat/ditutup, distribusi per triwulan, per unit, per proses, emerging risks |
| | KRI & Early Warning | #15, #16 — ambang Normal/Waspada/Kritis, sparkline 12 bulan, umpan peringatan, kanal notifikasi |
| Manajemen Risiko | Konteks & Kriteria | #3 — ruang lingkup, konteks internal/eksternal (PESTLE), skala kemungkinan & dampak, appetite/tolerance/capacity |
| | Identifikasi Risiko | #4, #6, #30 — wizard 5 langkah, format *Karena… dapat terjadi… sehingga…*, saran AI, matriks inheren & residual, opsi perlakuan, ajukan ke alur persetujuan |
| | Risk Register | #5 — 127 risiko, filter (level, unit, kategori, status), pencarian, urutan, paginasi, tautan dari sel heatmap |
| | Detail risiko | #5, #9–#14, #17, #21, #22 — tab Ringkasan, Analisis (posisi I/R/P/T di matriks), Kontrol, Mitigasi, KRI, Insiden, Dokumen, Riwayat |
| | Analisis & Evaluasi | #6, #8 — kalkulator L × I, status evaluasi otomatis terhadap appetite per kategori |
| | Risk Review | #19 — sebelumnya → saat ini → tren, jadwal reviu bulanan/triwulanan/semester/tahunan |
| Penanganan & Kontrol | Mitigasi & Action Plan | #9–#11 — tabel & kanban, progres, tenggat, notifikasi terlambat |
| | Kontrol & Efektivitas | #13, #14 — control register, penilaian desain & operasi, unggah bukti |
| | Perbaikan Berkelanjutan | #33 — sumber masukan, improvement plan, lessons learned |
| Pemantauan | Insiden & Loss Event | #17, #18 — rantai Risiko → Kontrol → Insiden → Tindakan korektif, loss event database |
| Tata Kelola | Pemetaan Sasaran | #26 — Sasaran → Program → Proses → Risiko → Kontrol → KRI, deteksi kesenjangan kontrol |
| | Taksonomi & Appetite | #25 — kategori risiko dengan appetite & tolerance |
| | Kerangka ISO 31000 | #34 — prinsip (klausul 4), kerangka (5), proses (6) |
| | Persetujuan | #20 — kotak masuk, stepper 4 tahap, setujui / minta revisi / tolak |
| Pelaporan & Dokumen | Laporan | #23, #31 — katalog 13 laporan (PDF/Excel/Word), AI Generate Executive Report |
| | Dokumen & Bukti | #21 — versi, metadata, status persetujuan, tanggal kedaluwarsa, unggah |
| Administrasi | Organisasi & Pengguna | #2, #27, #28 — pohon organisasi, multi-organisasi, pengguna, matriks RBAC, konfigurasi alur |
| | Audit Trail | #22 — pengguna, aktivitas, nilai sebelum → sesudah, IP |
| AI | AI Risk Assistant | #30 — identifikasi, risk statement, rekomendasi mitigasi, analisis perubahan, ringkasan |

## Hal yang bisa dicoba

- **Ganti peran** di bilah atas (Super Admin … Auditor). Menu dan tombol menyesuaikan hak akses;
  Management dan Auditor masuk mode baca saja.
- **Klik sel heatmap** di Executive Dashboard untuk membuka Risk Register yang tersaring ke sel tersebut.
- **Isi wizard Identifikasi Risiko** sampai langkah 5, lalu *Ajukan*. Risiko baru muncul di register,
  di kotak masuk Persetujuan, dan di Audit Trail.
- **Setujui / tolak** item di menu Persetujuan; keputusan tercatat di Audit Trail.
- **Nilai efektivitas kontrol** di menu Kontrol & Efektivitas.
- **Buat laporan eksekutif AI** di menu Laporan.
- **Mode gelap** lewat tombol bulan, atau ikuti pengaturan sistem.

## Konvensi skor

Skor = Kemungkinan (1–5) × Dampak (1–5).

| Level | Skor |
|---|---|
| Rendah | 1–4 |
| Sedang | 5–9 |
| Tinggi | 10–15 |
| Sangat Tinggi | 16–25 |

Status evaluasi: *Dapat Diterima* (≤ appetite), *Dipantau* (≤ tolerance), *Perlu Penanganan*
(> tolerance), *Perlu Eskalasi* (16–19), *Kritis* (≥ 20). Appetite dan tolerance diatur per kategori
di Taksonomi & Appetite.

## Langkah berikutnya (usulan)

1. Validasi alur dengan calon pengguna (Risk Officer, Risk Owner, pimpinan).
2. Tetapkan design system final dan pindahkan ke Figma / komponen produksi.
3. Rancang skema basis data dan REST API (integrasi HR, ERP, ITSM, SIEM).
4. Implementasikan backend, autentikasi (SSO), dan RBAC sesungguhnya.
