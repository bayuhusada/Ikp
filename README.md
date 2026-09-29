# IKP - Sistem Pelaporan Insiden Keselamatan Pasien

Aplikasi web untuk mencatat, mengelompokkan, dan menelusuri insiden keselamatan
pasien di fasilitas kesehatan.

**Dokumen RAHASIA.** Isi dokumen dan basis data bersifat terbatas akses.

---

## Ringkasan teknis

| Komponen | Nilai |
|---|---|
| Framework | CodeIgniter 3 |
| Database | MySQL 8 |
| Admin UI | Sneat Bootstrap 5 (`assets/sneat/`) |
| PDF | Dompdf |
| Excel | PhpSpreadsheet |
| Auth | Session database (`ci_sessions`) + RBAC 3 role |
| Timezone | Asia/Jakarta (WIB), tanggal tampil `DD/MM/YYYY` |

## Role

| Role | Kemampuan |
|---|---|
| `admin` | Pengelolaan penuh: master data, investigasi, audit log, arsip |
| `kepala_ruangan` | Monitoring ruangannya, grading risiko, keputusan Komite PMKP |
| `pelapor` | Membuat dan memantau laporan miliknya sendiri |

Data antar-ruangan tidak pernah tercampur. Filter role diterapkan di model
(`MY_Model::apply_role_scope`), bukan di tampilan, jadi tidak bisa dilewati
dengan mengubah URL.

## Prasyarat

- Laragon (Apache + MySQL + PHP 7.2+)
- Database sudah dibuat (lihat cara di bawah)

## Instalasi

### 1. Siapkan konfigurasi
```bash
copy .env.example .env
```
Isi `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` di `.env`.
**`.env` tidak boleh masuk version control.**

### 2. Jalankan skema dan data awal
```bash
"C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe" -u root ^
  -e "source C:/laragon/www/ikp/database/schema.sql"

"C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe" -u root ^
  -e "source C:/laragon/www/ikp/database/seed.sql"
```
`schema.sql` membuat database `ikp` bila belum ada.

### 3. Buat akun admin
```bash
php database/seed_admin.php admin "Administrator IKP"
```
Password di-generate acak dan **hanya ditampilkan sekali**. Ganti lewat menu
Profil setelah login pertama.

### 4. Jalankan
Buka `http://localhost/ikp/login`

---

## Perintah bantu

```bash
# Lint semua file PHP
php -l <file.php>

# Seed data uji (development saja) - membuat 2 ruangan + 4 user uji
php database/seed_test.php

# Hapus akun uji
# DELETE FROM users WHERE username IN ('kepala_igd','pelapor_igd','kepala_rawat','pelapor_rawat');
```

## Struktur penting

```
application/
├─ config/          config, database (dari .env), routes, autoload
├─ controllers/     Auth, Dashboard, Profile, Audit_log, Notfound
├─ core/
│  └─ MY_Controller.php    auth, require_role, filter role, audit
├─ models/
│  ├─ MY_Model.php         soft delete + kolom audit + filter role
│  ├─ Master_model.php     untuk tabel master
│  └─ *_model.php
├─ helpers/
│  └─ ikp_helper.php       tgl(), waktu(), nilai_aman(), badge_band()
└─ views/
   ├─ template/      header, aside (role-aware), footers, js
   ├─ auth/ dashboard/ profile/ audit_log/ errors/
database/
├─ schema.sql       25 tabel
├─ seed.sql         master data + counter
├─ seed_admin.php   buat admin dengan password acak
└─ seed_test.php    data uji (development)
uploads/ttd/        gambar tanda tangan (hanya lewat controller)
```

## Yang WAJIB dijaga saat menambah kode

1. **Kelas dasar harus di-require eksplisit.** CI3 hanya otomatis memuat
   `core/MY_Controller.php`. Model yang mewarisi `MY_Model` atau
   `Master_model` wajib punya baris
   `require_once APPPATH . 'models/MY_Model.php';` di baris atasnya.

2. **`set_output()` + `exit()` butuh `_display()`.** Kalau tidak, respons
   keluar kosong (Content-Length: 0). Gunakan `$this->json_response()`.

3. **`count_all_results()` mereset query builder.** Ambil baris DULU, baru
   hitung total. Kalau dibalik, klausa `FROM` hilang.

4. **Tabel `ci_sessions` harus kolom `id`, bukan `session_id`.** Itu format
   CI3. Format CI4 akan merusak session.

5. **Rute yang hanya satu segment harus diakhiri `$`.** Tanpa anchor,
   `$route['dashboard']` akan menelan `dashboard/terbaru`.

6. **Query agregat tidak boleh `select ir.*`.** Bertentangan dengan
   `sql_mode=only_full_group_by` MySQL 8.

7. **Jangan pernah menulis data pasien ke log atau ke audit_logs.**
   `log_threshold` sudah dikunci di 1 demi alasan ini.

8. **Selalu pakai query builder, jangan concat SQL.**

9. **Tabel `audit_logs` tidak boleh punya method update atau delete.**

10. **Format nomor dokumen** `INS-{tahun}-{5 digit}` dibuat lewat
    `Incident_report_model::nomor_baru()` yang aman terhadap race condition.

## Yang belum dikerjakan

- Modul Laporan Insiden (form, daftar, detail, filter, arsip)
- Modul Investigasi
- Export PDF dan Excel
- Master data UI
- Dashboard statistik dengan grafik periode

Lihat konteks di vault Obsidian:
`Website-Insiden-Keselamatan-Pasien/`.
