<?php
declare(strict_types=1);
?>
</main> <!-- /.content-area -->

<?php if (is_logged_in()): ?>
<footer class="footer">
    <p>Warehouse Management System &bull; Version 1.0 &bull; Running on PHP <?= PHP_VERSION ?></p>
</footer>
<?php endif; ?>

</div> <!-- /.main-wrapper -->
</div> <!-- /.app-container -->

<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
