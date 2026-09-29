<!DOCTYPE html>
<html lang="id" class="light-style" dir="ltr" data-theme="theme-default">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
	<title>Login - Sistem Pelaporan Insiden Keselamatan Pasien</title>
	<link rel="icon" type="image/x-icon" href="<?php echo base_url('assets/sneat/assets/img/favicon/favicon.ico') ?>" />
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
	<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
	<link rel="stylesheet" href="<?php echo base_url('assets/sneat/assets/vendor/fonts/boxicons.css') ?>" />
	<link rel="stylesheet" href="<?php echo base_url('assets/sneat/assets/vendor/css/core.css') ?>" />
	<link rel="stylesheet" href="<?php echo base_url('assets/sneat/assets/vendor/css/theme-default.css') ?>" />
	<link rel="stylesheet" href="<?php echo base_url('assets/sneat/assets/css/demo.css') ?>" />
	<link rel="stylesheet" href="<?php echo base_url('assets/sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') ?>" />
	<link rel="stylesheet" href="<?php echo base_url('assets/sneat/assets/vendor/libs/sweetalert/sweetalert2.min.css') ?>" />
	<link rel="stylesheet" href="<?php echo base_url('assets/sneat/assets/vendor/css/pages/page-auth.css') ?>" />
</head>
<body>
	<div class="container-xxl">
		<div class="authentication-wrapper authentication-basic container-p-y">
			<div class="authentication-inner">
				<div class="card">
					<div class="card-body">
						<div class="app-brand justify-content-center mb-4">
							<span class="app-brand-text fw-bold fs-4">Pelaporan IKP</span>
						</div>
						<p class="text-center text-muted small mb-4">
							Sistem Pelaporan Insiden Keselamatan Pasien
						</p>

						<?php if ($this->session->flashdata('error')): ?>
							<div class="alert alert-danger py-2"><?php echo htmlspecialchars($this->session->flashdata('error'), ENT_QUOTES, 'UTF-8'); ?></div>
						<?php endif; ?>

						<?php if ($this->session->flashdata('success')): ?>
							<div class="alert alert-success py-2"><?php echo htmlspecialchars($this->session->flashdata('success'), ENT_QUOTES, 'UTF-8'); ?></div>
						<?php endif; ?>

						<?php echo form_open('auth/proses_login', array('class' => 'mb-3')); ?>
							<div class="mb-3">
								<label class="form-label" for="username">Username</label>
								<input type="text" class="form-control" id="username" name="username"
										placeholder="Masukkan username" required autofocus
										autocomplete="username" maxlength="50" />
							</div>
							<div class="mb-3 form-password-toggle">
								<label class="form-label" for="password">Password</label>
								<div class="input-group input-group-merge">
									<input type="password" class="form-control" id="password" name="password"
											placeholder="Masukkan password" required
											autocomplete="current-password" maxlength="255" />
									<span class="input-group-text cursor-pointer" onclick="const p=this.previousElementSibling; p.type=p.type==='password'?'text':'password'">
										<i class="bx bx-hide"></i>
									</span>
								</div>
							</div>
							<div class="mb-3">
								<button class="btn btn-primary d-grid w-100" type="submit">Login</button>
							</div>
						<?php echo form_close(); ?>

						<div class="text-center">
							<span class="badge bg-label-danger">RAHASIA</span>
							<p class="text-muted small mt-2 mb-0">
								Dokumen terbatas akses. Dilarang difotocopy.
							</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script src="<?php echo base_url('assets/sneat/assets/vendor/js/helpers.js') ?>"></script>
	<script src="<?php echo base_url('assets/sneat/assets/js/config.js') ?>"></script>
	<script src="<?php echo base_url('assets/sneat/assets/vendor/libs/jquery/jquery.js') ?>"></script>
	<script src="<?php echo base_url('assets/sneat/assets/vendor/js/bootstrap.js') ?>"></script>
	<script src="<?php echo base_url('assets/sneat/assets/vendor/libs/sweetalert/sweetalert2.min.js') ?>"></script>
	<script>
		<?php if ($this->session->flashdata('error')): ?>
		Swal.fire({
			icon: 'error',
			title: 'Gagal',
			text: <?php echo json_encode($this->session->flashdata('error'), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>,
			confirmButtonText: 'Mengerti'
		});
		<?php endif; ?>
	</script>
</body>
</html>
