<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array|null $row
 * @var bool       $is_edit
 * @var array      $untuk_list
 * @var array      $user
 */
$row = is_array($row) ? $row : array();

$v_kode      = isset($row['kode']) ? $row['kode'] : '';
$v_nama      = isset($row['nama']) ? $row['nama'] : '';
$v_untuk     = isset($row['untuk']) ? $row['untuk'] : 'laporan';
$v_urutan    = isset($row['urutan']) ? $row['urutan'] : 1;
$v_terminal  = $is_edit && isset($row['is_terminal']) ? (int) $row['is_terminal'] : 0;
$v_aktif     = $is_edit && isset($row['is_active']) ? ((int) $row['is_active'] === 1) : true;

$aksi = $is_edit
    ? base_url('master/status/save/' . (int) $row['id'])
    : base_url('master/status/save');
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0"><?php echo nilai_aman($title); ?></h4>
      <a href="<?php echo base_url('master/status'); ?>" class="text-muted small">
        <i class="bx bx-arrow-back"></i> Kembali ke daftar
      </a>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-body">
          <?php echo form_open($aksi, array('class' => 'row g-3')); ?>

            <div class="col-md-4">
              <label class="form-label" for="untuk">Untuk <span class="text-danger">*</span></label>
              <?php if ($is_edit): ?>
                <input type="hidden" name="untuk" value="<?php echo nilai_aman($v_untuk); ?>" />
                <input type="text" class="form-control" id="untuk" disabled
                       value="<?php echo nilai_aman($untuk_list[$v_untuk]); ?>" />
                <div class="form-text">
                  Tidak bisa diubah. Status ini sudah menentukan alurnya.
                </div>
              <?php else: ?>
                <select class="form-select" id="untuk" name="untuk" required>
                  <?php foreach ($untuk_list as $k => $label): ?>
                    <option value="<?php echo nilai_aman($k); ?>" <?php echo $v_untuk === $k ? 'selected' : ''; ?>>
                      <?php echo nilai_aman($label); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text">Pilih alur yang akan memakai status ini.</div>
              <?php endif; ?>
            </div>

            <div class="col-md-4">
              <label class="form-label" for="kode">Kode <span class="text-danger">*</span></label>
              <input type="text" class="form-control text-lowercase" id="kode" name="kode" required
                     maxlength="30" value="<?php echo nilai_aman($v_kode); ?>" />
              <div class="form-text">Huruf kecil tanpa spasi, misalnya draft.</div>
            </div>

            <div class="col-md-4">
              <label class="form-label" for="urutan">Urutan <span class="text-danger">*</span></label>
              <input type="number" class="form-control" id="urutan" name="urutan" required
                     min="0" max="255" value="<?php echo nilai_aman($v_urutan); ?>" />
              <div class="form-text">Menentukan urutan transisi. 1 = paling awal.</div>
            </div>

            <div class="col-12">
              <label class="form-label" for="nama">Nama <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="nama" name="nama" required
                     maxlength="50" value="<?php echo nilai_aman($v_nama); ?>" />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="is_terminal">Status Terminal <span class="text-danger">*</span></label>
              <select class="form-select" id="is_terminal" name="is_terminal" required>
                <option value="0" <?php echo $v_terminal === 0 ? 'selected' : ''; ?>>
                  Tidak - masih ada tahap berikutnya
                </option>
                <option value="1" <?php echo $v_terminal === 1 ? 'selected' : ''; ?>>
                  Ya - tahap akhir, proses ditutup di sini
                </option>
              </select>
              <div class="form-text">
                Status terminal tidak bisa dipilih lagi sebagai tujuan perpindahan status.
              </div>
            </div>

            <div class="col-12">
              <div class="form-check form-switch">
                <input type="hidden" name="is_active" value="0" />
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                       <?php echo $v_aktif ? 'checked' : ''; ?> />
                <label class="form-check-label" for="is_active">
                  Aktif dan bisa dipilih saat berpindah status
                </label>
              </div>
            </div>

            <div class="col-12">
              <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Simpan</button>
              <a href="<?php echo base_url('master/status'); ?>" class="btn btn-outline-secondary">Batal</a>
            </div>

          <?php echo form_close(); ?>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h6 class="mb-0">Alur yang Terdaftar</h6></div>
        <div class="card-body small">
          <dl class="mb-0">
            <dt>Laporan</dt>
            <dd class="text-muted mb-2">
              Draft &rarr; Dilaporkan &rarr; Menunggu Grading &rarr; Sudah Grading
              &rarr; Investigasi &rarr; Tindak Lanjut &rarr; Selesai
            </dd>

            <dt>Investigasi</dt>
            <dd class="text-muted mb-2">
              Draft &rarr; Dalam Investigasi &rarr; Menunggu Tindak Lanjut
              &rarr; Dalam Pelaksanaan &rarr; Selesai
            </dd>

            <dt>Tindakan</dt>
            <dd class="text-muted mb-0">
              Belum Dimulai &rarr; Dalam Proses &rarr; Selesai
            </dd>
          </dl>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-body small text-muted">
          <i class="bx bx-warning"></i>
          Mengubah <strong>Urutan</strong> atau <strong>Terminal</strong> akan mengubah
          alur perpindahan status yang sedang berjalan. Lakukan hanya bila
          status lama benar-benar tidak sesuai.
        </div>
      </div>
    </div>
  </div>

</div>
