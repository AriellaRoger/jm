<?php
// File: admin/settings.php
// Company settings management page for administrators
// Allows editing company information, contacts, and system settings

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/CompanySettingsController.php';

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Only administrators can access this page
if ($_SESSION['user_role'] !== 'Administrator') {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$settingsController = new CompanySettingsController();

// Initialize settings if they don't exist
$settingsController->initializeSettings();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updatedCount = $settingsController->updateMultipleSettings($_POST);
    if ($updatedCount !== false) {
        $successMessage = "Successfully updated {$updatedCount} settings.";
    } else {
        $errorMessage = "Failed to update settings. Please try again.";
    }
}

// Get all settings
$allSettings = $settingsController->getAllSettings();

$pageTitle = 'Company Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-4 pb-3 mb-4">
    <div class="animate-fade-in">
        <h1 class="dashboard-title mb-2">
            <i class="bi bi-gear-fill"></i> Company Settings
        </h1>
        <p class="text-muted mb-0">Manage company information, contacts, and system configuration.</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 animate-fade-in">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-primary" onclick="exportSettings()">
                <i class="bi bi-download"></i> Export Settings
            </button>
        </div>
        <button type="submit" form="settingsForm" class="btn btn-primary">
            <i class="bi bi-check2"></i> Save Changes
        </button>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if (isset($successMessage)): ?>
    <div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($errorMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form id="settingsForm" method="POST">
    <div class="row">
        <!-- Company Information -->
        <?php if (isset($allSettings['company'])): ?>
        <div class="col-lg-6 mb-4">
            <div class="card animate-fade-in">
                <div class="card-header" style="background: linear-gradient(45deg, #059669, #047857); color: white;">
                    <h5 class="mb-0">
                        <i class="bi bi-building-fill"></i> Company Information
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($allSettings['company'] as $setting): ?>
                        <div class="mb-3">
                            <label for="<?php echo $setting['setting_key']; ?>" class="form-label fw-bold">
                                <?php echo ucwords(str_replace('_', ' ', str_replace('company_', '', $setting['setting_key']))); ?>
                            </label>
                            <?php if ($setting['setting_key'] === 'company_address'): ?>
                                <textarea name="<?php echo $setting['setting_key']; ?>"
                                          id="<?php echo $setting['setting_key']; ?>"
                                          class="form-control"
                                          rows="3"
                                          placeholder="<?php echo htmlspecialchars($setting['description']); ?>"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                            <?php else: ?>
                                <input type="<?php echo $setting['setting_type'] === 'EMAIL' ? 'email' : ($setting['setting_type'] === 'URL' ? 'url' : 'text'); ?>"
                                       name="<?php echo $setting['setting_key']; ?>"
                                       id="<?php echo $setting['setting_key']; ?>"
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                       placeholder="<?php echo htmlspecialchars($setting['description']); ?>">
                            <?php endif; ?>
                            <div class="form-text"><?php echo htmlspecialchars($setting['description']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Business Information -->
        <?php if (isset($allSettings['business'])): ?>
        <div class="col-lg-6 mb-4">
            <div class="card animate-fade-in" style="animation-delay: 0.1s;">
                <div class="card-header" style="background: linear-gradient(45deg, #2563eb, #1d4ed8); color: white;">
                    <h5 class="mb-0">
                        <i class="bi bi-file-earmark-text-fill"></i> Business Registration
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($allSettings['business'] as $setting): ?>
                        <div class="mb-3">
                            <label for="<?php echo $setting['setting_key']; ?>" class="form-label fw-bold">
                                <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                            </label>
                            <input type="text"
                                   name="<?php echo $setting['setting_key']; ?>"
                                   id="<?php echo $setting['setting_key']; ?>"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                   placeholder="<?php echo htmlspecialchars($setting['description']); ?>">
                            <div class="form-text"><?php echo htmlspecialchars($setting['description']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- System Settings -->
        <?php if (isset($allSettings['system'])): ?>
        <div class="col-lg-6 mb-4">
            <div class="card animate-fade-in" style="animation-delay: 0.2s;">
                <div class="card-header" style="background: linear-gradient(45deg, #7c3aed, #6d28d9); color: white;">
                    <h5 class="mb-0">
                        <i class="bi bi-sliders"></i> System Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($allSettings['system'] as $setting): ?>
                        <div class="mb-3">
                            <label for="<?php echo $setting['setting_key']; ?>" class="form-label fw-bold">
                                <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                            </label>
                            <?php if ($setting['setting_key'] === 'default_currency'): ?>
                                <select name="<?php echo $setting['setting_key']; ?>"
                                        id="<?php echo $setting['setting_key']; ?>"
                                        class="form-select">
                                    <option value="TZS" <?php echo $setting['setting_value'] === 'TZS' ? 'selected' : ''; ?>>Tanzanian Shilling (TZS)</option>
                                    <option value="USD" <?php echo $setting['setting_value'] === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                    <option value="EUR" <?php echo $setting['setting_value'] === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                </select>
                            <?php elseif ($setting['setting_key'] === 'timezone'): ?>
                                <select name="<?php echo $setting['setting_key']; ?>"
                                        id="<?php echo $setting['setting_key']; ?>"
                                        class="form-select">
                                    <option value="Africa/Dar_es_Salaam" <?php echo $setting['setting_value'] === 'Africa/Dar_es_Salaam' ? 'selected' : ''; ?>>East Africa Time</option>
                                    <option value="UTC" <?php echo $setting['setting_value'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                </select>
                            <?php else: ?>
                                <input type="<?php echo $setting['setting_type'] === 'NUMBER' ? 'number' : 'text'; ?>"
                                       name="<?php echo $setting['setting_key']; ?>"
                                       id="<?php echo $setting['setting_key']; ?>"
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                       placeholder="<?php echo htmlspecialchars($setting['description']); ?>">
                            <?php endif; ?>
                            <div class="form-text"><?php echo htmlspecialchars($setting['description']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Support Information -->
        <?php if (isset($allSettings['support'])): ?>
        <div class="col-lg-6 mb-4">
            <div class="card animate-fade-in" style="animation-delay: 0.3s;">
                <div class="card-header" style="background: linear-gradient(45deg, #dc2626, #b91c1c); color: white;">
                    <h5 class="mb-0">
                        <i class="bi bi-headset"></i> Support & Emergency
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($allSettings['support'] as $setting): ?>
                        <div class="mb-3">
                            <label for="<?php echo $setting['setting_key']; ?>" class="form-label fw-bold">
                                <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                            </label>
                            <input type="<?php echo $setting['setting_type'] === 'EMAIL' ? 'email' : 'text'; ?>"
                                   name="<?php echo $setting['setting_key']; ?>"
                                   id="<?php echo $setting['setting_key']; ?>"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                   placeholder="<?php echo htmlspecialchars($setting['description']); ?>">
                            <div class="form-text"><?php echo htmlspecialchars($setting['description']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Notification Settings -->
        <?php if (isset($allSettings['notifications'])): ?>
        <div class="col-lg-12 mb-4">
            <div class="card animate-fade-in" style="animation-delay: 0.4s;">
                <div class="card-header" style="background: linear-gradient(45deg, #d97706, #b45309); color: white;">
                    <h5 class="mb-0">
                        <i class="bi bi-bell-fill"></i> Notification Settings
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($allSettings['notifications'] as $setting): ?>
                            <div class="col-md-6 mb-3">
                                <label for="<?php echo $setting['setting_key']; ?>" class="form-label fw-bold">
                                    <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                                </label>
                                <?php if ($setting['setting_type'] === 'BOOLEAN'): ?>
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="<?php echo $setting['setting_key']; ?>" value="false">
                                        <input type="checkbox"
                                               name="<?php echo $setting['setting_key']; ?>"
                                               id="<?php echo $setting['setting_key']; ?>"
                                               class="form-check-input"
                                               value="true"
                                               <?php echo $setting['setting_value'] === 'true' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="<?php echo $setting['setting_key']; ?>">
                                            Enable email notifications
                                        </label>
                                    </div>
                                <?php else: ?>
                                    <input type="email"
                                           name="<?php echo $setting['setting_key']; ?>"
                                           id="<?php echo $setting['setting_key']; ?>"
                                           class="form-control"
                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                           placeholder="<?php echo htmlspecialchars($setting['description']); ?>">
                                <?php endif; ?>
                                <div class="form-text"><?php echo htmlspecialchars($setting['description']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</form>

<!-- Export Settings Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-download"></i> Export Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Export all company settings as a JSON backup file. This can be used for backup purposes or transferring settings.</p>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> The exported file will contain all settings including sensitive information. Store securely.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="downloadSettings()">
                    <i class="bi bi-download"></i> Download
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function exportSettings() {
    const modal = new bootstrap.Modal(document.getElementById('exportModal'));
    modal.show();
}

function downloadSettings() {
    fetch('<?php echo BASE_URL; ?>/admin/ajax/export_settings.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Create download link
                const blob = new Blob([data.settings], { type: 'application/json' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'company_settings_' + new Date().toISOString().slice(0,10) + '.json';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);

                bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
            } else {
                alert('Failed to export settings: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Export error:', error);
            alert('Failed to export settings');
        });
}

// Form validation
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    // Basic validation
    const requiredFields = ['company_name', 'company_email', 'company_phone'];
    let hasErrors = false;

    requiredFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (field && !field.value.trim()) {
            field.classList.add('is-invalid');
            hasErrors = true;
        } else if (field) {
            field.classList.remove('is-invalid');
        }
    });

    if (hasErrors) {
        e.preventDefault();
        alert('Please fill in all required fields');
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>