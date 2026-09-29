<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array  $rows
 * @var string $search
 * @var string $jenis
 * @var int    $total, $page, $pages, $limit
 * @var array  $user
 */
$qs = array_filter(array('search' => $search, 'jenis' => $jenis, 'limit' => $limit));
$qs = $qs ? '?' . http_build_query($qs) : '';
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0">Master Ruangan</h4>
      <p class="text-muted mb-0">Daftar unit dan departemen. Dipakai untuk lokasi insiden dan filter data Kepala Ruangan.</p>
    </div>
    <div class="col text-end">
      <a href="<?php echo base_url('master/ruangan/form'); ?>" class="btn btn-primary">
        <i class="bx bx-plus-circle"></i> Tambah Ruangan
      </a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <?php echo form_open('master/ruangan', array('method' => 'get', 'class' => 'row g-2')); ?>
        <div class="col-md-5">
          <input type="text" class="form-control" name="search" placeholder="Cari kode atau nama"
                 value="<?php echo nilai_aman($search); ?>" />
        </div>
        <div class="col-md-3">
          <select name="jenis" class="form-select">
            <option value="">Semua Jenis</option>
            <option value="unit" <?php echo $jenis === 'unit' ? 'selected' : ''; ?>>Unit</option>
            <option value="departemen" <?php echo $jenis === 'departemen' ? 'selected' : ''; ?>>Departemen</option>
          </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="bx bx-search"></i> Filter</button>
          <a href="<?php echo base_url('master/ruangan'); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
      <?php echo form_close(); ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <h5 class="mb-0"><?php echo (int) $total; ?> ruangan</h5>
      <span class="text-muted small">Halaman <?php echo (int) $page; ?> dari <?php echo max(1, (int) $pages); ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Nama Ruangan</th>
            <th>Jenis</th>
            <th>Status</th>
            <th>Dibuat</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-4">
              Belum ada ruangan. Tambahkan dulu agar bisa membuat laporan insiden.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><code><?php echo nilai_aman($r['kode']); ?></code></td>
              <td><?php echo nilai_aman($r['nama']); ?></td>
              <td>
                <span class="badge bg-label-secondary">
                  <?php echo $r['jenis'] === 'departemen' ? 'Departemen' : 'Unit'; ?>
                </span>
              </td>
              <td>
                <?php if ((int) $r['is_active'] === 1): ?>
                  <span class="badge bg-label-success">Aktif</span>
                <?php else: ?>
                  <span class="badge bg-label-danger">Nonaktif</span>
                <?php endif; ?>
              </td>
              <td class="text-nowrap"><small><?php echo waktu($r['created_at']); ?></small></td>
              <td class="text-end">
                <div class="d-inline-flex gap-1">
                  <a href="<?php echo base_url('master/ruangan/form/' . (int) $r['id']); ?>"
                     class="btn btn-sm btn-outline-primary" title="Ubah">
                    <i class="bx bx-edit-alt"></i>
                  </a>

                  <?php echo form_open('master/ruangan/toggle/' . (int) $r['id'], array('class' => 'd-inline')); ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary"
                            title="<?php echo (int) $r['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                      <i class="bx bx-power-off"></i>
                    </button>
                  <?php echo form_close(); ?>

                  <?php echo form_open('master/ruangan/hapus/' . (int) $r['id'], array('class' => 'd-inline')); ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Hapus ruangan &quot;<?php echo nilai_aman(addslashes($r['nama'])); ?>&quot;? Ruangan yang sudah dipakai tidak bisa dihapus.')">
                      <i class="bx bx-trash"></i>
                    </button>
                  <?php echo form_close(); ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
