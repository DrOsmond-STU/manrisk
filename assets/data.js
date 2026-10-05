/* ManRisk ERM — data contoh untuk purwarupa.
   Seluruh organisasi, nama, dan angka di bawah ini fiktif dan hanya untuk demonstrasi alur UI. */
window.D = (function () {
  const TODAY = '2026-10-05';

  const ORG = { name: 'Badan Layanan Digital Nusantara', short: 'BLDN', period: 'TW III 2026' };

  const UNITS = [
    'Direktorat Teknologi Informasi', 'Biro Keuangan', 'Direktorat Pelayanan Publik',
    'Biro Umum & Pengadaan', 'Biro SDM', 'Biro Hukum', 'Direktorat Perencanaan', 'Inspektorat'
  ];

  /* Skala & kriteria (ISO 31000 6.3.4) */
  const LIKELIHOOD = [
    { v: 1, n: 'Rare', id: 'Hampir tidak terjadi', d: '< 5% per tahun, atau < 1 kali dalam 5 tahun' },
    { v: 2, n: 'Unlikely', id: 'Jarang terjadi', d: '5–25% per tahun, atau 1 kali dalam 2–5 tahun' },
    { v: 3, n: 'Possible', id: 'Mungkin terjadi', d: '25–50% per tahun, atau 1 kali per tahun' },
    { v: 4, n: 'Likely', id: 'Sering terjadi', d: '50–80% per tahun, atau beberapa kali per tahun' },
    { v: 5, n: 'Almost Certain', id: 'Hampir pasti', d: '> 80% per tahun, atau terjadi setiap bulan' }
  ];
  const IMPACT = [
    { v: 1, n: 'Insignificant', id: 'Tidak signifikan', fin: '< Rp10 jt', ops: 'Gangguan < 1 jam', rep: 'Tidak ada pemberitaan', law: 'Teguran lisan' },
    { v: 2, n: 'Minor', id: 'Minor', fin: 'Rp10–100 jt', ops: 'Gangguan 1–4 jam', rep: 'Keluhan terbatas', law: 'Teguran tertulis' },
    { v: 3, n: 'Moderate', id: 'Moderat', fin: 'Rp100–500 jt', ops: 'Gangguan 4–24 jam', rep: 'Media lokal', law: 'Temuan audit material' },
    { v: 4, n: 'Major', id: 'Signifikan', fin: 'Rp500 jt–2 M', ops: 'Gangguan 1–3 hari', rep: 'Media nasional', law: 'Sanksi administratif' },
    { v: 5, n: 'Severe', id: 'Sangat signifikan', fin: '> Rp2 M', ops: 'Layanan berhenti > 3 hari', rep: 'Krisis kepercayaan publik', law: 'Proses pidana / pencabutan izin' }
  ];
  const LEVELS = [
    { k: 'l', n: 'Rendah', en: 'Low', min: 1, max: 4 },
    { k: 'm', n: 'Sedang', en: 'Medium', min: 5, max: 9 },
    { k: 'h', n: 'Tinggi', en: 'High', min: 10, max: 15 },
    { k: 'vh', n: 'Sangat Tinggi', en: 'Very High', min: 16, max: 25 }
  ];

  /* Taksonomi & selera risiko per kategori (appetite / tolerance dalam skor residual) */
  const TAXONOMY = [
    { k: 'Strategis', en: 'Strategic Risk', app: 8, tol: 12, cnt: 9, d: 'Risiko tidak tercapainya sasaran strategis dan arah kebijakan organisasi.' },
    { k: 'Operasional', en: 'Operational Risk', app: 6, tol: 9, cnt: 31, d: 'Kegagalan proses, orang, sistem, atau kejadian eksternal pada operasi harian.' },
    { k: 'Keuangan', en: 'Financial Risk', app: 6, tol: 9, cnt: 16, d: 'Kerugian keuangan, salah saji, dan gangguan penyerapan anggaran.' },
    { k: 'Kepatuhan', en: 'Compliance Risk', app: 4, tol: 6, cnt: 12, d: 'Ketidakpatuhan terhadap peraturan perundang-undangan dan kebijakan internal.' },
    { k: 'Hukum', en: 'Legal Risk', app: 4, tol: 8, cnt: 6, d: 'Tuntutan hukum, sengketa kontrak, dan kelemahan dasar hukum.' },
    { k: 'Teknologi Informasi', en: 'IT Risk', app: 6, tol: 9, cnt: 18, d: 'Ketersediaan, integritas, dan kinerja sistem informasi.' },
    { k: 'Keamanan Siber', en: 'Cyber Security Risk', app: 4, tol: 8, cnt: 14, d: 'Serangan siber, kebocoran data, dan penyalahgunaan akses.' },
    { k: 'SDM', en: 'HR Risk', app: 8, tol: 12, cnt: 8, d: 'Ketersediaan, kompetensi, dan integritas sumber daya manusia.' },
    { k: 'Reputasi', en: 'Reputational Risk', app: 6, tol: 9, cnt: 7, d: 'Menurunnya kepercayaan publik dan pemangku kepentingan.' },
    { k: 'Pihak Ketiga', en: 'Third Party Risk', app: 6, tol: 9, cnt: 6, d: 'Ketergantungan dan kegagalan vendor, mitra, atau penyedia layanan.' }
  ];

  const OBJECTIVES = [
    { id: 'SS-1', n: 'Meningkatkan kualitas pelayanan publik digital', ik: 'Indeks kepuasan layanan ≥ 85', cnt: 41 },
    { id: 'SS-2', n: 'Mewujudkan tata kelola keuangan yang akuntabel', ik: 'Opini WTP & realisasi anggaran ≥ 95%', cnt: 29 },
    { id: 'SS-3', n: 'Menjamin keamanan dan keandalan infrastruktur digital', ik: 'Ketersediaan sistem ≥ 99,5%', cnt: 38 },
    { id: 'SS-4', n: 'Meningkatkan kapasitas SDM dan budaya kerja', ik: 'Indeks profesionalitas ASN ≥ 80', cnt: 19 }
  ];

  const P = (n, j) => ({ n, j });
  const PEOPLE = {
    ti: P('Hendra Wijaya', 'Direktur Teknologi Informasi'),
    keu: P('Sri Wahyuni', 'Kepala Biro Keuangan'),
    yan: P('Agus Prasetyo', 'Direktur Pelayanan Publik'),
    um: P('Dewi Lestari', 'Kepala Biro Umum & Pengadaan'),
    sdm: P('Rudi Hartono', 'Kepala Biro SDM'),
    hk: P('Maya Anggraini', 'Kepala Biro Hukum'),
    ren: P('Bambang Setiadi', 'Direktur Perencanaan'),
    ins: P('Nurul Hidayah', 'Inspektur')
  };

  /* Risk register — inh/res/tgt = [Likelihood, Impact]. proj = proyeksi setelah mitigasi selesai. */
  const RISKS = [
    { id: 'R-001', name: 'Gangguan layanan pusat data', unit: 0, cat: 'Teknologi Informasi', obj: 'SS-3', proc: 'Pengelolaan infrastruktur TI',
      cause: 'pusat data hanya memiliki satu jalur catu daya dan pendingin tanpa redundansi', event: 'gangguan operasional pusat data', impact: 'seluruh aplikasi pelayanan tidak dapat diakses pengguna lebih dari 4 jam',
      owner: 'ti', inh: [4, 5], res: [3, 4], proj: [2, 4], tgt: [2, 3], treat: 'Kurangi', status: 'Dalam Penanganan', trend: 'up', due: '2026-12-15', ctrl: ['C-01', 'C-02', 'C-03'], kri: ['K-01'], src: 'Internal · Infrastructure' },
    { id: 'R-002', name: 'Serangan ransomware pada sistem layanan', unit: 0, cat: 'Keamanan Siber', obj: 'SS-3', proc: 'Keamanan informasi',
      cause: 'masih terdapat server dengan sistem operasi usang dan patch keamanan yang tertunda', event: 'serangan ransomware yang mengenkripsi basis data layanan', impact: 'layanan terhenti, data pengguna tidak dapat dipulihkan, dan kepercayaan publik menurun',
      owner: 'ti', inh: [5, 5], res: [4, 4], proj: [3, 3], tgt: [2, 2], treat: 'Kurangi', status: 'Dalam Penanganan', trend: 'up', due: '2026-11-30', ctrl: ['C-02', 'C-04', 'C-05'], kri: ['K-02', 'K-03'], src: 'Eksternal · Technology' },
    { id: 'R-003', name: 'Kebocoran data pribadi pengguna', unit: 0, cat: 'Keamanan Siber', obj: 'SS-1', proc: 'Pengelolaan data pengguna',
      cause: 'enkripsi data pribadi belum diterapkan pada seluruh basis data dan log aplikasi', event: 'kebocoran data pribadi pengguna layanan', impact: 'sanksi UU Pelindungan Data Pribadi dan tuntutan dari subjek data',
      owner: 'ti', inh: [4, 5], res: [3, 5], proj: [2, 4], tgt: [2, 3], treat: 'Kurangi', status: 'Dalam Penanganan', trend: 'flat', due: '2026-12-31', ctrl: ['C-05', 'C-06'], kri: ['K-03'], src: 'Internal · Technology' },
    { id: 'R-004', name: 'Ketergantungan pada satu penyedia cloud', unit: 3, cat: 'Pihak Ketiga', obj: 'SS-3', proc: 'Pengadaan layanan TI',
      cause: 'ketergantungan terhadap satu penyedia layanan cloud tanpa rencana keluar', event: 'gangguan layanan dari penyedia', impact: 'proses pelayanan kepada pengguna terhenti',
      owner: 'um', inh: [4, 4], res: [4, 4], proj: [3, 3], tgt: [2, 3], treat: 'Bagikan', status: 'Dalam Penanganan', trend: 'up', due: '2026-09-30', ctrl: ['C-07'], kri: ['K-04'], src: 'Eksternal · Third Party' },
    { id: 'R-005', name: 'Pembayaran ganda kepada vendor', unit: 1, cat: 'Keuangan', obj: 'SS-2', proc: 'Pembayaran vendor',
      cause: 'verifikasi tagihan masih manual dan tidak terhubung dengan data kontrak', event: 'pembayaran ganda atas tagihan yang sama', impact: 'kerugian keuangan negara dan temuan audit BPK',
      owner: 'keu', inh: [3, 4], res: [2, 3], tgt: [1, 3], treat: 'Kurangi', status: 'Dipantau', trend: 'down', due: '2026-10-31', ctrl: ['C-08'], kri: ['K-05'], src: 'Internal · Process' },
    { id: 'R-006', name: 'Keterlambatan pencairan anggaran', unit: 1, cat: 'Keuangan', obj: 'SS-2', proc: 'Pengelolaan anggaran',
      cause: 'revisi DIPA dan blokir anggaran belum selesai tepat waktu', event: 'keterlambatan pencairan anggaran kegiatan', impact: 'kegiatan prioritas tertunda dan realisasi anggaran rendah',
      owner: 'keu', inh: [4, 3], res: [3, 3], tgt: [2, 3], treat: 'Kurangi', status: 'Dalam Penanganan', trend: 'flat', due: '2026-11-15', ctrl: ['C-09'], kri: ['K-06'], src: 'Eksternal · Regulation' },
    { id: 'R-007', name: 'Ketidakpatuhan terhadap UU PDP', unit: 5, cat: 'Kepatuhan', obj: 'SS-1', proc: 'Kepatuhan regulasi',
      cause: 'belum ada pejabat pelindungan data dan inventaris pemrosesan data pribadi', event: 'ketidakpatuhan terhadap kewajiban UU Pelindungan Data Pribadi', impact: 'sanksi administratif dan denda',
      owner: 'hk', inh: [3, 5], res: [3, 4], proj: [2, 3], tgt: [1, 4], treat: 'Kurangi', status: 'Dalam Penanganan', trend: 'flat', due: '2027-01-31', ctrl: ['C-06'], kri: [], src: 'Eksternal · Regulation' },
    { id: 'R-008', name: 'Penurunan kepuasan pengguna layanan', unit: 2, cat: 'Reputasi', obj: 'SS-1', proc: 'Penanganan pengaduan',
      cause: 'waktu tanggapan pengaduan melebihi standar pelayanan', event: 'penurunan indeks kepuasan pengguna', impact: 'target IKM tidak tercapai dan citra lembaga menurun',
      owner: 'yan', inh: [3, 4], res: [3, 3], tgt: [2, 3], treat: 'Kurangi', status: 'Dalam Penanganan', trend: 'down', due: '2026-12-20', ctrl: ['C-10'], kri: ['K-07'], src: 'Internal · People' },
    { id: 'R-009', name: 'Kekurangan SDM kompetensi keamanan siber', unit: 4, cat: 'SDM', obj: 'SS-4', proc: 'Perencanaan kebutuhan SDM',
      cause: 'formasi jabatan fungsional keamanan siber belum tersedia dan remunerasi kurang kompetitif', event: 'kekurangan personel keamanan siber yang kompeten', impact: 'respons insiden lambat dan beban kerja berlebih',
      owner: 'sdm', inh: [4, 4], res: [4, 4], tgt: [3, 3], treat: 'Kurangi', status: 'Dalam Penanganan', trend: 'up', due: '2026-10-01', ctrl: [], kri: ['K-08'], src: 'Internal · People' },
    { id: 'R-010', name: 'Fraud dalam pengadaan barang/jasa', unit: 3, cat: 'Kepatuhan', obj: 'SS-2', proc: 'Pengadaan barang/jasa',
      cause: 'pemisahan tugas antara pejabat pengadaan dan penerima barang belum konsisten', event: 'kolusi atau mark-up dalam pengadaan', impact: 'kerugian negara dan proses hukum',
      owner: 'um', inh: [3, 5], res: [2, 5], tgt: [1, 5], treat: 'Kurangi', status: 'Dipantau', trend: 'flat', due: '2026-12-31', ctrl: ['C-11'], kri: [], src: 'Internal · People' },
    { id: 'R-011', name: 'Sasaran strategis tidak tercapai', unit: 6, cat: 'Strategis', obj: 'SS-1', proc: 'Perencanaan & evaluasi kinerja',
      cause: 'indikator kinerja tidak diturunkan sampai level kegiatan', event: 'target sasaran strategis tidak tercapai', impact: 'nilai SAKIP menurun dan anggaran tahun berikutnya dipotong',
      owner: 'ren', inh: [3, 4], res: [3, 3], tgt: [2, 3], treat: 'Kurangi', status: 'Dipantau', trend: 'flat', due: '2026-12-31', ctrl: [], kri: [], src: 'Internal · Process' },
    { id: 'R-012', name: 'Kegagalan migrasi aplikasi pelayanan', unit: 0, cat: 'Teknologi Informasi', obj: 'SS-1', proc: 'Pengembangan aplikasi',
      cause: 'kebutuhan pengguna berubah selama proyek dan pengujian tidak memadai', event: 'migrasi aplikasi pelayanan gagal atau tertunda', impact: 'layanan digital baru tidak dapat diluncurkan sesuai jadwal',
      owner: 'ti', inh: [4, 4], res: [3, 4], tgt: [2, 3], treat: 'Kurangi', status: 'Dalam Penanganan', trend: 'down', due: '2026-12-01', ctrl: ['C-03'], kri: ['K-01'], src: 'Internal · Process' },
    { id: 'R-013', name: 'Temuan audit BPK berulang', unit: 7, cat: 'Kepatuhan', obj: 'SS-2', proc: 'Tindak lanjut hasil audit',
      cause: 'pemantauan tindak lanjut rekomendasi audit belum terdokumentasi', event: 'temuan audit yang sama muncul kembali', impact: 'opini laporan keuangan terancam turun',
      owner: 'ins', inh: [3, 3], res: [2, 3], tgt: [1, 3], treat: 'Kurangi', status: 'Dipantau', trend: 'down', due: '2026-11-30', ctrl: ['C-12'], kri: ['K-05'], src: 'Internal · Process' },
    { id: 'R-014', name: 'Kerusakan aset gedung akibat banjir', unit: 3, cat: 'Operasional', obj: 'SS-3', proc: 'Pemeliharaan aset',
      cause: 'ruang server cadangan berada di lantai dasar wilayah rawan genangan', event: 'banjir merusak gedung dan perangkat', impact: 'kerugian aset dan gangguan operasional kantor',
      owner: 'um', inh: [2, 4], res: [2, 3], tgt: [1, 3], treat: 'Bagikan', status: 'Dipantau', trend: 'flat', due: '2027-02-28', ctrl: ['C-07'], kri: [], src: 'Eksternal · Infrastructure' },
    { id: 'R-015', name: 'Gugatan hukum dari mitra kerja sama', unit: 5, cat: 'Hukum', obj: 'SS-2', proc: 'Penyusunan perjanjian',
      cause: 'klausul wanprestasi dan penyelesaian sengketa pada perjanjian belum baku', event: 'gugatan dari mitra kerja sama', impact: 'biaya litigasi dan kewajiban ganti rugi',
      owner: 'hk', inh: [2, 4], res: [2, 3], tgt: [1, 3], treat: 'Kurangi', status: 'Menunggu Persetujuan', trend: 'flat', due: '2027-01-15', ctrl: [], kri: [], src: 'Eksternal · Regulation' },
    { id: 'R-016', name: 'Pemberitaan negatif di media sosial', unit: 2, cat: 'Reputasi', obj: 'SS-1', proc: 'Komunikasi publik',
      cause: 'belum ada protokol komunikasi krisis dan pemantauan media sosial', event: 'isu negatif menyebar luas di media sosial', impact: 'kepercayaan publik terhadap lembaga menurun',
      owner: 'yan', inh: [3, 3], res: [2, 3], tgt: [2, 2], treat: 'Kurangi', status: 'Dipantau', trend: 'down', due: '2026-11-10', ctrl: ['C-10'], kri: ['K-07'], src: 'Eksternal · Social' },
    { id: 'R-017', name: 'Turnover pegawai pada posisi kunci', unit: 4, cat: 'SDM', obj: 'SS-4', proc: 'Manajemen talenta',
      cause: 'belum ada rencana suksesi untuk jabatan kritikal', event: 'pegawai kunci mengundurkan diri atau mutasi', impact: 'kehilangan pengetahuan dan pekerjaan terhambat',
      owner: 'sdm', inh: [3, 3], res: [2, 2], tgt: [2, 2], treat: 'Terima', status: 'Ditutup', trend: 'down', due: '2026-08-31', ctrl: [], kri: ['K-08'], src: 'Internal · People' },
    { id: 'R-018', name: 'Kesalahan input data keuangan', unit: 1, cat: 'Operasional', obj: 'SS-2', proc: 'Akuntansi & pelaporan',
      cause: 'input jurnal masih manual tanpa validasi berlapis', event: 'kesalahan pencatatan transaksi', impact: 'salah saji laporan keuangan',
      owner: 'keu', inh: [3, 2], res: [2, 2], tgt: [1, 2], treat: 'Kurangi', status: 'Dipantau', trend: 'down', due: '2026-10-20', ctrl: ['C-08'], kri: [], src: 'Internal · Process' },
    { id: 'R-019', name: 'Penyalahgunaan hak akses sistem', unit: 0, cat: 'Keamanan Siber', obj: 'SS-3', proc: 'Manajemen identitas & akses',
      cause: 'akun pegawai yang mutasi atau pensiun tidak segera dinonaktifkan', event: 'penyalahgunaan hak akses oleh pihak tidak berwenang', impact: 'perubahan data tanpa otorisasi',
      owner: 'ti', inh: [3, 4], res: [2, 3], tgt: [1, 3], treat: 'Kurangi', status: 'Dalam Penanganan', trend: 'down', due: '2026-10-25', ctrl: ['C-04'], kri: ['K-02'], src: 'Internal · Technology' },
    { id: 'R-020', name: 'Keterlambatan vendor penyedia jaringan', unit: 3, cat: 'Pihak Ketiga', obj: 'SS-3', proc: 'Manajemen kontrak',
      cause: 'SLA kontrak jaringan tidak mengatur penalti keterlambatan', event: 'vendor terlambat memulihkan gangguan jaringan', impact: 'kantor daerah tidak dapat mengakses aplikasi',
      owner: 'um', inh: [3, 3], res: [2, 2], tgt: [2, 2], treat: 'Bagikan', status: 'Draft', trend: 'flat', due: '2027-01-31', ctrl: [], kri: ['K-04'], src: 'Eksternal · Third Party' }
  ];

  /* Distribusi agregat 127 risiko (heatmap) — key "L-I" */
  const HEAT = {
    res: { '4-4': 3, '4-5': 3, '5-4': 1, '5-5': 1, '2-5': 3, '5-2': 1, '3-4': 7, '4-3': 6, '3-5': 4, '5-3': 2,
      '1-5': 4, '5-1': 1, '2-3': 12, '3-2': 11, '2-4': 13, '4-2': 6, '3-3': 14,
      '1-1': 2, '1-2': 5, '2-1': 3, '1-3': 7, '3-1': 4, '1-4': 5, '4-1': 2, '2-2': 7 },
    inh: { '4-4': 6, '4-5': 7, '5-4': 4, '5-5': 4, '2-5': 4, '5-2': 2, '3-4': 11, '4-3': 9, '3-5': 8, '5-3': 4,
      '1-5': 3, '5-1': 1, '2-3': 9, '3-2': 8, '2-4': 11, '4-2': 6, '3-3': 11,
      '1-1': 1, '1-2': 2, '2-1': 2, '1-3': 3, '3-1': 3, '1-4': 3, '4-1': 1, '2-2': 4 }
  };
  const PROFILE = { total: 127, vh: 8, h: 23, m: 61, l: 35, inh: { vh: 21, h: 38, m: 49, l: 19 }, newQ: 9, treating: 64, overdueRisk: 7, closed: 14, up: 12, flat: 83, down: 32, mitig: 78, effect: 71 };
  const QUARTERS = [
    { q: 'TW IV 2025', vh: 13, h: 27, m: 49, l: 23 },
    { q: 'TW I 2026', vh: 11, h: 26, m: 54, l: 27 },
    { q: 'TW II 2026', vh: 10, h: 25, m: 57, l: 29 },
    { q: 'TW III 2026', vh: 8, h: 23, m: 61, l: 35 }
  ];
  const AVG_TREND = { labels: ['Nov', 'Des', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt'], values: [10.8, 10.6, 10.9, 10.4, 10.1, 9.9, 10.2, 9.8, 9.5, 9.6, 9.3, 9.1] };
  const BY_UNIT = [['Direktorat Teknologi Informasi', 29, 5, 9], ['Direktorat Pelayanan Publik', 22, 0, 4], ['Biro Keuangan', 18, 0, 3], ['Biro Umum & Pengadaan', 17, 1, 3], ['Biro SDM', 12, 1, 1], ['Direktorat Perencanaan', 11, 0, 1], ['Biro Hukum', 9, 0, 1], ['Inspektorat', 9, 1, 1]];
  const BY_PROCESS = [['Pengelolaan infrastruktur TI', 17], ['Pengadaan barang/jasa', 14], ['Pelayanan & pengaduan', 13], ['Pengelolaan anggaran', 12], ['Keamanan informasi', 11], ['Manajemen SDM', 9]];
  const MITIG = { total: 214, done: 112, run: 61, todo: 18, late: 17, cancel: 6 };

  const ACTIONS = [
    { id: 'A-001', risk: 'R-001', t: 'Melakukan backup harian ke lokasi DRC', pic: 'Tim Infrastruktur', budget: 85, due: '2026-08-31', prog: 100, prio: 'Tinggi', ev: 3 },
    { id: 'A-002', risk: 'R-001', t: 'Menyediakan redundant server & catu daya ganda', pic: 'Tim Infrastruktur', budget: 1450, due: '2026-12-15', prog: 75, prio: 'Kritis', ev: 2 },
    { id: 'A-003', risk: 'R-001', t: 'Melaksanakan uji Disaster Recovery (DR Test)', pic: 'Tim Infrastruktur', budget: 120, due: '2026-11-20', prog: 30, prio: 'Tinggi', ev: 0 },
    { id: 'A-004', risk: 'R-001', t: 'Monitoring NOC 24/7', pic: 'Tim Operasi TI', budget: 640, due: '2026-10-31', prog: 90, prio: 'Tinggi', ev: 4 },
    { id: 'A-005', risk: 'R-002', t: 'Vulnerability assessment & penetration test', pic: 'Tim Keamanan Informasi', budget: 210, due: '2026-09-30', prog: 60, prio: 'Kritis', ev: 1 },
    { id: 'A-006', risk: 'R-002', t: 'Patch management terpusat untuk seluruh server', pic: 'Tim Keamanan Informasi', budget: 95, due: '2026-11-30', prog: 45, prio: 'Kritis', ev: 1 },
    { id: 'A-007', risk: 'R-002', t: 'Backup immutable (offline) untuk basis data inti', pic: 'Tim Infrastruktur', budget: 310, due: '2026-12-31', prog: 0, prio: 'Tinggi', ev: 0 },
    { id: 'A-008', risk: 'R-003', t: 'Enkripsi data pribadi at-rest pada 14 basis data', pic: 'Tim Basis Data', budget: 180, due: '2026-12-31', prog: 40, prio: 'Tinggi', ev: 1 },
    { id: 'A-009', risk: 'R-004', t: 'Menyusun strategi multi-cloud & exit plan', pic: 'Biro Umum & Pengadaan', budget: 60, due: '2026-09-15', prog: 55, prio: 'Tinggi', ev: 1 },
    { id: 'A-010', risk: 'R-005', t: 'Integrasi verifikasi tagihan dengan data kontrak', pic: 'Subbag Perbendaharaan', budget: 140, due: '2026-10-31', prog: 85, prio: 'Sedang', ev: 2 },
    { id: 'A-011', risk: 'R-006', t: 'Kalender revisi DIPA & monitoring blokir', pic: 'Subbag Anggaran', budget: 0, due: '2026-11-15', prog: 50, prio: 'Sedang', ev: 1 },
    { id: 'A-012', risk: 'R-007', t: 'Menetapkan Pejabat Pelindungan Data Pribadi', pic: 'Biro Hukum', budget: 0, due: '2026-10-15', prog: 80, prio: 'Tinggi', ev: 1 },
    { id: 'A-013', risk: 'R-008', t: 'Chatbot & SLA tanggapan pengaduan 1×24 jam', pic: 'Direktorat Pelayanan', budget: 230, due: '2026-12-20', prog: 35, prio: 'Sedang', ev: 0 },
    { id: 'A-014', risk: 'R-009', t: 'Rekrutmen 6 pranata komputer keamanan siber', pic: 'Biro SDM', budget: 0, due: '2026-09-01', prog: 20, prio: 'Tinggi', ev: 0 },
    { id: 'A-015', risk: 'R-019', t: 'Rekonsiliasi akun bulanan dengan data kepegawaian', pic: 'Tim Keamanan Informasi', budget: 25, due: '2026-10-25', prog: 100, prio: 'Sedang', ev: 2 },
    { id: 'A-016', risk: 'R-012', t: 'User acceptance test bertahap per modul', pic: 'Tim Aplikasi', budget: 75, due: '2026-12-01', prog: 0, prio: 'Sedang', ev: 0, cancel: false },
    { id: 'A-017', risk: 'R-016', t: 'Langganan media monitoring tool', pic: 'Humas', budget: 48, due: '2026-08-15', prog: 0, prio: 'Rendah', ev: 0, cancel: true }
  ];

  const CONTROLS = [
    { id: 'C-01', n: 'Backup database harian', obj: 'Menjamin data dapat dipulihkan setelah kegagalan', owner: 'Tim Infrastruktur', freq: 'Harian', type: 'Korektif', mode: 'Otomatis', des: 4, ope: 3, last: '2026-09-20' },
    { id: 'C-02', n: 'Monitoring NOC 24/7', obj: 'Mendeteksi gangguan sistem secara dini', owner: 'Tim Operasi TI', freq: 'Berkelanjutan', type: 'Detektif', mode: 'Otomatis', des: 3, ope: 3, last: '2026-09-12' },
    { id: 'C-03', n: 'Change management board', obj: 'Mencegah perubahan sistem tanpa uji dan persetujuan', owner: 'Direktorat TI', freq: 'Per perubahan', type: 'Preventif', mode: 'Manual', des: 3, ope: 2, last: '2026-08-28' },
    { id: 'C-04', n: 'Review hak akses triwulanan', obj: 'Memastikan hak akses sesuai kewenangan', owner: 'Tim Keamanan Informasi', freq: 'Triwulanan', type: 'Detektif', mode: 'Manual', des: 3, ope: 2, last: '2026-07-10' },
    { id: 'C-05', n: 'Endpoint detection & response (EDR)', obj: 'Mendeteksi dan memblokir malware', owner: 'Tim Keamanan Informasi', freq: 'Berkelanjutan', type: 'Preventif', mode: 'Otomatis', des: 4, ope: 4, last: '2026-09-25' },
    { id: 'C-06', n: 'Klasifikasi & masking data pribadi', obj: 'Membatasi paparan data pribadi', owner: 'Tim Basis Data', freq: 'Per rilis', type: 'Preventif', mode: 'Otomatis', des: 3, ope: 2, last: '2026-08-15' },
    { id: 'C-07', n: 'Asuransi aset & kontrak SLA penyedia', obj: 'Mengalihkan dampak finansial ke pihak ketiga', owner: 'Biro Umum & Pengadaan', freq: 'Tahunan', type: 'Korektif', mode: 'Manual', des: 2, ope: 1, last: '2026-03-30' },
    { id: 'C-08', n: 'Verifikasi tagihan dua tingkat', obj: 'Mencegah pembayaran tidak sah atau ganda', owner: 'Subbag Perbendaharaan', freq: 'Per transaksi', type: 'Preventif', mode: 'Manual', des: 4, ope: 3, last: '2026-09-18' },
    { id: 'C-09', n: 'Rapat monitoring realisasi anggaran', obj: 'Mendeteksi deviasi penyerapan anggaran', owner: 'Biro Keuangan', freq: 'Bulanan', type: 'Detektif', mode: 'Manual', des: 3, ope: 3, last: '2026-09-30' },
    { id: 'C-10', n: 'Dashboard SLA pengaduan', obj: 'Memantau ketepatan waktu tanggapan pengaduan', owner: 'Direktorat Pelayanan', freq: 'Harian', type: 'Detektif', mode: 'Otomatis', des: 3, ope: 2, last: '2026-09-22' },
    { id: 'C-11', n: 'E-procurement & pemisahan tugas', obj: 'Mencegah kolusi dalam pengadaan', owner: 'Biro Umum & Pengadaan', freq: 'Per paket', type: 'Preventif', mode: 'Otomatis', des: 4, ope: 4, last: '2026-09-05' },
    { id: 'C-12', n: 'Register tindak lanjut audit', obj: 'Memastikan rekomendasi audit ditindaklanjuti', owner: 'Inspektorat', freq: 'Bulanan', type: 'Detektif', mode: 'Manual', des: 3, ope: 3, last: '2026-09-29' }
  ];
  const EFF = ['', 'Tidak Efektif', 'Sebagian Efektif', 'Efektif', 'Sangat Efektif'];

  /* KRI: dir 1 = makin tinggi makin buruk */
  const KRIS = [
    { id: 'K-01', n: 'Downtime sistem layanan', u: 'jam/bulan', risk: 'R-001', g: 1, r: 3, v: [0.5, 0.8, 0.4, 1.2, 0.6, 0.9, 1.6, 0.7, 1.1, 2.2, 2.6, 3.4], fmt: 1 },
    { id: 'K-02', n: 'Insiden keamanan terkonfirmasi', u: 'insiden/bulan', risk: 'R-002', g: 2, r: 5, v: [1, 2, 1, 3, 2, 2, 4, 3, 3, 4, 5, 4], fmt: 0 },
    { id: 'K-03', n: 'Server dengan patch kritikal tertunda', u: '% server', risk: 'R-002', g: 10, r: 15, v: [22, 20, 18, 19, 17, 15, 14, 16, 13, 12, 11, 12], fmt: 0 },
    { id: 'K-04', n: 'Pencapaian SLA penyedia cloud', u: '%', risk: 'R-004', g: 99.5, r: 99, v: [99.9, 99.8, 99.9, 99.7, 99.6, 99.8, 99.4, 99.6, 99.5, 99.3, 99.2, 99.4], fmt: 1, inv: true },
    { id: 'K-05', n: 'Temuan audit belum ditindaklanjuti', u: 'temuan', risk: 'R-013', g: 5, r: 10, v: [14, 13, 12, 12, 11, 10, 9, 9, 8, 7, 6, 5], fmt: 0 },
    { id: 'K-06', n: 'Deviasi realisasi anggaran', u: '% dari rencana', risk: 'R-006', g: 5, r: 10, v: [4, 5, 6, 8, 7, 9, 8, 7, 6, 7, 6, 5], fmt: 0 },
    { id: 'K-07', n: 'Pengaduan melewati SLA', u: '% pengaduan', risk: 'R-008', g: 12, r: 20, v: [24, 22, 25, 21, 19, 18, 20, 17, 16, 15, 14, 12], fmt: 0 },
    { id: 'K-08', n: 'Posisi kunci tanpa pengganti', u: 'posisi', risk: 'R-009', g: 2, r: 5, v: [7, 7, 6, 6, 6, 5, 5, 6, 6, 6, 6, 6], fmt: 0 }
  ];

  const INCIDENTS = [
    { id: 'INC-2026-014', date: '2026-09-28', loc: 'Pusat Data Utama, Jakarta', risk: 'R-001', t: 'Pendingin ruang server gagal, 3 rak mati', chrono: 'Pukul 02.10 suhu ruang server naik ke 34°C. Pukul 02.40 tiga rak server melakukan shutdown otomatis. Layanan perizinan daring tidak tersedia hingga 06.15.', cause: 'Kompresor CRAC unit 2 rusak; unit cadangan dalam perbaikan.', impact: 'Layanan perizinan daring tidak tersedia 4 jam 5 menit; 1.240 permohonan tertunda.', loss: 75, response: 'Pemindahan beban ke DRC, penyewaan pendingin portabel.', corrective: 'Pengadaan CRAC redundan (A-002), kontrak pemeliharaan preventif.', status: 'Investigasi' },
    { id: 'INC-2026-013', date: '2026-09-11', loc: 'Aplikasi Pengaduan', risk: 'R-019', t: 'Akun pegawai mutasi masih aktif dan digunakan', chrono: 'Hasil review log menemukan login dari akun pegawai yang telah mutasi 2 bulan sebelumnya.', cause: 'Tidak ada integrasi data mutasi dengan direktori akun.', impact: '23 tiket pengaduan diubah statusnya tanpa otorisasi.', loss: 0, response: 'Akun dinonaktifkan, tiket dipulihkan.', corrective: 'Rekonsiliasi akun bulanan (A-015).', status: 'Ditutup' },
    { id: 'INC-2026-011', date: '2026-08-19', loc: 'Biro Keuangan', risk: 'R-005', t: 'Pembayaran ganda atas satu tagihan pemeliharaan', chrono: 'Tagihan yang sama diajukan dua kali dengan nomor berbeda dan dibayarkan.', cause: 'Verifikasi tidak mencocokkan nilai kontrak kumulatif.', impact: 'Kelebihan bayar Rp48 jt (sudah dikembalikan vendor).', loss: 48, response: 'Penagihan kembali ke vendor.', corrective: 'Integrasi verifikasi tagihan (A-010).', status: 'Ditutup' },
    { id: 'INC-2026-009', date: '2026-07-02', loc: 'Jaringan Kantor Wilayah Timur', risk: 'R-020', t: 'Gangguan jaringan 2 hari di 4 kantor wilayah', chrono: 'Kabel serat optik putus akibat pekerjaan jalan; vendor baru tiba H+1.', cause: 'Tidak ada jalur cadangan; SLA tanpa penalti.', impact: 'Pelayanan tatap muka di 4 kantor menggunakan formulir manual.', loss: 32, response: 'Penggunaan modem seluler sementara.', corrective: 'Revisi SLA kontrak jaringan.', status: 'Tindakan Korektif' },
    { id: 'INC-2026-006', date: '2026-05-14', loc: 'Portal Layanan Publik', risk: 'R-002', t: 'Upaya ransomware terblokir EDR', chrono: 'EDR memblokir eksekusi berkas mencurigakan pada 2 server aplikasi.', cause: 'Lampiran phishing dibuka oleh pegawai.', impact: 'Tidak ada data terenkripsi; 2 server diisolasi 6 jam.', loss: 0, response: 'Isolasi, forensik, reset kredensial.', corrective: 'Simulasi phishing triwulanan.', status: 'Ditutup' }
  ];
  const LOSS_HISTORY = [
    { y: 2024, risk: 'Downtime layanan', ev: 'Server gagal', loss: 150 },
    { y: 2025, risk: 'Downtime layanan', ev: 'Network failure', loss: 75 },
    { y: 2025, risk: 'Pembayaran ganda', ev: 'Tagihan ganda vendor ATK', loss: 22 },
    { y: 2026, risk: 'Downtime layanan', ev: 'Database failure', loss: 50 },
    { y: 2026, risk: 'Downtime layanan', ev: 'Pendingin ruang server gagal', loss: 75 },
    { y: 2026, risk: 'Pembayaran ganda', ev: 'Tagihan pemeliharaan ganda', loss: 48 },
    { y: 2026, risk: 'Gangguan jaringan', ev: 'Serat optik putus', loss: 32 }
  ];

  const APPROVALS = [
    { id: 'WF-0412', type: 'Risiko Baru', ref: 'R-015', by: 'Fajar Nugroho', role: 'Risk Officer', date: '2026-10-03', stage: 2, note: 'Risiko baru dari reviu perjanjian kerja sama 2026.' },
    { id: 'WF-0410', type: 'Perubahan Skor', ref: 'R-002', by: 'Osmond', role: 'Risk Manager', date: '2026-10-05', stage: 3, note: 'Skor residual naik 12 → 16 berdasarkan KRI insiden keamanan.' },
    { id: 'WF-0407', type: 'Rencana Mitigasi', ref: 'R-004', by: 'Fajar Nugroho', role: 'Risk Officer', date: '2026-10-01', stage: 1, note: 'Perubahan opsi perlakuan menjadi Bagikan (multi-cloud).' },
    { id: 'WF-0405', type: 'Penutupan Risiko', ref: 'R-017', by: 'Lina Marlina', role: 'Risk Officer', date: '2026-09-29', stage: 2, note: 'Rencana suksesi telah ditetapkan untuk 12 jabatan kritikal.' },
    { id: 'WF-0401', type: 'Risiko Baru', ref: 'R-020', by: 'Fajar Nugroho', role: 'Risk Officer', date: '2026-09-26', stage: 0, note: 'Draft — menunggu kelengkapan analisis.' }
  ];
  const WF_STAGES = ['Risk Officer', 'Risk Owner', 'Risk Manager', 'Direktur / Manajemen'];

  const AUDIT = [
    { u: 'Osmond', a: 'Mengubah Risk Score', ref: 'R-002', f: 'Skor residual', p: '12', n: '16', t: '2026-10-05 14:32', ip: '10.12.4.21' },
    { u: 'Sistem', a: 'Early warning KRI', ref: 'K-01', f: 'Status KRI', p: 'Warning', n: 'Critical', t: '2026-10-05 08:00', ip: '—' },
    { u: 'Fajar Nugroho', a: 'Mengajukan risiko baru', ref: 'R-015', f: 'Status', p: 'Draft', n: 'Menunggu Persetujuan', t: '2026-10-03 10:15', ip: '10.12.7.40' },
    { u: 'Hendra Wijaya', a: 'Memperbarui progres mitigasi', ref: 'A-002', f: 'Progres', p: '60%', n: '75%', t: '2026-10-02 16:48', ip: '10.12.2.11' },
    { u: 'Fajar Nugroho', a: 'Mengubah opsi perlakuan', ref: 'R-004', f: 'Perlakuan', p: 'Kurangi', n: 'Bagikan', t: '2026-10-01 09:20', ip: '10.12.7.40' },
    { u: 'Nurul Hidayah', a: 'Mengunggah bukti', ref: 'C-12', f: 'Dokumen', p: '—', n: 'Register TL Audit Sep 2026.xlsx (v3)', t: '2026-09-29 15:02', ip: '10.12.9.3' },
    { u: 'Sri Wahyuni', a: 'Menilai efektivitas kontrol', ref: 'C-08', f: 'Efektivitas operasi', p: 'Sebagian Efektif', n: 'Efektif', t: '2026-09-18 11:30', ip: '10.12.3.8' },
    { u: 'Osmond', a: 'Mengubah kriteria risiko', ref: 'Kriteria', f: 'Appetite Keamanan Siber', p: '6', n: '4', t: '2026-09-10 13:05', ip: '10.12.4.21' },
    { u: 'Admin Sistem', a: 'Menambahkan pengguna', ref: 'USR-031', f: 'Peran', p: '—', n: 'Risk Officer', t: '2026-09-08 08:41', ip: '10.12.1.2' }
  ];

  const DOCS = [
    { n: 'Kebijakan Manajemen Risiko BLDN 2026', type: 'Kebijakan', ref: 'Kerangka', v: 'v2.1', by: 'Osmond', d: '2026-02-12', exp: '2027-02-12', st: 'Disetujui' },
    { n: 'SOP Backup & Restore Basis Data', type: 'SOP', ref: 'C-01', v: 'v1.4', by: 'Hendra Wijaya', d: '2026-06-03', exp: '2026-12-03', st: 'Disetujui' },
    { n: 'Laporan Uji DR Semester I', type: 'Hasil Pengujian', ref: 'R-001', v: 'v1.0', by: 'Tim Infrastruktur', d: '2026-07-21', exp: '—', st: 'Disetujui' },
    { n: 'Berita Acara Insiden INC-2026-014', type: 'Berita Acara', ref: 'INC-2026-014', v: 'v1.0', by: 'Tim Operasi TI', d: '2026-09-29', exp: '—', st: 'Review' },
    { n: 'Hasil VA/PT Portal Layanan', type: 'Laporan', ref: 'A-005', v: 'v0.9', by: 'Tim Keamanan Informasi', d: '2026-09-27', exp: '—', st: 'Draft' },
    { n: 'Kontrak Layanan Cloud 2026', type: 'Kontrak', ref: 'R-004', v: 'v1.0', by: 'Dewi Lestari', d: '2026-01-15', exp: '2026-12-31', st: 'Disetujui' },
    { n: 'Sertifikat ISO/IEC 27001', type: 'Sertifikat', ref: 'Kerangka', v: '—', by: 'Hendra Wijaya', d: '2023-11-02', exp: '2026-11-01', st: 'Disetujui' },
    { n: 'Register Tindak Lanjut Audit Sep 2026', type: 'Laporan', ref: 'C-12', v: 'v3', by: 'Nurul Hidayah', d: '2026-09-29', exp: '—', st: 'Disetujui' }
  ];

  const USERS = [
    { n: 'Osmond', e: 'osmond@bldn.go.id', unit: 'Unit Manajemen Risiko', role: 'Risk Manager', last: 'Hari ini 14:32' },
    { n: 'Admin Sistem', e: 'admin@bldn.go.id', unit: 'Direktorat Teknologi Informasi', role: 'Super Admin', last: 'Hari ini 08:41' },
    { n: 'Fajar Nugroho', e: 'fajar.n@bldn.go.id', unit: 'Biro Umum & Pengadaan', role: 'Risk Officer', last: '3 Okt 2026' },
    { n: 'Lina Marlina', e: 'lina.m@bldn.go.id', unit: 'Biro SDM', role: 'Risk Officer', last: '29 Sep 2026' },
    { n: 'Hendra Wijaya', e: 'hendra.w@bldn.go.id', unit: 'Direktorat Teknologi Informasi', role: 'Risk Owner', last: '2 Okt 2026' },
    { n: 'Sri Wahyuni', e: 'sri.w@bldn.go.id', unit: 'Biro Keuangan', role: 'Risk Owner', last: '30 Sep 2026' },
    { n: 'Ir. Taufik Rahman', e: 'kepala@bldn.go.id', unit: 'Pimpinan', role: 'Management', last: '1 Okt 2026' },
    { n: 'Nurul Hidayah', e: 'nurul.h@bldn.go.id', unit: 'Inspektorat', role: 'Auditor', last: '29 Sep 2026' },
    { n: 'Yudi Pratama', e: 'yudi.p@bldn.go.id', unit: 'Unit Manajemen Risiko', role: 'Risk Administrator', last: '25 Sep 2026' }
  ];

  const PRIVS = ['Lihat dashboard', 'Kelola risk register', 'Ubah skor risiko', 'Kelola mitigasi', 'Setujui risiko', 'Kelola kriteria & taksonomi', 'Kelola pengguna & peran', 'Lihat audit trail', 'Unduh laporan'];
  const ROLES = {
    'Super Admin': { d: 'Mengelola seluruh sistem', p: [1, 1, 1, 1, 1, 1, 1, 1, 1], nav: 'all', write: true },
    'Risk Administrator': { d: 'Mengelola konfigurasi risiko', p: [1, 1, 1, 1, 0, 1, 0, 1, 1], nav: 'all', write: true },
    'Risk Manager': { d: 'Melakukan review & koordinasi', p: [1, 1, 1, 1, 1, 1, 0, 1, 1], nav: 'all', write: true },
    'Risk Officer': { d: 'Mengelola risiko unit', p: [1, 1, 1, 1, 0, 0, 0, 0, 1], nav: ['risk-dash', 'kri', 'context', 'identify', 'register', 'analysis', 'review', 'treatment', 'improve', 'controls', 'incidents', 'objective', 'workflow', 'reports', 'documents', 'ai'], write: true },
    'Risk Owner': { d: 'Bertanggung jawab atas risiko', p: [1, 1, 0, 1, 1, 0, 0, 0, 1], nav: ['risk-dash', 'kri', 'identify', 'register', 'analysis', 'review', 'treatment', 'controls', 'incidents', 'workflow', 'documents', 'ai'], write: true },
    'Management': { d: 'Melihat dashboard & menyetujui', p: [1, 0, 0, 0, 1, 0, 0, 0, 1], nav: ['exec', 'risk-dash', 'kri', 'register', 'objective', 'workflow', 'reports', 'ai'], write: false },
    'Auditor': { d: 'Baca saja + bukti & audit trail', p: [1, 0, 0, 0, 0, 0, 0, 1, 1], nav: ['exec', 'risk-dash', 'kri', 'register', 'controls', 'incidents', 'iso', 'documents', 'audit', 'reports'], write: false }
  };

  const ORG_TREE = { n: 'Badan Layanan Digital Nusantara', t: 'Organisasi', c: 127, k: [
    { n: 'Sekretariat Utama', t: 'Unit Eselon I', c: 62, k: [
      { n: 'Biro Keuangan', t: 'Biro', c: 18, k: [{ n: 'Bagian Anggaran', t: 'Bagian', c: 7 }, { n: 'Bagian Perbendaharaan', t: 'Bagian', c: 6 }, { n: 'Bagian Akuntansi & Pelaporan', t: 'Bagian', c: 5 }] },
      { n: 'Biro Umum & Pengadaan', t: 'Biro', c: 17, k: [{ n: 'Bagian Pengadaan', t: 'Bagian', c: 10 }, { n: 'Bagian Rumah Tangga & Aset', t: 'Bagian', c: 7 }] },
      { n: 'Biro SDM', t: 'Biro', c: 12 },
      { n: 'Biro Hukum', t: 'Biro', c: 9 },
      { n: 'Direktorat Perencanaan', t: 'Direktorat', c: 6 }
    ] },
    { n: 'Deputi Layanan Digital', t: 'Unit Eselon I', c: 56, k: [
      { n: 'Direktorat Teknologi Informasi', t: 'Direktorat', c: 29, k: [{ n: 'Program Transformasi Digital', t: 'Program', c: 12, k: [{ n: 'Kegiatan Migrasi Aplikasi Pelayanan', t: 'Kegiatan', c: 5, k: [{ n: 'Pengembangan aplikasi', t: 'Proses Bisnis', c: 3 }] }] }, { n: 'Subdit Infrastruktur', t: 'Subdirektorat', c: 11 }, { n: 'Subdit Keamanan Informasi', t: 'Subdirektorat', c: 6 }] },
      { n: 'Direktorat Pelayanan Publik', t: 'Direktorat', c: 22 },
      { n: 'Direktorat Perencanaan Layanan', t: 'Direktorat', c: 5 }
    ] },
    { n: 'Inspektorat', t: 'Unit Pengawasan', c: 9 }
  ] };

  const REVIEWS = [
    { r: 'R-002', prev: 12, cur: 16, note: 'Insiden keamanan naik 2 bulan berturut-turut; patch tertunda.' },
    { r: 'R-001', prev: 12, cur: 12, note: 'Redundansi belum selesai; KRI downtime melewati batas.' },
    { r: 'R-004', prev: 16, cur: 16, note: 'Exit plan belum disetujui; SLA penyedia menurun.' },
    { r: 'R-009', prev: 12, cur: 16, note: 'Rekrutmen tertunda; 2 personel keamanan mengundurkan diri.' },
    { r: 'R-003', prev: 20, cur: 15, note: 'Masking data diterapkan pada 9 dari 14 basis data.' },
    { r: 'R-005', prev: 9, cur: 6, note: 'Verifikasi tagihan dua tingkat berjalan efektif.' },
    { r: 'R-008', prev: 12, cur: 9, note: 'Pengaduan melewati SLA turun ke 12%.' },
    { r: 'R-019', prev: 9, cur: 6, note: 'Rekonsiliasi akun bulanan berjalan sejak Sep 2026.' },
    { r: 'R-016', prev: 9, cur: 6, note: 'Protokol komunikasi krisis telah ditetapkan.' }
  ];

  const IMPROVE = [
    { id: 'IP-08', src: 'Insiden', ref: 'INC-2026-014', t: 'Standar redundansi N+1 untuk pendingin & catu daya ruang server', pic: 'Direktorat TI', due: '2026-12-31', st: 'Berjalan' },
    { id: 'IP-07', src: 'Kegagalan Kontrol', ref: 'C-04', t: 'Otomatisasi review hak akses berbasis data kepegawaian', pic: 'Tim Keamanan Informasi', due: '2026-11-30', st: 'Berjalan' },
    { id: 'IP-06', src: 'Pelanggaran KRI', ref: 'K-02', t: 'Simulasi phishing triwulanan & pelatihan kesadaran keamanan', pic: 'Biro SDM', due: '2026-10-31', st: 'Selesai' },
    { id: 'IP-05', src: 'Temuan Audit', ref: 'LHP BPK 2025', t: 'Integrasi register tindak lanjut audit ke aplikasi ERM', pic: 'Inspektorat', due: '2026-12-15', st: 'Belum Mulai' },
    { id: 'IP-04', src: 'Efektivitas Mitigasi', ref: 'R-004', t: 'Kebijakan exit strategy untuk seluruh kontrak layanan TI kritikal', pic: 'Biro Umum & Pengadaan', due: '2027-01-31', st: 'Belum Mulai' }
  ];

  return { TODAY, ORG, UNITS, LIKELIHOOD, IMPACT, LEVELS, TAXONOMY, OBJECTIVES, PEOPLE, RISKS, HEAT, PROFILE, QUARTERS, AVG_TREND, BY_UNIT, BY_PROCESS, MITIG, ACTIONS, CONTROLS, EFF, KRIS, INCIDENTS, LOSS_HISTORY, APPROVALS, WF_STAGES, AUDIT, DOCS, USERS, PRIVS, ROLES, ORG_TREE, REVIEWS, IMPROVE };
})();
