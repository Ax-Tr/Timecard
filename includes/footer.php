<?php
// Common Footer Template
?>
    </div> <!-- End Wrapper -->

    <!-- Toast Notification Container (Fixed position top-right) -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
        <div id="liveToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMessage">
                    <!-- Dynamic Message -->
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- App Configuration for Client-Side optimization -->
    <script>
        window.appConfig = {
            isLoggedIn: <?php echo is_logged_in() ? 'true' : 'false'; ?>,
            isAdmin: <?php echo is_admin() ? 'true' : 'false'; ?>
        };
    </script>
    <!-- Custom Main JS -->
    <script src="<?php echo APP_ROOT; ?>assets/js/main.js?v=5"></script>
</body>
</html>
