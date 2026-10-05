# ManRisk ERM — Spesifikasi Pengembangan Aplikasi

**Integrated Enterprise Risk Management Platform berbasis ISO 31000:2018**

| | |
|---|---|
| Dokumen | Spesifikasi kebutuhan & rancangan teknis (pra-pengembangan) |
| Versi | 1.0 — 5 Oktober 2026 |
| Status | Draf untuk disetujui sebelum pengembangan dimulai |
| Acuan | Rancangan fitur ManRisk (35 butir), purwarupa `manrisk.semestateknologiutama.com`, ISO 31000:2018 |
| Pemilik | PT Semesta Teknologi Utama |

> Dokumen ini menjadi dasar bersama bagi pemilik produk, perancang, pengembang, dan penguji. Setiap perubahan ruang lingkup setelah dokumen disetujui dicatat pada bagian **Riwayat perubahan** di akhir dokumen.

---

## Daftar isi

1. [Pendahuluan](#1-pendahuluan)
2. [Gambaran produk](#2-gambaran-produk)
3. [Pengguna & peran (RBAC)](#3-pengguna--peran-rbac)
4. [Kebutuhan fungsional per modul](#4-kebutuhan-fungsional-per-modul)
5. [Aturan bisnis & rumus perhitungan](#5-aturan-bisnis--rumus-perhitungan)
6. [Mesin status & alur kerja](#6-mesin-status--alur-kerja)
7. [Kebutuhan non-fungsional](#7-kebutuhan-non-fungsional)
8. [Arsitektur & tumpukan teknologi](#8-arsitektur--tumpukan-teknologi)
9. [Model data](#9-model-data)
10. [Desain API](#10-desain-api)
11. [Desain antarmuka (UI/UX)](#11-desain-antarmuka-uiux)
12. [Notifikasi & early warning](#12-notifikasi--early-warning)
13. [Pelaporan & ekspor](#13-pelaporan--ekspor)
14. [AI Risk Assistant](#14-ai-risk-assistant)
15. [Integrasi sistem eksternal](#15-integrasi-sistem-eksternal)
16. [Keamanan & kepatuhan](#16-keamanan--kepatuhan)
17. [Infrastruktur, lingkungan & deployment](#17-infrastruktur-lingkungan--deployment)
18. [Rencana pengembangan bertahap](#18-rencana-pengembangan-bertahap)
19. [Strategi pengujian & kriteria penerimaan](#19-strategi-pengujian--kriteria-penerimaan)
20. [Migrasi data & go-live](#20-migrasi-data--go-live)
21. [Risiko proyek & mitigasinya](#21-risiko-proyek--mitigasinya)
22. [Lampiran](#22-lampiran)

---

## 1. Pendahuluan

### 1.1 Latar belakang

Organisasi sektor publik dan swasta di Indonesia (kementerian/lembaga, pemerintah daerah, BUMN/BUMD, perguruan tinggi, rumah sakit, perusahaan) diwajibkan atau didorong menerapkan manajemen risiko (SPIP, Permen BUMN, OJK, akreditasi). Praktik yang umum masih berupa *risk register* di Excel: tidak terintegrasi dengan sasaran, kontrol, KRI, dan insiden; sulit dipantau; dan tidak meninggalkan jejak audit.

ManRisk ERM dirancang sebagai **satu platform** untuk mengidentifikasi, menganalisis, mengevaluasi, menangani, memantau, dan melaporkan risiko organisasi secara terintegrasi, terukur, terdokumentasi, dan real-time, dengan alur:

```
STRATEGY → OBJECTIVE → RISK → CONTROL → TREATMENT → KRI → MONITORING → INCIDENT → IMPROVEMENT
```

### 1.2 Tujuan dokumen

- Menetapkan **ruang lingkup** aplikasi versi 1.0 dan versi berikutnya.
- Menjadi **kontrak kebutuhan** antara pemilik produk dan tim pengembang.
- Menetapkan **keputusan teknis** (arsitektur, stack, model data, API) agar pengembangan tidak dimulai dari nol.
- Menjadi dasar **rencana kerja, estimasi, dan kriteria penerimaan**.

### 1.3 Ruang lingkup

**Termasuk (v1.0):** seluruh proses ISO 31000 klausul 6 (komunikasi & konsultasi, konteks & kriteria, penilaian risiko, perlakuan, pemantauan & reviu, pencatatan & pelaporan), pengelolaan kontrol dan KRI, insiden & loss event, alur persetujuan, dokumen & bukti, audit trail, laporan, administrasi, dan asisten AI dasar.

**Termasuk (v1.x–v2.0):** multi-organisasi (tenant), integrasi sistem eksternal, notifikasi WhatsApp, laporan eksekutif AI penuh, aplikasi seluler (PWA).

**Tidak termasuk:** manajemen audit internal lengkap (hanya tindak lanjut temuan), manajemen aset, manajemen proyek, GRC lintas standar selain ISO 31000 (dapat ditambahkan sebagai modul terpisah).

### 1.4 Acuan

- ISO 31000:2018 *Risk management — Guidelines*
- IEC 31010:2019 *Risk assessment techniques* (acuan teknik penilaian)
- UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP)
- Purwarupa UI/UX ManRisk (repositori `DrOsmond-STU/manrisk`, 22 layar, data demo 127 risiko)
- Rancangan fitur ManRisk (35 butir) — lihat Lampiran A untuk pemetaan

### 1.5 Istilah

| Istilah | Arti dalam dokumen ini |
|---|---|
| Risiko inheren | Tingkat risiko sebelum memperhitungkan kontrol yang ada |
| Risiko residual | Tingkat risiko setelah kontrol eksisting berjalan |
| Risiko target | Tingkat risiko yang ingin dicapai setelah perlakuan selesai |
| Risk appetite | Batas risiko yang bersedia diambil organisasi untuk mencapai sasaran |
| Risk tolerance | Penyimpangan di sekitar appetite yang masih dapat diterima dengan pemantauan |
| Kontrol | Pengendalian yang sudah berjalan (preventif/detektif/korektif) |
| Perlakuan (treatment) | Opsi penanganan risiko: hindari, kurangi, bagikan, terima |
| Action plan | Tindakan konkret untuk melaksanakan perlakuan, dengan PIC dan tenggat |
| KRI | Key Risk Indicator, indikator dini yang menandakan risiko meningkat |
| Insiden | Risiko yang benar-benar terjadi |
| Loss event | Catatan kerugian dari insiden (dasar analisis historis) |
| Tenant | Organisasi terpisah dalam satu instalasi (multi-organisasi) |

---

## 2. Gambaran produk

### 2.1 Posisi produk

ManRisk diposisikan sebagai **Integrated Risk Management System**, bukan "aplikasi risk register". Pembedanya: keterkaitan sasaran → risiko → kontrol → KRI → insiden → perbaikan dalam satu basis data, dashboard real-time, alur persetujuan berjenjang, audit trail penuh, dan asisten AI.

### 2.2 Pengguna target

| Segmen | Contoh | Kebutuhan dominan |
|---|---|---|
| Kementerian/Lembaga, Pemda | Biro/Inspektorat, unit kepatuhan | SPIP, pelaporan berjenjang, audit trail |
| BUMN/BUMD | Divisi manajemen risiko | Appetite per kategori, KRI, laporan Direksi/Komisaris |
| Perguruan tinggi, rumah sakit | Satuan penjaminan mutu | Akreditasi, risiko layanan, insiden |
| Perusahaan swasta | Tim GRC | Integrasi ERP/ITSM, dashboard eksekutif |

### 2.3 Prinsip desain

1. **Satu sumber kebenaran**: setiap angka di dashboard dihitung dari data transaksional, bukan diketik ulang.
2. **Terstruktur sejak input**: risk statement wajib berformat *Karena [penyebab], dapat terjadi [peristiwa], sehingga menyebabkan [dampak]*.
3. **Dapat diaudit**: semua perubahan tercatat (siapa, kapan, nilai lama → baru).
4. **Dikonfigurasi, bukan diprogram ulang**: skala, matriks, appetite, taksonomi, alur persetujuan adalah data.
5. **Berjalan di hosting biasa**: tanpa ketergantungan infrastruktur mahal (lihat §8).

### 2.4 Peta modul

```
DASHBOARD            Executive Dashboard · Risk Dashboard · KRI Dashboard
MANAJEMEN RISIKO     Konteks & Kriteria · Identifikasi · Risk Register · Analisis & Evaluasi · Perlakuan · Residual · Review
KONTROL              Control Register · Control Assessment · Control Effectiveness
PEMANTAUAN           KRI · Early Warning · Risk Trend · Risk Incident · Loss Event
PENANGANAN           Mitigation Plan · Action Plan · Corrective Action · Improvement Plan
TATA KELOLA          Risk Appetite · Risk Criteria · Taxonomy · Risk Policy · Framework ISO 31000 · Persetujuan
PELAPORAN            Register · Profile · Heatmap · Management Report · KRI Report · Executive Report
DOKUMEN              Evidence · Policy · SOP · Documents
ADMINISTRASI         Organisasi · Pengguna & Akun · Peran · Workflow · Master Data · Audit Trail
AI ASSISTANT         Identify · Analyze · Recommend Treatment · Summary · Generate Report
```

---

## 3. Pengguna & peran (RBAC)

### 3.1 Peran

| Peran | Deskripsi | Cakupan data |
|---|---|---|
| Super Admin | Mengelola seluruh sistem, akun, dan konfigurasi | Seluruh organisasi |
| Risk Administrator | Mengelola konfigurasi risiko (kriteria, taksonomi, appetite, master data) | Seluruh organisasi |
| Risk Manager | Mereviu, mengoordinasikan, menyetujui tahap 3 | Seluruh organisasi |
| Risk Officer | Mengidentifikasi dan mengelola risiko unitnya | Unit kerja sendiri (dan unit di bawahnya) |
| Risk Owner | Bertanggung jawab atas risiko; menyetujui tahap 2 | Risiko yang dimilikinya / unitnya |
| Management | Melihat dashboard, menyetujui tahap 4 | Seluruh organisasi (baca) |
| Auditor | Baca saja + akses bukti & audit trail | Seluruh organisasi (baca) |

### 3.2 Matriks hak akses

| Hak | Super Admin | Risk Admin | Risk Manager | Risk Officer | Risk Owner | Management | Auditor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Lihat dashboard | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Kelola risk register (buat/ubah) | ✓ | ✓ | ✓ | ✓ (unit) | ✓ (milik) | – | – |
| Ubah skor risiko | ✓ | ✓ | ✓ | ✓ (unit) | – | – | – |
| Kelola mitigasi & progres | ✓ | ✓ | ✓ | ✓ | ✓ | – | – |
| Setujui / tolak | ✓ | – | ✓ (tahap 3) | – | ✓ (tahap 2) | ✓ (tahap 4) | – |
| Kelola kriteria, taksonomi, appetite | ✓ | ✓ | ✓ | – | – | – | – |
| Kelola pengguna & peran | ✓ | – | – | – | – | – | – |
| Lihat audit trail | ✓ | ✓ | ✓ | – | – | – | ✓ |
| Unduh laporan | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Kelola kontrol & penilaian efektivitas | ✓ | ✓ | ✓ | ✓ (unit) | ✓ (milik) | – | – |
| Catat insiden | ✓ | ✓ | ✓ | ✓ | ✓ | – | – |
| Kelola KRI & nilai | ✓ | ✓ | ✓ | ✓ (unit) | – | – | – |
| Gunakan AI Assistant | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – |

Aturan tambahan:

- Hak **berbasis cakupan**: Risk Officer/Owner hanya melihat dan mengubah data unitnya (dan sub-unit) kecuali diberi cakupan lebih luas oleh Super Admin.
- Pemisahan tugas: pengaju tidak dapat menyetujui pengajuannya sendiri pada tahap mana pun.
- Semua pemeriksaan hak dilakukan **di server**; tampilan hanya menyembunyikan tombol.

---

## 4. Kebutuhan fungsional per modul

Format tiap butir: **ID** — kebutuhan. *Kriteria penerimaan* ditulis ringkas; detail skenario uji ada di §19.

### 4.1 Dashboard (fitur #1, #24)

- **F-DSH-01** Executive Dashboard menampilkan profil risiko: total, jumlah per level residual (Sangat Tinggi/Tinggi/Sedang/Rendah) beserta pembanding inheren, risiko baru periode ini, ditutup, dalam penanganan, melewati target.
- **F-DSH-02** Heatmap 5×5 dengan sakelar inheren/residual; klik sel membuka register tersaring ke sel tersebut.
- **F-DSH-03** Top 10 risiko (urut skor residual, lalu inheren) dengan tren naik/stabil/turun.
- **F-DSH-04** Tren skor residual rata-rata 12 bulan; distribusi level per triwulan (kolom bertumpuk).
- **F-DSH-05** Realisasi mitigasi (% rata-rata progres action plan aktif) dan efektivitas mitigasi (% risiko yang turun ≥ 1 level dari inheren ke residual).
- **F-DSH-06** Arah risiko dibanding periode lalu: meningkat / stabil / menurun.
- **F-DSH-07** Perjalanan risiko: inheren → setelah kontrol → proyeksi setelah mitigasi → target (contoh 25 → 16 → 9 → 4).
- **F-DSH-08** Risiko per kategori, per sasaran strategis, per unit kerja, per proses bisnis.
- **F-DSH-09** Ringkasan AI satu paragraf (lihat §14), diperbarui saat data berubah atau terjadwal.
- **F-DSH-10** Risk Dashboard (operasional): status mitigasi, emerging risks, peringatan terbaru, aktivitas terbaru.
- **F-DSH-11** Filter global: organisasi (tenant), periode, unit kerja; filter tersimpan per pengguna.
- *Penerimaan*: setiap angka dapat ditelusuri ke daftar data sumbernya (klik → daftar); angka profil = hasil hitung dari register pada saat itu.

### 4.2 Organisasi & struktur risiko (fitur #2, #27)

- **F-ORG-01** Pohon organisasi tak terbatas kedalaman: Organisasi → Direktorat/Deputi → Biro → Bagian → Subbagian; tiap simpul bertipe.
- **F-ORG-02** Program → Kegiatan → Proses bisnis sebagai struktur terpisah yang dapat dikaitkan ke unit.
- **F-ORG-03** Jabatan dan penugasan peran risiko per unit (Risk Owner, Risk Coordinator, Risk Officer, Approver).
- **F-ORG-04** Multi-organisasi (tenant) dengan data terisolasi; satu pengguna dapat berada di lebih dari satu tenant (v1.5).
- **F-ORG-05** Impor struktur dari Excel/CSV; sinkronisasi dari sistem HR (v2).

### 4.3 Ruang lingkup, konteks & kriteria (fitur #3)

- **F-CTX-01** Penetapan ruang lingkup per objek analisis: nama objek/proses, tujuan, batasan, periode, unit terkait, area yang dicakup; riwayat versi.
- **F-CTX-02** Analisis konteks internal (struktur, SDM, teknologi, keuangan, infrastruktur, kebijakan, proses, budaya) dan eksternal (regulasi, politik, ekonomi, sosial, teknologi, lingkungan, stakeholder, industri) dengan sifat kekuatan/kelemahan/peluang/ancaman.
- **F-CTX-03** Catatan komunikasi & konsultasi pemangku kepentingan (FGD, rapat, sosialisasi) dengan tanggal, peserta, keputusan, lampiran.
- **F-CTX-04** Kriteria risiko dapat dikonfigurasi Risk Administrator: skala kemungkinan (1–5, deskripsi & frekuensi), skala dampak multidimensi (keuangan, operasional, reputasi, hukum, keselamatan, dst. — dimensi dapat ditambah), matriks level, ambang level, appetite/tolerance/capacity per kategori, kriteria penerimaan.
- **F-CTX-05** Perubahan kriteria **berversi**: risiko yang dinilai dengan versi lama tetap menyimpan versi kriterianya; sistem menawarkan penilaian ulang massal.

### 4.4 Identifikasi risiko (fitur #4, #30)

- **F-IDN-01** Wizard 5 langkah: konteks (unit, proses, sasaran, kategori, pemilik, sumber) → pernyataan risiko (penyebab, peristiwa, dampak) → analisis (inheren & residual) → perlakuan (opsi + action plan awal) → tinjau & ajukan.
- **F-IDN-02** Pernyataan risiko disusun otomatis dari tiga komponen: *Karena [CAUSE], dapat terjadi [EVENT], sehingga menyebabkan [IMPACT]*; pratinjau langsung.
- **F-IDN-03** Sumber penyebab: Internal/Eksternal × People/Process/Technology/Infrastructure/Regulation/Financial/Third Party (dapat ditambah).
- **F-IDN-04** Beberapa penyebab dan beberapa dampak per risiko (v1.5; v1.0 satu teks per komponen).
- **F-IDN-05** Saran AI: kandidat risiko dari nama proses; satu klik mengisi formulir.
- **F-IDN-06** Draf tersimpan otomatis; pengajuan masuk alur persetujuan.
- **F-IDN-07** Deteksi duplikat: peringatan bila pernyataan mirip dengan risiko yang ada di unit yang sama (kemiripan teks ≥ 80%).

### 4.5 Risk register (fitur #5)

- **F-REG-01** Daftar seluruh risiko dengan kolom: ID, nama, unit, sasaran, proses, penyebab, peristiwa, dampak, kontrol eksisting, kemungkinan, dampak, skor, level, pemilik, perlakuan, action plan, target, residual, status.
- **F-REG-02** Pencarian teks penuh; filter level, unit, kategori, status, pemilik, sasaran, periode; urutan; paginasi; kolom dapat dipilih; filter tersimpan.
- **F-REG-03** Penomoran ID otomatis per organisasi dengan pola yang dapat diatur (misal `R-{tahun}-{unit}-{urut}`), tidak pernah dipakai ulang.
- **F-REG-04** Halaman detail risiko dengan tab: Ringkasan, Analisis (posisi di matriks: I/R/P/T), Kontrol, Mitigasi, KRI, Insiden, Dokumen, Riwayat.
- **F-REG-05** Versi risiko: setiap perubahan skor/pernyataan membuat versi baru; perbandingan antarversi.
- **F-REG-06** Ekspor Excel/PDF sesuai filter aktif; impor dari templat Excel dengan validasi dan pratinjau kesalahan.
- **F-REG-07** Penutupan risiko memerlukan alasan dan persetujuan; risiko tertutup tetap terbaca (read-only).

### 4.6 Analisis & evaluasi (fitur #6, #7, #8, #12, #32)

- **F-ANA-01** Skor = kemungkinan × dampak; level dari matriks terkonfigurasi; dihitung otomatis dan tidak dapat diketik manual.
- **F-ANA-02** Nilai dampak = nilai tertinggi di antara dimensi dampak yang diisi (dapat diubah menjadi rata-rata tertimbang per konfigurasi).
- **F-ANA-03** Tiga titik penilaian: inheren, residual (setelah kontrol eksisting), target; proyeksi setelah mitigasi dihitung dari action plan yang menyatakan penurunan.
- **F-ANA-04** Evaluasi otomatis terhadap appetite/tolerance kategori: Dapat Diterima, Dipantau, Perlu Penanganan, Perlu Eskalasi, Kritis (rumus §5.3).
- **F-ANA-05** Matriks/heatmap interaktif; kalkulator risiko untuk simulasi.
- **F-ANA-06** Perubahan skor residual ≥ 1 level memicu pengajuan persetujuan "Perubahan Skor" dan peringatan bila naik.
- **F-ANA-07** Dukungan teknik penilaian tambahan (v2): bow-tie (penyebab–kontrol–dampak), skor kuantitatif (nilai rupiah × probabilitas).

### 4.7 Perlakuan, action plan & monitoring mitigasi (fitur #9, #10, #11)

- **F-TRT-01** Opsi perlakuan: Hindari, Kurangi, Bagikan, Terima; pilihan "Terima" di atas appetite wajib alasan dan persetujuan Management.
- **F-TRT-02** Action plan per risiko (banyak): uraian, PIC (pengguna), unit, anggaran, prioritas, target tanggal, progres 0–100 %, bukti, status, penurunan yang diharapkan (Δ kemungkinan/dampak).
- **F-TRT-03** Status action plan dihitung: Belum Mulai, Berjalan, Selesai, Terlambat (tenggat lewat & < 100 %), Dibatalkan (dengan alasan).
- **F-TRT-04** Pembaruan progres mencatat tanggal, nilai lama → baru, catatan, bukti; riwayat progres membentuk grafik.
- **F-TRT-05** Tampilan tabel dan kanban; filter PIC/unit/status; "action plan saya".
- **F-TRT-06** Notifikasi H-7, H-1, dan saat lewat tenggat ke PIC dan Risk Owner; eskalasi ke Risk Manager setelah 14 hari terlambat.
- **F-TRT-07** Penyelesaian action plan memerlukan bukti dan persetujuan Risk Owner (dapat dinonaktifkan per konfigurasi).

### 4.8 Kontrol & efektivitas (fitur #13, #14)

- **F-CTL-01** Control register: ID, nama, tujuan, deskripsi, pemilik, frekuensi, jenis (preventif/detektif/korektif), sifat (manual/otomatis), bukti, risiko terkait (banyak-ke-banyak).
- **F-CTL-02** Penilaian efektivitas desain dan operasi (1 Tidak Efektif … 4 Sangat Efektif) dengan tanggal uji, penguji, bukti, catatan; efektivitas keseluruhan = minimum keduanya.
- **F-CTL-03** Jadwal pengujian kontrol berkala sesuai frekuensi; pengingat jatuh tempo.
- **F-CTL-04** Kontrol yang dinilai Tidak Efektif otomatis membuka "kegagalan kontrol" sebagai masukan Improvement Plan dan memicu peninjauan residual risiko terkait.
- **F-CTL-05** Daftar risiko tanpa kontrol (kesenjangan pengendalian).

### 4.9 KRI & early warning (fitur #15, #16)

- **F-KRI-01** Definisi KRI: nama, satuan, risiko terkait, sumber data (manual/API), frekuensi, arah (naik = buruk / turun = buruk), ambang normal/waspada/kritis, pemilik.
- **F-KRI-02** Input nilai berkala (manual, impor Excel, API) dengan tanggal periode; riwayat 12+ bulan; sparkline.
- **F-KRI-03** Status KRI dihitung dari nilai terakhir vs ambang; perubahan status memicu peringatan.
- **F-KRI-04** Early warning: aturan terkonfigurasi — KRI melewati ambang, skor residual naik, action plan terlambat, dokumen/sertifikat kedaluwarsa, kontrol belum diuji; kanal: dashboard, email, WhatsApp (v1.5), push (v2).
- **F-KRI-05** Umpan peringatan dengan status dibaca/ditindaklanjuti dan tautan ke objeknya.

### 4.10 Insiden & loss event (fitur #17, #18)

- **F-INC-01** Pencatatan insiden: ID, tanggal/jam, lokasi, risiko terkait (boleh kosong lalu dikaitkan), kronologi, penyebab, dampak, kerugian (nilai & jenis), respons, tindakan korektif, bukti, status (Dilaporkan, Investigasi, Tindakan Korektif, Ditutup).
- **F-INC-02** Pelaporan cepat (formulir singkat) oleh pengguna mana pun; dilengkapi kemudian oleh Risk Officer.
- **F-INC-03** Keterkaitan Risiko → Kontrol → Insiden → Tindakan korektif → Improvement.
- **F-INC-04** Loss event database: ringkasan kerugian per tahun/kategori/unit; ekspor; dipakai sebagai referensi saat menilai kemungkinan/dampak.
- **F-INC-05** Insiden pada risiko yang belum terdaftar menawarkan pembuatan risiko baru dari data insiden.

### 4.11 Risk review (fitur #19)

- **F-REV-01** Siklus reviu terjadwal: bulanan, triwulanan, semester, tahunan; dapat dibuat ad hoc.
- **F-REV-02** Lembar reviu per risiko: skor sebelumnya → saat ini, tren, catatan, keputusan (lanjut/ubah perlakuan/tutup), penandatangan.
- **F-REV-03** Snapshot register pada tiap periode (dasar grafik tren & perbandingan antarperiode).
- **F-REV-04** Berita acara reviu (PDF) dengan daftar hadir dan tanda tangan elektronik sederhana (nama, waktu, IP).

### 4.12 Alur persetujuan (fitur #20)

- **F-WFL-01** Alur berjenjang terkonfigurasi: Risk Officer → Risk Owner → Risk Manager → Direktur/Management; jumlah tahap dan peran per tahap dapat diubah per jenis pengajuan.
- **F-WFL-02** Jenis pengajuan: risiko baru, perubahan skor, rencana mitigasi, penerimaan risiko, penutupan risiko, perubahan kriteria.
- **F-WFL-03** Aksi: Submit, Review, Revisi (kembali ke pengaju), Approve, Reject, dengan catatan wajib untuk Revisi/Reject.
- **F-WFL-04** Aturan otomatis: skor residual ≥ 16 wajib sampai tahap 4; SLA per tahap dengan pengingat dan eskalasi.
- **F-WFL-05** Kotak masuk persetujuan per pengguna; riwayat keputusan di audit trail; delegasi persetujuan saat cuti (v1.5).

### 4.13 Dokumen & bukti (fitur #21)

- **F-DOC-01** Unggah dokumen (PDF, DOCX, XLSX, JPG/PNG, maks. 25 MB) ke risiko, kontrol, action plan, insiden, reviu, kebijakan.
- **F-DOC-02** Metadata: jenis (SOP, kebijakan, berita acara, foto, laporan, hasil audit, kontrak, sertifikat, hasil pengujian), versi, pengunggah, tanggal, kedaluwarsa, status (draf/review/disetujui).
- **F-DOC-03** Versioning: unggah ulang membuat versi baru; versi lama tetap dapat diunduh.
- **F-DOC-04** Pengingat kedaluwarsa (H-60, H-30, H-7); daftar dokumen kedaluwarsa.
- **F-DOC-05** Pemindaian virus pada unggahan (ClamAV bila tersedia) dan pemeriksaan tipe berkas sebenarnya (bukan hanya ekstensi).

### 4.14 Audit trail (fitur #22)

- **F-AUD-01** Setiap pembuatan/perubahan/penghapusan logis mencatat: pengguna, waktu, objek, field, nilai lama, nilai baru, IP, user agent, konteks (pengajuan/aksi).
- **F-AUD-02** Tidak dapat diubah atau dihapus dari aplikasi; retensi minimal 5 tahun; ekspor CSV.
- **F-AUD-03** Tampilan per objek (tab Riwayat) dan global dengan filter pengguna/objek/aksi/rentang waktu.
- **F-AUD-04** Log keamanan terpisah: masuk, gagal, terkunci, keluar, ganti sandi, perubahan akun.

### 4.15 Pelaporan (fitur #23, #31) — lihat §13.

### 4.16 Taksonomi, appetite & kerangka (fitur #25, #26, #34)

- **F-GOV-01** Taksonomi risiko hierarkis (kategori → subkategori) dengan deskripsi dan contoh; dapat ditambah.
- **F-GOV-02** Appetite & tolerance per kategori (skor residual maksimum) dan pernyataan appetite organisasi (teks) dengan dasar penetapan dan tanggal.
- **F-GOV-03** Pemetaan Sasaran strategis → Program → Proses → Risiko → Kontrol → KRI; indikator kinerja utama per sasaran; cakupan kontrol per sasaran.
- **F-GOV-04** Modul kerangka ISO 31000: daftar prinsip (klausul 4), kerangka (5), proses (6) dengan status penerapan, bukti, dan tautan ke modul; self-assessment maturitas (skala 1–5) tahunan.
- **F-GOV-05** Kebijakan & SOP manajemen risiko sebagai dokumen terkendali dengan versi.

### 4.17 Perbaikan berkelanjutan (fitur #33)

- **F-IMP-01** Sumber masukan otomatis: insiden, temuan audit, kegagalan kontrol, pelanggaran KRI, efektivitas mitigasi rendah, tren memburuk, lessons learned.
- **F-IMP-02** Improvement plan: uraian, sumber, referensi, PIC, target, status, bukti; terhubung ke objek sumbernya.
- **F-IMP-03** Lessons learned per insiden/reviu, dapat dicari.

### 4.18 Administrasi, pengguna & akun (fitur #28)

- **F-ADM-01** Pengelolaan akun: tambah (sandi sementara sekali tampil), ubah, reset sandi, aktif/nonaktif, hapus akun yang belum pernah masuk; penugasan peran dan cakupan unit.
- **F-ADM-02** Kebijakan sandi: ≥ 10 karakter, huruf + angka; sandi sementara wajib diganti saat masuk; riwayat 5 sandi terakhir tidak boleh dipakai ulang (v1.5).
- **F-ADM-03** Master data: unit, jabatan, kategori, sumber penyebab, jenis dokumen, jenis insiden, satuan KRI, periode.
- **F-ADM-04** Konfigurasi workflow, notifikasi, penomoran ID, format laporan (logo, kop).
- **F-ADM-05** Pengaturan tenant (v1.5): nama, logo, domain, bahasa, zona waktu, kebijakan sandi.

### 4.19 AI Risk Assistant (fitur #30, #31) — lihat §14.

### 4.20 Integrasi (fitur #29) — lihat §15.

---

## 5. Aturan bisnis & rumus perhitungan

### 5.1 Skala & matriks (konfigurasi bawaan)

Kemungkinan (L): 1 Rare, 2 Unlikely, 3 Possible, 4 Likely, 5 Almost Certain.
Dampak (I): 1 Insignificant, 2 Minor, 3 Moderate, 4 Major, 5 Severe.

| Level | Skor L × I | Warna | Kriteria penerimaan bawaan |
|---|---|---|---|
| Rendah | 1–4 | hijau | Dapat diterima; dipantau tahunan |
| Sedang | 5–9 | kuning | Dapat diterima dengan pemantauan triwulanan |
| Tinggi | 10–15 | oranye | Wajib rencana mitigasi; dipantau bulanan |
| Sangat Tinggi | 16–25 | merah | Tidak dapat diterima; eskalasi ≤ 2×24 jam |

Matriks disimpan sebagai tabel 25 sel (L, I → level), sehingga organisasi dapat memakai matriks tidak simetris (misal 4×5=20 dianggap Sangat Tinggi, 5×3=15 Tinggi).

### 5.2 Skor

```
skor_inheren  = L_inheren  × I_inheren
skor_residual = L_residual × I_residual
skor_target   = L_target   × I_target
skor_proyeksi = max(1, L_residual − ΣΔL_actionplan_aktif) × max(1, I_residual − ΣΔI_actionplan_aktif)
level(skor)   = matriks[L][I]           (bukan dari rentang skor bila matriks tidak simetris)
```

Nilai dampak bila multidimensi: `I = max(I_keuangan, I_operasional, I_reputasi, I_hukum, …)` (bawaan), atau rata-rata tertimbang bila dikonfigurasi.

### 5.3 Status evaluasi

Dengan `app` = appetite kategori, `tol` = tolerance kategori (skor residual):

| Urutan cek | Kondisi | Status |
|---|---|---|
| 1 | skor ≥ 20 | Kritis |
| 2 | skor ≥ 16 | Perlu Eskalasi |
| 3 | skor > tol | Perlu Penanganan |
| 4 | skor > app | Dipantau |
| 5 | selainnya | Dapat Diterima |

Ambang 16/20 dapat diatur per organisasi.

### 5.4 Tren

Tren risiko dihitung dari snapshot periode sebelumnya: `naik` bila skor residual sekarang > sebelumnya, `turun` bila <, `stabil` bila =. Tren organisasi = jumlah risiko per arah.

### 5.5 Mitigasi

```
realisasi_mitigasi (%)  = rata-rata progres action plan yang tidak dibatalkan
efektivitas_mitigasi (%) = jumlah risiko dengan level(residual) < level(inheren) ÷ jumlah risiko × 100
status_action = Dibatalkan | Selesai (progres = 100) | Terlambat (tenggat < hari ini) | Belum Mulai (progres = 0) | Berjalan
```

### 5.6 Kontrol

```
efektivitas_keseluruhan = min(efektivitas_desain, efektivitas_operasi)   // skala 1–4
kontrol_efektif         = efektivitas_keseluruhan ≥ 3
```

### 5.7 KRI

Arah naik = buruk: `Normal` bila nilai ≤ ambang_normal; `Waspada` bila ambang_normal < nilai ≤ ambang_kritis; `Kritis` bila nilai > ambang_kritis. Arah turun = buruk dibalik. Perubahan status ke Waspada/Kritis membuat peringatan; kembali ke Normal menutupnya.

### 5.8 Penomoran

`R-{tahun}-{kode_unit}-{urut 3 digit}` (contoh `R-2026-TI-014`), berurutan per organisasi, tidak dipakai ulang meski risiko dihapus logis. Pola sama untuk `C-` (kontrol), `A-` (action plan), `K-` (KRI), `INC-` (insiden), `WF-` (pengajuan).

---

## 6. Mesin status & alur kerja

### 6.1 Status risiko

```
Draft → Menunggu Persetujuan → Dalam Penanganan ⇄ Dipantau → Ditutup
   ↑            │ (Revisi)                         (Penutupan disetujui)
   └────────────┘
```

| Transisi | Pemicu | Syarat |
|---|---|---|
| Draft → Menunggu Persetujuan | Submit | Pernyataan, skor inheren & residual, pemilik terisi |
| Menunggu → Draft | Revisi/Reject | Catatan wajib |
| Menunggu → Dalam Penanganan | Approve tahap terakhir | Residual > appetite |
| Menunggu → Dipantau | Approve tahap terakhir | Residual ≤ appetite |
| Dalam Penanganan → Dipantau | Semua action plan selesai & residual ≤ appetite | Persetujuan Risk Owner |
| Dipantau → Dalam Penanganan | Skor naik > appetite / KRI kritis | Otomatis + notifikasi |
| → Ditutup | Pengajuan penutupan disetujui | Alasan; risiko tidak relevan / diterima permanen |

### 6.2 Status action plan — dihitung (§5.5); hanya *Dibatalkan* yang diset manual (alasan wajib).

### 6.3 Status insiden

`Dilaporkan → Investigasi → Tindakan Korektif → Ditutup`; penutupan mensyaratkan penyebab, dampak, dan tindakan korektif terisi.

### 6.4 Alur persetujuan

```
[Pengaju] Submit → Tahap 1 Risk Owner → Tahap 2 Risk Manager → Tahap 3 Direktur/Management → Disetujui
                      │ Revisi ↩ pengaju        │ Reject ✕ (selesai)
```

- Jumlah tahap per jenis pengajuan dikonfigurasi (1–5). Tahap dapat dilewati otomatis bila peran tahap = pengaju (misal Risk Owner yang mengajukan).
- SLA per tahap (bawaan 3 hari kerja); lewat SLA → pengingat; 2× SLA → eskalasi ke peran tahap berikutnya.
- Persetujuan menyimpan: siapa, kapan, catatan, versi data yang disetujui (snapshot JSON).

### 6.5 Status dokumen

`Draf → Review → Disetujui → Kedaluwarsa`; dokumen kedaluwarsa tidak dapat dipakai sebagai bukti baru.

---

## 7. Kebutuhan non-fungsional

| Area | Kebutuhan |
|---|---|
| Kinerja | Halaman daftar ≤ 1,5 detik untuk 10.000 risiko (paginasi server); dashboard ≤ 2 detik dengan agregat ter-cache (diperbarui saat data berubah, maksimal 5 menit) |
| Skalabilitas | 50 tenant, 5.000 pengguna, 100.000 risiko, 1 juta baris audit per instalasi tanpa perubahan arsitektur |
| Ketersediaan | 99,5 % jam kerja; pemeliharaan terjadwal di luar jam kerja |
| Keamanan | Lihat §16; OWASP ASVS level 2 sebagai target |
| Audit | Semua perubahan data bisnis tercatat (§4.14) |
| Kompatibilitas | Chrome, Edge, Firefox, Safari 2 versi terakhir; layar 360 px–4K; tanpa plugin |
| Aksesibilitas | WCAG 2.1 AA: kontras, navigasi keyboard, label formulir, status tidak hanya lewat warna |
| Bahasa | Indonesia (bawaan), Inggris; semua teks lewat berkas terjemahan; format tanggal/angka lokal |
| Dokumentasi | Panduan pengguna per peran, panduan admin, dokumentasi API (OpenAPI), catatan rilis |
| Pencadangan | Basis data harian (retensi 30 hari) + berkas; uji pemulihan tiap 6 bulan; RPO 24 jam, RTO 8 jam |
| Observabilitas | Log aplikasi terstruktur, log kesalahan dengan notifikasi, metrik dasar (waktu respons, antrean) |
| Lisensi | Komponen pihak ketiga berlisensi permisif (MIT/BSD/Apache); daftar dipelihara |

---

## 8. Arsitektur & tumpukan teknologi

### 8.1 Keputusan

Dipilih arsitektur **aplikasi web monolitik modular** (server-rendered + komponen reaktif), bukan microservice, agar dapat dijalankan di hosting cPanel/VPS biasa seperti yang dipakai purwarupa, dengan jalur peningkatan ke kontainer.

| Lapisan | Pilihan | Alasan |
|---|---|---|
| Bahasa & kerangka | **PHP 8.3 + Laravel 11** | Tersedia di hosting cPanel yang dipakai (PHP 8.3), tim sudah menjalankan Laravel untuk aplikasi lain di server yang sama (artisan schedule/queue), ekosistem lengkap (auth, queue, mail, policy, audit) |
| Basis data | **MySQL 8 / MariaDB 10.6+** (cPanel) — PostgreSQL 15 sebagai alternatif VPS | Tersedia di hosting; fitur JSON, window function cukup untuk agregat |
| Antarmuka | **Vue 3 + Inertia.js + Vite**, CSS kustom dengan token desain purwarupa (tanpa Tailwind agar tema biru gradasi/tombol warna-warni dipertahankan) | Reaktivitas untuk wizard, kanban, heatmap; routing dan otorisasi tetap di server |
| Grafik | **Apache ECharts** (heatmap, bar, line, sparkline) | Interaktif, aksesibel, tanpa biaya |
| Antrean & jadwal | Laravel queue (driver `database`) + `schedule:run` lewat cron tiap menit | Tanpa Redis di shared hosting; dapat diganti Redis di VPS |
| Berkas | Penyimpanan lokal di luar docroot (`storage/app`), opsional S3-compatible | Keamanan unduhan lewat endpoint berotorisasi |
| PDF/Excel | Browsershot (Chromium) atau DomPDF untuk PDF; Laravel Excel untuk XLSX | Laporan dengan tata letak HTML yang sama dengan layar |
| Email | SMTP organisasi | — |
| WhatsApp (v1.5) | Penyedia API resmi (WhatsApp Business API) lewat adaptor | Dapat diganti tanpa mengubah modul notifikasi |
| AI | Adaptor LLM (Anthropic Claude API sebagai bawaan; penyedia lain lewat antarmuka yang sama) | Lihat §14 |
| Pencarian | MySQL full-text (v1); Meilisearch (v2) | — |
| Autentikasi | Sesi Laravel + Sanctum untuk API token; SSO (SAML/OIDC) v2 | — |

### 8.2 Diagram komponen

```
┌───────────── Browser (Vue 3 + Inertia) ─────────────┐
│ Dashboard · Register · Wizard · Kanban · Heatmap · … │
└───────────────┬──────────────────────────────────────┘
                │ HTTPS (sesi, CSRF)              ┌──── Integrasi ────┐
┌───────────────▼──────────────────────────────┐ │ HR · ERP · ITSM    │
│ Laravel 11                                   │◄┤ SIEM · Audit · BI  │
│  Controllers → Services → Repositories       │ │ (REST + webhook)   │
│  Policies (RBAC+cakupan) · Jobs · Events     │ └────────────────────┘
│  Modul: Risk · Control · KRI · Incident ·    │
│         Workflow · Report · AI · Admin       │
└──┬───────────┬───────────┬───────────┬───────┘
   │           │           │           │
 MySQL     Storage      Queue      LLM API
 (data,    (berkas,     (email,    (asisten,
  audit)    laporan)    WA, agregat) laporan)
```

### 8.3 Struktur kode (modular)

```
app/
  Domain/
    Risk/        (Models, Services, Policies, Events, Jobs)
    Control/
    Kri/
    Incident/
    Workflow/
    Report/
    Ai/
    Organization/
    Admin/
  Http/Controllers/{Modul}/
  Support/ (Scoring, Numbering, Audit, Tenancy)
resources/js/
  Pages/{Modul}/   Components/   design-tokens.css
database/migrations, seeders (kriteria bawaan, taksonomi, demo)
tests/Feature, tests/Unit
```

Prinsip: logika bisnis di *Service* (dapat diuji tanpa HTTP); perhitungan skor di satu kelas `Scoring`; semua query data bisnis melewati *global scope* tenant dan cakupan unit.

---

## 9. Model data

### 9.1 Entitas utama & relasi

```
organizations 1─n org_units (self-ref parent) 1─n users
objectives 1─n programs 1─n processes
risks n─1 org_units, n─1 objectives, n─1 processes, n─1 risk_categories, n─1 users(owner)
risks 1─n risk_versions, 1─n action_plans, 1─n kris, 1─n incidents, n─n controls, 1─n reviews, 1─n documents
controls 1─n control_assessments
action_plans 1─n action_progress, 1─n documents
kris 1─n kri_values, 1─n alerts
incidents 1─n loss_events, 1─n documents
approvals 1─n approval_steps
audit_logs (polimorfik ke semua)
```

### 9.2 Tabel & kolom kunci

Semua tabel memiliki `id` (ULID), `organization_id`, `created_at`, `updated_at`, `created_by`, `updated_by`, dan `deleted_at` (hapus logis) kecuali disebutkan lain.

| Tabel | Kolom penting |
|---|---|
| `organizations` | name, code, logo, settings(JSON: numbering, thresholds, locale) |
| `org_units` | parent_id, name, code, type (direktorat/biro/bagian/…), head_user_id, level, path |
| `users` | name, email (unik per org), password_hash, role, unit_id, scope(JSON unit ids), active, must_change_password, last_login_at, mfa_enabled, mfa_enabled_at, mfa_recovery_codes (HMAC) — tabel pendukung `mfa_codes`, `mfa_trusted_devices`, `system_settings` |
| `objectives` | code, name, kpi, period |
| `programs` | objective_id, name, budget |
| `processes` | program_id, unit_id, name |
| `risk_categories` | parent_id, name, name_en, description, appetite, tolerance |
| `criteria_versions` | version, effective_from, likelihood(JSON), impact(JSON dimensi), matrix(JSON 25 sel), thresholds(JSON), active |
| `risks` | code, name, unit_id, objective_id, process_id, category_id, owner_id, cause, event, impact, source_type, source_kind, treatment (avoid/reduce/share/retain), status, due_date, closed_at, closed_reason, current_version_id, criteria_version_id |
| `risk_versions` | risk_id, version, inherent_l, inherent_i, inherent_dims(JSON), residual_l, residual_i, residual_dims, target_l, target_i, note, approved_at, approved_by |
| `risk_snapshots` | risk_id, period (YYYY-MM), residual_score, level, status (untuk tren) |
| `controls` | code, name, objective, description, owner_id, frequency, type (preventive/detective/corrective), mode (manual/automated), last_tested_at |
| `risk_control` | risk_id, control_id |
| `control_assessments` | control_id, tested_at, tester_id, design_eff (1–4), operating_eff (1–4), note |
| `action_plans` | code, risk_id, title, pic_id, unit_id, budget, priority, due_date, progress, expected_dl, expected_di, status_manual (cancelled), cancel_reason |
| `action_progress` | action_plan_id, at, from_pct, to_pct, note |
| `kris` | code, name, unit, risk_id, owner_id, source (manual/api), frequency, direction (up_bad/down_bad), threshold_warn, threshold_crit |
| `kri_values` | kri_id, period, value, entered_by, source_ref |
| `alerts` | type, severity, subject_type, subject_id, message, read_at, handled_at, handled_by |
| `incidents` | code, occurred_at, location, risk_id, title, chronology, cause, impact, loss_amount, loss_type, response, corrective_action, status |
| `loss_events` | incident_id, year, category_id, amount, description |
| `reviews` | period_type, period, risk_id, previous_score, current_score, trend, note, decision, reviewer_id, signed_at |
| `approvals` | code, type, subject_type, subject_id, requester_id, current_step, status, payload(JSON snapshot) |
| `approval_steps` | approval_id, step_no, role, approver_id, action, note, acted_at, due_at |
| `documents` | subject_type, subject_id, type, title, version, path, mime, size, hash, uploaded_by, expires_at, status |
| `improvements` | code, source_type, source_id, title, pic_id, due_date, status |
| `lessons` | subject_type, subject_id, text |
| `audit_logs` (tanpa deleted_at, append-only) | user_id, action, subject_type, subject_id, field, old_value, new_value, ip, user_agent, context, created_at |
| `auth_logs` (append-only) | user_id/email, event, ip, user_agent, detail, created_at |
| `notifications` | user_id, channel, type, payload, sent_at, read_at |
| `ai_interactions` | user_id, feature, prompt_hash, tokens_in, tokens_out, cost, created_at (tanpa menyimpan data sensitif) |

Indeks: `(organization_id, code)` unik; `(organization_id, unit_id, status)`; `(risk_id, period)` pada snapshot; full-text pada `risks(name, cause, event, impact)`.

### 9.3 Aturan integritas

- Risiko tidak dapat dihapus fisik; hapus logis hanya untuk Draft tanpa riwayat.
- Perubahan `residual_l/i` membuat `risk_versions` baru; `risks.current_version_id` menunjuk versi terakhir yang disetujui.
- Snapshot bulanan dibuat oleh job terjadwal pada hari pertama bulan (dan saat reviu ditutup).
- `audit_logs` ditulis oleh *observer* model, bukan oleh pemanggil, agar tidak terlewat.

---

## 10. Desain API

### 10.1 Prinsip

- REST JSON di bawah `/api/v1`; autentikasi sesi (aplikasi web) atau token Sanctum (integrasi) dengan cakupan izin.
- Paginasi kursor/halaman (`?page=&per_page=` maks 200), filter `?filter[field]=`, urutan `?sort=-residual_score`, pilihan kolom `?fields=`.
- Format kesalahan: `{ "error": { "code": "validation", "message": "…", "fields": { "name": ["…"] } } }` dengan status HTTP yang sesuai (400/401/403/404/409/422/429/500).
- Idempotensi untuk POST integrasi lewat header `Idempotency-Key`.
- Versi API dalam path; perubahan tidak kompatibel = versi baru.
- Spesifikasi OpenAPI 3.1 dihasilkan dari kode dan dipublikasikan di `/api/docs` (hanya pengguna masuk).

### 10.2 Endpoint utama

| Sumber daya | Endpoint | Catatan |
|---|---|---|
| Sesi | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`, `POST /auth/password` | Rate limit 5/15 menit per email+IP |
| Risiko | `GET/POST /risks`, `GET/PATCH /risks/{id}`, `POST /risks/{id}/submit`, `POST /risks/{id}/close`, `GET /risks/{id}/versions`, `GET /risks/{id}/history` | |
| Penilaian | `POST /risks/{id}/assessments` (inheren/residual/target) | Memicu versi & persetujuan |
| Kontrol | `GET/POST /controls`, `PATCH /controls/{id}`, `POST /controls/{id}/assessments`, `PUT /risks/{id}/controls` | |
| Action plan | `GET/POST /action-plans`, `PATCH /action-plans/{id}`, `POST /action-plans/{id}/progress`, `POST /action-plans/{id}/cancel` | |
| KRI | `GET/POST /kris`, `POST /kris/{id}/values`, `POST /kris/values:bulk` | Bulk untuk integrasi |
| Insiden | `GET/POST /incidents`, `PATCH /incidents/{id}`, `POST /incidents/{id}/close` | |
| Reviu | `GET/POST /reviews`, `POST /reviews/{id}/sign` | |
| Persetujuan | `GET /approvals?inbox=me`, `POST /approvals/{id}/approve|revise|reject` | |
| Dokumen | `POST /documents` (multipart), `GET /documents/{id}/download`, `DELETE` | Unduh lewat otorisasi, bukan URL publik |
| Dashboard | `GET /dashboard/executive`, `GET /dashboard/operational`, `GET /dashboard/heatmap?mode=` | Ter-cache |
| Laporan | `POST /reports/{jenis}` → job, `GET /reports/{jobId}` → status & tautan | Asinkron |
| AI | `POST /ai/identify`, `POST /ai/statement`, `POST /ai/treatment`, `POST /ai/analyze`, `POST /ai/summary`, `POST /ai/report` | Lihat §14 |
| Admin | `GET/POST/PATCH /users`, `POST /users/{id}/reset-password`, `GET /audit-logs`, `GET/PUT /settings/*` | Super Admin |
| Webhook keluar | `POST /webhooks` (daftar), peristiwa: `risk.updated`, `alert.created`, `approval.decided` | HMAC signature |

---

## 11. Desain antarmuka (UI/UX)

### 11.1 Sistem desain (dari purwarupa)

- **Warna**: biru gradasi `#062b63 → #1a7bd4` untuk sidebar dan header halaman; permukaan `#f1f5fb`/`#ffffff`; mode gelap `#071220`/`#0b1724`. Warna level risiko tetap: Rendah `#0ca30c`, Sedang `#fab219`, Tinggi `#ec835a`, Sangat Tinggi `#d03b3b`, selalu disertai label teks.
- **Tombol**: gradasi berwarna sesuai makna (PDF merah, Excel hijau, Word indigo, AI ungu, ubah kuning, tambah toska, ekspor cyan; lainnya bergiliran), bayangan sewarna, terangkat saat disorot; dimatikan untuk *reduced motion*.
- **Huruf**: Plus Jakarta Sans (judul), IBM Plex Sans (teks), IBM Plex Mono (angka/ID).
- **Komponen**: kartu sudut 14 px, chip level, pill status, heatmap 5×5, kalkulator matriks, stepper wizard, kanban, sparkline, timeline persetujuan, modal, drawer notifikasi, toast.
- Token disimpan di `design-tokens.css`; komponen Vue tidak memakai warna literal.

### 11.2 Navigasi

Sidebar tetap (desktop) / drawer (ponsel) dengan kelompok: Dashboard, Manajemen Risiko, Penanganan & Kontrol, Pemantauan, Tata Kelola, Pelaporan & Dokumen, Administrasi, AI. Menu yang tampil mengikuti peran. Bilah atas: pencarian global, pemilih organisasi/periode/unit, tema, notifikasi, menu akun.

### 11.3 Daftar layar v1.0 (acuan purwarupa)

| # | Layar | Komponen utama |
|---|---|---|
| 1 | Masuk | formulir, kunci 15 menit, paksa ganti sandi |
| 2 | Executive Dashboard | profil, heatmap, top 10, tren, mitigasi, perjalanan risiko, per kategori/sasaran, ringkasan AI |
| 3 | Risk Dashboard | KPI, distribusi per triwulan, status mitigasi, per unit/proses, emerging, peringatan, aktivitas |
| 4 | KRI & Early Warning | kartu KRI + sparkline + ambang, umpan peringatan, kanal |
| 5 | Konteks & Kriteria | tab ruang lingkup / konteks / kriteria (skala, matriks, appetite) |
| 6 | Identifikasi Risiko | wizard 5 langkah + saran AI |
| 7 | Risk Register | filter, tabel, paginasi, ekspor/impor |
| 8 | Detail Risiko | 8 tab |
| 9 | Analisis & Evaluasi | kalkulator, tabel evaluasi |
| 10 | Risk Review | hasil reviu, jadwal |
| 11 | Mitigasi & Action Plan | tabel/kanban, progres, bukti |
| 12 | Kontrol & Efektivitas | register, panel penilaian |
| 13 | Perbaikan Berkelanjutan | sumber, improvement plan, lessons |
| 14 | Insiden & Loss Event | daftar, detail rantai, loss database |
| 15 | Pemetaan Sasaran | rantai sasaran→KRI, cakupan |
| 16 | Taksonomi & Appetite | tabel kategori, pernyataan |
| 17 | Kerangka ISO 31000 | prinsip/kerangka/proses, maturitas |
| 18 | Persetujuan | kotak masuk, stepper, aksi |
| 19 | Laporan | katalog, AI report, riwayat |
| 20 | Dokumen & Bukti | unggah, tabel, kedaluwarsa |
| 21 | Organisasi | pohon, program/proses, RBAC, workflow |
| 22 | Pengguna & Akun | tabel, tambah/ubah/reset, log keamanan |
| 23 | Audit Trail | filter, tabel |
| 24 | AI Risk Assistant | percakapan, contoh pertanyaan |

### 11.4 Responsif & aksesibilitas

- Titik putus 1180 / 900 / 560 px; tabel lebar dalam kontainer gulir horizontal; kanban gulir.
- Semua kontrol dapat dijangkau keyboard; fokus terlihat; `aria-*` pada tab, dialog, menu; status warna selalu berpasangan dengan teks/ikon.
- Formulir: validasi inline, pesan kesalahan spesifik, simpan otomatis draf.

---

## 12. Notifikasi & early warning

| Pemicu | Penerima | Kanal bawaan |
|---|---|---|
| KRI masuk Waspada/Kritis | Pemilik KRI, Risk Owner, Risk Manager | Dashboard, email; WA bila Kritis |
| Skor residual naik ≥ 1 level | Risk Owner, Risk Manager | Dashboard, email |
| Action plan H-7 / H-1 / terlambat | PIC, Risk Owner | Dashboard, email |
| Terlambat > 14 hari | + Risk Manager | Email |
| Pengajuan menunggu / SLA lewat | Approver tahap aktif | Dashboard, email |
| Dokumen/sertifikat H-60/30/7 | Pengunggah, pemilik objek | Dashboard, email |
| Kontrol belum diuji sesuai frekuensi | Pemilik kontrol | Dashboard |
| Insiden baru | Risk Owner risiko terkait, Risk Manager | Dashboard, email, WA |

Preferensi per pengguna (kanal, ringkasan harian vs. langsung); templat pesan dapat diubah admin; semua pengiriman tercatat di `notifications`.

---

## 13. Pelaporan & ekspor

| Laporan | Isi | Format |
|---|---|---|
| Risk Register | Seluruh kolom register sesuai filter | Excel, PDF |
| Risk Profile | Ringkasan per level/kategori/unit, heatmap | PDF |
| Risk Heatmap | Matriks inheren & residual, daftar per sel | PDF, PNG |
| Top Risks | 10/20 risiko prioritas dengan perlakuan | PDF, Word |
| Risk Treatment | Rencana & realisasi action plan per unit | Excel, PDF |
| Residual Risk | Inheren → residual → target per risiko | Excel |
| KRI Report | Status, nilai 12 bulan, pelanggaran | PDF, Excel |
| Risk Incident Report | Insiden periode, kerugian, tindakan | PDF, Word |
| Risk Trend | Perbandingan antarperiode | PDF |
| Control Effectiveness | Hasil penilaian, kontrol tidak efektif | Excel, PDF |
| Mitigation Progress / Overdue Action | Progres & keterlambatan per unit/PIC | Excel |
| Risk Review Report | Berita acara reviu | PDF, Word |
| Executive Risk Report (AI) | Ringkasan eksekutif, top risk, tren, mitigasi, KRI, insiden, rekomendasi | PDF, Word |

Ketentuan: laporan dibuat **asinkron** (job) dengan notifikasi saat siap; kop/logo organisasi; nomor dan tanggal cetak; tersimpan di riwayat laporan dengan pembuat dan parameter; dapat dijadwalkan (misal KRI Report tiap tanggal 1).

---

## 14. AI Risk Assistant

### 14.1 Fitur

| Fitur | Masukan | Keluaran |
|---|---|---|
| Identify Risk | nama proses/unit/konteks | 5–10 kandidat risiko (nama, kategori, penyebab, peristiwa, dampak) |
| Risk Statement Generator | catatan bebas | pernyataan berformat Cause → Event → Impact |
| Treatment Recommendation | risiko + kontrol eksisting | opsi perlakuan, 3–6 action plan, perkiraan penurunan |
| Risk Analysis Assistant | risiko + riwayat skor + KRI + insiden | penjelasan perubahan, faktor pemicu |
| Risk Summary | agregat dashboard | paragraf ringkasan untuk pimpinan |
| Generate Report | periode | draf Executive Risk Report terstruktur |

### 14.2 Arsitektur

- Adaptor `LlmClient` dengan implementasi bawaan Anthropic Claude API (model terbaru yang tersedia saat implementasi; dikonfigurasi lewat `.env`), dapat diganti penyedia lain.
- **Grounding**: setiap permintaan menyertakan data terstruktur dari basis data (bukan dokumen bebas) dalam batas token; model diminta merujuk ID risiko/KRI nyata.
- **Keluaran terstruktur** (JSON schema) untuk kandidat risiko dan action plan agar dapat langsung mengisi formulir.
- **Guardrail**: tidak mengirim data pribadi pengguna/pemohon; prompt sistem membatasi ranah; keluaran selalu ditinjau manusia sebelum disimpan; label "disusun AI" pada laporan.
- **Biaya & kuota**: batas permintaan per pengguna/hari; pencatatan token dan biaya di `ai_interactions`; cache hasil ringkasan dashboard 15 menit.
- **Mode tanpa AI**: fitur AI dapat dimatikan per organisasi; aplikasi tetap lengkap tanpa AI.

---

## 15. Integrasi sistem eksternal

| Sistem | Arah | Mekanisme | Data |
|---|---|---|---|
| HR / kepegawaian (SIMPEG) | masuk | Sinkron terjadwal (REST/CSV) | unit, pegawai, jabatan, mutasi → akun & cakupan |
| ERP / keuangan | masuk | REST / impor | realisasi anggaran → KRI keuangan, anggaran action plan |
| ITSM / monitoring | masuk | Webhook / REST | downtime, insiden TI → nilai KRI, insiden |
| SIEM | masuk | Webhook | insiden keamanan → KRI/insiden |
| Audit management | dua arah | REST | temuan audit → improvement; tindak lanjut → status |
| Document management | dua arah | REST | tautan dokumen sebagai bukti |
| BI | keluar | REST baca / ekspor terjadwal | register, snapshot, KRI |
| Email / WhatsApp | keluar | SMTP / API penyedia | notifikasi |
| SSO (v2) | masuk | SAML 2.0 / OIDC | autentikasi |

Standar: semua integrasi lewat API berdokumentasi (OpenAPI), token dengan cakupan, pencatatan setiap panggilan, pemetaan field dikonfigurasi di admin, dan **impor Excel** sebagai jalur cadangan untuk setiap integrasi masuk.

---

## 16. Keamanan & kepatuhan

### 16.1 Autentikasi & sesi

- Kata sandi: bcrypt/argon2id; kebijakan §4.18; kunci 15 menit setelah 5 kegagalan (email+IP); CAPTCHA setelah 3 kegagalan (v1.5).
- MFA kode email/OTP via SMTP — **sudah diterapkan**: wajib per peran (kebijakan organisasi) atau diaktifkan pengguna sendiri; kode 6 digit sekali pakai (HMAC, kedaluwarsa 10 menit), 10 kode pemulihan, perangkat tepercaya opsional 7/30 hari, notifikasi email saat MFA diubah/kode pemulihan dipakai, reset oleh Super Admin/CLI. SMTP dikonfigurasi Super Admin dari aplikasi (sandi terenkripsi). MFA TOTP (aplikasi autentikator) — v1.5.
- Sesi: cookie HttpOnly, Secure, SameSite=Lax; idle 30 menit; absolut 8 jam; regenerasi ID saat masuk; satu klik keluar dari semua perangkat.
- Token API: Sanctum, cakupan per endpoint, kedaluwarsa, dapat dicabut.

### 16.2 Otorisasi

- Policy per model memeriksa peran **dan** cakupan unit/tenant; uji otomatis untuk setiap kombinasi peran × aksi.
- Pemisahan tugas pada persetujuan (§3.2).

### 16.3 Perlindungan aplikasi

- CSRF pada semua mutasi; validasi & sanitasi masukan; keluaran di-escape (Vue/Blade); CSP; HSTS; header keamanan.
- Unggahan: tipe diperiksa dari isi, disimpan di luar docroot, nama acak, pemindaian antivirus, unduh lewat endpoint berotorisasi.
- Rate limit API; perlindungan enumerasi akun (respons seragam).
- Dependensi dipindai (Composer audit, npm audit) di CI; pembaruan keamanan bulanan.

### 16.4 Data & kepatuhan

- Enkripsi in transit (TLS 1.2+) dan at rest untuk field sensitif (nomor telepon, token integrasi).
- Minimisasi data pribadi; data pemohon/pihak ketiga tidak disimpan kecuali diperlukan; dasar pemrosesan dan retensi terdokumentasi (UU PDP).
- Audit trail append-only; log keamanan; retensi 5 tahun.
- Pencadangan terenkripsi; uji pemulihan.
- Penilaian keamanan (VA/PT) sebelum go-live dan tahunan.

---

## 17. Infrastruktur, lingkungan & deployment

| Lingkungan | Tujuan | Spesifikasi |
|---|---|---|
| Development | kerja harian | Docker Compose lokal (PHP 8.3, MySQL 8, Node 20, Mailpit) |
| Staging | UAT, demo klien | Subdomain `staging-manrisk.…`, data demo, deploy otomatis dari branch `develop` |
| Production | operasional | Subdomain klien / VPS; deploy dari tag rilis `vX.Y.Z` |

- **Pipeline CI** (GitHub Actions): lint (PHP-CS-Fixer, ESLint), analisis statis (PHPStan level 6), uji unit & fitur (PHPUnit), uji E2E (Playwright) pada PR; build aset.
- **Deploy**: Git Deploy cPanel (seperti purwarupa) menjalankan `composer install --no-dev`, `php artisan migrate --force`, `npm ci && npm run build`, `artisan config:cache`; atau skrip Deployer untuk VPS; zero-downtime dengan symlink rilis (VPS).
- **Cron**: `* * * * * php artisan schedule:run` (snapshot bulanan, pengingat, laporan terjadwal, antrean).
- **Monitoring**: health endpoint, log kesalahan ke Sentry/alternatif, uptime check eksternal.
- **Cache proxy hosting**: HTML dan respons PHP `no-store`; aset berversi (hash Vite); verifikasi deploy dari server (pelajaran purwarupa).
- **Pencadangan**: `spatie/laravel-backup` harian ke penyimpanan terpisah.

---

## 18. Rencana pengembangan bertahap

Estimasi untuk tim: 1 pemilik produk (paruh waktu), 1 desainer UI/UX (paruh waktu), 2 pengembang full-stack, 1 QA, 1 DevOps (paruh waktu). Sprint 2 minggu.

| Fase | Durasi | Lingkup | Keluaran |
|---|---|---|---|
| 0. Persiapan | 2 minggu | Persetujuan dokumen ini, desain high-fidelity dari purwarupa, penyiapan repo/CI/lingkungan, kerangka Laravel + Inertia, token desain, autentikasi & RBAC dasar | Repo berjalan, login, kerangka layar |
| 1. Inti risiko (MVP) | 8 minggu | Organisasi, kriteria & matriks, taksonomi/appetite, wizard identifikasi, register, detail, analisis & evaluasi, versi risiko, persetujuan dasar (4 tahap), audit trail, dashboard eksekutif & operasional, dokumen, pengguna & akun | Rilis internal 0.5 — dapat dipakai satu unit percontohan |
| 2. Penanganan & pemantauan | 6 minggu | Action plan + progres + kanban, kontrol & efektivitas, KRI & nilai, early warning + email, insiden & loss event, reviu & snapshot, improvement plan | Rilis 0.9 (beta) — UAT organisasi percontohan |
| 3. Pelaporan & AI | 4 minggu | 13 laporan, laporan terjadwal, AI identify/statement/treatment/summary/report, impor Excel register & KRI | Rilis **1.0** — go-live |
| 4. Skala & integrasi | 6 minggu | Multi-tenant, WhatsApp, MFA, delegasi persetujuan, API publik + webhook, integrasi HR/ITSM pertama, PWA | Rilis 1.5 |
| 5. Lanjutan | berkelanjutan | SSO, bow-tie & kuantitatif, Meilisearch, BI connector, modul audit internal | Rilis 2.0 |

Total sampai 1.0: ± 20 minggu (5 bulan). Setiap fase ditutup dengan demo, UAT, dan catatan rilis.

**Definition of Done** per fitur: kode ditinjau (PR), uji unit/fitur lulus, uji E2E skenario utama, dokumentasi pengguna diperbarui, migrasi dapat dibatalkan, tidak ada temuan keamanan tinggi.

---

## 19. Strategi pengujian & kriteria penerimaan

### 19.1 Lapisan uji

| Lapisan | Alat | Cakupan target |
|---|---|---|
| Unit | PHPUnit | Scoring, status, penomoran, aturan evaluasi, policy — 90 % |
| Fitur/API | PHPUnit (HTTP) | Setiap endpoint × peran; validasi; audit log tercatat |
| E2E | Playwright | 25 skenario utama (lihat 19.2) di Chrome & ponsel |
| Kinerja | k6 | 200 pengguna bersamaan, register 10.000 baris |
| Keamanan | OWASP ZAP, Composer/npm audit, VA/PT | Sebelum go-live |
| Aksesibilitas | axe-core | Semua layar tanpa pelanggaran serius |
| UAT | Skenario bisnis dengan organisasi percontohan | Semua skenario diterima |

### 19.2 Skenario penerimaan utama (ringkas)

1. Risk Officer membuat risiko lewat wizard → pernyataan berformat → skor otomatis → diajukan → muncul di kotak masuk Risk Owner.
2. Risk Owner → Risk Manager → Management menyetujui; status risiko menjadi Dalam Penanganan; semua keputusan ada di audit trail.
3. Pengaju tidak dapat menyetujui pengajuannya sendiri.
4. Perubahan residual 9 → 16 memicu pengajuan "Perubahan Skor" dan peringatan.
5. Appetite kategori diubah → status evaluasi seluruh risiko kategori itu berubah sesuai rumus.
6. Action plan lewat tenggat → status Terlambat → notifikasi PIC & Owner → eskalasi 14 hari.
7. Kontrol dinilai Tidak Efektif → improvement plan terbuka → residual risiko terkait ditandai untuk ditinjau.
8. Nilai KRI melewati ambang kritis → peringatan dashboard & email dalam ≤ 1 menit.
9. Insiden dicatat → terkait risiko & kontrol → loss event bertambah → dashboard kerugian berubah.
10. Reviu triwulan dibuat → snapshot → laporan tren menampilkan perbandingan.
11. Laporan Risk Register Excel sesuai filter berisi kolom lengkap; PDF berkop.
12. AI menghasilkan kandidat risiko; satu klik mengisi wizard; tidak ada data pribadi dalam prompt (diverifikasi log).
13. Risk Officer unit A tidak melihat risiko unit B; Auditor hanya baca.
14. 5× sandi salah → terkunci 15 menit; sandi sementara wajib diganti; Super Admin tidak dapat menonaktifkan dirinya.
15. Unggahan berkas `.exe` berganti nama `.pdf` ditolak; unduhan tanpa sesi ditolak.
16. Semua perubahan field risiko tercatat (lama → baru) dan tidak dapat dihapus.
17. Dashboard memuat ≤ 2 detik dengan 10.000 risiko.
18. Aplikasi dapat dipakai penuh di lebar 360 px.
19. Mode gelap dan terang keduanya memenuhi kontras AA.
20. Impor register Excel dengan 3 baris salah → pratinjau kesalahan → hanya baris valid masuk setelah dikonfirmasi.
21. Deploy baru terlihat oleh pengguna tanpa menghapus cache manual (aset berversi).
22. Pemulihan dari cadangan harian berhasil di staging.
23. Pengguna yang dinonaktifkan tidak bisa masuk dan sesinya berakhir ≤ 1 menit.
24. Laporan terjadwal terkirim via email pada waktu yang ditentukan.
25. Multi-tenant (v1.5): data tenant A tidak pernah muncul di tenant B pada semua endpoint.

---

## 20. Migrasi data & go-live

1. **Templat Excel** untuk register, kontrol, KRI, insiden, struktur organisasi, pengguna — dengan validasi di aplikasi.
2. **Pemetaan skala**: organisasi dengan skala 1–4 atau 1–10 dipetakan ke matriks 5×5 lewat tabel konversi yang disetujui pemilik risiko.
3. **Uji coba migrasi** di staging, laporan selisih, perbaikan sumber, migrasi ulang.
4. **Pelatihan** per peran (2 jam Risk Officer/Owner, 1 jam Management/Auditor, 4 jam Admin) dengan data organisasi sendiri.
5. **Go-live bertahap**: 1–2 unit percontohan (4 minggu) → seluruh organisasi; register lama dibekukan dengan tanggal potong.
6. **Hypercare** 4 minggu: dukungan harian, pertemuan mingguan, perbaikan prioritas.

---

## 21. Risiko proyek & mitigasinya

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Ruang lingkup melebar selama pengembangan | jadwal & biaya | Dokumen ini sebagai batas; perubahan lewat *change request* dengan estimasi |
| Kriteria/matriks tiap klien berbeda | pengerjaan ulang | Kriteria & matriks sepenuhnya data (§4.3, §5.1) sejak fase 1 |
| Kinerja dashboard menurun saat data besar | pengalaman pengguna | Agregat ter-cache, snapshot, indeks, uji k6 sejak fase 2 |
| Keterbatasan shared hosting (antrean, Chromium) | fitur laporan/notifikasi | Driver antrean database; DomPDF sebagai cadangan Browsershot; opsi VPS |
| Ketergantungan penyedia AI / biaya | fitur AI | Adaptor penyedia, kuota, mode tanpa AI |
| Kualitas data migrasi rendah | adopsi | Validasi templat, uji coba migrasi, pelatihan |
| Adopsi pengguna rendah | manfaat tidak tercapai | Wizard terpandu, saran AI, pelatihan per peran, unit percontohan |
| Keamanan (aplikasi menghadap internet) | kebocoran data | §16, VA/PT sebelum go-live, pembaruan rutin |

---

## 22. Lampiran

### A. Pemetaan 35 fitur rancangan → bagian dokumen & layar purwarupa

| # | Fitur rancangan | Bagian | Layar purwarupa |
|---|---|---|---|
| 1 | Dashboard manajemen risiko | 4.1 | Executive Dashboard, Risk Dashboard |
| 2 | Organisasi & struktur risiko | 4.2 | Organisasi & Pengguna |
| 3 | Scope, konteks & kriteria | 4.3 | Konteks & Kriteria |
| 4 | Identifikasi risiko | 4.4 | Identifikasi Risiko |
| 5 | Risk register | 4.5 | Risk Register, Detail |
| 6 | Analisis risiko | 4.6, 5.2 | Analisis & Evaluasi |
| 7 | Risk matrix / heatmap | 4.1, 4.6 | Dashboard, Detail › Analisis |
| 8 | Evaluasi risiko | 4.6, 5.3 | Analisis & Evaluasi |
| 9 | Risk treatment | 4.7 | Wizard langkah 4, Detail › Mitigasi |
| 10 | Action plan management | 4.7 | Mitigasi & Action Plan |
| 11 | Monitoring mitigasi | 4.7, 12 | Mitigasi & Action Plan |
| 12 | Residual risk | 4.6, 5.2 | Perjalanan risiko |
| 13 | Control management | 4.8 | Kontrol & Efektivitas |
| 14 | Control effectiveness | 4.8, 5.6 | Kontrol & Efektivitas |
| 15 | KRI | 4.9, 5.7 | KRI & Early Warning |
| 16 | Early warning system | 4.9, 12 | KRI & Early Warning |
| 17 | Risk incident management | 4.10 | Insiden & Loss Event |
| 18 | Loss/risk event database | 4.10 | Insiden & Loss Event |
| 19 | Risk review | 4.11 | Risk Review |
| 20 | Risk approval workflow | 4.12, 6.4 | Persetujuan |
| 21 | Document & evidence | 4.13 | Dokumen & Bukti |
| 22 | Audit trail | 4.14 | Audit Trail |
| 23 | Reporting | 13 | Laporan |
| 24 | Executive risk dashboard | 4.1 | Executive Dashboard |
| 25 | Risk taxonomy | 4.16 | Taksonomi & Appetite |
| 26 | Strategic objective mapping | 4.16 | Pemetaan Sasaran |
| 27 | Multi-unit / multi-organization | 4.2, 8 | Organisasi (tenant) |
| 28 | Role based access control | 3, 4.18 | Pengguna & Akun, Organisasi › Peran |
| 29 | Integrasi sistem | 15 | — |
| 30 | AI risk assistant | 14 | AI Risk Assistant, saran di wizard |
| 31 | AI generate risk report | 14, 13 | Laporan |
| 32 | Automatic risk scoring | 5.2 | Perjalanan risiko |
| 33 | Continual improvement | 4.17 | Perbaikan Berkelanjutan |
| 34 | Modul ISO 31000:2018 | 4.16 | Kerangka ISO 31000 |
| 35 | Arsitektur modul | 2.4, 11.2 | Sidebar |

### B. Kriteria bawaan (dapat diubah per organisasi)

**Kemungkinan**

| Nilai | Nama | Deskripsi |
|---|---|---|
| 1 | Rare | < 5 % per tahun, atau < 1 kali dalam 5 tahun |
| 2 | Unlikely | 5–25 % per tahun, atau 1 kali dalam 2–5 tahun |
| 3 | Possible | 25–50 % per tahun, atau 1 kali per tahun |
| 4 | Likely | 50–80 % per tahun, atau beberapa kali per tahun |
| 5 | Almost Certain | > 80 % per tahun, atau terjadi setiap bulan |

**Dampak**

| Nilai | Nama | Keuangan | Operasional | Reputasi | Hukum |
|---|---|---|---|---|---|
| 1 | Insignificant | < Rp10 jt | gangguan < 1 jam | tidak ada pemberitaan | teguran lisan |
| 2 | Minor | Rp10–100 jt | 1–4 jam | keluhan terbatas | teguran tertulis |
| 3 | Moderate | Rp100–500 jt | 4–24 jam | media lokal | temuan audit material |
| 4 | Major | Rp500 jt–2 M | 1–3 hari | media nasional | sanksi administratif |
| 5 | Severe | > Rp2 M | > 3 hari | krisis kepercayaan | pidana / pencabutan izin |

**Appetite & tolerance bawaan per kategori (skor residual maksimum)**

| Kategori | Appetite | Tolerance |
|---|---|---|
| Strategis, SDM | 8 | 12 |
| Operasional, Keuangan, TI, Reputasi, Pihak Ketiga | 6 | 9 |
| Kepatuhan | 4 | 6 |
| Hukum, Keamanan Siber | 4 | 8 |

### C. Format pernyataan risiko

> Karena **[penyebab]**, dapat terjadi **[peristiwa]**, sehingga menyebabkan **[dampak]**.

Contoh: *Karena ketergantungan terhadap satu penyedia layanan cloud tanpa rencana keluar, dapat terjadi gangguan layanan dari penyedia, sehingga menyebabkan proses pelayanan kepada pengguna terhenti.*

Aturan penulisan: penyebab adalah kondisi yang ada sekarang (bukan "kurangnya…" semata); peristiwa adalah kejadian yang belum terjadi; dampak dikaitkan ke sasaran.

### D. Glosarium peran persetujuan

Submit (pengaju), Review (menelaah tanpa memutus), Revisi (dikembalikan untuk perbaikan), Approve (menyetujui dan meneruskan/menyelesaikan), Reject (menolak dan mengakhiri pengajuan).

### E. Riwayat perubahan dokumen

| Versi | Tanggal | Perubahan | Oleh |
|---|---|---|---|
| 1.0 | 5 Okt 2026 | Draf awal berdasarkan rancangan 35 fitur dan purwarupa | Tim ManRisk |
