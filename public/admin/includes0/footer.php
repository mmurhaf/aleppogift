        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (isset($additional_js)) echo $additional_js; ?>
    <script>
        (function () {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.querySelector('[data-sidebar-overlay]');
            const toggleButton = document.querySelector('[data-sidebar-toggle]');

            if (!sidebar || !overlay || !toggleButton) {
                return;
            }

            function setSidebarState(isOpen) {
                sidebar.classList.toggle('active', isOpen);
                overlay.classList.toggle('active', isOpen);
                document.body.classList.toggle('sidebar-open', isOpen);
                toggleButton.classList.toggle('is-active', isOpen);
                toggleButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }

            toggleButton.setAttribute('aria-expanded', 'false');

            toggleButton.addEventListener('click', function () {
                if (window.innerWidth > 991) {
                    return;
                }
                setSidebarState(!sidebar.classList.contains('active'));
            });

            overlay.addEventListener('click', function () {
                setSidebarState(false);
            });

            document.querySelectorAll('.sidebar-menu a').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (window.innerWidth <= 991) {
                        setSidebarState(false);
                    }
                });
            });

            window.addEventListener('resize', function () {
                if (window.innerWidth > 991) {
                    setSidebarState(false);
                }
            });
        }());
    </script>
</body>
</html>