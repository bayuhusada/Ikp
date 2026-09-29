<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array  $rows
 * @var string $search, $role
 * @var int    $ruangan, $total, $page, $pages, $limit, $total_admin
 * @var array  $ruangan_list
 * @var array  $user
 */
$qs = array_filter(array(
    'search' => $search, 'role' => $role,
    'ruangan_id' => $ruangan ?: null, 'limit' => $limit,
));
$qs = $qs ? '?' . http_build_query($qs) : '';
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0">Master User</h4>
      <p class="text-muted mb-0">
        Admin aktif saat ini: <strong><?php echo (int) $total_admin; ?></strong>.
        Minimal harus selalu ada satu.
      </p>
    </div>
    <div class="col text-end">
      <a href="<?php echo base_url('master/user/form'); ?>" class="btn btn-primary">
        <i class="bx bx-plus-circle"></i> Tambah User
      </a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <?php echo form_open('master/user', array('method' => 'get', 'class' => 'row g-2')); ?>
        <div class="col-md-4">
          <input type="text" class="form-control" name="search" placeholder="Cari nama atau username"
                 value="<?php echo nilai_aman($search); ?>" />
        </div>
        <div class="col-md-3">
          <select name="role" class="form-select">
            <option value="">Semua Role</option>
            <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
            <option value="kepala_ruangan" <?php echo $role === 'kepala_ruangan' ? 'selected' : ''; ?>>Kepala Ruangan</option>
            <option value="pelapor" <?php echo $role === 'pelapor' ? 'selected' : ''; ?>>Pelapor</option>
          </select>
        </div>
        <div class="col-md-3">
          <select name="ruangan_id" class="form-select">
            <option value="">Semua Ruangan</option>
            <?php echo pilihan($ruangan_list, $ruangan ?: null); ?>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="bx bx-search"></i></button>
          <a href="<?php echo base_url('master/user'); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
      <?php echo form_close(); ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <h5 class="mb-0"><?php echo (int) $total; ?> user</h5>
      <span class="text-muted small">Halaman <?php echo (int) $page; ?> dari <?php echo max(1, (int) $pages); ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Nama</th>
            <th>Username</th>
            <th>Role</th>
            <th>Ruangan</th>
            <th>NIP</th>
            <th>Status</th>
            <th>Login Terakhir</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Belum ada user selain admin.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $badge = array(
                'admin'          => array('bg-label-primary', 'Admin'),
                'kepala_ruangan' => array('bg-label-info', 'Kepala Ruangan'),
                'pelapor'        => array('bg-label-secondary', 'Pelapor'),
              );
              $b = isset($badge[$r['role']]) ? $badge[$r['role']] : array('bg-label-secondary', $r['role']);
            ?>
            <tr>
              <td>
                <?php echo nilai_aman($r['nama']); ?>
                <?php if ((int) $r['id'] === (int) $user['id']): ?>
                  <span class="badge bg-label-warning ms-1">Anda</span>
                <?php endif; ?>
              </td>
              <td><code><?php echo nilai_aman($r['username']); ?></code></td>
              <td><span class="badge <?php echo $b[0]; ?>"><?php echo $b[1]; ?></span></td>
              <td><small><?php echo nilai_aman($r['ruang_nama']); ?></small></td>
              <td><small><?php echo nilai_aman($r['nip']); ?></small></td>
              <td>
                <?php if ((int) $r['is_active'] === 1): ?>
                  <span class="badge bg-label-success">Aktif</span>
                <?php else: ?>
                  <span class="badge bg-label-danger">Nonaktif</span>
                <?php endif; ?>
              </td>
              <td class="text-nowrap"><small><?php echo waktu($r['last_login']); ?></small></td>
              <td class="text-end">
                <div class="d-inline-flex gap-1">
                  <a href="<?php echo base_url('master/user/form/' . (int) $r['id']); ?>"
                     class="btn btn-sm btn-outline-primary" title="Ubah"><i class="bx bx-edit-alt"></i></a>

                  <button type="button" class="btn btn-sm btn-outline-secondary"
                          data-bs-toggle="modal" data-bs-target="#modal-sandi-<?php echo (int) $r['id']; ?>"
                          title="Reset password"><i class="bx bx-key"></i></button>

                  <?php if ((int) $r['id'] !== (int) $user['id']): ?>
                    <?php echo form_open('master/user/toggle/' . (int) $r['id'], array('class' => 'd-inline')); ?>
                      <button type="submit" class="btn btn-sm btn-outline-secondary"
                              title="<?php echo (int) $r['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                        <i class="bx bx-power-off"></i>
                      </button>
                    <?php echo form_close(); ?>

                    <?php echo form_open('master/user/hapus/' . (int) $r['id'], array('class' => 'd-inline')); ?>
                      <button type="submit" class="btn btn-sm btn-outline-danger"
                              onclick="return confirm('Hapus user &quot;<?php echo nilai_aman(addslashes($r['username'])); ?>&quot;? Riwayat laporannya tetap tersimpan.')">
                        <i class="bx bx-trash"></i>
                      </button>
                    <?php echo form_close(); ?>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modal reset password -->
  <?php foreach ($rows as $r): ?>
    <div class="modal fade" id="modal-sandi-<?php echo (int) $r['id']; ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-sm">
        <div class="modal-content">
          <?php echo form_open('master/user/reset-sandi/' . (int) $r['id']); ?>
            <div class="modal-header">
              <h5 class="modal-title">Reset Password</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <p class="small text-muted mb-3">
                User <strong><?php echo nilai_aman($r['username']); ?></strong>
              </p>
              <div class="mb-2">
                <label class="form-label" for="pw-baru-<?php echo (int) $r['id']; ?>">Password Baru</label>
                <input type="password" class="form-control" required minlength="8"
                       id="pw-baru-<?php echo (int) $r['id']; ?>" name="password_baru"
                       autocomplete="new-password" />
              </div>
              <div>
                <label class="form-label" for="pw-ulang-<?php echo (int) $r['id']; ?>">Ulangi Password</label>
                <input type="password" class="form-control" required minlength="8"
                       id="pw-ulang-<?php echo (int) $r['id']; ?>" name="password_konfirmasi"
                       autocomplete="new-password" />
              </div>
              <div class="form-text">Minimal 8 karakter, ada huruf besar, huruf kecil, dan angka.</div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
          <?php echo form_close(); ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

</div>
