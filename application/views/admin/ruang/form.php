<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array|null $row
 * @var bool      $is_edit
 * @var array     $user
 */
$aksi = $is_edit
    ? base_url('master/ruangan/save/' . (int) $row['id'])
    : base_url('master/ruangan/save');
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0"><?php echo $is_edit ? 'Ubah Ruangan' : 'Tambah Ruangan'; ?></h4>
      <a href="<?php echo base_url('master/ruangan'); ?>" class="text-muted small">
        <i class="bx bx-arrow-back"></i> Kembali ke daftar
      </a>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-body">
          <?php echo form_open($aksi, array('class' => 'row g-3')); ?>

            <div class="col-md-4">
              <label class="form-label" for="kode">Kode Ruangan <span class="text-danger">*</span></label>
              <input type="text" class="form-control text-uppercase" id="kode" name="kode" required
                     maxlength="20" placeholder="cth. IGD"
                     value="<?php echo nilai_aman($row['kode'] ?? ''); ?>" />
              <div class="form-text">Otomatis jadi huruf besar. Harus unik.</div>
            </div>

            <div class="col-md-8">
              <label class="form-label" for="nama">Nama Ruangan <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="nama" name="nama" required maxlength="100"
                     placeholder="cth. Instalasi Gawat Darurat"
                     value="<?php echo nilai_aman($row['nama'] ?? ''); ?>" />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="jenis">Jenis <span class="text-danger">*</span></label>
              <select class="form-select" id="jenis" name="jenis" required>
                <option value="unit" <?php echo $row['jenis'] ?? 'unit' === 'unit' ? 'selected' : ''; ?>>Unit</option>
                <option value="departemen" <?php echo $row['jenis'] ?? 'unit' === 'departemen' ? 'selected' : ''; ?>>Departemen</option>
              </select>
            </div>

            <div class="col-12">
              <div class="alert alert-secondary mb-0 small">
                <i class="bx bx-info-circle"></i>
                Ruangan dipakai untuk dua hal: lokasi tempat insiden terjadi, dan
                pembatas data yang boleh dilihat Kepala Ruangan. Pastikan setiap
                Kepala Ruangan punya satu ruangan yang benar.
              </div>
            </div>

            <div class="col-12">
              <button type="submit" class="btn btn-primary">
                <i class="bx bx-save"></i> Simpan
              </button>
              <a href="<?php echo base_url('master/ruangan'); ?>" class="btn btn-outline-secondary">Batal</a>
            </div>

          <?php echo form_close(); ?>
        </div>
      </div>
    </div>
  </div>

</div>
