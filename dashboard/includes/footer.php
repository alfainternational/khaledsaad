            </main>

            <!-- Footer -->
            <footer class="dashboard-footer">
                <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. جميع الحقوق محفوظة.</p>
            </footer>
        </div>
    </div>

    <!-- Scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar Toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('dashboardSidebar');
        const sidebarClose = document.getElementById('sidebarClose');

        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.add('active');
            });
        }

        if (sidebarClose && sidebar) {
            sidebarClose.addEventListener('click', () => {
                sidebar.classList.remove('active');
            });
        }

        // Close sidebar on outside click
        document.addEventListener('click', (e) => {
            if (sidebar && sidebar.classList.contains('active') &&
                !sidebar.contains(e.target) &&
                !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                const isDark = document.body.classList.contains('dark-mode');
                document.cookie = `darkMode=${isDark};path=/;max-age=31536000`;
                themeToggle.querySelector('i').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            });

            // Update icon based on current mode
            if (document.body.classList.contains('dark-mode')) {
                themeToggle.querySelector('i').className = 'fas fa-sun';
            }
        }
    });
    </script>
    <?php if (isset($pageScripts)): ?>
    <?= $pageScripts ?>
    <?php endif; ?>
</body>
</html>
