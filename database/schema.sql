-- =====================================================================
-- SISTEM PELAPORAN INSIDEN KESELAMATAN PASIEN (IKP)
-- Skema database
--
-- Sumber: vault 02 - Klasifikasi & Model Data.md
-- Aturan yang harus dijaga saat menambah tabel:
--   1. Tabel turunan laporan WAJIB punya ruangan_id + created_by
--      supaya filter per-role bisa diterapkan seragam.
--   2. Tabel yang punya baris historis (laporan, investigasi)
--      WAJIB punya is_deleted / deleted_at / deleted_by (soft delete).
--   3. Tabel master TIDAK perlu soft delete, cukup is_active.
--   4. Tabel anak (1-many yang ikut terhapus dengan induknya)
--      tidak perlu soft delete.
--   5. TIDAK ADA kolom NIK, alamat, nomor telepon, atau tanggal lahir.
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE DATABASE IF NOT EXISTS `ikp`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `ikp`;

-- ---------------------------------------------------------------------
-- 1. users
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama`        VARCHAR(100) NOT NULL,
  `username`    VARCHAR(50)  NOT NULL,
  `password`    VARCHAR(255) NOT NULL COMMENT 'password_hash, jangan pernah plain',
  `role`        ENUM('admin','kepala_ruangan','pelapor') NOT NULL DEFAULT 'pelapor',
  `ruangan_id`  INT UNSIGNED NULL COMMENT 'wajib untuk kepala_ruangan dan pelapor',
  `nip`         VARCHAR(50)  NULL,
  `ttd`         VARCHAR(255) NULL COMMENT 'path gambar tanda tangan relatif ke uploads/ttd',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login`  DATETIME     NULL,
  `is_deleted`  TINYINT(1)   NOT NULL DEFAULT 0,
  `deleted_at`  DATETIME     NULL,
  `deleted_by`  INT UNSIGNED NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  INT UNSIGNED NULL,
  `updated_at`  DATETIME     NULL,
  `updated_by`  INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `ix_users_role` (`role`),
  KEY `ix_users_ruangan` (`ruangan_id`),
  KEY `ix_users_is_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. roles (referensi; enforcement utama tetap lewat enum users.role)
-- ---------------------------------------------------------------------
CREATE TABLE `roles` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`        VARCHAR(30)  NOT NULL,
  `nama`        VARCHAR(50)  NOT NULL,
  `deskripsi`   VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. ruangan
-- ---------------------------------------------------------------------
CREATE TABLE `ruangan` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`        VARCHAR(20)  NOT NULL,
  `nama`        VARCHAR(100) NOT NULL,
  `jenis`       ENUM('unit','departemen') NOT NULL DEFAULT 'unit',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `is_deleted`  TINYINT(1)   NOT NULL DEFAULT 0,
  `deleted_at`  DATETIME     NULL,
  `deleted_by`  INT UNSIGNED NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  INT UNSIGNED NULL,
  `updated_at`  DATETIME     NULL,
  `updated_by`  INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ruangan_kode` (`kode`),
  KEY `ix_ruangan_is_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. patients
--    Data Pasien minimal sesuai formulir. TIDAK ada NIK / alamat / telp.
-- ---------------------------------------------------------------------
CREATE TABLE `patients` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_pasien`       VARCHAR(150) NOT NULL,
  `nomor_mr`          VARCHAR(50)  NOT NULL,
  `ruangan_id`        INT UNSIGNED NOT NULL,
  -- Kelompok umur OPSIONAL pada formulir (05 - Spesifikasi Field Form 7.4),
  -- jadi kolomnya harus boleh NULL.
  `kelompok_umur_id`  TINYINT UNSIGNED NULL,
  `jenis_kelamin`     ENUM('L','P') NULL,
  `penanggung_jawab_id` TINYINT UNSIGNED NULL,
  `tanggal_masuk`     DATE NULL,
  `jam_masuk`         TIME NULL,
  `is_deleted`        TINYINT(1)   NOT NULL DEFAULT 0,
  `deleted_at`        DATETIME     NULL,
  `deleted_by`        INT UNSIGNED NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`        INT UNSIGNED NULL,
  `updated_at`        DATETIME     NULL,
  `updated_by`        INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `ix_patients_ruangan` (`ruangan_id`),
  KEY `ix_patients_mr` (`nomor_mr`),
  KEY `ix_patients_is_deleted` (`is_deleted`),
  CONSTRAINT `fk_patients_ruangan` FOREIGN KEY (`ruangan_id`)
    REFERENCES `ruangan` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. incident_types  (KNC / KTC / KTD / Sentinel)
-- ---------------------------------------------------------------------
CREATE TABLE `incident_types` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`        VARCHAR(10)  NOT NULL,
  `nama`        VARCHAR(100) NOT NULL,
  `nama_asing`  VARCHAR(100) NULL COMMENT 'Near Miss, No Harm, Adverse Event, dll',
  `definisi`    TEXT         NULL,
  `urutan`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_incident_types_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. incident_categories
