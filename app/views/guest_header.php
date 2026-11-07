<?php $pageTitle = $pageTitle ?? 'UniSync'; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · UniSync</title>
  <link rel="icon" href="<?= e(asset('img/logo.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset('vendor/fonts/fonts.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <script>try { var t = localStorage.getItem('unisync-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}</script>
</head>
<body class="guest">
