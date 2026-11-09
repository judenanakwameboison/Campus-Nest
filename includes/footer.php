<?php $area = $area ?? 'public'; ?>
<?php if ($area === 'admin'): ?>
  </main>
</div>
<?php else: ?>
</main>
<footer class="foot"><b><?= APP_NAME ?></b> &middot; Hostel &amp; Rent Management System</footer>
<?php endif; ?>
<button id="toTop" type="button" aria-label="Back to top">&uarr;</button>
<script src="<?= BASE ?>/assets/js/validation.js"></script>
<script src="<?= BASE ?>/assets/js/ui.js"></script>
<script src="<?= BASE ?>/assets/js/modern.js"></script>
<?php if (!empty($extraJs)): ?><script src="<?= BASE ?>/assets/js/<?= e($extraJs) ?>"></script><?php endif; ?>
</body>
</html>