-- ---------------------------------------------------------------------
CREATE TABLE `incident_categories` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`        VARCHAR(5)   NOT NULL,
  `nama`        VARCHAR(100) NOT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_incident_categories_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. incident_specializations
-- ---------------------------------------------------------------------
CREATE TABLE `incident_specializations` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`        VARCHAR(5)   NOT NULL,
  `nama`        VARCHAR(100) NOT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_incident_specializations_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. incident_impacts  (akibat insiden terhadap pasien)
-- ---------------------------------------------------------------------
CREATE TABLE `incident_impacts` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`        VARCHAR(5)   NOT NULL,
  `nama`        VARCHAR(100) NOT NULL,
  `urutan`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 paling ringan',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_incident_impacts_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. risk_bands  (grading risiko)
--    Kolom `kriteria` WAJIB diisi klien sebelum fitur grading dipakai.
--    Sistem dilarang menentukan kriteria ini sendiri.
-- ---------------------------------------------------------------------
CREATE TABLE `risk_bands` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`        VARCHAR(10)  NOT NULL COMMENT 'BIRU / HIJAU / KUNING / MERAH',
  `nama`        VARCHAR(50)  NOT NULL,
  `warna_hex`   VARCHAR(7)   NULL COMMENT 'untuk badge di UI',
  `urutan`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `kriteria`    TEXT         NULL COMMENT 'WAJIB dari klien - lihat 06 - Keputusan & Pertanyaan Tertunda item 1',
  `perlu_investigasi_sederhana` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_risk_bands_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. statuses  (state machine laporan, investigasi, tindakan)
-- ---------------------------------------------------------------------
CREATE TABLE `statuses` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`        VARCHAR(30)  NOT NULL,
  `nama`        VARCHAR(50)  NOT NULL,
  `untuk`       ENUM('laporan','investigasi','tindakan') NOT NULL DEFAULT 'laporan',
  `urutan`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_terminal` TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_statuses_kode_untuk` (`kode`,`untuk`),
  KEY `ix_statuses_untuk` (`untuk`,`urutan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. age_groups  (kelompok umur)
-- ---------------------------------------------------------------------
CREATE TABLE `age_groups` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`        VARCHAR(5)   NOT NULL,
  `nama`        VARCHAR(50)  NOT NULL,
  `urutan`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_age_groups_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. patient_responsibles  (penanggung jawab / penjamin)
-- ---------------------------------------------------------------------
CREATE TABLE `patient_responsibles` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`        VARCHAR(5)   NOT NULL,
  `nama`        VARCHAR(100) NOT NULL,
  `urutan`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_patient_responsibles_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. first_reporters  (orang pertama yang melaporkan)
--     Field formulir, BUKAH user login.
-- ---------------------------------------------------------------------
CREATE TABLE `first_reporters` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`        VARCHAR(5)   NOT NULL,
  `nama`        VARCHAR(100) NOT NULL,
  `urutan`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_first_reporters_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. care_types  (jenis perawatan pasien yang terdampak)
-- ---------------------------------------------------------------------
CREATE TABLE `care_types` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`        VARCHAR(5)   NOT NULL,
  `nama`        VARCHAR(100) NOT NULL,
  `urutan`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_care_types_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 15. action_teams  (tim yang melakukan tindakan)
-- ---------------------------------------------------------------------
CREATE TABLE `action_teams` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`        VARCHAR(5)   NOT NULL,
  `nama`        VARCHAR(100) NOT NULL,
  `urutan`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_action_teams_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 16. document_counters  (nomor dokumen otomatis, aman race condition)
-- ---------------------------------------------------------------------
CREATE TABLE `document_counters` (
  `jenis`       VARCHAR(10)  NOT NULL COMMENT 'INS atau INV',
  `tahun`       SMALLINT UNSIGNED NOT NULL,
  `counter`     INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`jenis`,`tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 17. incident_reports  (tabel utama)
