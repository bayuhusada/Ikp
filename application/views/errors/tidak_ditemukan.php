<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Halaman error. Tanpa layout admin, tanpa sidebar.
 * Variabel: $kode, $judul, $pesan
 */
?>
<!DOCTYPE html>
<html lang="id" class="light-style" dir="ltr" data-theme="theme-default">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?php echo htmlspecialchars((string) $judul, ENT_QUOTES, 'UTF-8'); ?> - Pelaporan IKP</title>
	<link rel="icon" type="image/x-icon" href="<?php echo base_url('assets/sneat/assets/img/favicon/favicon.ico') ?>" />
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
	<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
	<link rel="stylesheet" href="<?php echo base_url('assets/sneat/assets/vendor/fonts/boxicons.css') ?>" />
	<link rel="stylesheet" href="<?php echo base_url('assets/sneat/assets/vendor/css/core.css') ?>" />
	<link rel="stylesheet" href="<?php echo base_url('assets/sneat/assets/vendor/css/theme-default.css') ?>" />
	<link rel="stylesheet" href="<?php echo base_url('assets/sneat/assets/css/demo.css') ?>" />
</head>
<body>
	<div class="container-xxl">
		<div class="authentication-wrapper authentication-basic container-p-y">
			<div class="authentication-inner">
				<div class="card">
					<div class="card-body text-center">
						<div class="app-brand justify-content-center mb-4">
							<span class="app-brand-text fw-bold fs-4">Pelaporan IKP</span>
						</div>

						<h2 class="display-1 mb-2 text-primary"><?php echo (int) $kode; ?></h2>
						<p class="h5 mb-3"><?php echo htmlspecialchars((string) $judul, ENT_QUOTES, 'UTF-8'); ?></p>
						<p class="text-muted mb-4"><?php echo htmlspecialchars((string) $pesan, ENT_QUOTES, 'UTF-8'); ?></p>

						<a href="<?php echo base_url('dashboard'); ?>" class="btn btn-primary">
							<i class="bx bx-home-circle"></i> Kembali ke Dashboard
						</a>
						<a href="<?php echo base_url('auth/login'); ?>" class="btn btn-outline-secondary">
							<i class="bx bx-log-in"></i> Login
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script src="<?php echo base_url('assets/sneat/assets/vendor/js/helpers.js') ?>"></script>
	<script src="<?php echo base_url('assets/sneat/assets/js/config.js') ?>"></script>
</body>
</html>
