<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array|null $row
 * @var bool      $is_edit
 * @var array     $ruangan_list
 * @var array     $user
 */
$aksi = $is_edit
    ? base_url('master/user/save/' . (int) $row['id'])
    : base_url('master/user/save');

$selRole  = $row['role'] ?? '' ?? 'pelapor';
$selRuang = $row['ruangan_id'] ?? '' ?? null;
$aktif    = $is_edit ? (int) $row['is_active'] ?? 1 === 1 : true;
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0"><?php echo $is_edit ? 'Ubah User' : 'Tambah User'; ?></h4>
      <a href="<?php echo base_url('master/user'); ?>" class="text-muted small">
        <i class="bx bx-arrow-back"></i> Kembali ke daftar
      </a>
    </div>
  </div>

  <?php if (empty($ruangan_list)): ?>
    <div class="alert alert-warning">
      <i class="bx bx-error"></i>
      Belum ada ruangan. Buat dulu di
      <a href="<?php echo base_url('master/ruangan/form'); ?>" class="alert-link">Master Ruangan</a>,
      baru tambah user.
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-body">
          <?php echo form_open($aksi, array('class' => 'row g-3')); ?>

            <div class="col-md-7">
              <label class="form-label" for="nama">Nama Lengkap <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="nama" name="nama" required maxlength="100"
                     value="<?php echo nilai_aman($row['nama'] ?? ''); ?>" />
            </div>

            <div class="col-md-5">
              <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
              <input type="text" class="form-control text-lowercase" id="username" name="username" required
                     minlength="3" maxlength="50"
                     value="<?php echo nilai_aman($row['username'] ?? ''); ?>" />
              <div class="form-text">Otomatis jadi huruf kecil. Harus unik.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="role">Role <span class="text-danger">*</span></label>
              <select class="form-select" id="role" name="role" required>
                <option value="pelapor" <?php echo $selRole === 'pelapor' ? 'selected' : ''; ?>>Pelapor</option>
                <option value="kepala_ruangan" <?php echo $selRole === 'kepala_ruangan' ? 'selected' : ''; ?>>Kepala Ruangan</option>
                <option value="admin" <?php echo $selRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
              </select>
              <div class="form-text">
                Pelapor hanya melihat laporannya sendiri. Kepala Ruangan hanya melihat
                ruangannya dan bisa melakukan grading.
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="ruangan_id">Ruangan <span class="text-danger">*</span></label>
              <select class="form-select" id="ruangan_id" name="ruangan_id" required>
                <option value="">-- Pilih Ruangan --</option>
                <?php echo pilihan($ruangan_list, $selRuang); ?>
              </select>
              <div class="form-text">Wajib untuk semua role. Menjadi pembatas data.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="nip">NIP <span class="text-muted">(opsional)</span></label>
              <input type="text" class="form-control" id="nip" name="nip" maxlength="50"
                     value="<?php echo nilai_aman($row['nip'] ?? ''); ?>" />
            </div>

            <div class="col-md-6">
              <?php if ($is_edit): ?>
                <label class="form-label" for="password">Password Baru</label>
                <input type="password" class="form-control" id="password" name="password"
                       minlength="8" autocomplete="new-password" />
                <div class="form-text">Kosongkan bila tidak ingin mengubah password.</div>
              <?php else: ?>
                <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="password" name="password" required
                       minlength="8" autocomplete="new-password" />
                <div class="form-text">Minimal 8 karakter, ada huruf besar, huruf kecil, dan angka.</div>
              <?php endif; ?>
            </div>

            <div class="col-12">
              <div class="form-check form-switch">
                <input type="hidden" name="is_active" value="0" />
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                       <?php echo $aktif ? 'checked' : ''; ?> />
                <label class="form-check-label" for="is_active">User aktif dan bisa login</label>
              </div>
            </div>

            <div class="col-12">
              <button type="submit" class="btn btn-primary" <?php echo empty($ruangan_list) ? 'disabled' : ''; ?>>
                <i class="bx bx-save"></i> Simpan
              </button>
              <a href="<?php echo base_url('master/user'); ?>" class="btn btn-outline-secondary">Batal</a>
            </div>

          <?php echo form_close(); ?>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Tentang Tiap Role</h5></div>
        <div class="card-body">
          <dl class="mb-0 small">
            <dt>Pelapor</dt>
            <dd>Membuat laporan, melihat dan mengubah laporannya sendiri selama masih Draft.</dd>

            <dt class="mt-3">Kepala Ruangan</dt>
            <dd>Melihat semua laporan ruangannya, melakukan grading risiko, mencatat keputusan Komite PMKP.</dd>

            <dt class="mt-3">Admin</dt>
            <dd>Melihat seluruh data, membuat lembar investigasi, mengelola master data, membaca audit log.</dd>
          </dl>
        </div>
      </div>
    </div>
  </div>

</div>
