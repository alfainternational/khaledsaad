            </main>

            <!-- Footer -->
            <footer class="admin-footer">
                <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. جميع الحقوق محفوظة.</p>
            </footer>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notifications"></div>

    <!-- Scripts -->
    <script src="<?= asset('js/admin.js') ?>"></script>
    <?php if (isset($pageScripts)): ?>
    <?= $pageScripts ?>
    <?php endif; ?>
</body>
</html>
