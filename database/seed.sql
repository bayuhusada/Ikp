-- =====================================================================
-- SISTEM PELAPORAN INSIDEN KESELAMATAN PASIEN (IKP)
-- Data awal (seed)
--
-- Jalankan SETELAH schema.sql.
--   mysql -u root ikp < database/seed.sql
--
-- CATATAN PENTING
-- 1. Password admin di-generate acak oleh database/seed_admin.php,
--    BUKAN ditulis di file ini. Print sekali di terminal, lalu ubahi.
-- 2. Kolom risk_bands.kriteria sengaja KOSONG. Kriteria band risiko harus
--    diminta ke klien dulu (lihat 06 - Keputusan & Pertanyaan Tertunda
--    item 1). Sistem dilarang menentukannya sendiri.
-- 3. Daftar ruangan harus disesuaikan dengan fasilitas kesehatan.
-- =====================================================================

SET NAMES utf8mb4;
USE `ikp`;

-- ---------------------------------------------------------------------
-- roles
-- ---------------------------------------------------------------------
INSERT INTO `roles` (`kode`, `nama`, `deskripsi`) VALUES
  ('admin',          'Admin',           'Pengelolaan penuh sistem: master data, investigasi, audit log'),
  ('kepala_ruangan', 'Kepala Ruangan',  'Monitoring, grading risiko, dan keputusan Komite PMKP untuk ruangannya'),
  ('pelapor',        'Pelapor',         'Membuat dan memantau laporan insiden miliknya sendiri');

-- ---------------------------------------------------------------------
-- incident_types
-- ---------------------------------------------------------------------
INSERT INTO `incident_types` (`kode`, `nama`, `nama_asing`, `definisi`, `urutan`) VALUES
  ('KNC', 'Kejadian Nyaris Cedera',     'Near Miss',     'Hampir terjadi, berhasil dicegah sebelum mencapai pasien', 1),
  ('KTC', 'Kejadian Tidak Cedera',      'No Harm',       'Sudah mencapai pasien, tidak menyebabkan cedera', 2),
  ('KTD', 'Kejadian Tidak Diharapkan',  'Adverse Event', 'Tidak diharapkan dan menyebabkan cedera', 3),
  ('ST',  'Kejadian Sentinel',          'Sentinel Event','Memenuhi kriteria sentinel sesuai kebijakan fasilitas kesehatan', 4);

-- ---------------------------------------------------------------------
-- incident_categories
-- ---------------------------------------------------------------------
INSERT INTO `incident_categories` (`kode`, `nama`) VALUES
  ('01', 'Kesalahan obat'),
  ('02', 'Kesalahan identifikasi pasien'),
  ('03', 'Jatuh'),
  ('04', 'Kesalahan prosedur'),
  ('05', 'Infeksi'),
  ('06', 'Masalah komunikasi'),
  ('07', 'Masalah dokumentasi'),
  ('08', 'Masalah alat kesehatan'),
  ('99', 'Lainnya');

-- ---------------------------------------------------------------------
-- incident_specializations
-- "Lokasi Kejadian" sengaja TIDAK ada di sini. Itu keliru pada
-- MODUL-LAPORAN bag. 594 - perlu konfirmasi (item 3 di 06).
-- ---------------------------------------------------------------------
INSERT INTO `incident_specializations` (`kode`, `nama`) VALUES
  ('01', 'Penyakit Dalam'),
  ('02', 'Anak'),
  ('03', 'Bedah'),
  ('04', 'Obstetri Ginekologi'),
  ('05', 'THT'),
  ('06', 'Mata'),
  ('07', 'Saraf'),
  ('08', 'Anestesi'),
  ('09', 'Kulit dan Kelamin'),
  ('10', 'Jantung'),
  ('11', 'Paru'),
  ('12', 'Jiwa'),
  ('99', 'Lainnya');

-- ---------------------------------------------------------------------
-- incident_impacts
-- ---------------------------------------------------------------------
INSERT INTO `incident_impacts` (`kode`, `nama`, `urutan`) VALUES
  ('05', 'Tidak Ada Cedera', 1),
  ('04', 'Cedera Ringan', 2),
  ('03', 'Cedera Reversibel / Cedera Sedang', 3),
  ('02', 'Cedera Irreversibel / Cedera Berat', 4),
  ('01', 'Kematian', 5);

-- ---------------------------------------------------------------------
-- risk_bands
-- kriteria = NULL karena belum ada dari klien. JANGAN diisi karangan.
-- ---------------------------------------------------------------------
INSERT INTO `risk_bands` (`kode`, `nama`, `warna_hex`, `urutan`, `kriteria`, `perlu_investigasi_sederhana`) VALUES
  ('BIRU',  'Risiko Biru',  '#2196F3', 1, NULL, 1),
  ('HIJAU', 'Risiko Hijau', '#4CAF50', 2, NULL, 1),
  ('KUNING','Risiko Kuning','#FFC107', 3, NULL, 0),
  ('MERAH',  'Risiko Merah',  '#F44336', 4, NULL, 0);

