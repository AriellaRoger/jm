<?php
// Minimal test to isolate the issue
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_clean();
header('Content-Type: application/json');

$steps = [];
$steps[] = "Step 1: Basic PHP working";

try {
    $steps[] = "Step 2: Session already started";

    $steps[] = "Step 3: Loading AuthController";
    require_once __DIR__ . '/../../controllers/AuthController.php';
    $steps[] = "Step 4: AuthController loaded";

    $steps[] = "Step 5: Creating AuthController instance";
    $authController = new AuthController();
    $steps[] = "Step 6: AuthController created";

    $steps[] = "Step 7: Testing isLoggedIn";
    $isLoggedIn = $authController->isLoggedIn();
    $steps[] = "Step 8: isLoggedIn result: " . ($isLoggedIn ? 'true' : 'false');

    $steps[] = "Step 9: Loading ExpenseController";
    require_once __DIR__ . '/../../controllers/ExpenseController.php';
    $steps[] = "Step 10: ExpenseController loaded";

    $steps[] = "Step 11: Creating ExpenseController instance";
    $expenseController = new ExpenseController();
    $steps[] = "Step 12: ExpenseController created";

    $steps[] = "Step 13: All tests passed!";

    echo json_encode(['success' => true, 'steps' => $steps]);

} catch (Throwable $e) {
    $steps[] = "ERROR: " . $e->getMessage();
    $steps[] = "File: " . $e->getFile();
    $steps[] = "Line: " . $e->getLine();
    echo json_encode(['success' => false, 'steps' => $steps, 'trace' => $e->getTraceAsString()]);
}
?>