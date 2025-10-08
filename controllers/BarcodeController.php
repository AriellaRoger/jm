<?php
// File: controllers/BarcodeController.php
// Barcode and SKU management controller for JM Animal Feeds ERP System
// Handles SKU generation, barcode creation, and product identification

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeController {
    private $db;
    private $barcodeGeneratorPNG;
    private $barcodeGeneratorSVG;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->barcodeGeneratorPNG = new BarcodeGeneratorPNG();
        $this->barcodeGeneratorSVG = new BarcodeGeneratorSVG();
    }

    // Generate SKU for different product types
    public function generateSKU($productType, $category = '', $brand = '') {
        try {
            $prefix = $this->getSKUPrefix($productType);
            $categoryCode = $this->getCategoryCode($productType, $category, $brand);
            $sequence = $this->getNextSequence($productType);
            $checkDigit = $this->calculateCheckDigit($prefix, $categoryCode, $sequence);

            return sprintf('%s-%s-%06d-%s', $prefix, $categoryCode, $sequence, $checkDigit);
        } catch (Exception $e) {
            error_log("Error generating SKU: " . $e->getMessage());
            throw $e;
        }
    }

    // Get SKU prefix based on product type
    private function getSKUPrefix($productType) {
        $prefixes = [
            'finished_product' => 'FP',
            'raw_material' => 'RM',
            'third_party_product' => 'TP',
            'packaging_material' => 'PM'
        ];

        return $prefixes[$productType] ?? 'XX';
    }

    // Get category code for SKU
    private function getCategoryCode($productType, $category = '', $brand = '') {
        switch ($productType) {
            case 'finished_product':
                return $this->getFinishedProductCategory($category);
            case 'raw_material':
                return $this->getRawMaterialCategory($category);
            case 'third_party_product':
                return $this->getThirdPartyCategory($brand);
            case 'packaging_material':
                return $this->getPackagingCategory($category);
            default:
                return 'GEN';
        }
    }

    // Category codes for finished products
    private function getFinishedProductCategory($category) {
        $categories = [
            'Dairy Cow' => 'COW',
            'Poultry' => 'PLT',
            'Pig' => 'PIG',
            'Fish' => 'FSH',
            'Goat' => 'GOT',
            'Sheep' => 'SHP',
            'Rabbit' => 'RBT'
        ];

        foreach ($categories as $key => $code) {
            if (stripos($category, $key) !== false) {
                return $code;
            }
        }

        return 'GEN'; // Generic
    }

    // Category codes for raw materials
    private function getRawMaterialCategory($category) {
        $categories = [
            'Grain' => 'GRN',
            'Protein' => 'PRO',
            'Vitamin' => 'VIT',
            'Mineral' => 'MIN',
            'Oil' => 'OIL',
            'Salt' => 'SAL',
            'Additive' => 'ADD'
        ];

        foreach ($categories as $key => $code) {
            if (stripos($category, $key) !== false) {
                return $code;
            }
        }

        return 'RAW'; // Raw material generic
    }

    // Category codes for third party products
    private function getThirdPartyCategory($brand) {
        $brands = [
            'VetCare' => 'VET',
            'NutriBlock' => 'NUT',
            'EggStrong' => 'EGG',
            'SwineBoost' => 'SWN',
            'AquaVit' => 'AQU'
        ];

        foreach ($brands as $key => $code) {
            if (stripos($brand, $key) !== false) {
                return $code;
            }
        }

        return 'EXT'; // External product
    }

    // Category codes for packaging materials
    private function getPackagingCategory($category) {
        $categories = [
            'Bag' => 'BAG',
            'Tape' => 'TAP',
            'Label' => 'LBL',
            'Sticker' => 'STK',
            'Liner' => 'LNR'
        ];

        foreach ($categories as $key => $code) {
            if (stripos($category, $key) !== false) {
                return $code;
            }
        }

        return 'PKG'; // Packaging generic
    }

    // Get next sequence number for product type
    private function getNextSequence($productType) {
        $table = $this->getTableName($productType);
        $stmt = $this->db->prepare("SELECT COUNT(*) + 1 as next_seq FROM $table");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['next_seq'];
    }

    // Get table name for product type
    private function getTableName($productType) {
        $tables = [
            'finished_product' => 'products',
            'raw_material' => 'raw_materials',
            'third_party_product' => 'third_party_products',
            'packaging_material' => 'packaging_materials'
        ];

        return $tables[$productType] ?? 'products';
    }

    // Calculate check digit using simple algorithm
    private function calculateCheckDigit($prefix, $category, $sequence) {
        $combined = $prefix . $category . str_pad($sequence, 6, '0', STR_PAD_LEFT);
        $sum = 0;

        for ($i = 0; $i < strlen($combined); $i++) {
            $char = $combined[$i];
            if (is_numeric($char)) {
                $sum += intval($char);
            } else {
                $sum += ord($char);
            }
        }

        $checkDigits = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return $checkDigits[$sum % 26];
    }

    // Generate barcode image (PNG format)
    public function generateBarcodePNG($sku, $width = 2, $height = 30) {
        try {
            return $this->barcodeGeneratorPNG->getBarcode($sku, $this->barcodeGeneratorPNG::TYPE_CODE_128, $width, $height);
        } catch (Exception $e) {
            error_log("Error generating PNG barcode: " . $e->getMessage());
            throw $e;
        }
    }

    // Generate barcode image (SVG format)
    public function generateBarcodeSVG($sku, $width = 2, $height = 30, $color = 'black') {
        try {
            return $this->barcodeGeneratorSVG->getBarcode($sku, $this->barcodeGeneratorSVG::TYPE_CODE_128, $width, $height, $color);
        } catch (Exception $e) {
            error_log("Error generating SVG barcode: " . $e->getMessage());
            throw $e;
        }
    }

    // Save barcode image to file system
    public function saveBarcodeImage($sku, $format = 'PNG') {
        try {
            $directory = __DIR__ . '/../assets/barcodes/';
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = $sku . '.' . strtolower($format);
            $filepath = $directory . $filename;

            if ($format === 'PNG') {
                $barcodeData = $this->generateBarcodePNG($sku);
                file_put_contents($filepath, $barcodeData);
            } else if ($format === 'SVG') {
                $barcodeData = $this->generateBarcodeSVG($sku);
                file_put_contents($filepath, $barcodeData);
            }

            return $filepath;
        } catch (Exception $e) {
            error_log("Error saving barcode image: " . $e->getMessage());
            throw $e;
        }
    }

    // Generate and assign SKU to existing product
    public function assignSKUToProduct($productType, $productId, $category = '', $brand = '') {
        try {
            $table = $this->getTableName($productType);
            $sku = $this->generateSKU($productType, $category, $brand);

            $stmt = $this->db->prepare("UPDATE $table SET sku = ? WHERE id = ?");
            $result = $stmt->execute([$sku, $productId]);

            if ($result) {
                // Generate and save barcode
                $this->saveBarcodeImage($sku, 'PNG');
                $this->saveBarcodeImage($sku, 'SVG');
                return $sku;
            }

            return false;
        } catch (Exception $e) {
            error_log("Error assigning SKU to product: " . $e->getMessage());
            throw $e;
        }
    }

    // Bulk generate SKUs for all existing products
    public function generateSKUsForAllProducts() {
        try {
            $results = [
                'finished_products' => 0,
                'raw_materials' => 0,
                'third_party_products' => 0,
                'packaging_materials' => 0,
                'errors' => []
            ];

            // Generate SKUs for finished products
            $stmt = $this->db->query("SELECT id, name FROM products WHERE sku IS NULL OR sku = ''");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($products as $product) {
                try {
                    $sku = $this->assignSKUToProduct('finished_product', $product['id'], $product['name']);
                    if ($sku) $results['finished_products']++;
                } catch (Exception $e) {
                    $results['errors'][] = "Finished Product {$product['id']}: " . $e->getMessage();
                }
            }

            // Generate SKUs for raw materials
            $stmt = $this->db->query("SELECT id, name FROM raw_materials WHERE sku IS NULL OR sku = ''");
            $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($materials as $material) {
                try {
                    $sku = $this->assignSKUToProduct('raw_material', $material['id'], $material['name']);
                    if ($sku) $results['raw_materials']++;
                } catch (Exception $e) {
                    $results['errors'][] = "Raw Material {$material['id']}: " . $e->getMessage();
                }
            }

            // Generate SKUs for third party products
            $stmt = $this->db->query("SELECT id, brand FROM third_party_products WHERE sku IS NULL OR sku = ''");
            $thirdPartyProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($thirdPartyProducts as $product) {
                try {
                    $sku = $this->assignSKUToProduct('third_party_product', $product['id'], '', $product['brand']);
                    if ($sku) $results['third_party_products']++;
                } catch (Exception $e) {
                    $results['errors'][] = "Third Party Product {$product['id']}: " . $e->getMessage();
                }
            }

            // Generate SKUs for packaging materials
            $stmt = $this->db->query("SELECT id, name FROM packaging_materials WHERE sku IS NULL OR sku = ''");
            $packagingMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($packagingMaterials as $material) {
                try {
                    $sku = $this->assignSKUToProduct('packaging_material', $material['id'], $material['name']);
                    if ($sku) $results['packaging_materials']++;
                } catch (Exception $e) {
                    $results['errors'][] = "Packaging Material {$material['id']}: " . $e->getMessage();
                }
            }

            return $results;
        } catch (Exception $e) {
            error_log("Error in bulk SKU generation: " . $e->getMessage());
            throw $e;
        }
    }

    // Get product by SKU
    public function getProductBySKU($sku) {
        try {
            $tables = ['products', 'raw_materials', 'third_party_products', 'packaging_materials'];

            foreach ($tables as $table) {
                $stmt = $this->db->prepare("SELECT *, '$table' as product_type FROM $table WHERE sku = ?");
                $stmt->execute([$sku]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result) {
                    return $result;
                }
            }

            return null;
        } catch (Exception $e) {
            error_log("Error getting product by SKU: " . $e->getMessage());
            return null;
        }
    }

    // Log activity
    private function logActivity($action, $details, $user_id) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, action, module, details, ip_address, created_at)
                VALUES (?, ?, 'BARCODE', ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $action,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'localhost'
            ]);
        } catch (PDOException $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
}
?>