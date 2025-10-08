<?php
// File: admin/serial_lookup.php
// Admin-only serial number lookup system for responsibility tracking
// Shows complete bag details including responsible officers

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';

// Check authentication and admin access only
$authController = new AuthController();
if (!$authController->isLoggedIn() || $_SESSION['user_role'] !== 'Administrator') {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

$pageTitle = 'Serial Number Lookup';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="bi bi-search"></i> Serial Number Lookup</h2>
                    <p class="text-muted">Administrator-only system for tracking production responsibility</p>
                </div>
            </div>

            <!-- Search Form -->
            <div class="row mb-4">
                <div class="col-md-8 mx-auto">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-upc-scan"></i> Enter Serial Number or QR Code</h5>
                            <form id="serialLookupForm">
                                <div class="input-group mb-3">
                                    <span class="input-group-text"><i class="bi bi-qr-code-scan"></i></span>
                                    <input type="text" class="form-control form-control-lg" id="serialNumber"
                                           placeholder="Enter serial number (e.g., JM-PB202509260016-001)"
                                           autocomplete="off" required>
                                    <button class="btn btn-primary btn-lg" type="submit">
                                        <i class="bi bi-search"></i> Lookup
                                    </button>
                                </div>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i>
                                    Enter the complete serial number from the product bag or QR code
                                </small>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Section -->
            <div id="resultsSection" style="display: none;">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-check-circle"></i> Bag Details Found</h5>
                            </div>
                            <div class="card-body" id="bagDetails">
                                <!-- Results will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error Section -->
            <div id="errorSection" style="display: none;">
                <div class="row">
                    <div class="col-md-8 mx-auto">
                        <div class="alert alert-danger" role="alert">
                            <h6><i class="bi bi-exclamation-triangle"></i> Serial Number Not Found</h6>
                            <p id="errorMessage">The serial number you entered was not found in our system.</p>
                            <small>Please check the serial number and try again. Contact IT support if the issue persists.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading Section -->
            <div id="loadingSection" style="display: none;">
                <div class="row">
                    <div class="col-md-6 mx-auto text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Searching for serial number...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('serialLookupForm');
    const serialInput = document.getElementById('serialNumber');
    const resultsSection = document.getElementById('resultsSection');
    const errorSection = document.getElementById('errorSection');
    const loadingSection = document.getElementById('loadingSection');
    const bagDetails = document.getElementById('bagDetails');
    const errorMessage = document.getElementById('errorMessage');

    // Auto-focus on serial number input
    serialInput.focus();

    // Handle form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const serialNumber = serialInput.value.trim();
        if (!serialNumber) {
            return;
        }

        // Show loading, hide others
        loadingSection.style.display = 'block';
        resultsSection.style.display = 'none';
        errorSection.style.display = 'none';

        // Perform lookup
        fetch('<?php echo BASE_URL; ?>/admin/ajax/lookup_serial.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                serial_number: serialNumber
            })
        })
        .then(response => response.json())
        .then(data => {
            loadingSection.style.display = 'none';

            if (data.success) {
                bagDetails.innerHTML = data.html;
                resultsSection.style.display = 'block';
                errorSection.style.display = 'none';
            } else {
                errorMessage.textContent = data.message || 'Serial number not found.';
                errorSection.style.display = 'block';
                resultsSection.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            loadingSection.style.display = 'none';
            errorMessage.textContent = 'System error occurred. Please try again.';
            errorSection.style.display = 'block';
            resultsSection.style.display = 'none';
        });
    });

    // Clear results when input changes
    serialInput.addEventListener('input', function() {
        resultsSection.style.display = 'none';
        errorSection.style.display = 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>