-- ---------------------------------------------------------------------
-- statuses - laporan (7 status, state machine)
-- ---------------------------------------------------------------------
INSERT INTO `statuses` (`kode`, `nama`, `untuk`, `urutan`, `is_terminal`) VALUES
  ('draft',             'Draft',              'laporan', 1, 0),
  ('dilaporkan',        'Dilaporkan',         'laporan', 2, 0),
  ('menunggu_grading',  'Menunggu Grading',   'laporan', 3, 0),
  ('sudah_grading',     'Sudah Grading',      'laporan', 4, 0),
  ('investigasi',       'Investigasi',        'laporan', 5, 0),
  ('tindak_lanjut',     'Tindak Lanjut',      'laporan', 6, 0),
  ('selesai',           'Selesai',            'laporan', 7, 1);

-- ---------------------------------------------------------------------
-- statuses - investigasi (5 status)
-- ---------------------------------------------------------------------
INSERT INTO `statuses` (`kode`, `nama`, `untuk`, `urutan`, `is_terminal`) VALUES
  ('inv_draft',              'Draft',                    'investigasi', 1, 0),
  ('dalam_investigasi',      'Dalam Investigasi',         'investigasi', 2, 0),
  ('menunggu_tindak_lanjut', 'Menunggu Tindak Lanjut',   'investigasi', 3, 0),
  ('dalam_pelaksanaan',      'Dalam Pelaksanaan',        'investigasi', 4, 0),
  ('inv_selesai',            'Selesai',                  'investigasi', 5, 1);

-- ---------------------------------------------------------------------
-- statuses - tindakan investigasi
-- ---------------------------------------------------------------------
INSERT INTO `statuses` (`kode`, `nama`, `untuk`, `urutan`, `is_terminal`) VALUES
  ('belum',  'Belum Dimulai', 'tindakan', 1, 0),
  ('proses', 'Dalam Proses',   'tindakan', 2, 0),
  ('tuntas', 'Selesai',        'tindakan', 3, 1);

-- ---------------------------------------------------------------------
-- age_groups
-- ---------------------------------------------------------------------
INSERT INTO `age_groups` (`kode`, `nama`, `urutan`) VALUES
  ('01', '0 - 1 Bulan', 1),
  ('02', 'Lebih dari 1 - 5 Tahun', 2),
  ('03', 'Lebih dari 5 - 15 Tahun', 3),
  ('04', 'Lebih dari 15 - 30 Tahun', 4),
  ('05', 'Lebih dari 30 - 65 Tahun', 5),
  ('06', 'Lebih dari 65 Tahun', 6);

-- ---------------------------------------------------------------------
-- patient_responsibles
-- ---------------------------------------------------------------------
INSERT INTO `patient_responsibles` (`kode`, `nama`, `urutan`) VALUES
  ('01', 'Pribadi', 1),
  ('02', 'Askes Pemerintah', 2),
  ('03', 'Jamsangga', 3),
  ('04', 'Asuransi Swasta', 4),
  ('05', 'Perusahaan', 5),
  ('06', 'Jaminan Kesehatan Daerah', 6);

-- ---------------------------------------------------------------------
-- first_reporters
-- Field formulir, BUKA user login. Pelapor non-akun (pasien, keluarga)
-- dicatat oleh staf lewat field ini.
-- ---------------------------------------------------------------------
INSERT INTO `first_reporters` (`kode`, `nama`, `urutan`) VALUES
  ('01', 'Karyawan: Dokter / Perawat / Petugas lainnya', 1),
  ('02', 'Pasien', 2),
  ('03', 'Keluarga / Pendamping pasien', 3),
  ('04', 'Pengunjung', 4),
  ('99', 'Lain-lain', 9);

-- ---------------------------------------------------------------------
-- care_types
-- ---------------------------------------------------------------------
INSERT INTO `care_types` (`kode`, `nama`, `urutan`) VALUES
  ('01', 'Pasien Rawat Inap', 1),
  ('02', 'Pasien Rawat Jalan', 2),
  ('03', 'Pasien UGD', 3),
  ('99', 'Lain-lain', 9);

-- ---------------------------------------------------------------------
-- action_teams
-- ---------------------------------------------------------------------
INSERT INTO `action_teams` (`kode`, `nama`, `urutan`) VALUES
  ('01', 'Tim', 1),
  ('02', 'Dokter', 2),
  ('03', 'Perawat', 3),
  ('99', 'Petugas lainnya', 9);

-- ---------------------------------------------------------------------
-- document_counters
-- ---------------------------------------------------------------------
INSERT INTO `document_counters` (`jenis`, `tahun`, `counter`) VALUES
  ('INS', YEAR(CURDATE()), 0),
  ('INV', YEAR(CURDATE()), 0);
