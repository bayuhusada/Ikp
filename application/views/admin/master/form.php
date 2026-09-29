<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * View generik untuk form master data.
 *
 * @var array|null $row
 * @var bool       $is_edit
 * @var array      $fields, $spec
 * @var array      $user
 *
 * Kolom digambar dari $fields, jadi controller cukup menentukan definisi kolom
 * tanpa perlu mengubah view ini.
 */

$row = is_array($row) ? $row : array();
$slug = $spec['slug'];

$aksi = $is_edit
    ? base_url('master/' . $slug . '/save/' . (int) $row['id'])
    : base_url('master/' . $slug . '/save');
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0"><?php echo nilai_aman($title); ?></h4>
      <a href="<?php echo base_url('master/' . $slug); ?>" class="text-muted small">
        <i class="bx bx-arrow-back"></i> Kembali ke daftar
      </a>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-body">
          <?php echo form_open($aksi, array('class' => 'row g-3')); ?>

            <?php foreach ($fields as $f): ?>
              <?php
              $nilai = isset($row[$f['nama']]) ? $row[$f['nama']] : NULL;

              // Saat menambah, kolom switch default aktif.
              if ($f['jenis'] === 'switch' && ! $is_edit) {
                  $nilai = 1;
              }
              ?>

              <?php if ($f['jenis'] === 'switch'): ?>
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input type="hidden" name="<?php echo nilai_aman($f['nama']); ?>" value="0" />
                    <input class="form-check-input"
                           type="checkbox"
                           id="<?php echo nilai_aman($f['nama']); ?>"
                           name="<?php echo nilai_aman($f['nama']); ?>"
                           value="1"
                           <?php echo (int) $nilai === 1 ? 'checked' : ''; ?> />
                    <label class="form-check-label" for="<?php echo nilai_aman($f['nama']); ?>">
                      <?php echo nilai_aman($f['label']); ?>
                    </label>
                  </div>
                  <?php if (! empty($f['keterangan'])): ?>
                    <div class="form-text"><?php echo nilai_aman($f['keterangan']); ?></div>
                  <?php endif; ?>
                </div>

              <?php else: ?>
                <?php $lebar = $f['jenis'] === 'textarea' ? 'col-12' : 'col-md-6'; ?>
                <div class="<?php echo $lebar; ?>">
                  <label class="form-label" for="<?php echo nilai_aman($f['nama']); ?>">
                    <?php echo nilai_aman($f['label']); ?>
                    <?php echo ! empty($f['wajib']) ? '<span class="text-danger">*</span>' : ''; ?>
                  </label>

                  <?php if ($f['jenis'] === 'textarea'): ?>
                    <textarea class="form-control"
                              id="<?php echo nilai_aman($f['nama']); ?>"
                              name="<?php echo nilai_aman($f['nama']); ?>"
                              rows="<?php echo isset($f['baris']) ? (int) $f['baris'] : 4; ?>"
                              <?php echo ! empty($f['wajib']) ? 'required' : ''; ?>
                              <?php echo isset($f['max']) ? 'maxlength="' . (int) $f['max'] . '"' : ''; ?>
                              ><?php echo nilai_aman($nilai); ?></textarea>

                  <?php elseif ($f['jenis'] === 'number'): ?>
                    <input type="number"
                           class="form-control"
                           id="<?php echo nilai_aman($f['nama']); ?>"
                           name="<?php echo nilai_aman($f['nama']); ?>"
                           min="0"
                           max="255"
                           <?php echo ! empty($f['wajib']) ? 'required' : ''; ?>
                           value="<?php echo nilai_aman($nilai); ?>" />

                  <?php else: ?>
                    <input type="text"
                           class="form-control<?php echo $f['nama'] === 'kode' ? ' text-uppercase' : ''; ?>"
                           id="<?php echo nilai_aman($f['nama']); ?>"
                           name="<?php echo nilai_aman($f['nama']); ?>"
                           <?php echo ! empty($f['wajib']) ? 'required' : ''; ?>
                           <?php echo isset($f['max']) ? 'maxlength="' . (int) $f['max'] . '"' : ''; ?>
                           value="<?php echo nilai_aman($nilai); ?>" />
                  <?php endif; ?>

                  <?php if (! empty($f['keterangan'])): ?>
                    <div class="form-text"><?php echo nilai_aman($f['keterangan']); ?></div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>

            <div class="col-12">
              <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Simpan</button>
              <a href="<?php echo base_url('master/' . $slug); ?>" class="btn btn-outline-secondary">Batal</a>
            </div>

          <?php echo form_close(); ?>
        </div>
      </div>
    </div>
  </div>

</div>
