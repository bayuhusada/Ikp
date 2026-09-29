<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array  $blocks, $untuk_list
 * @var int    $total
 * @var string $search
 * @var array  $user
 */
$qs = $search !== '' ? '?search=' . urlencode($search) : '';
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0">Master Status</h4>
      <p class="text-muted mb-0">
        <?php echo (int) $total; ?> status. Urutan menentukan urutan alur, dan status
        terminal menandai tahap akhir satu proses.
      </p>
    </div>
    <div class="col text-end">
      <a href="<?php echo base_url('master/status/form'); ?>" class="btn btn-primary">
        <i class="bx bx-plus-circle"></i> Tambah Status
      </a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <?php echo form_open('master/status', array('method' => 'get', 'class' => 'row g-2')); ?>
        <div class="col-md-9">
          <input type="text" class="form-control" name="search"
                 placeholder="Cari kode atau nama status" value="<?php echo nilai_aman($search); ?>" />
        </div>
        <div class="col-md-3 d-flex gap-1">
          <button type="submit" class="btn btn-primary w-100"><i class="bx bx-search"></i> Cari</button>
          <a href="<?php echo base_url('master/status'); ?>" class="btn btn-outline-secondary">
            <i class="bx bx-x"></i>
          </a>
        </div>
      <?php echo form_close(); ?>
    </div>
  </div>

  <?php foreach ($blocks as $jenis => $b): ?>
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">
          <i class="bx <?php echo nilai_aman($b['icon']); ?>"></i>
          <?php echo nilai_aman($b['label']); ?>
          <span class="badge bg-label-secondary ms-1"><?php echo count($b['rows']); ?></span>
        </h5>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th style="width:60px">Urutan</th>
              <th style="width:170px">Kode</th>
              <th>Nama</th>
              <th style="width:120px">Terminal</th>
              <th style="width:110px">Status</th>
              <th style="width:100px">Dipakai</th>
              <th style="width:190px" class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($b['rows'])): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-4">
                <?php echo $search !== '' ? 'Tidak ada status yang cocok.' : 'Belum ada status untuk alur ini.'; ?>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($b['rows'] as $r): ?>
              <tr>
                <td><?php echo (int) $r['urutan']; ?></td>
                <td><code><?php echo nilai_aman($r['kode']); ?></code></td>
                <td>
                  <?php echo nilai_aman($r['nama']); ?>
                  <?php if ((int) $r['is_active'] === 0): ?>
                    <span class="badge bg-label-danger ms-1">Nonaktif</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ((int) $r['is_terminal'] === 1): ?>
                    <span class="badge bg-label-dark">Terminal</span>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ((int) $r['is_active'] === 1): ?>
                    <span class="badge bg-label-success">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-label-danger">Nonaktif</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ((int) $r['dipakai'] > 0): ?>
                    <span class="badge bg-label-info"><?php echo (int) $r['dipakai']; ?></span>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <a href="<?php echo base_url('master/status/form/' . (int) $r['id']); ?>"
                     class="btn btn-sm btn-outline-primary"><i class="bx bx-edit-alt"></i> Ubah</a>

                  <?php // WAJIB form_open(): CI3 menolak POST tanpa csrf_token dengan 403. ?>
                  <?php echo form_open('master/status/toggle/' . (int) $r['id'], array('class' => 'd-inline')); ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                      <?php echo (int) $r['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan'; ?>
                    </button>
                  <?php echo form_close(); ?>

                  <?php echo form_open('master/status/hapus/' . (int) $r['id'], array('class' => 'd-inline')); ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Hapus status &quot;<?php echo nilai_aman($r['nama']); ?>&quot;?<?php
                            echo (int) $r['dipakai'] > 0 ? '\n\nStatus ini masih dipakai, jadi penghapusan akan ditolak.' : ''; ?>')">
                      <i class="bx bx-trash"></i>
                    </button>
                  <?php echo form_close(); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="card">
    <div class="card-body small text-muted">
      <i class="bx bx-info-circle"></i>
      Kolom <strong>Untuk</strong> tidak bisa diubah setelah status dibuat, karena
      status menentukan alur mana yang memakainya. Kalau salah, nonaktifkan lalu
      buat baru dengan jenis yang benar.
    </div>
  </div>

</div>
