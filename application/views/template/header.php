<!DOCTYPE html>
<html
  lang="id"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="../assets/"
  data-template="vertical-menu-template-free"
>
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' | ' : ''; ?>Sistem Pelaporan Insiden Keselamatan Pasien</title>

    <meta name="description" content="Sistem Pelaporan Insiden Keselamatan Pasien" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo base_url('assets/sneat')?>/assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="<?php echo base_url('assets/sneat')?>/assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="<?php echo base_url('assets/sneat')?>/assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="<?php echo base_url('assets/sneat')?>/assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="<?php echo base_url('assets/sneat')?>/assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="<?php echo base_url('assets/sneat')?>/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <link rel="stylesheet" href="<?php echo base_url('assets/sneat')?>/assets/vendor/libs/apex-charts/apex-charts.css" />
		<link rel="stylesheet" href="<?php echo base_url('assets/sneat')?>/assets/vendor/libs/sweetalert/sweetalert2.min.css" />
		<link rel="stylesheet" href="<?php echo base_url('assets/sneat')?>/assets/vendor/libs/data-tables/datatables.min.css" />
		
		


    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="<?php echo base_url('assets/sneat')?>/assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="<?php echo base_url('assets/sneat')?>/assets/js/config.js"></script>
  </head>

  <body>
