/* ManRisk ERM — data demo lengkap.
   Melengkapi data inti (data.js) menjadi 127 risiko dengan action plan, kontrol, KRI, insiden,
   dokumen, pengguna, persetujuan, dan audit trail. Seluruh agregat dashboard dihitung ulang dari
   data ini sehingga angka di setiap layar konsisten. Semua data fiktif. */
(function (D) {
  'use strict';

  /* Pseudo-random deterministik agar demo selalu tampil sama */
  let seed = 31000;
  const rnd = () => { seed |= 0; seed = (seed + 0x6d2b79f5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  const pick = (a) => a[Math.floor(rnd() * a.length)];
  const between = (a, b) => a + Math.floor(rnd() * (b - a + 1));
  const pad = (n, w = 3) => String(n).padStart(w, '0');
  const addDays = (iso, d) => { const t = new Date(iso + 'T00:00:00Z'); t.setUTCDate(t.getUTCDate() + d); return t.toISOString().slice(0, 10); };
  const sc = (a) => a[0] * a[1];
  const lvk = (s) => (s >= 16 ? 'vh' : s >= 10 ? 'h' : s >= 5 ? 'm' : 'l');
  const OWNER = ['ti', 'keu', 'yan', 'um', 'sdm', 'hk', 'ren', 'ins'];

  /* ================= 1. Risiko tambahan (R-021 … R-127) =================
     [unit, kategori, sasaran, tier (4 VH · 3 H · 2 M · 1 L), nama, proses, penyebab, peristiwa, dampak, sumber] */
  const T = [
    // Direktorat Teknologi Informasi
    [0, 'Keamanan Siber', 'SS-3', 4, 'Serangan DDoS pada portal layanan publik', 'Keamanan informasi', 'kapasitas mitigasi DDoS hanya mengandalkan firewall on-premise', 'serangan DDoS volumetrik terhadap portal layanan', 'portal tidak dapat diakses masyarakat selama berjam-jam', 'Eksternal · Technology'],
    [0, 'Teknologi Informasi', 'SS-3', 4, 'Kegagalan sistem penyimpanan (storage) utama', 'Pengelolaan infrastruktur TI', 'storage area network telah melewati masa dukungan vendor', 'kegagalan controller storage utama', 'basis data layanan rusak dan pemulihan memakan waktu lebih dari 24 jam', 'Internal · Infrastructure'],
    [0, 'Keamanan Siber', 'SS-3', 4, 'Kredensial tertanam di repositori kode', 'Pengembangan aplikasi', 'secret scanning belum diterapkan pada repositori kode', 'kunci API dan kata sandi bocor melalui repositori', 'pihak luar mengakses sistem produksi', 'Internal · Technology'],
    [0, 'Keamanan Siber', 'SS-3', 3, 'Phishing terhadap akun pegawai', 'Keamanan informasi', 'kesadaran keamanan pegawai masih rendah dan MFA belum wajib', 'pengambilalihan akun email pegawai melalui phishing', 'kebocoran dokumen internal dan penyalahgunaan akun', 'Eksternal · People'],
    [0, 'Teknologi Informasi', 'SS-1', 3, 'Kinerja aplikasi menurun saat beban puncak', 'Pengelolaan aplikasi pelayanan', 'kapasitas server tidak diskalakan sesuai pola beban akhir bulan', 'waktu respons aplikasi melebihi 10 detik', 'permohonan gagal diproses dan pengaduan meningkat', 'Internal · Technology'],
    [0, 'Keamanan Siber', 'SS-3', 3, 'Kerentanan pada pustaka pihak ketiga aplikasi', 'Pengembangan aplikasi', 'inventaris komponen perangkat lunak (SBOM) belum tersedia', 'eksploitasi kerentanan pada pustaka open source', 'peretas menjalankan kode berbahaya pada server aplikasi', 'Eksternal · Technology'],
    [0, 'Teknologi Informasi', 'SS-3', 3, 'Lisensi perangkat lunak kritikal habis', 'Manajemen aset TI', 'pemantauan masa berlaku lisensi masih manual', 'lisensi basis data dan virtualisasi habis masa berlakunya', 'layanan berhenti atau organisasi terkena sanksi audit lisensi', 'Internal · Process'],
    [0, 'Teknologi Informasi', 'SS-1', 2, 'Integrasi API dengan instansi lain gagal', 'Interoperabilitas data', 'spesifikasi API mitra sering berubah tanpa pemberitahuan', 'pertukaran data dengan instansi mitra gagal', 'verifikasi data pemohon tertunda', 'Eksternal · Third Party'],
    [0, 'Teknologi Informasi', 'SS-1', 2, 'Bug kritikal lolos ke produksi', 'Pengembangan aplikasi', 'cakupan pengujian otomatis di bawah 40%', 'bug kritikal terbawa ke versi produksi', 'fitur layanan tidak berfungsi dan perlu rollback', 'Internal · Process'],
    [0, 'Teknologi Informasi', 'SS-3', 2, 'Kapasitas bandwidth internet tidak mencukupi', 'Pengelolaan jaringan', 'pertumbuhan trafik tidak diantisipasi dalam kontrak', 'kepadatan bandwidth pada jam sibuk', 'akses layanan melambat bagi pengguna', 'Internal · Infrastructure'],
    [0, 'Keamanan Siber', 'SS-3', 2, 'Kesalahan konfigurasi layanan cloud', 'Keamanan informasi', 'belum ada pemeriksaan konfigurasi cloud otomatis', 'bucket penyimpanan terbuka untuk publik', 'data internal dapat diakses pihak luar', 'Internal · Technology'],
    [0, 'Teknologi Informasi', 'SS-3', 2, 'Kegagalan proses backup terjadwal', 'Pengelolaan infrastruktur TI', 'notifikasi kegagalan backup tidak dipantau', 'backup harian gagal beberapa hari berturut-turut tanpa terdeteksi', 'data tidak dapat dipulihkan ke titik terbaru', 'Internal · Process'],
    [0, 'Teknologi Informasi', 'SS-1', 2, 'Ketergantungan pada pengembang tunggal', 'Pengembangan aplikasi', 'dokumentasi kode aplikasi warisan minim', 'pengembang utama tidak tersedia', 'perbaikan dan pengembangan aplikasi terhenti', 'Internal · People'],
    [0, 'Keamanan Siber', 'SS-3', 2, 'Log keamanan tidak lengkap untuk investigasi', 'Keamanan informasi', 'retensi log server hanya 14 hari', 'log yang dibutuhkan forensik sudah terhapus', 'investigasi insiden tidak dapat menemukan akar masalah', 'Internal · Technology'],
    [0, 'Teknologi Informasi', 'SS-1', 2, 'Data master pemohon tidak konsisten', 'Tata kelola data', 'standar data dan pemilik data belum ditetapkan', 'duplikasi dan inkonsistensi data pemohon', 'keputusan layanan didasarkan pada data yang keliru', 'Internal · Process'],
    [0, 'Teknologi Informasi', 'SS-3', 2, 'Perangkat kerja pegawai usang', 'Manajemen aset TI', '40% laptop pegawai berusia lebih dari 6 tahun', 'kerusakan perangkat dan sistem operasi tidak didukung', 'produktivitas menurun dan celah keamanan bertambah', 'Internal · Infrastructure'],
    [0, 'Keamanan Siber', 'SS-3', 2, 'Penggunaan perangkat lunak tidak sah (shadow IT)', 'Keamanan informasi', 'pegawai bebas memasang aplikasi pada perangkat kantor', 'pemasangan aplikasi tidak berlisensi atau berbahaya', 'malware masuk dan pelanggaran lisensi', 'Internal · People'],
    [0, 'Teknologi Informasi', 'SS-1', 2, 'Pembaruan aplikasi mobile ditolak app store', 'Pengembangan aplikasi', 'kebijakan privasi aplikasi belum diperbarui', 'pembaruan aplikasi mobile ditolak toko aplikasi', 'perbaikan bug tidak sampai ke pengguna', 'Eksternal · Regulation'],
    [0, 'Teknologi Informasi', 'SS-1', 1, 'Sertifikat SSL portal kedaluwarsa', 'Pengelolaan infrastruktur TI', 'perpanjangan sertifikat bergantung pada pengingat manual', 'sertifikat SSL portal kedaluwarsa', 'browser memblokir akses ke portal layanan', 'Internal · Process'],
    [0, 'Teknologi Informasi', 'SS-1', 1, 'Dokumentasi sistem tidak mutakhir', 'Pengembangan aplikasi', 'pembaruan dokumentasi tidak masuk definisi selesai', 'dokumentasi arsitektur tidak sesuai kondisi aktual', 'analisis dampak perubahan menjadi lambat', 'Internal · Process'],
    [0, 'Teknologi Informasi', 'SS-3', 1, 'Gangguan layanan email kantor', 'Layanan TI internal', 'server email tunggal tanpa failover', 'layanan email tidak tersedia beberapa jam', 'koordinasi internal terganggu', 'Internal · Technology'],
    [0, 'Teknologi Informasi', 'SS-1', 1, 'Helpdesk TI kewalahan saat lonjakan tiket', 'Layanan TI internal', 'jumlah agen helpdesk tetap saat migrasi aplikasi', 'antrean tiket melebihi SLA', 'pegawai menunggu lama untuk dukungan TI', 'Internal · People'],
    [0, 'Keamanan Siber', 'SS-3', 1, 'Akses fisik tidak sah ke ruang server', 'Keamanan fisik', 'kartu akses ruang server dipinjamkan antarpegawai', 'orang tidak berwenang memasuki ruang server', 'perangkat dirusak atau dicuri', 'Internal · People'],
    [0, 'Teknologi Informasi', 'SS-3', 1, 'Migrasi alamat IP berdampak ke layanan', 'Pengelolaan jaringan', 'rencana migrasi IPv6 belum diuji di lingkungan staging', 'layanan tidak dapat diakses setelah migrasi', 'gangguan layanan singkat', 'Internal · Technology'],
    // Biro Keuangan
    [1, 'Keuangan', 'SS-2', 3, 'Penyerapan anggaran menumpuk di akhir tahun', 'Pengelolaan anggaran', 'jadwal pengadaan dan pencairan tidak selaras dengan rencana kas', 'realisasi anggaran triwulan IV melebihi 45% total pagu', 'kualitas belanja rendah dan potensi sisa anggaran', 'Internal · Process'],
    [1, 'Keuangan', 'SS-2', 3, 'Salah saji laporan keuangan', 'Akuntansi & pelaporan', 'rekonsiliasi aset dan kas belum dilakukan bulanan', 'salah saji material pada laporan keuangan', 'opini audit BPK turun dari WTP', 'Internal · Process'],
    [1, 'Keuangan', 'SS-2', 2, 'Uang persediaan terlambat dipertanggungjawabkan', 'Perbendaharaan', 'bendahara pengeluaran pembantu belum memahami batas waktu GUP', 'pertanggungjawaban UP melewati batas waktu', 'pencairan berikutnya tertunda dan teguran KPPN', 'Internal · People'],
    [1, 'Keuangan', 'SS-2', 2, 'Kesalahan klasifikasi akun belanja', 'Pengelolaan anggaran', 'pemahaman bagan akun standar di unit teknis beragam', 'belanja dicatat pada akun yang tidak tepat', 'temuan audit dan revisi anggaran', 'Internal · People'],
    [1, 'Kepatuhan', 'SS-2', 2, 'Keterlambatan setor pajak potongan', 'Perpajakan', 'jadwal setor pajak tidak terintegrasi dengan aplikasi pembayaran', 'penyetoran PPh potongan melewati jatuh tempo', 'denda dan bunga perpajakan', 'Internal · Process'],
    [1, 'Keuangan', 'SS-2', 2, 'Piutang PNBP tidak tertagih', 'Pengelolaan PNBP', 'penagihan PNBP belum didukung sistem pengingat', 'piutang PNBP melewati jatuh tempo', 'pendapatan negara tidak optimal', 'Internal · Process'],
    [1, 'Operasional', 'SS-2', 2, 'Gangguan aplikasi SAKTI di akhir periode', 'Akuntansi & pelaporan', 'beban akses aplikasi SAKTI nasional tinggi di akhir bulan', 'aplikasi SAKTI tidak dapat diakses', 'penyusunan laporan keuangan terlambat', 'Eksternal · Technology'],
    [1, 'Keuangan', 'SS-2', 2, 'Perencanaan kas tidak akurat', 'Pengelolaan kas', 'rencana penarikan dana tidak diperbarui saat revisi kegiatan', 'deviasi realisasi terhadap rencana kas lebih dari 10%', 'penalti deviasi halaman III DIPA', 'Internal · Process'],
    [1, 'Kepatuhan', 'SS-2', 2, 'Perjalanan dinas tidak sesuai ketentuan', 'Perjalanan dinas', 'verifikasi bukti perjalanan dinas masih manual', 'pembayaran perjalanan dinas melebihi standar biaya', 'kelebihan bayar dan temuan audit', 'Internal · Process'],
    [1, 'Keuangan', 'SS-2', 2, 'Aset tetap tidak tercatat lengkap', 'Pengelolaan BMN', 'hasil pengadaan tidak segera dicatat ke SIMAK BMN', 'selisih saldo aset antara SIMAK BMN dan neraca', 'salah saji nilai aset', 'Internal · Process'],
    [1, 'Operasional', 'SS-2', 1, 'Arsip dokumen keuangan sulit ditelusuri', 'Akuntansi & pelaporan', 'arsip dokumen sumber masih fisik dan tersebar', 'dokumen pendukung transaksi sulit ditemukan saat audit', 'proses audit memanjang', 'Internal · Process'],
    [1, 'Keuangan', 'SS-2', 1, 'Selisih kurs pembayaran langganan luar negeri', 'Pembayaran vendor', 'pembayaran lisensi dilakukan dalam mata uang asing', 'selisih kurs melebihi pagu', 'kekurangan anggaran kecil', 'Eksternal · Financial'],
    [1, 'Operasional', 'SS-2', 1, 'Kehilangan uang kas kecil', 'Perbendaharaan', 'penyimpanan kas kecil tanpa brankas berpengaman ganda', 'kehilangan uang tunai di brankas', 'kerugian kecil dan pemeriksaan internal', 'Internal · People'],
    [1, 'Keuangan', 'SS-2', 1, 'Rekening bank satker tidak aktif', 'Perbendaharaan', 'rekening lama tidak ditutup setelah reorganisasi', 'rekening pasif masih menyimpan saldo', 'temuan pengelolaan rekening', 'Internal · Process'],
    [1, 'Kepatuhan', 'SS-2', 1, 'Laporan keuangan terlambat disampaikan', 'Akuntansi & pelaporan', 'jadwal penyusunan laporan bertabrakan dengan reviu internal', 'laporan keuangan semesteran terlambat', 'teguran dari Kementerian Keuangan', 'Internal · Process'],
    // Direktorat Pelayanan Publik
    [2, 'Operasional', 'SS-1', 4, 'Antrean permohonan menumpuk melebihi kapasitas', 'Penyelenggaraan layanan perizinan', 'jumlah verifikator tidak sebanding dengan lonjakan permohonan 35%', 'backlog permohonan melebihi 5.000 berkas', 'standar waktu layanan 3 hari kerja terlampaui dan pengaduan Ombudsman meningkat', 'Eksternal · Social'],
    [2, 'Reputasi', 'SS-1', 3, 'Maladministrasi pelayanan dilaporkan ke Ombudsman', 'Penanganan pengaduan', 'prosedur layanan tidak dipublikasikan dengan jelas', 'laporan maladministrasi kepada Ombudsman', 'rekomendasi Ombudsman dan sorotan media', 'Eksternal · Regulation'],
    [2, 'Operasional', 'SS-1', 3, 'Pungutan liar pada layanan tatap muka', 'Layanan tatap muka', 'masih ada interaksi langsung petugas dan pemohon tanpa pengawasan', 'petugas meminta imbalan di luar ketentuan', 'pelanggaran integritas dan citra lembaga rusak', 'Internal · People'],
    [2, 'Operasional', 'SS-1', 3, 'Kesalahan verifikasi berkas permohonan', 'Penyelenggaraan layanan perizinan', 'checklist verifikasi berbeda antarpetugas', 'izin terbit untuk berkas yang tidak memenuhi syarat', 'izin dibatalkan dan potensi gugatan', 'Internal · Process'],
    [2, 'Reputasi', 'SS-1', 2, 'Informasi layanan di kanal resmi tidak konsisten', 'Komunikasi publik', 'pembaruan informasi tidak terkoordinasi antarkanal', 'persyaratan berbeda di situs, media sosial, dan loket', 'pemohon salah menyiapkan berkas', 'Internal · Process'],
    [2, 'Operasional', 'SS-1', 2, 'Layanan call center berhenti', 'Penanganan pengaduan', 'kontrak call center berakhir tanpa perpanjangan tepat waktu', 'call center berhenti beroperasi', 'pengaduan tidak tertangani', 'Eksternal · Third Party'],
    [2, 'Operasional', 'SS-1', 2, 'Standar pelayanan tidak diperbarui', 'Penyelenggaraan layanan perizinan', 'reviu standar pelayanan belum dilaksanakan 2 tahun', 'standar pelayanan tidak sesuai regulasi terbaru', 'nilai kepatuhan pelayanan publik menurun', 'Eksternal · Regulation'],
    [2, 'Reputasi', 'SS-1', 2, 'Layanan belum ramah penyandang disabilitas', 'Layanan tatap muka', 'sarana dan aplikasi belum memenuhi standar aksesibilitas', 'penyandang disabilitas kesulitan mengakses layanan', 'pengaduan dan penilaian inklusivitas rendah', 'Internal · Infrastructure'],
    [2, 'Operasional', 'SS-1', 2, 'Survei kepuasan tidak representatif', 'Penanganan pengaduan', 'sampel survei hanya dari layanan daring', 'hasil IKM tidak mencerminkan kondisi riil', 'keputusan perbaikan layanan tidak tepat sasaran', 'Internal · Process'],
    [2, 'Operasional', 'SS-1', 2, 'Berkas asli pemohon hilang di loket', 'Layanan tatap muka', 'penerimaan berkas fisik tanpa tanda terima digital', 'berkas asli pemohon hilang', 'pemohon dirugikan dan mengadu', 'Internal · Process'],
    [2, 'Kepatuhan', 'SS-1', 2, 'Penyalahgunaan data pemohon oleh petugas', 'Pengelolaan data pengguna', 'akses petugas ke data pemohon tidak dibatasi sesuai kebutuhan', 'petugas mengakses data pemohon untuk kepentingan pribadi', 'pelanggaran pelindungan data pribadi', 'Internal · People'],
    [2, 'Operasional', 'SS-1', 2, 'Kantor wilayah belum siap menjalankan layanan baru', 'Layanan kantor wilayah', 'pelatihan petugas kantor wilayah tertinggal dari pusat', 'layanan di daerah tidak sesuai standar', 'kesenjangan kualitas layanan antarwilayah', 'Internal · People'],
    [2, 'Reputasi', 'SS-1', 2, 'Respons media sosial yang tidak tepat', 'Komunikasi publik', 'admin media sosial belum memiliki pedoman respons', 'balasan admin memicu kontroversi', 'isu viral dan citra menurun', 'Internal · People'],
    [2, 'Operasional', 'SS-1', 2, 'Pembayaran PNBP layanan gagal terverifikasi', 'Penyelenggaraan layanan perizinan', 'integrasi dengan SIMPONI kadang terputus', 'kode billing tidak terbit atau pembayaran tidak terverifikasi', 'permohonan tertahan meskipun pemohon sudah membayar', 'Eksternal · Technology'],
    [2, 'Strategis', 'SS-1', 2, 'Inovasi layanan tidak berkelanjutan', 'Perencanaan layanan', 'inovasi bergantung pada pendanaan satu tahun', 'inovasi layanan berhenti setelah peluncuran', 'manfaat inovasi tidak dirasakan publik', 'Internal · Process'],
    [2, 'Operasional', 'SS-1', 1, 'Ruang tunggu layanan melebihi kapasitas', 'Layanan tatap muka', 'sistem antrean belum terhubung dengan janji temu daring', 'ruang tunggu penuh pada jam sibuk', 'ketidaknyamanan pemohon', 'Internal · Infrastructure'],
    [2, 'Operasional', 'SS-1', 1, 'Mesin antrean rusak', 'Layanan tatap muka', 'perangkat antrean tanpa kontrak pemeliharaan', 'mesin antrean tidak berfungsi', 'antrean manual dan pelayanan lebih lambat', 'Internal · Infrastructure'],
    [2, 'Reputasi', 'SS-1', 1, 'Kesalahan penulisan pada sertifikat izin', 'Penyelenggaraan layanan perizinan', 'pengecekan akhir dokumen dilakukan sekilas', 'sertifikat izin terbit dengan kesalahan data', 'penerbitan ulang dan keluhan pemohon', 'Internal · People'],
    [2, 'Operasional', 'SS-1', 1, 'FAQ layanan tidak diperbarui', 'Komunikasi publik', 'tidak ada penanggung jawab konten FAQ', 'FAQ memuat informasi kedaluwarsa', 'pertanyaan berulang ke call center', 'Internal · Process'],
    [2, 'Operasional', 'SS-1', 1, 'Jadwal janji temu daring ganda', 'Layanan tatap muka', 'aplikasi janji temu dibangun tanpa uji beban', 'jadwal janji temu tercatat ganda', 'pemohon menunggu lebih lama', 'Internal · Technology'],
    // Biro Umum & Pengadaan
    [3, 'Kepatuhan', 'SS-2', 4, 'Pengadaan darurat tanpa dasar yang memadai', 'Pengadaan barang/jasa', 'kriteria keadaan darurat ditafsirkan longgar oleh unit pengguna', 'pengadaan langsung bernilai besar dengan dalih darurat', 'kerugian negara dan pemeriksaan aparat penegak hukum', 'Internal · Regulation'],
    [3, 'Pihak Ketiga', 'SS-3', 3, 'Vendor kritikal mengalami kebangkrutan', 'Manajemen kontrak', 'belum ada penilaian kesehatan keuangan vendor', 'vendor pemeliharaan sistem inti berhenti beroperasi', 'dukungan teknis sistem inti terhenti', 'Eksternal · Third Party'],
    [3, 'Kepatuhan', 'SS-2', 3, 'Spesifikasi teknis mengarah ke merek tertentu', 'Pengadaan barang/jasa', 'penyusun spesifikasi bergantung pada brosur vendor', 'persaingan tender tidak sehat', 'sanggahan peserta dan pembatalan tender', 'Internal · People'],
    [3, 'Operasional', 'SS-3', 3, 'Kebakaran di gedung kantor', 'Pemeliharaan aset', 'instalasi listrik gedung berumur lebih dari 20 tahun', 'kebakaran akibat korsleting listrik', 'kerusakan aset dan ancaman keselamatan pegawai', 'Internal · Infrastructure'],
    [3, 'Pihak Ketiga', 'SS-2', 2, 'Kualitas pekerjaan penyedia di bawah spesifikasi', 'Manajemen kontrak', 'pengawasan pekerjaan hanya dilakukan di akhir', 'hasil pekerjaan tidak sesuai spesifikasi', 'pekerjaan ulang dan keterlambatan', 'Eksternal · Third Party'],
    [3, 'Operasional', 'SS-2', 2, 'Keterlambatan proses pengadaan', 'Pengadaan barang/jasa', 'RUP disusun terlambat dan dokumen persiapan tidak lengkap', 'kontrak ditandatangani melewati jadwal', 'kegiatan prioritas tertunda', 'Internal · Process'],
    [3, 'Operasional', 'SS-3', 2, 'Kendaraan dinas tidak laik jalan', 'Pemeliharaan aset', 'pemeliharaan kendaraan dilakukan berdasarkan kerusakan, bukan jadwal', 'kendaraan dinas rusak di perjalanan', 'kegiatan lapangan tertunda dan risiko kecelakaan', 'Internal · Infrastructure'],
    [3, 'Kepatuhan', 'SS-2', 2, 'BMN digunakan pihak lain tanpa izin', 'Pengelolaan BMN', 'pemanfaatan BMN belum diinventarisasi', 'BMN digunakan pihak ketiga tanpa perjanjian', 'potensi kehilangan aset dan PNBP', 'Internal · Process'],
    [3, 'Operasional', 'SS-3', 2, 'Gangguan pasokan listrik gedung', 'Pemeliharaan aset', 'genset hanya mampu menopang 60% beban', 'pemadaman listrik PLN lebih dari 4 jam', 'operasional kantor terhenti sebagian', 'Eksternal · Infrastructure'],
    [3, 'Pihak Ketiga', 'SS-3', 2, 'Petugas outsourcing tidak kompeten', 'Manajemen kontrak', 'kontrak outsourcing tidak mensyaratkan sertifikasi', 'petugas keamanan dan kebersihan tidak kompeten', 'gangguan keamanan dan kenyamanan kantor', 'Eksternal · Third Party'],
    [3, 'Operasional', 'SS-3', 1, 'Kerusakan lift gedung', 'Pemeliharaan aset', 'kontrak pemeliharaan lift tidak mencakup suku cadang', 'lift berhenti beroperasi', 'mobilitas pegawai dan tamu terganggu', 'Internal · Infrastructure'],
    [3, 'Operasional', 'SS-2', 1, 'Persediaan ATK habis', 'Pengelolaan persediaan', 'pencatatan persediaan tidak real-time', 'stok ATK habis sebelum pengadaan berikutnya', 'kegiatan administrasi sedikit terganggu', 'Internal · Process'],
    [3, 'Hukum', 'SS-2', 1, 'Sengketa sewa lahan parkir', 'Pengelolaan BMN', 'perjanjian sewa lahan parkir tidak mengatur pembagian biaya', 'perselisihan dengan pemilik lahan', 'biaya tambahan dan gangguan parkir', 'Eksternal · Third Party'],
    // Biro SDM
    [4, 'SDM', 'SS-4', 3, 'Kesenjangan kompetensi digital pegawai', 'Pengembangan kompetensi', 'program pelatihan belum berbasis analisis kebutuhan', 'pegawai tidak mampu mengoperasikan sistem baru', 'transformasi layanan digital terhambat', 'Internal · People'],
    [4, 'Kepatuhan', 'SS-4', 3, 'Pelanggaran disiplin dan kode etik pegawai', 'Penegakan disiplin', 'pengawasan atasan langsung belum optimal', 'pelanggaran disiplin berat oleh pegawai', 'sanksi, citra lembaga menurun, dan layanan terganggu', 'Internal · People'],
    [4, 'SDM', 'SS-4', 2, 'Beban kerja tidak merata antarunit', 'Perencanaan kebutuhan SDM', 'analisis beban kerja belum diperbarui', 'sebagian unit kelebihan beban kerja', 'kelelahan pegawai dan kesalahan kerja', 'Internal · Process'],
    [4, 'SDM', 'SS-4', 2, 'Penilaian kinerja tidak objektif', 'Manajemen kinerja', 'indikator kinerja individu tidak terukur', 'penilaian kinerja bias', 'motivasi pegawai menurun', 'Internal · Process'],
    [4, 'Operasional', 'SS-4', 2, 'Kesalahan perhitungan tunjangan kinerja', 'Penggajian', 'data presensi dan kinerja tidak terintegrasi', 'pembayaran tunjangan kinerja tidak sesuai', 'kelebihan atau kekurangan bayar', 'Internal · Process'],
    [4, 'Kepatuhan', 'SS-4', 2, 'Data kepegawaian tidak mutakhir di SIASN', 'Administrasi kepegawaian', 'pemutakhiran data bergantung pada inisiatif pegawai', 'data jabatan dan pangkat tidak sesuai', 'kenaikan pangkat dan pensiun terlambat', 'Internal · Process'],
    [4, 'SDM', 'SS-4', 1, 'Pegawai baru belum siap kerja', 'Rekrutmen & orientasi', 'program orientasi belum terstandar', 'pegawai baru melakukan kesalahan prosedur', 'kualitas kerja menurun di unit penempatan', 'Internal · Process'],
    [4, 'SDM', 'SS-4', 1, 'Kecelakaan kerja di lingkungan kantor', 'K3 perkantoran', 'sosialisasi K3 jarang dilakukan', 'pegawai mengalami kecelakaan kerja ringan', 'hari kerja hilang dan klaim', 'Internal · People'],
    [4, 'SDM', 'SS-4', 1, 'Kehadiran pelatihan wajib rendah', 'Pengembangan kompetensi', 'jadwal pelatihan bertabrakan dengan pekerjaan rutin', 'peserta tidak hadir pada pelatihan wajib', 'anggaran pelatihan tidak efektif', 'Internal · People'],
    [4, 'SDM', 'SS-4', 1, 'Keluhan kesejahteraan pegawai', 'Hubungan kepegawaian', 'saluran aspirasi pegawai belum formal', 'keluhan berkembang menjadi isu internal', 'iklim kerja menurun', 'Internal · People'],
    // Biro Hukum
    [5, 'Hukum', 'SS-2', 3, 'Peraturan lembaga bertentangan dengan peraturan lebih tinggi', 'Penyusunan peraturan', 'harmonisasi rancangan peraturan tidak melibatkan kementerian terkait', 'peraturan lembaga diuji materiil', 'peraturan dibatalkan dan layanan kehilangan dasar hukum', 'Eksternal · Regulation'],
    [5, 'Hukum', 'SS-2', 2, 'Kontrak tanpa klausul pelindungan data', 'Penyusunan perjanjian', 'templat kontrak belum diperbarui pasca UU PDP', 'vendor memproses data pribadi tanpa kewajiban kontraktual', 'tanggung jawab hukum lembaga atas kebocoran data', 'Eksternal · Regulation'],
    [5, 'Kepatuhan', 'SS-1', 2, 'Permohonan informasi publik terlambat ditanggapi', 'Layanan informasi publik', 'PPID pelaksana di unit belum aktif', 'permohonan informasi melewati 10 hari kerja', 'sengketa informasi di Komisi Informasi', 'Internal · Process'],
    [5, 'Hukum', 'SS-2', 2, 'Kekalahan dalam perkara PTUN', 'Bantuan hukum', 'dokumentasi dasar keputusan tidak lengkap', 'gugatan atas keputusan izin dikabulkan', 'keputusan dibatalkan dan biaya perkara', 'Eksternal · Regulation'],
    [5, 'Kepatuhan', 'SS-2', 2, 'Perjanjian kerja sama kedaluwarsa', 'Penyusunan perjanjian', 'monitoring masa berlaku perjanjian belum ada', 'kerja sama berjalan tanpa perjanjian yang berlaku', 'temuan audit dan sengketa', 'Internal · Process'],
    [5, 'Hukum', 'SS-2', 1, 'HKI aplikasi lembaga belum didaftarkan', 'Pengelolaan HKI', 'pendaftaran HKI tidak masuk rencana kerja', 'klaim pihak lain atas aplikasi lembaga', 'sengketa kepemilikan', 'Eksternal · Regulation'],
    [5, 'Hukum', 'SS-2', 1, 'Database peraturan internal tidak lengkap', 'Dokumentasi hukum', 'JDIH belum memuat seluruh produk hukum lama', 'pegawai merujuk peraturan yang sudah dicabut', 'kesalahan penerapan aturan', 'Internal · Process'],
    // Direktorat Perencanaan
    [6, 'Strategis', 'SS-1', 3, 'Pemotongan pagu anggaran oleh pemerintah pusat', 'Perencanaan anggaran', 'kebijakan efisiensi belanja nasional', 'pagu indikatif dipotong lebih dari 15%', 'program prioritas tidak dapat dilaksanakan penuh', 'Eksternal · Financial'],
    [6, 'Strategis', 'SS-3', 3, 'Peta jalan digital tidak selaras dengan SPBE nasional', 'Perencanaan strategis', 'arsitektur SPBE lembaga belum diperbarui', 'investasi TI duplikatif dengan layanan nasional', 'pemborosan anggaran dan teguran Kementerian PAN-RB', 'Eksternal · Regulation'],
    [6, 'Strategis', 'SS-1', 2, 'Indikator kinerja tidak terukur', 'Perencanaan & evaluasi kinerja', 'definisi operasional IKU belum baku', 'capaian kinerja tidak dapat diverifikasi', 'nilai SAKIP menurun', 'Internal · Process'],
    [6, 'Strategis', 'SS-2', 2, 'Data dukung evaluasi kinerja tidak lengkap', 'Perencanaan & evaluasi kinerja', 'pengumpulan data dukung dilakukan menjelang evaluasi', 'evidence capaian tidak tersedia saat evaluasi', 'penilaian kinerja rendah', 'Internal · Process'],
    [6, 'Strategis', 'SS-1', 2, 'Perubahan prioritas kebijakan nasional', 'Perencanaan strategis', 'renstra disusun sebelum RPJMN baru ditetapkan', 'program lembaga tidak selaras prioritas baru', 'revisi renstra dan realokasi anggaran', 'Eksternal · Political'],
    [6, 'Operasional', 'SS-2', 2, 'Revisi anggaran berulang', 'Perencanaan anggaran', 'perencanaan kegiatan unit tidak matang', 'revisi DIPA lebih dari 6 kali setahun', 'pelaksanaan kegiatan tertunda', 'Internal · Process'],
    [6, 'Strategis', 'SS-4', 1, 'Rekomendasi monev tidak ditindaklanjuti', 'Perencanaan & evaluasi kinerja', 'rekomendasi monitoring dan evaluasi tidak dilacak', 'masalah kinerja yang sama berulang', 'sasaran strategis tidak tercapai', 'Internal · Process'],
    [6, 'Strategis', 'SS-1', 1, 'Proyeksi kebutuhan layanan meleset', 'Perencanaan layanan', 'model proyeksi tidak menggunakan data historis lengkap', 'permintaan layanan berbeda jauh dari proyeksi', 'kapasitas layanan tidak sesuai kebutuhan', 'Internal · Process'],
    [6, 'Strategis', 'SS-2', 1, 'Penyusunan rencana kerja terlambat', 'Perencanaan anggaran', 'koordinasi penyusunan renja dengan unit lambat', 'renja terlambat disampaikan', 'penyusunan pagu indikatif terganggu', 'Internal · Process'],
    [6, 'Operasional', 'SS-2', 1, 'Kesalahan input data di aplikasi KRISNA', 'Perencanaan anggaran', 'operator aplikasi tidak memiliki pendamping', 'data rincian output salah input', 'revisi dan penundaan persetujuan', 'Internal · People'],
    // Inspektorat
    [7, 'Kepatuhan', 'SS-2', 3, 'Tindak lanjut rekomendasi audit melewati 60 hari', 'Tindak lanjut hasil audit', 'unit teknis tidak memprioritaskan tindak lanjut', 'rekomendasi BPK belum ditindaklanjuti melewati batas waktu', 'status tindak lanjut buruk dan opini terancam', 'Internal · Process'],
    [7, 'Kepatuhan', 'SS-2', 2, 'Audit internal belum berbasis risiko', 'Pengawasan internal', 'PKPT disusun berdasarkan rotasi, bukan risiko', 'area berisiko tinggi tidak diaudit', 'kecurangan atau kelemahan kontrol tidak terdeteksi', 'Internal · Process'],
    [7, 'Kepatuhan', 'SS-2', 2, 'Gratifikasi tidak dilaporkan', 'Pengendalian gratifikasi', 'pemahaman pegawai tentang gratifikasi rendah', 'penerimaan gratifikasi tidak dilaporkan ke UPG', 'pelanggaran integritas', 'Internal · People'],
    [7, 'Operasional', 'SS-2', 2, 'Auditor internal kekurangan kompetensi TI', 'Pengawasan internal', 'belum ada auditor bersertifikat audit TI', 'audit sistem informasi tidak mendalam', 'kelemahan kontrol TI tidak teridentifikasi', 'Internal · People'],
    [7, 'Kepatuhan', 'SS-2', 2, 'LHKPN pejabat terlambat disampaikan', 'Pengendalian gratifikasi', 'pengingat kewajiban LHKPN tidak sistematis', 'pejabat wajib lapor terlambat menyampaikan LHKPN', 'penilaian zona integritas menurun', 'Internal · People'],
    [7, 'Kepatuhan', 'SS-2', 1, 'Pengaduan whistleblowing tidak tertangani', 'Whistleblowing system', 'kanal WBS belum dikenal pegawai', 'pengaduan pelanggaran tidak masuk atau tidak diproses', 'pelanggaran berlanjut', 'Internal · Process'],
    [7, 'Operasional', 'SS-2', 1, 'Kertas kerja audit tidak terdokumentasi', 'Pengawasan internal', 'audit belum menggunakan aplikasi kertas kerja', 'kertas kerja hilang atau tidak lengkap', 'kualitas audit dipertanyakan saat telaah sejawat', 'Internal · Process'],
    [7, 'Kepatuhan', 'SS-2', 1, 'Reviu laporan keuangan terlambat', 'Pengawasan internal', 'jadwal reviu tidak sinkron dengan penyusunan laporan', 'reviu Inspektorat tidak sempat dilakukan menyeluruh', 'kualitas laporan keuangan kurang terjamin', 'Internal · Process']
  ];

  /* Sel residual yang tersisa per tier (setelah 20 risiko inti), agar distribusi = 8 / 23 / 61 / 35 */
  const POOL = {
    4: { '4-5': 3, '5-4': 1, '5-5': 1 },
    3: { '2-5': 2, '5-2': 1, '3-4': 4, '4-3': 6, '3-5': 3, '5-3': 2 },
    2: { '1-5': 4, '5-1': 1, '2-3': 6, '3-2': 11, '2-4': 13, '4-2': 6, '3-3': 11 },
    1: { '1-1': 2, '1-2': 5, '2-1': 3, '1-3': 7, '3-1': 4, '1-4': 5, '4-1': 2, '2-2': 4 }
  };
  const cellsFor = (tier) => { const out = []; Object.entries(POOL[tier]).forEach(([k, n]) => { for (let i = 0; i < n; i++) out.push(k.split('-').map(Number)); }); for (let i = out.length - 1; i > 0; i--) { const j = Math.floor(rnd() * (i + 1)); [out[i], out[j]] = [out[j], out[i]]; } return out; };
  const CELLS = { 4: [[4, 5], [4, 5], [5, 4], [5, 5], [4, 5]], 3: cellsFor(3), 2: cellsFor(2), 1: cellsFor(1) }; // VH: DDoS, storage, kredensial, antrean permohonan, pengadaan darurat

  const CTRL_NEW = [
    ['C-13', 'Firewall & WAF dengan proteksi DDoS', 'Menyaring trafik berbahaya sebelum mencapai aplikasi', 'Tim Keamanan Informasi', 'Berkelanjutan', 'Preventif', 'Otomatis', 4, 3, '2026-09-14', 'Keamanan Siber'],
    ['C-14', 'MFA untuk akses administratif & email', 'Mencegah pengambilalihan akun', 'Tim Keamanan Informasi', 'Berkelanjutan', 'Preventif', 'Otomatis', 4, 2, '2026-08-30', 'Keamanan Siber'],
    ['C-15', 'Pemindaian kerentanan bulanan', 'Menemukan celah keamanan sebelum dieksploitasi', 'Tim Keamanan Informasi', 'Bulanan', 'Detektif', 'Otomatis', 3, 3, '2026-09-30', 'Keamanan Siber'],
    ['C-16', 'Security awareness & simulasi phishing', 'Meningkatkan kewaspadaan pegawai', 'Biro SDM', 'Triwulanan', 'Preventif', 'Manual', 3, 2, '2026-07-18', 'Keamanan Siber'],
    ['C-17', 'Pengujian otomatis & code review', 'Mencegah bug dan kerentanan masuk ke produksi', 'Tim Aplikasi', 'Per rilis', 'Preventif', 'Otomatis', 3, 2, '2026-09-26', 'Teknologi Informasi'],
    ['C-18', 'Monitoring kapasitas & autoscaling', 'Menjaga kinerja aplikasi saat beban puncak', 'Tim Operasi TI', 'Berkelanjutan', 'Detektif', 'Otomatis', 3, 3, '2026-09-21', 'Teknologi Informasi'],
    ['C-19', 'Register lisensi & aset TI', 'Memastikan lisensi dan aset tercatat dan berlaku', 'Tim Infrastruktur', 'Bulanan', 'Detektif', 'Manual', 2, 2, '2026-06-30', 'Teknologi Informasi'],
    ['C-20', 'Rekonsiliasi kas & bank bulanan', 'Mendeteksi selisih kas dan rekening', 'Subbag Perbendaharaan', 'Bulanan', 'Detektif', 'Manual', 4, 3, '2026-09-30', 'Keuangan'],
    ['C-21', 'Reviu laporan keuangan oleh Inspektorat', 'Menjamin keandalan laporan keuangan', 'Inspektorat', 'Semesteran', 'Detektif', 'Manual', 3, 3, '2026-07-25', 'Keuangan'],
    ['C-22', 'Verifikasi SPJ perjalanan dinas berbasis aplikasi', 'Mencegah pembayaran melebihi standar biaya', 'Subbag Perbendaharaan', 'Per transaksi', 'Preventif', 'Otomatis', 3, 3, '2026-09-10', 'Kepatuhan'],
    ['C-23', 'Checklist verifikasi berkas terstandar', 'Menyeragamkan verifikasi permohonan', 'Direktorat Pelayanan', 'Per berkas', 'Preventif', 'Manual', 3, 2, '2026-08-20', 'Operasional'],
    ['C-24', 'Maklumat & standar pelayanan dipublikasikan', 'Memberi kepastian prosedur kepada publik', 'Direktorat Pelayanan', 'Tahunan', 'Preventif', 'Manual', 3, 3, '2026-02-28', 'Reputasi'],
    ['C-25', 'CCTV & pengawasan loket layanan', 'Mendeteksi perilaku tidak berintegritas di loket', 'Biro Umum & Pengadaan', 'Berkelanjutan', 'Detektif', 'Otomatis', 3, 3, '2026-09-08', 'Operasional'],
    ['C-26', 'Monitoring media sosial & protokol krisis', 'Mendeteksi isu negatif lebih awal', 'Humas', 'Harian', 'Detektif', 'Manual', 3, 2, '2026-09-15', 'Reputasi'],
    ['C-27', 'Penilaian kinerja & kesehatan keuangan vendor', 'Mengantisipasi kegagalan penyedia', 'Biro Umum & Pengadaan', 'Tahunan', 'Preventif', 'Manual', 2, 2, '2026-03-12', 'Pihak Ketiga'],
    ['C-28', 'Reviu spesifikasi teknis oleh tim independen', 'Mencegah spesifikasi yang mengarah', 'Biro Umum & Pengadaan', 'Per paket', 'Preventif', 'Manual', 3, 2, '2026-08-11', 'Kepatuhan'],
    ['C-29', 'Pemeliharaan preventif gedung & APAR', 'Mencegah kebakaran dan kerusakan fasilitas', 'Biro Umum & Pengadaan', 'Triwulanan', 'Preventif', 'Manual', 3, 3, '2026-07-05', 'Operasional'],
    ['C-30', 'Analisis kebutuhan diklat & LMS', 'Menyusun pelatihan sesuai kesenjangan kompetensi', 'Biro SDM', 'Tahunan', 'Preventif', 'Otomatis', 3, 2, '2026-04-22', 'SDM'],
    ['C-31', 'Pengawasan melekat & e-presensi', 'Mendeteksi pelanggaran disiplin', 'Biro SDM', 'Harian', 'Detektif', 'Otomatis', 3, 3, '2026-09-28', 'SDM'],
    ['C-32', 'Harmonisasi rancangan peraturan', 'Menjamin peraturan selaras dengan regulasi di atasnya', 'Biro Hukum', 'Per rancangan', 'Preventif', 'Manual', 4, 3, '2026-08-06', 'Hukum'],
    ['C-33', 'Templat kontrak baku hasil reviu Biro Hukum', 'Melindungi kepentingan hukum lembaga', 'Biro Hukum', 'Per kontrak', 'Preventif', 'Manual', 3, 2, '2026-05-19', 'Hukum'],
    ['C-34', 'Monitoring capaian IKU triwulanan', 'Mendeteksi deviasi capaian kinerja', 'Direktorat Perencanaan', 'Triwulanan', 'Detektif', 'Manual', 3, 3, '2026-07-15', 'Strategis'],
    ['C-35', 'PKPT berbasis risiko', 'Mengarahkan audit ke area berisiko tinggi', 'Inspektorat', 'Tahunan', 'Preventif', 'Manual', 2, 2, '2026-01-20', 'Kepatuhan'],
    ['C-36', 'Unit Pengendalian Gratifikasi & WBS', 'Menerima dan menindaklanjuti laporan pelanggaran', 'Inspektorat', 'Berkelanjutan', 'Detektif', 'Manual', 3, 2, '2026-06-12', 'Kepatuhan']
  ];
  const CAT_OF = { 'C-01': 'Teknologi Informasi', 'C-02': 'Teknologi Informasi', 'C-03': 'Teknologi Informasi', 'C-04': 'Keamanan Siber', 'C-05': 'Keamanan Siber', 'C-06': 'Keamanan Siber', 'C-07': 'Pihak Ketiga', 'C-08': 'Keuangan', 'C-09': 'Keuangan', 'C-10': 'Reputasi', 'C-11': 'Kepatuhan', 'C-12': 'Kepatuhan' };
  D.CONTROLS.forEach((c) => { c.cat = CAT_OF[c.id]; });
  CTRL_NEW.forEach(([id, n, obj, owner, freq, type, mode, des, ope, last, cat]) => D.CONTROLS.push({ id, n, obj, owner, freq, type, mode, des, ope, last, cat }));
  const ctrlByCat = {};
  D.CONTROLS.forEach((c) => { (ctrlByCat[c.cat] = ctrlByCat[c.cat] || []).push(c.id); });
  ctrlByCat['Operasional'] = (ctrlByCat['Operasional'] || []).concat(['C-07']);

  /* Status & atribut untuk risiko tambahan */
  let closedLeft = 13, waitLeft = 4, draftLeft = 3;
  const counters = { 1: 0, 2: 0, 3: 0, 4: 0 };
  T.forEach((t, i) => {
    const [unit, cat, obj, tier, name, proc, cause, event, impact, src] = t;
    const res = CELLS[tier][counters[tier]++];
    if (!res) throw new Error('Pool sel residual habis untuk tier ' + tier);
    const noCtrl = (i % 11 === 7);
    const dL = noCtrl ? 0 : pick([1, 1, 2, 0]), dI = noCtrl ? 0 : pick([0, 1, 0]);
    const inh = [Math.min(5, res[0] + dL), Math.min(5, res[1] + dI)];
    if (!noCtrl && sc(inh) === sc(res)) inh[0] = Math.min(5, inh[0] + 1);
    const tgt = tier >= 3 ? [Math.max(1, res[0] - 2), Math.max(1, res[1] - 1)] : tier === 2 ? [Math.max(1, res[0] - 1), res[1]] : res.slice();
    let status;
    if (tier === 1 && closedLeft > 0 && i % 2 === 0) { status = 'Ditutup'; closedLeft--; }
    else if (tier >= 2 && waitLeft > 0 && i % 9 === 4) { status = 'Menunggu Persetujuan'; waitLeft--; }
    else if (tier === 2 && draftLeft > 0 && i % 13 === 6) { status = 'Draft'; draftLeft--; }
    else if (tier >= 3) status = 'Dalam Penanganan';
    else if (tier === 2) status = i % 3 === 0 ? 'Dipantau' : 'Dalam Penanganan';
    else status = 'Dipantau';
    const trend = tier === 4 ? 'up' : tier === 3 ? (i % 4 === 0 ? 'up' : i % 4 === 1 ? 'down' : 'flat') : tier === 2 ? (i % 5 === 0 ? 'down' : i % 17 === 3 ? 'up' : 'flat') : (status === 'Ditutup' ? 'down' : 'flat');
    const due = status === 'Ditutup' ? addDays('2026-05-01', between(0, 120)) : addDays('2026-09-12', between(0, 200));
    const created = addDays('2025-11-01', between(0, 330));
    const pool = ctrlByCat[cat] || [];
    const ctrl = noCtrl || !pool.length ? [] : (tier >= 2 && pool.length > 1 ? [pool[i % pool.length], pool[(i + 1) % pool.length]] : [pool[i % pool.length]]);
    const treat = cat === 'Pihak Ketiga' || (cat === 'Operasional' && /listrik|kebakaran|kendaraan/i.test(name)) ? 'Bagikan' : tier === 1 ? (i % 3 === 0 ? 'Terima' : 'Kurangi') : name.startsWith('Pengadaan darurat') ? 'Hindari' : 'Kurangi';
    D.RISKS.push({ id: 'R-' + pad(21 + i), name, unit, cat, obj, proc, cause, event, impact, owner: OWNER[unit], inh, res, tgt, treat, status, trend, due, ctrl: Array.from(new Set(ctrl)), kri: [], src, created });
  });
  D.RISKS.slice(0, 20).forEach((r, i) => { r.created = addDays('2025-10-15', i * 9); });
  ['R-002', 'R-009', 'R-015', 'R-020'].forEach((id) => { const r = D.RISKS.find((x) => x.id === id); if (r) r.created = addDays('2026-07-03', r.id.charCodeAt(4) * 3 % 80); });
  const byName = (s) => D.RISKS.find((r) => r.name.toLowerCase().includes(s.toLowerCase()));

  /* ================= 2. KRI tambahan ================= */
  const KRI_NEW = [
    ['K-09', 'Backlog permohonan perizinan', 'berkas', 'Antrean permohonan', 1500, 3000, [800, 950, 1100, 1300, 1250, 1600, 2100, 2600, 3100, 3900, 4600, 5200], 0],
    ['K-10', 'Waktu respons aplikasi (P95)', 'detik', 'Kinerja aplikasi menurun', 3, 8, [2.1, 2.4, 2.2, 2.8, 3.1, 2.9, 3.6, 4.2, 3.8, 4.5, 5.1, 4.8], 1],
    ['K-11', 'Serangan DDoS terdeteksi', 'kejadian/bulan', 'Serangan DDoS', 3, 8, [1, 2, 1, 3, 2, 4, 3, 5, 4, 6, 7, 6], 0],
    ['K-12', 'Realisasi anggaran kumulatif vs target', '% target', 'Penyerapan anggaran menumpuk', 95, 85, [96, 94, 97, 93, 92, 95, 91, 90, 89, 88, 87, 88], 0, true],
    ['K-13', 'Laporan ke Ombudsman', 'laporan/bulan', 'Maladministrasi', 3, 6, [2, 1, 3, 2, 4, 3, 3, 5, 4, 4, 5, 4], 0],
    ['K-14', 'Rekomendasi audit lebih dari 60 hari', 'rekomendasi', 'Tindak lanjut rekomendasi audit', 5, 10, [18, 17, 16, 15, 14, 13, 12, 12, 11, 10, 9, 8], 0],
    ['K-15', 'Klik tautan pada simulasi phishing', '% pegawai', 'Phishing terhadap akun', 5, 12, [18, 16, 15, 14, 12, 12, 11, 10, 9, 8, 7, 6], 0],
    ['K-16', 'Pelanggaran disiplin berat', 'kasus/bulan', 'Pelanggaran disiplin', 2, 4, [1, 0, 2, 1, 1, 0, 2, 1, 0, 1, 1, 0], 0],
    ['K-17', 'Ketersediaan portal layanan', '%', 'Gangguan layanan pusat data', 99.5, 99, [99.8, 99.9, 99.7, 99.9, 99.8, 99.6, 99.7, 99.9, 99.8, 99.7, 99.6, 99.7], 1, true],
    ['K-18', 'Paket pengadaan terlambat', '% paket', 'Keterlambatan proses pengadaan', 10, 20, [22, 20, 18, 19, 17, 15, 16, 14, 13, 12, 11, 9], 0]
  ];
  KRI_NEW.forEach(([id, n, u, rk, g, r, v, fmt, inv]) => { const risk = byName(rk); D.KRIS.push({ id, n, u, risk: risk ? risk.id : 'R-001', g, r, v, fmt, inv: !!inv }); });
  D.KRIS.forEach((k) => { const r = D.RISKS.find((x) => x.id === k.risk); if (r && !r.kri.includes(k.id)) r.kri.push(k.id); });

  /* ================= 3. Action plan ================= */
  const ACT_LIB = {
    'Teknologi Informasi': ['Menyusun & menguji rencana pemulihan bencana (DRP)', 'Menerapkan monitoring kapasitas dan alert otomatis', 'Memperbarui perangkat yang melewati masa dukungan', 'Menyusun runbook operasional & eskalasi', 'Uji beban (load test) sebelum rilis besar', 'Dokumentasi arsitektur & knowledge transfer'],
    'Keamanan Siber': ['Penerapan MFA untuk seluruh akun', 'Pemindaian kerentanan & remediasi temuan kritikal', 'Hardening konfigurasi server sesuai CIS Benchmark', 'Pelatihan kesadaran keamanan seluruh pegawai', 'Penerapan SIEM & korelasi log 90 hari', 'Secret scanning pada pipeline CI/CD'],
    'Keuangan': ['Rekonsiliasi bulanan dengan KPPN', 'Penyusunan rencana penarikan dana per bulan', 'Bimbingan teknis bendahara unit', 'Integrasi aplikasi keuangan dengan data kontrak', 'Reviu internal sebelum penyampaian laporan'],
    'Kepatuhan': ['Sosialisasi regulasi terbaru ke unit teknis', 'Penyusunan checklist kepatuhan', 'Pemantauan tindak lanjut secara bulanan', 'Penguatan pemisahan tugas pada proses kritikal', 'Reviu kepatuhan oleh Inspektorat'],
    'Operasional': ['Revisi SOP dan sosialisasi ke petugas', 'Penambahan kapasitas petugas pada jam sibuk', 'Kontrak pemeliharaan preventif', 'Digitalisasi tanda terima berkas', 'Simulasi tanggap darurat'],
    'Strategis': ['Penyelarasan cascading IKU sampai level kegiatan', 'Reviu renstra paruh waktu', 'Forum koordinasi perencanaan triwulanan', 'Penyusunan skenario anggaran alternatif'],
    'Hukum': ['Reviu & pembaruan templat perjanjian', 'Harmonisasi dengan Kementerian Hukum', 'Inventarisasi perjanjian & masa berlakunya', 'Pendampingan hukum untuk keputusan berisiko tinggi'],
    'SDM': ['Analisis kebutuhan diklat berbasis kompetensi', 'Penyusunan rencana suksesi jabatan kritikal', 'Pemutakhiran analisis beban kerja', 'Program mentoring pegawai baru'],
    'Reputasi': ['Penyusunan protokol komunikasi krisis', 'Pelatihan admin media sosial', 'Publikasi standar pelayanan di seluruh kanal', 'Survei kepuasan dengan sampel representatif'],
    'Pihak Ketiga': ['Penilaian kesehatan keuangan vendor', 'Revisi SLA & klausul penalti kontrak', 'Penyusunan exit plan dan vendor cadangan', 'Rapat evaluasi kinerja vendor triwulanan']
  };
  const PIC = ['Direktorat TI', 'Biro Keuangan', 'Direktorat Pelayanan', 'Biro Umum & Pengadaan', 'Biro SDM', 'Biro Hukum', 'Direktorat Perencanaan', 'Inspektorat'];
  const PIC_SUB = { 0: ['Tim Infrastruktur', 'Tim Aplikasi', 'Tim Keamanan Informasi', 'Tim Operasi TI'], 1: ['Subbag Anggaran', 'Subbag Perbendaharaan', 'Subbag Akuntansi'], 2: ['Subdit Standar Layanan', 'Subdit Pengaduan', 'Koordinator Loket'], 3: ['Bagian Pengadaan', 'Bagian Rumah Tangga & Aset'], 4: ['Bagian Pengembangan SDM', 'Bagian Mutasi'], 5: ['Bagian Peraturan', 'Bagian Bantuan Hukum'], 6: ['Bagian Program', 'Bagian Evaluasi Kinerja'], 7: ['Auditor Wilayah I', 'Auditor Wilayah II'] };
  let actN = D.ACTIONS.length;
  D.RISKS.slice(20).forEach((r, i) => {
    const tier = sc(r.res) >= 16 ? 4 : sc(r.res) >= 10 ? 3 : sc(r.res) >= 5 ? 2 : 1;
    const n = r.status === 'Draft' ? 0 : tier >= 3 ? 3 : tier === 2 ? 2 : 1;
    const lib = ACT_LIB[r.cat] || ACT_LIB.Operasional;
    for (let k = 0; k < n; k++) {
      actN++;
      const t = lib[(i + k * 2) % lib.length];
      let prog, due;
      if (r.status === 'Ditutup') { prog = 100; due = addDays(r.due, -between(5, 40)); }
      else {
        due = addDays('2026-07-15', between(0, 200));
        const elapsed = Math.max(0, Math.min(1, (new Date('2026-10-05') - new Date(addDays(due, -120))) / (120 * 864e5)));
        prog = Math.min(100, Math.round((elapsed * 100 + between(-35, 25)) / 5) * 5);
        if (prog < 0) prog = 0;
        if (due < '2026-10-05' && prog < 100 && rnd() < 0.62) prog = 100;
      }
      D.ACTIONS.push({ id: 'A-' + pad(actN), risk: r.id, t, pic: pick(PIC_SUB[r.unit] || [PIC[r.unit]]), budget: pick([0, 0, 15, 25, 40, 60, 85, 120, 175, 240, 320, 450]), due, prog, prio: tier === 4 ? 'Kritis' : tier === 3 ? 'Tinggi' : tier === 2 ? 'Sedang' : 'Rendah', ev: prog >= 100 ? between(1, 4) : prog > 0 ? between(0, 2) : 0, cancel: r.status !== 'Ditutup' && actN % 37 === 0 });
    }
  });

  /* ================= 4. Insiden & loss event ================= */
  const INC_NEW = [
    ['INC-2026-012', '2026-08-30', 'Kantor Pelayanan Terpadu, Jakarta', 'Antrean permohonan', 'Backlog permohonan mencapai 4.100 berkas', 'Lonjakan permohonan pasca-kebijakan relaksasi perizinan. Rata-rata waktu proses naik dari 2,6 menjadi 6,8 hari kerja.', 'Verifikator tetap 42 orang; tidak ada skema lembur terencana.', '1.380 permohonan melewati standar waktu layanan; 96 pengaduan baru.', 0, 'Lembur terjadwal dan perbantuan 15 pegawai dari unit lain.', 'Verifikasi berbasis risiko dan pra-verifikasi otomatis.', 'Tindakan Korektif'],
    ['INC-2026-010', '2026-07-22', 'Portal Layanan Publik', 'Serangan DDoS', 'Serangan DDoS membuat portal tidak dapat diakses 3 jam', 'Trafik 38 Gbps dari botnet terdeteksi pukul 09.12; portal tidak responsif hingga 12.20.', 'Kapasitas scrubbing on-premise hanya 10 Gbps.', '27.000 pengguna gagal mengakses layanan.', 18, 'Pengalihan trafik ke layanan scrubbing penyedia jaringan.', 'Langganan anti-DDoS berbasis cloud (C-13).', 'Ditutup'],
    ['INC-2026-008', '2026-06-17', 'Email Kantor', 'Phishing terhadap akun', 'Empat akun email pegawai diambil alih melalui phishing', 'Email palsu bertema "pembaruan tunjangan kinerja" dikirim ke 312 pegawai; 4 akun memasukkan kredensial.', 'MFA belum wajib untuk email.', 'Email phishing lanjutan terkirim ke mitra dari akun resmi.', 0, 'Reset kredensial, blokir domain pengirim, pemberitahuan ke mitra.', 'Kewajiban MFA (C-14) dan simulasi phishing.', 'Ditutup'],
    ['INC-2026-007', '2026-06-02', 'Direktorat Pelayanan Publik', 'Kesalahan verifikasi berkas', 'Izin terbit untuk berkas yang tidak memenuhi syarat', 'Audit internal menemukan 7 izin terbit tanpa dokumen AMDAL yang dipersyaratkan.', 'Checklist verifikasi tidak seragam antarpetugas.', '7 izin dibekukan dan pemohon dipanggil ulang.', 0, 'Pembekuan izin dan verifikasi ulang.', 'Checklist verifikasi terstandar (C-23).', 'Ditutup'],
    ['INC-2026-005', '2026-04-11', 'Portal Layanan Publik', 'Sertifikat SSL', 'Sertifikat SSL portal kedaluwarsa selama 2 jam', 'Browser menampilkan peringatan keamanan sejak 00.00; diperbarui pukul 02.05.', 'Pengingat perpanjangan dikirim ke email pegawai yang sudah mutasi.', 'Akses portal terganggu pada dini hari.', 0, 'Perpanjangan sertifikat darurat.', 'Otomatisasi perpanjangan sertifikat (ACME).', 'Ditutup'],
    ['INC-2026-004', '2026-03-20', 'Gedung A lantai 3', 'Kebakaran di gedung', 'Korsleting panel listrik memicu api kecil', 'Asap terdeteksi pukul 15.40; api dipadamkan dengan APAR dalam 8 menit. Gedung dievakuasi.', 'Panel listrik berumur lebih dari 20 tahun tanpa termografi berkala.', 'Panel dan 6 perangkat jaringan rusak; lantai 3 tanpa listrik 2 hari.', 64, 'Evakuasi, pemadaman dengan APAR, penggantian panel.', 'Pemeliharaan preventif & termografi panel (C-29).', 'Ditutup'],
    ['INC-2026-003', '2026-02-27', 'Pusat Data Utama', 'Kegagalan proses backup', 'Backup harian gagal 5 hari tanpa terdeteksi', 'Kegagalan diketahui saat permintaan restore data uji pada 27 Februari.', 'Notifikasi kegagalan backup masuk ke kotak surat yang tidak dipantau.', 'Titik pemulihan terakhir mundur 5 hari.', 0, 'Backup manual penuh dan perbaikan job.', 'Integrasi notifikasi backup ke NOC (C-02).', 'Ditutup'],
    ['INC-2026-002', '2026-02-05', 'Pusat Data Utama', 'Gangguan layanan pusat data', 'Kegagalan basis data layanan perizinan', 'Disk basis data penuh akibat log transaksi tidak dirotasi; layanan berhenti 5 jam.', 'Rotasi log tidak dikonfigurasi setelah migrasi.', 'Layanan perizinan tidak tersedia 5 jam.', 50, 'Penambahan kapasitas disk dan pembersihan log.', 'Monitoring kapasitas disk (C-18).', 'Ditutup'],
    ['INC-2026-001', '2026-01-14', 'Loket Layanan Kantor Wilayah Timur', 'Pungutan liar', 'Laporan dugaan pungutan liar oleh petugas loket', 'Pemohon melaporkan permintaan uang Rp500 ribu untuk mempercepat layanan melalui WBS.', 'Interaksi tatap muka tanpa pengawasan CCTV di loket belakang.', 'Pemeriksaan disiplin satu petugas; pemberitaan media lokal.', 0, 'Pemeriksaan oleh Inspektorat dan pembebasan tugas sementara.', 'CCTV seluruh loket (C-25) dan layanan tanpa tatap muka.', 'Ditutup']
  ];
  INC_NEW.forEach(([id, date, loc, rk, t, chrono, cause, impact, loss, response, corrective, status]) => { const r = byName(rk); D.INCIDENTS.push({ id, date, loc, risk: r ? r.id : 'R-001', t, chrono, cause, impact, loss, response, corrective, status }); });
  D.INCIDENTS.sort((a, b) => b.date.localeCompare(a.date));
  D.LOSS_HISTORY = [
    { y: 2024, risk: 'Downtime layanan', ev: 'Server aplikasi gagal', loss: 150 },
    { y: 2024, risk: 'Kebakaran gedung', ev: 'Kebakaran gudang arsip', loss: 40 },
    { y: 2024, risk: 'Fraud pengadaan', ev: 'Kelebihan bayar pekerjaan renovasi', loss: 86 },
    { y: 2025, risk: 'Gangguan jaringan', ev: 'Network failure kantor pusat', loss: 75 },
    { y: 2025, risk: 'Pembayaran ganda', ev: 'Tagihan ganda vendor ATK', loss: 22 },
    { y: 2025, risk: 'Gugatan hukum', ev: 'Biaya perkara PTUN', loss: 35 },
    { y: 2025, risk: 'Kendaraan dinas', ev: 'Kecelakaan kendaraan operasional', loss: 12 }
  ].concat(D.INCIDENTS.filter((i) => i.loss > 0).map((i) => ({ y: +i.date.slice(0, 4), risk: (D.RISKS.find((r) => r.id === i.risk) || {}).name || i.risk, ev: i.t, loss: i.loss }))).sort((a, b) => a.y - b.y);

  /* ================= 5. Dokumen ================= */
  [
    ['Profil Risiko BLDN TW II 2026', 'Laporan', 'Kerangka', 'v1.0', 'Osmond', '2026-07-12', '—', 'Disetujui'],
    ['Notulen Rapat Komite Manajemen Risiko Juli 2026', 'Berita Acara', 'Kerangka', 'v1.0', 'Yudi Pratama', '2026-07-19', '—', 'Disetujui'],
    ['Pedoman Penyusunan KRI', 'Kebijakan', 'Kerangka', 'v1.2', 'Osmond', '2026-03-04', '2028-03-04', 'Disetujui'],
    ['SOP Penanganan Insiden Keamanan Informasi', 'SOP', 'C-05', 'v2.0', 'Tim Keamanan Informasi', '2026-04-15', '2027-04-15', 'Disetujui'],
    ['SOP Verifikasi Berkas Permohonan Izin', 'SOP', 'C-23', 'v3.1', 'Agus Prasetyo', '2026-06-20', '2027-06-20', 'Disetujui'],
    ['SOP Pengelolaan Kas Kecil', 'SOP', 'C-20', 'v1.1', 'Sri Wahyuni', '2024-11-10', '2026-11-10', 'Disetujui'],
    ['Laporan Simulasi Phishing TW III', 'Hasil Pengujian', 'C-16', 'v1.0', 'Tim Keamanan Informasi', '2026-09-25', '—', 'Review'],
    ['Hasil Pemindaian Kerentanan September 2026', 'Hasil Pengujian', 'C-15', 'v1.0', 'Tim Keamanan Informasi', '2026-09-30', '—', 'Disetujui'],
    ['Foto pemasangan CCTV loket 1–12', 'Foto', 'C-25', 'v1.0', 'Bagian Rumah Tangga & Aset', '2026-09-08', '—', 'Disetujui'],
    ['Berita Acara Uji Termografi Panel Listrik', 'Berita Acara', 'C-29', 'v1.0', 'Bagian Rumah Tangga & Aset', '2026-07-05', '—', 'Disetujui'],
    ['Kontrak Layanan Anti-DDoS 2026–2027', 'Kontrak', 'C-13', 'v1.0', 'Dewi Lestari', '2026-08-12', '2027-08-11', 'Disetujui'],
    ['Kontrak Call Center 2026', 'Kontrak', 'Umum', 'v1.0', 'Dewi Lestari', '2026-01-02', '2026-12-31', 'Disetujui'],
    ['Sertifikat SSL *.bldn.go.id', 'Sertifikat', 'Umum', '—', 'Tim Infrastruktur', '2026-04-11', '2026-11-20', 'Disetujui'],
    ['Polis Asuransi Gedung & Isi 2026', 'Kontrak', 'C-07', 'v1.0', 'Bagian Rumah Tangga & Aset', '2026-01-10', '2026-12-31', 'Disetujui'],
    ['LHP BPK atas Laporan Keuangan 2025', 'Hasil Audit', 'C-12', 'v1.0', 'Nurul Hidayah', '2026-05-28', '—', 'Disetujui'],
    ['Screenshot konfigurasi MFA Microsoft 365', 'Screenshot', 'C-14', 'v1.0', 'Tim Keamanan Informasi', '2026-08-30', '—', 'Review'],
    ['Rencana Suksesi Jabatan Kritikal 2026', 'Kebijakan', 'R-017', 'v1.0', 'Rudi Hartono', '2026-08-25', '2027-08-25', 'Disetujui'],
    ['Draft Exit Plan Penyedia Cloud', 'Laporan', 'A-009', 'v0.4', 'Biro Umum & Pengadaan', '2026-09-21', '—', 'Draft']
  ].forEach(([n, type, ref, v, by, d, exp, st]) => D.DOCS.push({ n, type, ref, v, by, d, exp, st }));

  /* ================= 6. Pengguna ================= */
  [
    ['Rahmat Hidayat', 'rahmat.h@bldn.go.id', 'Direktorat Teknologi Informasi', 'Risk Officer', 'Hari ini 11:02'],
    ['Siti Nurhaliza', 'siti.n@bldn.go.id', 'Biro Keuangan', 'Risk Officer', 'Hari ini 09:47'],
    ['Andi Saputra', 'andi.s@bldn.go.id', 'Direktorat Pelayanan Publik', 'Risk Officer', '4 Okt 2026'],
    ['Wulan Sari', 'wulan.s@bldn.go.id', 'Biro Hukum', 'Risk Officer', '2 Okt 2026'],
    ['Eko Prabowo', 'eko.p@bldn.go.id', 'Direktorat Perencanaan', 'Risk Officer', '1 Okt 2026'],
    ['Dian Permata', 'dian.p@bldn.go.id', 'Inspektorat', 'Risk Officer', '30 Sep 2026'],
    ['Agus Prasetyo', 'agus.p@bldn.go.id', 'Direktorat Pelayanan Publik', 'Risk Owner', 'Hari ini 10:15'],
    ['Dewi Lestari', 'dewi.l@bldn.go.id', 'Biro Umum & Pengadaan', 'Risk Owner', '3 Okt 2026'],
    ['Rudi Hartono', 'rudi.h@bldn.go.id', 'Biro SDM', 'Risk Owner', '28 Sep 2026'],
    ['Maya Anggraini', 'maya.a@bldn.go.id', 'Biro Hukum', 'Risk Owner', '27 Sep 2026'],
    ['Bambang Setiadi', 'bambang.s@bldn.go.id', 'Direktorat Perencanaan', 'Risk Owner', '25 Sep 2026'],
    ['Dr. Ratna Kusuma', 'sestama@bldn.go.id', 'Sekretariat Utama', 'Management', '2 Okt 2026'],
    ['Ir. Haris Munandar', 'deputi.ld@bldn.go.id', 'Deputi Layanan Digital', 'Management', '30 Sep 2026'],
    ['Teguh Santoso', 'teguh.s@bldn.go.id', 'Inspektorat', 'Auditor', '29 Sep 2026'],
    ['Putri Ayu', 'putri.a@bldn.go.id', 'Unit Manajemen Risiko', 'Risk Administrator', 'Hari ini 08:30']
  ].forEach(([n, e, unit, role, last]) => D.USERS.push({ n, e, unit, role, last }));

  /* ================= 7. Persetujuan ================= */
  const wfRisks = D.RISKS.filter((r) => r.status === 'Menunggu Persetujuan' && !['R-015'].includes(r.id));
  const officers = ['Rahmat Hidayat', 'Siti Nurhaliza', 'Andi Saputra', 'Wulan Sari', 'Eko Prabowo', 'Dian Permata', 'Fajar Nugroho', 'Lina Marlina'];
  const officerOf = (u) => ['Rahmat Hidayat', 'Siti Nurhaliza', 'Andi Saputra', 'Fajar Nugroho', 'Lina Marlina', 'Wulan Sari', 'Eko Prabowo', 'Dian Permata'][u];
  wfRisks.forEach((r, i) => D.APPROVALS.push({ id: 'WF-0' + (398 - i * 3), type: 'Risiko Baru', ref: r.id, by: officerOf(r.unit), role: 'Risk Officer', date: addDays('2026-09-20', i * 3), stage: 1 + (i % 2), note: `Risiko baru hasil identifikasi ${r.proc.toLowerCase()}.` }));
  const extraWF = [['Perubahan Skor', 'Antrean permohonan', 2, 'Skor residual naik ke 20 berdasarkan KRI backlog permohonan (K-09).'], ['Rencana Mitigasi', 'Serangan DDoS', 3, 'Penambahan langganan anti-DDoS berbasis cloud senilai Rp450 jt.'], ['Penutupan Risiko', 'Sertifikat SSL', 2, 'Perpanjangan sertifikat sudah otomatis sejak Mei 2026.'], ['Perubahan Skor', 'Phishing terhadap akun', 1, 'Skor turun setelah MFA aktif untuk 92% akun.']];
  extraWF.forEach(([type, rk, stage, note], i) => { const r = byName(rk); if (r) D.APPROVALS.push({ id: 'WF-0' + (409 - i * 2), type, ref: r.id, by: officerOf(r.unit) || officers[i], role: 'Risk Officer', date: addDays('2026-09-24', i * 2), stage, note }); });
  D.APPROVALS.sort((a, b) => b.date.localeCompare(a.date) || b.id.localeCompare(a.id));

  /* ================= 8. Audit trail ================= */
  const ACTORS = { 0: 'Rahmat Hidayat', 1: 'Siti Nurhaliza', 2: 'Andi Saputra', 3: 'Fajar Nugroho', 4: 'Lina Marlina', 5: 'Wulan Sari', 6: 'Eko Prabowo', 7: 'Dian Permata' };
  let ts = new Date('2026-10-05T13:50:00Z').getTime();
  const stamp = () => { ts -= between(95, 760) * 60000; let d = new Date(ts); let h = d.getUTCHours(); if (h < 7 || h > 18) { d.setUTCHours(between(8, 16)); ts = d.getTime(); } if (d.getUTCDay() === 0 || d.getUTCDay() === 6) { ts -= 2 * 864e5; d = new Date(ts); } return d.toISOString().slice(0, 16).replace('T', ' '); };
  const ips = () => `10.12.${between(1, 9)}.${between(2, 60)}`;
  const actSample = D.ACTIONS.filter((a) => a.prog > 0 && a.prog < 100);
  for (let i = 0; i < 64; i++) {
    const kind = i % 8, r = D.RISKS[between(0, D.RISKS.length - 1)], a = actSample[between(0, actSample.length - 1)];
    const who = ACTORS[r.unit];
    let e;
    if (kind === 0 || kind === 4) { const p = Math.max(0, a.prog - pick([10, 15, 20, 25])); const ra = D.RISKS.find((x) => x.id === a.risk); e = { u: ACTORS[ra.unit], a: 'Memperbarui progres mitigasi', ref: a.id, f: 'Progres', p: p + '%', n: a.prog + '%' }; }
    else if (kind === 1) e = { u: who, a: 'Mengunggah bukti', ref: a.id, f: 'Dokumen', p: '—', n: pick(['Laporan pelaksanaan', 'Berita acara', 'Foto kegiatan', 'Screenshot konfigurasi', 'Daftar hadir sosialisasi']) + '.pdf' };
    else if (kind === 2) e = { u: 'Osmond', a: 'Mereviu risiko', ref: r.id, f: 'Catatan reviu', p: '—', n: pick(['Kontrol perlu diuji ulang', 'Sesuai, lanjutkan pemantauan', 'Mohon lengkapi bukti pelaksanaan', 'Pertimbangkan opsi Bagikan']) };
    else if (kind === 3) { const k = D.KRIS[between(0, D.KRIS.length - 1)]; e = { u: 'Sistem', a: 'Sinkronisasi nilai KRI', ref: k.id, f: 'Nilai', p: String(k.v[k.v.length - 2]), n: String(k.v[k.v.length - 1]) }; }
    else if (kind === 5) e = { u: who, a: 'Mengubah pernyataan risiko', ref: r.id, f: 'Penyebab', p: '(versi sebelumnya)', n: r.cause.slice(0, 40) + '…' };
    else if (kind === 6) e = { u: D.PEOPLE[r.owner].n, a: 'Menyetujui rencana mitigasi', ref: r.id, f: 'Status', p: 'Review', n: 'Disetujui' };
    else e = { u: who, a: 'Menghubungkan kontrol', ref: r.id, f: 'Kontrol', p: '—', n: (r.ctrl[0] || 'C-01') };
    e.t = stamp(); e.ip = e.u === 'Sistem' ? '—' : ips();
    D.AUDIT.push(e);
  }
  D.AUDIT.sort((a, b) => b.t.localeCompare(a.t));

  /* ================= 9. Reviu periodik ================= */
  const noteFor = { up: ['KRI memburuk dua bulan berturut-turut.', 'Mitigasi tertunda dan paparan bertambah.', 'Muncul kejadian baru yang memperbesar kemungkinan.'], down: ['Kontrol baru terbukti efektif.', 'Action plan utama selesai lebih cepat.', 'Insiden tidak berulang sejak triwulan lalu.'], flat: ['Belum ada perubahan signifikan.', 'Mitigasi berjalan sesuai jadwal.', 'Menunggu hasil uji efektivitas kontrol.'] };
  const reviewed = new Set(D.REVIEWS.map((v) => v.r));
  D.RISKS.filter((r) => r.status !== 'Ditutup' && !reviewed.has(r.id) && (sc(r.res) >= 10 || r.trend !== 'flat')).forEach((r, i) => {
    const cur = sc(r.res);
    const prev = r.trend === 'up' ? Math.max(1, cur - pick([3, 4, 6])) : r.trend === 'down' ? Math.min(25, cur + pick([3, 4, 6])) : cur;
    D.REVIEWS.push({ r: r.id, prev, cur, note: noteFor[r.trend][i % 3] });
  });

  /* ================= 10. Perbaikan, peringatan, riwayat laporan ================= */
  [
    { id: 'IP-09', src: 'Insiden', ref: 'INC-2026-010', t: 'Langganan proteksi DDoS berbasis cloud dan uji ketahanan tahunan', pic: 'Tim Keamanan Informasi', due: '2026-11-15', st: 'Berjalan' },
    { id: 'IP-10', src: 'Insiden', ref: 'INC-2026-012', t: 'Model kapasitas verifikator berbasis proyeksi permohonan', pic: 'Direktorat Pelayanan', due: '2026-12-20', st: 'Berjalan' },
    { id: 'IP-11', src: 'Temuan Audit', ref: 'LHP Inspektorat 07/2026', t: 'Standarisasi checklist verifikasi berkas di seluruh kantor wilayah', pic: 'Direktorat Pelayanan', due: '2026-09-30', st: 'Selesai' },
    { id: 'IP-12', src: 'Pelanggaran KRI', ref: 'K-12', t: 'Kalender pengadaan terintegrasi dengan rencana penarikan dana', pic: 'Biro Keuangan', due: '2026-11-30', st: 'Berjalan' },
    { id: 'IP-13', src: 'Lessons Learned', ref: 'INC-2026-005', t: 'Otomatisasi perpanjangan sertifikat & domain', pic: 'Tim Infrastruktur', due: '2026-05-31', st: 'Selesai' },
    { id: 'IP-14', src: 'Kegagalan Kontrol', ref: 'C-27', t: 'Penilaian kesehatan keuangan vendor sebelum perpanjangan kontrak', pic: 'Biro Umum & Pengadaan', due: '2027-01-15', st: 'Belum Mulai' }
  ].forEach((p) => D.IMPROVE.push(p));
  D.IMPROVE.sort((a, b) => b.id.localeCompare(a.id, undefined, { numeric: true }));

  const backlog = byName('Antrean permohonan'), ddos = byName('Serangan DDoS');
  D.WARNINGS = [
    { c: 'var(--lv-vh)', i: 'alert', t: '<b>KRITIS</b> · Backlog permohonan perizinan 5.200 berkas, melewati batas toleransi 3.000.', m: `K-09 · ${backlog.id} · 5 Okt 2026 07:00`, go: 'kri' },
    { c: 'var(--lv-vh)', i: 'alert', t: '<b>KRITIS</b> · KRI downtime sistem layanan 3,4 jam telah melewati batas toleransi 3 jam.', m: 'K-01 · R-001 · 5 Okt 2026 08:00', go: 'kri' },
    { c: 'var(--lv-h)', i: 'up', t: '<b>PERINGATAN</b> · Risiko “Serangan ransomware pada sistem layanan” meningkat dari Tinggi menjadi Sangat Tinggi.', m: 'R-002 · 5 Okt 2026 14:32', go: 'risk-R-002' },
    { c: 'var(--lv-vh)', i: 'alert', t: '<b>KRITIS</b> · 6 posisi kunci belum memiliki pengganti (batas 5).', m: 'K-08 · R-009 · 1 Okt 2026', go: 'kri' },
    { c: 'var(--lv-h)', i: 'clock', t: '<b>PERINGATAN</b> · Action plan melewati tenggat, termasuk A-005, A-009, dan A-014.', m: 'Mitigasi · 1 Okt 2026', go: 'treatment' },
    { c: 'var(--lv-m)', i: 'gauge', t: `Serangan DDoS terdeteksi 6 kali bulan ini (batas waspada 3).`, m: `K-11 · ${ddos.id} · 30 Sep 2026`, go: 'kri' },
    { c: 'var(--lv-m)', i: 'gauge', t: 'Realisasi anggaran kumulatif 88% dari target triwulan.', m: 'K-12 · 30 Sep 2026', go: 'kri' },
    { c: 'var(--lv-m)', i: 'gauge', t: 'SLA penyedia cloud 99,4% berada di bawah target 99,5%.', m: 'K-04 · R-004 · 30 Sep 2026', go: 'kri' },
    { c: 'var(--accent)', i: 'file', t: 'Sertifikat ISO/IEC 27001 kedaluwarsa dalam 27 hari.', m: 'Dokumen · 5 Okt 2026', go: 'documents' },
    { c: 'var(--accent)', i: 'file', t: 'SOP Pengelolaan Kas Kecil kedaluwarsa dalam 36 hari.', m: 'Dokumen · 5 Okt 2026', go: 'documents' }
  ];

  D.REPORT_LOG = [
    ['Executive Risk Report TW II 2026', 'PDF', 'Osmond', '2026-07-14 10:20', 'Disetujui Kepala BLDN'],
    ['Risk Register TW III 2026 (snapshot)', 'Excel', 'Putri Ayu', '2026-10-01 08:05', 'Otomatis bulanan'],
    ['KRI Report September 2026', 'PDF', 'Sistem', '2026-10-01 07:00', 'Otomatis bulanan'],
    ['Overdue Action Report', 'Excel', 'Osmond', '2026-09-29 16:12', 'Dikirim ke Risk Owner'],
    ['Control Effectiveness Semester I 2026', 'Word', 'Nurul Hidayah', '2026-08-02 13:40', 'Disetujui Inspektur'],
    ['Risk Incident Report TW II 2026', 'PDF', 'Yudi Pratama', '2026-07-10 09:15', 'Disetujui'],
    ['Risk Profile Unit — Direktorat TI', 'PDF', 'Rahmat Hidayat', '2026-09-18 14:55', 'Internal unit'],
    ['Risk Review Report Agustus 2026', 'Word', 'Osmond', '2026-09-06 11:30', 'Berita acara ditandatangani']
  ].map(([n, f, by, t, note]) => ({ n, f, by, t, note }));

  /* ================= 11. Hitung ulang seluruh agregat ================= */
  const R = D.RISKS, TODAY = D.TODAY;
  const heat = (key) => { const h = {}; R.forEach((r) => { const k = r[key].join('-'); h[k] = (h[k] || 0) + 1; }); return h; };
  D.HEAT = { res: heat('res'), inh: heat('inh') };
  const lvCount = (key) => { const c = { vh: 0, h: 0, m: 0, l: 0 }; R.forEach((r) => { c[lvk(sc(r[key]))]++; }); return c; };
  const res = lvCount('res'), inh = lvCount('inh');
  const live = D.ACTIONS.filter((a) => !a.cancel);
  const st = (a) => (a.cancel ? 'cancel' : a.prog >= 100 ? 'done' : a.due < TODAY ? 'late' : a.prog === 0 ? 'todo' : 'run');
  D.MITIG = { total: D.ACTIONS.length, done: 0, run: 0, todo: 0, late: 0, cancel: 0 };
  D.ACTIONS.forEach((a) => { D.MITIG[st(a)]++; });
  const lvOrd = { l: 0, m: 1, h: 2, vh: 3 };
  D.PROFILE = {
    total: R.length, vh: res.vh, h: res.h, m: res.m, l: res.l, inh,
    newQ: R.filter((r) => r.created >= '2026-07-01').length,
    treating: R.filter((r) => r.status === 'Dalam Penanganan').length,
    overdueRisk: R.filter((r) => r.status !== 'Ditutup' && r.due < TODAY).length,
    closed: R.filter((r) => r.status === 'Ditutup').length,
    up: R.filter((r) => r.trend === 'up').length, flat: R.filter((r) => r.trend === 'flat').length, down: R.filter((r) => r.trend === 'down').length,
    mitig: Math.round(live.reduce((s, a) => s + a.prog, 0) / live.length),
    effect: Math.round((R.filter((r) => lvOrd[lvk(sc(r.res))] < lvOrd[lvk(sc(r.inh))]).length / R.length) * 100),
    avg: Math.round((R.reduce((s, r) => s + sc(r.res), 0) / R.length) * 10) / 10
  };
  D.QUARTERS[D.QUARTERS.length - 1] = { q: D.QUARTERS[D.QUARTERS.length - 1].q, vh: res.vh, h: res.h, m: res.m, l: res.l };
  const last = D.AVG_TREND.values[D.AVG_TREND.values.length - 1];
  D.AVG_TREND.values = D.AVG_TREND.values.map((v) => Math.round((v - last + D.PROFILE.avg) * 10) / 10);
  D.TAXONOMY.forEach((t) => { t.cnt = R.filter((r) => r.cat === t.k).length; });
  D.OBJECTIVES.forEach((o) => {
    const rs = R.filter((r) => r.obj === o.id);
    o.cnt = rs.length; o.vh = rs.filter((r) => sc(r.res) >= 16).length; o.h = rs.filter((r) => sc(r.res) >= 10 && sc(r.res) < 16).length;
    o.cov = Math.round((rs.filter((r) => r.ctrl.length).length / (rs.length || 1)) * 100); o.kri = new Set(rs.flatMap((r) => r.kri)).size;
  });
  D.BY_UNIT = D.UNITS.map((u, i) => { const rs = R.filter((r) => r.unit === i); return [u, rs.length, rs.filter((r) => sc(r.res) >= 16).length, rs.filter((r) => sc(r.res) >= 10 && sc(r.res) < 16).length]; }).sort((a, b) => b[1] - a[1]);
  const proc = {}; R.forEach((r) => { proc[r.proc] = (proc[r.proc] || 0) + 1; });
  D.BY_PROCESS = Object.entries(proc).sort((a, b) => b[1] - a[1]).slice(0, 8);
})(window.D);
