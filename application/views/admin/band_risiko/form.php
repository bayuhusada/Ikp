<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array|null $row
 * @var bool      $is_edit
 * @var array     $kode_list
 * @var array     $user
 */
$row = is_array($row) ? $row : array();

$v_kode    = isset($row['kode']) ? $row['kode'] : '';
$v_nama    = isset($row['nama']) ? $row['nama'] : '';
$v_warna   = isset($row['warna_hex']) && $row['warna_hex'] ? $row['warna_hex'] : '#2196F3';
$v_urutan  = isset($row['urutan']) ? $row['urutan'] : 1;
$v_kriteria= isset($row['kriteria']) ? $row['kriteria'] : '';
$v_aktif   = $is_edit && isset($row['is_active']) ? ((int) $row['is_active'] === 1) : true;

// Default: band 1-2 butuh investigasi sederhana, 3-4 tidak
if (! isset($row['perlu_investigasi_sederhana'])) {
    $v_inv = ((int) $v_urutan <= 2) ? 1 : 0;
} else {
    $v_inv = (int) $row['perlu_investigasi_sederhana'];
}

$aksi = $is_edit
    ? base_url('master/band-risiko/save/' . (int) $row['id'])
    : base_url('master/band-risiko/save');
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0"><?php echo $is_edit ? 'Ubah Band Risiko' : 'Tambah Band Risiko'; ?></h4>
      <a href="<?php echo base_url('master/band-risiko'); ?>" class="text-muted small">
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
              <label class="form-label" for="kode">Kode <span class="text-danger">*</span></label>
              <select class="form-select text-uppercase" id="kode" name="kode" required>
                <?php foreach ($kode_list as $k): ?>
                  <option value="<?php echo $k; ?>" <?php echo $v_kode === $k ? 'selected' : ''; ?>>
                    <?php echo $k; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-5">
              <label class="form-label" for="nama">Nama <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="nama" name="nama" required maxlength="50"
                     placeholder="cth. Risiko Rendah"
                     value="<?php echo nilai_aman($v_nama); ?>" />
            </div>

            <div class="col-md-3">
              <label class="form-label" for="warna_hex">Warna</label>
              <input type="color" class="form-control form-control-color w-100" id="warna_hex" name="warna_hex"
                     value="<?php echo nilai_aman($v_warna); ?>" />
            </div>

            <div class="col-md-4">
              <label class="form-label" for="urutan">Urutan <span class="text-danger">*</span></label>
              <input type="number" class="form-control" id="urutan" name="urutan" required
                     min="1" max="99" value="<?php echo nilai_aman($v_urutan); ?>" />
              <div class="form-text">1 = paling rendah.</div>
            </div>

            <div class="col-md-8">
              <label class="form-label" for="perlu_investigasi_sederhana">
                Perlu Investigasi Sederhana <span class="text-danger">*</span>
              </label>
              <select class="form-select" id="perlu_investigasi_sederhana"
                      name="perlu_investigasi_sederhana" required>
                <option value="1" <?php echo $v_inv === 1 ? 'selected' : ''; ?>>
                  Ya - pakai Lembar Kerja Investigasi Sederhana
                </option>
                <option value="0" <?php echo $v_inv === 0 ? 'selected' : ''; ?>>
                  Tidak - proses mengikuti SOP di luar sistem
                </option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label" for="kriteria">
                Kriteria Penentuan <span class="text-danger">*</span>
              </label>
              <textarea class="form-control" id="kriteria" name="kriteria" rows="7" required
                        placeholder="Tuliskan kriteria dari kebijakan resmi fasilitas kesehatan."><?php echo nilai_aman($v_kriteria); ?></textarea>
              <div class="form-text">
                Kriteria ini ditampilkan ke Kepala Ruangan saat melakukan grading.
                Sistem tidak boleh menentukan sendiri, jadi tulislah apa adanya
                dari dokumen resmi fasilitas kesehatan.
              </div>
            </div>

            <div class="col-12">
              <div class="form-check form-switch">
                <input type="hidden" name="is_active" value="0" />
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                       <?php echo $v_aktif ? 'checked' : ''; ?> />
                <label class="form-check-label" for="is_active">Band aktif dan bisa dipilih saat grading</label>
              </div>
            </div>

            <div class="col-12">
              <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Simpan</button>
              <a href="<?php echo base_url('master/band-risiko'); ?>" class="btn btn-outline-secondary">Batal</a>
            </div>

          <?php echo form_close(); ?>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Alur Setelah Grading</h5></div>
        <div class="card-body">
          <p class="small mb-2">Band menentukan jalur berikutnya:</p>
          <ul class="small">
            <li>
              <span class="badge bg-label-info">BIRU</span> dan
              <span class="badge bg-label-success">HIJAU</span><br>
              Dizuqukan dengan Lembar Kerja Investigasi Sederhana.
            </li>
            <li class="mt-2">
              <span class="badge bg-label-warning">KUNING</span> dan
              <span class="badge bg-label-danger">MERAH</span><br>
              Prosesnya mengikuti SOP di luar sistem ini.
            </li>
          </ul>
          <p class="small text-muted mb-0">
            Field "Perlu Investigasi Sederhana" yang menentukan pemisahan tersebut.
          </p>
        </div>
      </div>
    </div>
  </div>

</div>
