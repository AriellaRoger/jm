<?php
// File: logout.php
// Logout functionality for JM Animal Feeds ERP System
// Handles user logout and session cleanup

require_once 'config/database.php';
require_once 'controllers/AuthController.php';

$auth = new AuthController();

// Perform logout
$result = $auth->logout();

$pageTitle = 'Logout';
include 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-6 col-lg-4">
            <!-- Logout Confirmation Card -->
            <div class="card shadow-lg border-0">
                <div class="card-body p-4 text-center">
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                    </div>

                    <h4 class="card-title mb-3">Logged Out Successfully</h4>

                    <?php if ($result['success']): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($result['message']); ?>
                        </div>
                    <?php endif; ?>

                    <p class="text-muted mb-4">
                        Thank you for using JM Animal Feeds ERP System.<br>
                        Your session has been securely terminated.
                    </p>

                    <div class="d-grid gap-2">
                        <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-success btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Login Again
                        </a>

                        <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-house"></i> Go to Homepage
                        </a>
                    </div>

                    <!-- Security Note -->
                    <div class="mt-4 p-3 bg-light rounded">
                        <small class="text-muted">
                            <i class="bi bi-shield-check"></i>
                            <strong>Security Note:</strong><br>
                            For your security, please close your browser if you're using a shared computer.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Session Info -->
            <div class="text-center mt-4">
                <small class="text-muted">
                    Session ended at <?php echo date('Y-m-d H:i:s'); ?><br>
                    JM Animal Feeds ERP System
                </small>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-redirect to login after 10 seconds
    setTimeout(function() {
        window.location.href = '<?php echo BASE_URL; ?>/login.php';
    }, 10000);

    // Show countdown
    let countdown = 10;
    const countdownElement = document.createElement('div');
    countdownElement.className = 'text-center mt-3';
    countdownElement.innerHTML = '<small class="text-muted">Redirecting to login in <span id="countdown">10</span> seconds...</small>';

    document.querySelector('.card-body').appendChild(countdownElement);

    const countdownTimer = setInterval(function() {
        countdown--;
        document.getElementById('countdown').textContent = countdown;

        if (countdown <= 0) {
            clearInterval(countdownTimer);
        }
    }, 1000);

    // Clear any sensitive data from browser storage
    if (typeof(Storage) !== "undefined") {
        localStorage.clear();
        sessionStorage.clear();
    }
</script>

<?php include 'includes/footer.php'; ?>