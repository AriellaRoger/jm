<?php
// File: controllers/CompanySettingsController.php
// Company settings management controller
// Manages company information, contacts, and system settings

require_once 'ActivityLogger.php';

class CompanySettingsController {
    private $pdo;
    private $logger;

    public function __construct() {
        $this->pdo = getDbConnection();
        $this->logger = new ActivityLogger();
    }

    /**
     * Initialize default company settings
     */
    public function initializeSettings() {
        $defaultSettings = [
            // Company Information
            ['setting_key' => 'company_name', 'setting_value' => 'JM Animal Feeds Ltd.', 'category' => 'company', 'setting_type' => 'TEXT', 'description' => 'Company name'],
            ['setting_key' => 'company_tagline', 'setting_value' => 'Quality Feed for Healthy Animals', 'category' => 'company', 'setting_type' => 'TEXT', 'description' => 'Company tagline'],
            ['setting_key' => 'company_address', 'setting_value' => 'Kariakoo Street, Plot 123\nDar es Salaam, Tanzania', 'category' => 'company', 'setting_type' => 'TEXT', 'description' => 'Company physical address'],
            ['setting_key' => 'company_phone', 'setting_value' => '+255 123 456 789', 'category' => 'company', 'setting_type' => 'TEXT', 'description' => 'Primary phone number'],
            ['setting_key' => 'company_email', 'setting_value' => 'info@jmanimalfeeds.co.tz', 'category' => 'company', 'setting_type' => 'EMAIL', 'description' => 'Primary email address'],
            ['setting_key' => 'company_website', 'setting_value' => 'https://www.jmanimalfeeds.co.tz', 'category' => 'company', 'setting_type' => 'URL', 'description' => 'Company website'],
            ['setting_key' => 'company_logo', 'setting_value' => '', 'category' => 'company', 'setting_type' => 'TEXT', 'description' => 'Company logo URL'],

            // Business Information
            ['setting_key' => 'business_registration', 'setting_value' => 'TZ-12345678', 'category' => 'business', 'setting_type' => 'TEXT', 'description' => 'Business registration number'],
            ['setting_key' => 'tax_id', 'setting_value' => 'TIN-123-456-789', 'category' => 'business', 'setting_type' => 'TEXT', 'description' => 'Tax identification number'],
            ['setting_key' => 'vat_number', 'setting_value' => 'VAT-40-123456', 'category' => 'business', 'setting_type' => 'TEXT', 'description' => 'VAT registration number'],

            // System Settings
            ['setting_key' => 'default_currency', 'setting_value' => 'TZS', 'category' => 'system', 'setting_type' => 'TEXT', 'description' => 'Default currency'],
            ['setting_key' => 'timezone', 'setting_value' => 'Africa/Dar_es_Salaam', 'category' => 'system', 'setting_type' => 'TEXT', 'description' => 'System timezone'],
            ['setting_key' => 'date_format', 'setting_value' => 'Y-m-d', 'category' => 'system', 'setting_type' => 'TEXT', 'description' => 'Date display format'],
            ['setting_key' => 'low_stock_threshold', 'setting_value' => '10', 'category' => 'system', 'setting_type' => 'NUMBER', 'description' => 'Default low stock alert threshold'],

            // Notification Settings
            ['setting_key' => 'enable_email_notifications', 'setting_value' => 'true', 'category' => 'notifications', 'setting_type' => 'BOOLEAN', 'description' => 'Enable email notifications'],
            ['setting_key' => 'notification_email', 'setting_value' => 'notifications@jmanimalfeeds.co.tz', 'category' => 'notifications', 'setting_type' => 'EMAIL', 'description' => 'System notification email'],

            // Support Information
            ['setting_key' => 'support_email', 'setting_value' => 'support@jmanimalfeeds.co.tz', 'category' => 'support', 'setting_type' => 'EMAIL', 'description' => 'Technical support email'],
            ['setting_key' => 'support_phone', 'setting_value' => '+255 987 654 321', 'category' => 'support', 'setting_type' => 'TEXT', 'description' => 'Support phone number'],
            ['setting_key' => 'emergency_phone', 'setting_value' => '+255 987 654 321', 'category' => 'support', 'setting_type' => 'TEXT', 'description' => '24/7 emergency hotline']
        ];

        try {
            foreach ($defaultSettings as $setting) {
                $sql = "INSERT INTO company_settings (setting_key, setting_value, category, setting_type, description, updated_by, is_public)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        description = VALUES(description)";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $setting['setting_key'],
                    $setting['setting_value'],
                    $setting['category'],
                    $setting['setting_type'],
                    $setting['description'],
                    $_SESSION['user_id'] ?? 1,
                    in_array($setting['setting_key'], ['company_name', 'company_tagline', 'company_address', 'company_phone', 'company_email'])
                ]);
            }