-- ---------------------------------------------------------------------
CREATE TABLE `incident_reports` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`             VARCHAR(20)  NOT NULL COMMENT 'INS-2026-00001',

  -- Identitas laporan (form bag. 8)
  `tanggal_lapor`     DATE NOT NULL,
  `jam_lapor`         TIME NULL,
  `nama_pembuat`      VARCHAR(100) NULL,
  `paraf_pembuat`     VARCHAR(100) NULL,
  `nama_penerima`     VARCHAR(100) NULL,
  `paraf_penerima`    VARCHAR(100) NULL,
  `tanggal_terima`    DATE NULL,

  -- Data pasien (form bag. 7)
  `patient_id`        INT UNSIGNED NOT NULL,
  `ruangan_id`        INT UNSIGNED NOT NULL COMMENT 'ruangan tempat kejadian',

  -- Rincian kejadian (form bag. 8)
  `tanggal_insiden`   DATE NOT NULL,
  `jam_insiden`       TIME NOT NULL,
  `insiden`           TEXT         NULL COMMENT 'ringkasan kejadian',
  `kronologi_insiden` TEXT         NOT NULL,

  -- Klasifikasi (form bag. 9 dan 16)
  `jenis_insiden_id`  TINYINT UNSIGNED NOT NULL,
  `akibat_insiden_id` TINYINT UNSIGNED NOT NULL,
  `incident_category_id`   TINYINT UNSIGNED NULL,
  `specialization_id`     TINYINT UNSIGNED NULL,

  -- Pelapor & lokasi (form bag. 10-15)
  `first_reporter_id`   TINYINT UNSIGNED NULL,
  `first_reporter_lainnya`  VARCHAR(150) NULL,
  `insiden_pada`        VARCHAR(20)  NULL COMMENT 'PASIEN / LAIN',
  `insiden_pada_lainnya` VARCHAR(150) NULL,
  `care_type_id`        TINYINT UNSIGNED NULL,
  `care_type_lainnya`   VARCHAR(150) NULL,
  `tempat_insiden`      VARCHAR(200) NULL,
  `unit_terkait_id`     INT UNSIGNED NULL COMMENT 'unit yang menyebabkan insiden',

  -- Tindakan setelah kejadian (form bag. 17-18)
  `tindakan_segera`   TEXT         NULL,
  `hasil_tindakan`    TEXT         NULL,
  `action_team_id`    TINYINT UNSIGNED NULL,
  `action_team_lainnya` VARCHAR(150) NULL,

  -- Riwayat kejadian serupa (form bag. 19)
  `pernah_terjadi_di_unit_lain`     TINYINT(1) NOT NULL DEFAULT 0,
  `tindakan_pencegahan_sebelumnya`  TEXT NULL,

  -- Grading risiko (form bag. 21-22)
  `risk_band_id`      TINYINT UNSIGNED NULL,
  `graded_by`         INT UNSIGNED NULL,
  `graded_at`         DATETIME     NULL,

  -- Status
  `status_id`         TINYINT UNSIGNED NOT NULL,

  `is_deleted`        TINYINT(1)   NOT NULL DEFAULT 0,
  `deleted_at`        DATETIME     NULL,
  `deleted_by`        INT UNSIGNED NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`        INT UNSIGNED NULL,
  `updated_at`        DATETIME     NULL,
  `updated_by`        INT UNSIGNED NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_incident_reports_nomor` (`nomor`),
  KEY `ix_ir_ruangan` (`ruangan_id`),
  KEY `ix_ir_created_by` (`created_by`),
  KEY `ix_ir_status` (`status_id`),
  KEY `ix_ir_jenis` (`jenis_insiden_id`),
  KEY `ix_ir_band` (`risk_band_id`),
  KEY `ix_ir_tanggal_insiden` (`tanggal_insiden`),
  KEY `ix_ir_is_deleted` (`is_deleted`),
  -- Filter dashboard sering memakai kombinasi tanggal + ruangan
  KEY `ix_ir_tanggal_ruangan` (`tanggal_insiden`,`ruangan_id`),
  KEY `ix_ir_kategori_tanggal` (`incident_category_id`,`tanggal_insiden`),

  CONSTRAINT `fk_ir_patient` FOREIGN KEY (`patient_id`)
    REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ir_ruangan` FOREIGN KEY (`ruangan_id`)
    REFERENCES `ruangan` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ir_unit_terkait` FOREIGN KEY (`unit_terkait_id`)
    REFERENCES `ruangan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ir_jenis` FOREIGN KEY (`jenis_insiden_id`)
    REFERENCES `incident_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ir_akibat` FOREIGN KEY (`akibat_insiden_id`)
    REFERENCES `incident_impacts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ir_kategori` FOREIGN KEY (`incident_category_id`)
    REFERENCES `incident_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ir_spesialisasi` FOREIGN KEY (`specialization_id`)
    REFERENCES `incident_specializations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ir_band` FOREIGN KEY (`risk_band_id`)
    REFERENCES `risk_bands` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ir_status` FOREIGN KEY (`status_id`)
    REFERENCES `statuses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ir_graded_by` FOREIGN KEY (`graded_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 18. incident_report_history
--     Jejak perubahan per laporan. Termasuk Grading Ulang.
-- ---------------------------------------------------------------------
CREATE TABLE `incident_report_history` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `incident_report_id` INT UNSIGNED NOT NULL,
  `action`            VARCHAR(40) NOT NULL
                      COMMENT 'create / update / grading / grading_ulang / status_change / submit / archive / export',
  `sebelum`           TEXT NULL,
  `sesudah`           TEXT NULL,
  `user_id`           INT UNSIGNED NULL,
  `waktu`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip`                VARCHAR(45) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_irh_report` (`incident_report_id`,`waktu`),
  KEY `ix_irh_user` (`user_id`),
  CONSTRAINT `fk_irh_report` FOREIGN KEY (`incident_report_id`)
    REFERENCES `incident_reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_irh_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 19. incident_report_actions  (tindakan setelah kejadian, bisa >1)
-- ---------------------------------------------------------------------
CREATE TABLE `incident_report_actions` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `incident_report_id` INT UNSIGNED NOT NULL,
  `tindakan`          TEXT NOT NULL,
  `hasil`             TEXT NULL,
  `action_team_id`    TINYINT UNSIGNED NULL,
  `petugas`           VARCHAR(150) NULL,
  `waktu`             DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_ira_report` (`incident_report_id`),
  CONSTRAINT `fk_ira_report` FOREIGN KEY (`incident_report_id`)
    REFERENCES `incident_reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ira_team` FOREIGN KEY (`action_team_id`)
    REFERENCES `action_teams` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 20. investigations  (hanya untuk band BIRU / HIJAU)
-- ---------------------------------------------------------------------
CREATE TABLE `investigations` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor`             VARCHAR(20) NOT NULL COMMENT 'INV-2026-00001',
  `incident_report_id` INT UNSIGNED NOT NULL,
  `tanggal_investigasi` DATE NOT NULL,
  `ruangan_id`        INT UNSIGNED NOT NULL,
  `jenis_insiden_id`  TINYINT UNSIGNED NOT NULL,
  `risk_band_id`      TINYINT UNSIGNED NOT NULL,
  `status_id`         TINYINT UNSIGNED NOT NULL,

  -- Analisis (MODUL-LAPORAN bag. 26-27)
  `penyebab_langsung` TEXT NULL,
  `akar_masalah`       TEXT NULL,

  -- Persetujuan (bag. 32)
  `nama_penyetuju`       VARCHAR(100) NULL COMMENT 'Manager / Kepala Bagian / Kepala Unit',
  `tanda_tangan`         VARCHAR(255) NULL COMMENT 'path gambar tanda tangan',
  `tanggal_mulai_investigasi`  DATE NULL,
  `tanggal_selesai_investigasi` DATE NULL,

  -- Keputusan Komite PMKP (bag. 33)
  `investigasi_lanjut`        TINYINT(1) NULL,
  `tanggal_keputusan_pmkp`    DATE NULL,
  `catatan_pmkp`              TEXT NULL,
  `grading_ulang`             VARCHAR(10) NULL COMMENT 'BIRU / HIJAU / KUNING / MERAH',

  `is_deleted`        TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`        DATETIME NULL,
  `deleted_by`        INT UNSIGNED NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`        INT UNSIGNED NULL,
  `updated_at`        DATETIME NULL,
  `updated_by`        INT UNSIGNED NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_investigations_nomor` (`nomor`),
  UNIQUE KEY `uq_investigations_report` (`incident_report_id`)
                      COMMENT 'satu laporan punya maksimal satu lembar investigasi (v1)',
  KEY `ix_inv_ruangan` (`ruangan_id`),
  KEY `ix_inv_status` (`status_id`),
  KEY `ix_inv_band` (`risk_band_id`),
  KEY `ix_inv_is_deleted` (`is_deleted`),
  CONSTRAINT `fk_inv_report` FOREIGN KEY (`incident_report_id`)
    REFERENCES `incident_reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_inv_ruangan` FOREIGN KEY (`ruangan_id`)
    REFERENCES `ruangan` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_inv_jenis` FOREIGN KEY (`jenis_insiden_id`)
    REFERENCES `incident_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_inv_band` FOREIGN KEY (`risk_band_id`)
    REFERENCES `risk_bands` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_inv_status` FOREIGN KEY (`status_id`)
    REFERENCES `statuses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 21. investigation_recommendations  (bisa lebih dari satu)
-- ---------------------------------------------------------------------
CREATE TABLE `investigation_recommendations` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `investigation_id`  INT UNSIGNED NOT NULL,
  `rekomendasi`       TEXT NOT NULL,
  `penanggung_jawab`  VARCHAR(150) NULL,
  `tanggal_rekomendasi` DATE NULL,
  PRIMARY KEY (`id`),
  KEY `ix_rec_investigation` (`investigation_id`),
  CONSTRAINT `fk_rec_investigation` FOREIGN KEY (`investigation_id`)
    REFERENCES `investigations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 22. investigation_actions  (tindakan, bisa lebih dari satu)
-- ---------------------------------------------------------------------
CREATE TABLE `investigation_actions` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `investigation_id`  INT UNSIGNED NOT NULL,
  `tindakan`          TEXT NOT NULL,
  `penanggung_jawab`  VARCHAR(150) NULL,
  `tanggal_tindakan`  DATE NULL,
  `status_id`         TINYINT UNSIGNED NULL COMMENT 'sumber: statuses(untuk=tindakan)',
  PRIMARY KEY (`id`),
  KEY `ix_iact_investigation` (`investigation_id`),
  CONSTRAINT `fk_iact_investigation` FOREIGN KEY (`investigation_id`)
    REFERENCES `investigations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_iact_status` FOREIGN KEY (`status_id`)
    REFERENCES `statuses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 23. audit_logs  (append-only, tidak boleh di-update atau di-delete)
-- ---------------------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NULL,
  `username`    VARCHAR(50)  NULL COMMENT 'denormalisasi agar log tetap terbaca setelah user dihapus',
  `aktivitas`   VARCHAR(100) NOT NULL,
  `entitas`     VARCHAR(50)  NULL,
  `entitas_id`  VARCHAR(50)  NULL,
  `sebelum`     TEXT NULL,
  `sesudah`     TEXT NULL,
  `ip`          VARCHAR(45)  NULL,
  `waktu`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_audit_user` (`user_id`),
  KEY `ix_audit_waktu` (`waktu`),
  KEY `ix_audit_aktivitas` (`aktivitas`),
  KEY `ix_audit_entitas` (`entitas`,`entitas_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 24. login_attempts  (lockout: 5 gagal -> kunci 15 menit)
-- ---------------------------------------------------------------------
CREATE TABLE `login_attempts` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login`       VARCHAR(50)  NOT NULL,
  `ip_address`  VARCHAR(45)  NOT NULL,
  `time`        INT UNSIGNED NOT NULL COMMENT 'unix timestamp',
  `success`     TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_la_lookup` (`ip_address`,`login`,`time`,`success`),
  KEY `ix_la_time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 25. ci_sessions  (session database driver)
--     Format WAJIB mengikuti CI3 Session_database_driver:
--     kolom id / ip_address / timestamp / data.
--     Nama kolom TIDAK BOLEH diganti menjadi session_id / user_data
--     (itu format CodeIgniter 4). Driver memakai where('id', ...).
--     Lihat system/libraries/Session/drivers/Session_database_driver.php
-- ---------------------------------------------------------------------
CREATE TABLE `ci_sessions` (
  `id`         VARCHAR(128) NOT NULL DEFAULT '0',
  `ip_address` VARCHAR(45)  NOT NULL DEFAULT '0',
  `timestamp`  INT UNSIGNED NOT NULL DEFAULT 0,
  `data`       TEXT         NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_ci_sessions_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ---------------------------------------------------------------------
-- Selesai
-- ---------------------------------------------------------------------
-- ---------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 1;
