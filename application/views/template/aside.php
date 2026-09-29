<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Sidebar role-aware.
 *
 * Menu ditentukan oleh $user['role']. Jangan pernah menampilkan menu
 * yang tidak ada di sini, walau kontrollernya sudah menjaga akses.
 *
 * @var array $user
 */

$role = isset($user['role']) ? $user['role'] : '';
$seg1 = $this->uri->segment(1);
$seg2 = $this->uri->segment(1) . '/' . $this->uri->segment(2);

/** Helper: aktif bila path cocok. */
if (! function_exists('nav_active')) {
	function nav_active($current, $path, $exact = false)
	{
		if ($exact) {
			return $current === $path ? ' active' : '';
		}
		return ($current === $path OR strpos($current, $path) === 0) ? ' active' : '';
	}
}

$app_name = 'Pelaporan IKP';
?>
<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">

    <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
      <div class="app-brand demo">
        <a href="<?php echo base_url('dashboard'); ?>" class="app-brand-link">
          <span class="app-brand-text demo menu-text fw-bolder ms-2"><?php echo htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8'); ?></span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
          <i class="bx bx-chevron-left bx-sm align-middle"></i>
        </a>
      </div>

      <div class="menu-inner-shadow"></div>

      <ul class="menu-inner py-1">

        <!-- Dashboard -->
        <li class="menu-item<?php echo nav_active($seg1, 'dashboard', true); ?>">
          <a href="<?php echo base_url('dashboard'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-home-circle"></i>
            <div>Dashboard</div>
          </a>
        </li>

        <?php if ($role === 'pelapor' OR $role === 'kepala_ruangan' OR $role === 'admin'): ?>
        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">Laporan Insiden</span>
        </li>

        <li class="menu-item<?php echo nav_active($seg2, 'laporan/input'); ?>">
          <a href="<?php echo base_url('laporan/input'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-edit-alt"></i>
            <div>Input Laporan</div>
          </a>
        </li>

        <li class="menu-item<?php echo nav_active($seg2, 'laporan/detail'); ?>">
          <a href="<?php echo base_url('laporan'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-list-ul"></i>
            <div><?php echo $role === 'pelapor' ? 'Laporan Saya' : 'Semua Laporan'; ?></div>
          </a>
        </li>

        <?php if ($role === 'admin'): ?>
        <li class="menu-item<?php echo nav_active($seg2, 'laporan/arsip'); ?>">
          <a href="<?php echo base_url('laporan/arsip'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-archive"></i>
            <div>Arsip Laporan</div>
          </a>
        </li>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($role === 'admin' OR $role === 'kepala_ruangan'): ?>
        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">Investigasi</span>
        </li>

        <li class="menu-item<?php echo nav_active($seg1, 'investigasi', true); ?>">
          <a href="<?php echo base_url('investigasi'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-search-alt"></i>
            <div><?php echo $role === 'admin' ? 'Semua Investigasi' : 'Status Investigasi'; ?></div>
          </a>
        </li>
        <?php /* Tidak ada menu "Buat Investigasi". Lembar investigasi WAJIB dibuat
                dari halaman detail laporan yang sudah di-grading, karena harus
                punya induk. Endpointnya juga butuh id laporan, jadi tautan
                tanpa id akan 404. */ ?>
        <?php endif; ?>

        <?php if (($role === 'kepala_ruangan' OR $role === 'admin') AND fitur_ada('rekap')): ?>
        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">Laporan dan Statistik</span>
        </li>

        <li class="menu-item<?php echo nav_active($seg1, 'rekap', true); ?>">
          <a href="<?php echo base_url('rekap'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-line-chart"></i>
            <div>Rekap dan Statistik</div>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">Master Data</span>
        </li>

        <li class="menu-item<?php echo nav_active($seg2, 'master/user'); ?>">
          <a href="<?php echo base_url('master/user'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-user"></i>
            <div>User</div>
          </a>
        </li>

        <li class="menu-item<?php echo nav_active($seg2, 'master/ruangan'); ?>">
          <a href="<?php echo base_url('master/ruangan'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-diamond"></i>
            <div>Ruangan</div>
          </a>
        </li>

        <?php // Menu master di bawah ini hanya tampil kalau fiturnya sudah jadi. ?>
        <?php if (fitur_ada('master_jenis_insiden')): ?>
        <li class="menu-item<?php echo nav_active($seg2, 'master/jenis-insiden'); ?>">
          <a href="<?php echo base_url('master/jenis-insiden'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-error"></i>
            <div>Jenis Insiden</div>
          </a>
        </li>
        <?php endif; ?>

        <?php if (fitur_ada('master_kategori')): ?>
        <li class="menu-item<?php echo nav_active($seg2, 'master/kategori'); ?>">
          <a href="<?php echo base_url('master/kategori'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-category"></i>
            <div>Kategori Kejadian</div>
          </a>
        </li>
        <?php endif; ?>

        <?php if (fitur_ada('master_spesialisasi')): ?>
        <li class="menu-item<?php echo nav_active($seg2, 'master/spesialisasi'); ?>">
          <a href="<?php echo base_url('master/spesialisasi'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-plus-square"></i>
            <div>Spesialisasi</div>
          </a>
        </li>
        <?php endif; ?>

        <?php if (fitur_ada('master_akibat')): ?>
        <li class="menu-item<?php echo nav_active($seg2, 'master/akibat'); ?>">
          <a href="<?php echo base_url('master/akibat'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-injury"></i>
            <div>Akibat Insiden</div>
          </a>
        </li>
        <?php endif; ?>

        <li class="menu-item<?php echo nav_active($seg2, 'master/band-risiko'); ?>">
          <a href="<?php echo base_url('master/band-risiko'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-traffic-cone"></i>
            <div>Band Risiko</div>
          </a>
        </li>

        <?php if (fitur_ada('master_status')): ?>
        <li class="menu-item<?php echo nav_active($seg2, 'master/status'); ?>">
          <a href="<?php echo base_url('master/status'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-git-commit"></i>
            <div>Status</div>
          </a>
        </li>
        <?php endif; ?>

        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">Sistem</span>
        </li>

        <li class="menu-item<?php echo nav_active($seg1, 'audit-log', true); ?>">
          <a href="<?php echo base_url('audit-log'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-history"></i>
            <div>Audit Log</div>
          </a>
        </li>
        <?php endif; ?>

        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">Akun</span>
        </li>

        <li class="menu-item<?php echo nav_active($seg1, 'profil', true); ?>">
          <a href="<?php echo base_url('profil'); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-user-circle"></i>
            <div>Profil</div>
          </a>
        </li>

        <!-- Logout WAJIB POST, bukan GET, agar tidak bisa dipicu diam-diam -->
        <li class="menu-item">
          <?php echo form_open('auth/logout', array('id' => 'form-logout')); ?>
          <button type="submit" class="menu-link border-0 bg-transparent w-100 text-start">
            <i class="menu-icon tf-icons bx bx-log-out"></i>
            <div>Logout</div>
          </button>
          <?php echo form_close(); ?>
        </li>

      </ul>
    </aside>

    <div class="layout-page">
      <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
        id="layout-navbar">

        <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
          <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
            <i class="bx bx-menu bx-sm"></i>
          </a>
        </div>

        <div class="position-absolute start-50 translate-middle-x">
          <a href="#" class="navbar-brand mb-0 h1 text-primary fw-bold"><?php echo htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8'); ?></a>
        </div>

        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
          <div class="navbar-nav ms-auto">
            <div class="nav-item d-flex align-items-center">
              <span class="me-3">
                <i class="bx bx-user"></i>
                <?php
                  $display = isset($user['nama']) ? $user['nama'] : 'Pengguna';
                  echo htmlspecialchars($display, ENT_QUOTES, 'UTF-8');
                ?>
                <small class="text-muted">(<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>)</small>
              </span>
              <?php if (! empty($user['ruangan_id'])): ?>
                <small class="badge bg-label-secondary me-3">
                  <?php
                    $rn = isset($user['ruang_nama']) ? $user['ruang_nama'] : null;
                    echo $rn ? htmlspecialchars($rn, ENT_QUOTES, 'UTF-8') : 'Ruangan #' . (int) $user['ruangan_id'];
                  ?>
                </small>
              <?php endif; ?>
              <?php echo form_open('auth/logout'); ?>
              <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bx bx-log-out"></i> Logout
              </button>
              <?php echo form_close(); ?>
            </div>
          </div>
        </div>
      </nav>

      <div class="content-wrapper">