            $this->logger->log('SETTINGS', 'SETTINGS_INITIALIZED', 'Company settings initialized with default values', ['severity' => 'LOW']);
            return true;
        } catch (Exception $e) {
            error_log("Failed to initialize settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all settings grouped by category
     */
    public function getAllSettings() {
        try {
            $sql = "SELECT * FROM company_settings ORDER BY category, setting_key";
            $stmt = $this->pdo->query($sql);
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Group by category
            $grouped = [];
            foreach ($settings as $setting) {
                $grouped[$setting['category']][] = $setting;
            }

            return $grouped;
        } catch (Exception $e) {
            error_log("Failed to fetch settings: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get specific setting value
     */
    public function getSetting($key, $default = null) {
        try {
            $sql = "SELECT setting_value FROM company_settings WHERE setting_key = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$key]);
            $result = $stmt->fetchColumn();
            return $result !== false ? $result : $default;
        } catch (Exception $e) {
            error_log("Failed to get setting: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Update setting value
     */
    public function updateSetting($key, $value) {
        try {
            $sql = "UPDATE company_settings SET setting_value = ?, updated_by = ?, updated_at = NOW()
                    WHERE setting_key = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([$value, $_SESSION['user_id'] ?? 1, $key]);

            if ($result) {
                $this->logger->log('SETTINGS', 'SETTING_UPDATED', "Setting '{$key}' updated", [
                    'entity_type' => 'setting',
                    'metadata' => ['setting_key' => $key, 'new_value' => $value],
                    'severity' => 'MEDIUM'
                ]);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Failed to update setting: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update multiple settings at once
     */
    public function updateMultipleSettings($settings) {
        try {
            $this->pdo->beginTransaction();
            $updatedCount = 0;

            foreach ($settings as $key => $value) {
                if ($this->updateSetting($key, $value)) {
                    $updatedCount++;
                }
            }

            $this->pdo->commit();

            $this->logger->log('SETTINGS', 'BULK_SETTINGS_UPDATE', "Updated {$updatedCount} settings", [
                'metadata' => ['updated_count' => $updatedCount],
                'severity' => 'MEDIUM'
            ]);

            return $updatedCount;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Failed to update multiple settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get public settings (for display without authentication)
     */
    public function getPublicSettings() {
        try {
            $sql = "SELECT setting_key, setting_value FROM company_settings WHERE is_public = 1";
            $stmt = $this->pdo->query($sql);
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            return $settings;
        } catch (Exception $e) {
            error_log("Failed to fetch public settings: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Backup settings to JSON
     */
    public function exportSettings() {
        try {
            $settings = $this->getAllSettings();
            $backup = [
                'export_date' => date('Y-m-d H:i:s'),
                'exported_by' => $_SESSION['user_name'] ?? 'System',
                'settings' => $settings
            ];

            $this->logger->log('SETTINGS', 'SETTINGS_EXPORTED', 'Company settings exported for backup', ['severity' => 'MEDIUM']);

            return json_encode($backup, JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            error_log("Failed to export settings: " . $e->getMessage());
            return false;
        }
    }
}
?>