    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> Admin Panel. All rights reserved.</p>
                <p>Logged in as: <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></p>
            </div>
        </div>
    </footer>

    <script src="<?php echo SITE_URL; ?>js/main.js"></script>
    <script>
    // If the page was restored from bfcache (back/forward cache), force navigation
    // to the admin login so stale admin UI isn't shown to a non-admin user.
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.href = '<?php echo SITE_URL; ?>admin/login.php';
        }
    });
    </script>
</body>
</html>