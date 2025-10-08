<?php
// File: login.php
// Login page for JM Animal Feeds ERP System
// Handles user authentication with responsive design

require_once 'config/database.php';
require_once 'controllers/AuthController.php';

$auth = new AuthController();
$error = '';
$success = '';

// Redirect if already logged in
if ($auth->isAuthenticated()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $result = $auth->login($username, $password);

        if ($result['success']) {
            $success = $result['message'];
            // Redirect to dashboard after successful login
            header('refresh:2;url=' . BASE_URL . '/dashboard.php');
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = 'Login';
include 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-6 col-lg-4">
            <!-- Logo and Welcome Section -->
            <div class="text-center mb-4">
                <div class="mb-3">
                    <i class="bi bi-grain text-success" style="font-size: 4rem;"></i>
                </div>
                <h2 class="text-success fw-bold">JM Animal Feeds</h2>
                <p class="text-muted">ERP System Login</p>
            </div>

            <!-- Login Form Card -->
            <div class="card shadow-lg border-0">
                <div class="card-body p-4">
                    <h4 class="card-title text-center mb-4">Welcome Back</h4>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                            <div class="mt-2">
                                <div class="spinner-border spinner-border-sm me-2" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                Redirecting to dashboard...
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="loginForm">
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <i class="bi bi-person"></i> Username or Email
                            </label>
                            <input type="text"
                                   class="form-control form-control-lg"
                                   id="username"
                                   name="username"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                   placeholder="Enter your username or email"
                                   required
                                   autocomplete="username">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="bi bi-lock"></i> Password
                            </label>
                            <div class="input-group">
                                <input type="password"
                                       class="form-control form-control-lg"
                                       id="password"
                                       name="password"
                                       placeholder="Enter your password"
                                       required
                                       autocomplete="current-password">
                                <button class="btn btn-outline-secondary"
                                        type="button"
                                        id="togglePassword">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                            <label class="form-check-label" for="rememberMe">
                                Remember me
                            </label>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg w-100 mb-3">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </button>
                    </form>

                    <!-- Demo Credentials Section -->
                    
                </div>
            </div>

            <!-- Footer Info -->
            <div class="text-center mt-4">
                <small class="text-muted">
                    JM Animal Feeds ERP System v1.0<br>
                    For support, contact your system administrator
                </small>
            </div>
        </div>
    </div>
</div>

<script>
    // Password toggle functionality
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordField = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleIcon.className = 'bi bi-eye-slash';
        } else {
            passwordField.type = 'password';
            toggleIcon.className = 'bi bi-eye';
        }
    });

    // Demo login buttons
    document.addEventListener('DOMContentLoaded', function() {
        const demoButtons = document.querySelectorAll('.demo-login');

        demoButtons.forEach(button => {
            button.addEventListener('click', function() {
                const username = this.dataset.username;
                const password = this.dataset.password;

                document.getElementById('username').value = username;
                document.getElementById('password').value = password;

                // Optional: Auto-submit form
                // document.getElementById('loginForm').submit();
            });
        });
    });

    // Form validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;

        if (!username || !password) {
            e.preventDefault();
            alert('Please enter both username and password');
        }
    });
</script>

<?php include 'includes/footer.php'; ?>