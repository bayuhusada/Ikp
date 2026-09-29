<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array $user
 * @var array $riwayat
 */
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0">Profil Saya</h4>
      <p class="text-muted mb-0">Data akun dan riwayat aktivitas Anda.</p>
    </div>
  </div>

  <div class="row g-3">

    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Data Akun</h5></div>
        <div class="card-body">
          <?php // WAJIB 'profil/ubah', bukan 'profil'. Rute 'profil' hanya
                // memanggil Profile::index() yang hanya menampilkan halaman. ?>
          <?php echo form_open('profil/ubah', array('class' => 'row g-3')); ?>
            <div class="col-12">
              <label class="form-label" for="nama">Nama Lengkap</label>
              <input type="text" class="form-control" id="nama" name="nama" required maxlength="100"
                     value="<?php echo nilai_aman($user['nama']); ?>" />
            </div>
            <div class="col-12">
              <label class="form-label" for="username">Username</label>
              <input type="text" class="form-control" id="username" value="<?php echo nilai_aman($user['username']); ?>" disabled />
              <div class="form-text">Username tidak dapat diubah.</div>
            </div>
            <div class="col-12">
              <label class="form-label" for="nip">NIP <span class="text-muted">(opsional)</span></label>
              <input type="text" class="form-control" id="nip" name="nip" maxlength="50"
                     value="<?php echo nilai_aman($user['nip']); ?>" />
            </div>
            <div class="col-12">
              <label class="form-label">Role</label>
              <input type="text" class="form-control" value="<?php echo nilai_aman($user['role']); ?>" disabled />
            </div>
            <div class="col-12">
              <label class="form-label">Ruangan</label>
              <input type="text" class="form-control"
                     value="<?php echo ! empty($user['ruangan_id']) ? 'ID ' . (int) $user['ruangan_id'] : '-'; ?>" disabled />
              <div class="form-text">Ruangan hanya dapat diubah oleh administrator.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Login Terakhir</label>
              <input type="text" class="form-control" value="<?php echo waktu($user['last_login']); ?>" disabled />
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Simpan Perubahan</button>
            </div>
          <?php echo form_close(); ?>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Tanda Tangan</h5></div>
        <div class="card-body">
          <?php if (! empty($user['ttd'])): ?>
            <div class="border rounded p-3 mb-3 bg-light">
              <img src="<?php echo base_url('uploads/ttd/' . rawurlencode($user['ttd'])); ?>"
                   alt="Tanda tangan" class="img-fluid" style="max-height: 120px;" />
            </div>
          <?php else: ?>
            <p class="text-muted">Belum ada tanda tangan.</p>
          <?php endif; ?>

          <?php echo form_open_multipart('profil/unggah-ttd', array('class' => 'row g-2 align-items-end')); ?>
            <div class="col-sm-8">
              <label class="form-label" for="ttd">Unggah gambar tanda tangan</label>
              <input type="file" class="form-control" id="ttd" name="ttd" accept="image/png,image/jpeg,image/webp" required />
              <div class="form-text">PNG, JPG, atau WEBP. Maksimal 1 MB.</div>
            </div>
            <div class="col-sm-4">
              <button type="submit" class="btn btn-outline-primary w-100"><i class="bx bx-upload"></i> Unggah</button>
            </div>
          <?php echo form_close(); ?>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Ubah Password</h5></div>
        <div class="card-body">
          <?php echo form_open('profil/ubah-sandi', array('class' => 'row g-3')); ?>
            <div class="col-12">
              <label class="form-label" for="password_lama">Password Lama</label>
              <input type="password" class="form-control" id="password_lama" name="password_lama"
                     required autocomplete="current-password" />
            </div>
            <div class="col-12">
              <label class="form-label" for="password_baru">Password Baru</label>
              <input type="password" class="form-control" id="password_baru" name="password_baru"
                     required minlength="8" autocomplete="new-password" />
              <div class="form-text">Minimal 8 karakter, ada huruf besar, huruf kecil, dan angka.</div>
            </div>
            <div class="col-12">
              <label class="form-label" for="password_konfirmasi">Ulangi Password Baru</label>
              <input type="password" class="form-control" id="password_konfirmasi" name="password_konfirmasi"
                     required minlength="8" autocomplete="new-password" />
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-warning"><i class="bx bx-lock"></i> Ubah Password</button>
              <div class="form-text">Anda akan diminta login ulang setelah password diganti.</div>
            </div>
          <?php echo form_close(); ?>
        </div>
      </div>
    </div>

  </div>

  <div class="card">
    <div class="card-header"><h5 class="mb-0">Riwayat Aktivitas Saya</h5></div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th>Waktu</th><th>Aktivitas</th><th>Entitas</th><th>IP</th></tr>
        </thead>
        <tbody>
        <?php if (empty($riwayat['rows'])): ?>
          <tr><td colspan="4" class="text-center text-muted py-4">Belum ada aktivitas.</td></tr>
        <?php else: ?>
          <?php foreach ($riwayat['rows'] as $r): ?>
            <tr>
              <td class="text-nowrap"><?php echo waktu($r['waktu']); ?></td>
              <td><?php echo nilai_aman($r['aktivitas']); ?></td>
              <td><small><?php echo nilai_aman($r['entitas']); ?></small></td>
              <td><small><?php echo nilai_aman($r['ip']); ?></small></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
