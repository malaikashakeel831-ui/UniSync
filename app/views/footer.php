  </main>
  <footer class="app-footer">
    UniSync &middot; Smart University Life Organizer &middot; <?= date('Y') ?>
  </footer>
</div>
<?php if (!empty($useCharts)): ?>
<script src="<?= e(asset('vendor/chartjs/chart.umd.min.js')) ?>"></script>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if (!empty($pageScript)): ?>
<script><?= $pageScript ?></script>
<?php endif; ?>
</body>
</html>
