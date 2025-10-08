<?php
// File: inventory/transfers.php
// HQ Transfer Management Interface - Supervisor/Administrator only
// Send products from HQ to branches with driver assignment and QR forms

session_start();
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/TransferController.php';

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];

// Only Administrators and Supervisors can manage transfers from HQ
if (!in_array($userRole, ['Administrator', 'Supervisor'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$transferController = new TransferController();
$availableBags = $transferController->getAvailableBags();
$availableBulkItems = $transferController->getAvailableBulkItems();
$availableDrivers = $transferController->getAvailableDrivers();

// Load branches from database (excluding HQ)
require_once __DIR__ . '/../config/database.php';
$conn = Database::getInstance()->getConnection();
$branchStmt = $conn->query("SELECT id, name, location FROM branches WHERE id != 1 AND status = 'ACTIVE' ORDER BY name");
$branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HQ Transfer Management - JM Animal Feeds</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid py-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-arrow-right-circle me-2"></i>HQ Transfer Management</h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="transferTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#createTransfer">
                            <i class="bi bi-plus-circle"></i> Create Transfer
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#pendingTransfers">
                            <i class="bi bi-clock"></i> Pending Transfers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#transferHistory">
                            <i class="bi bi-list"></i> Transfer History
                        </a>
                    </li>
                </ul>

                <div class="tab-content mt-3">
                    <!-- Create Transfer Tab -->
                    <div id="createTransfer" class="tab-pane fade show active">
                        <form id="transferForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Destination Branch</label>
                                        <select class="form-select" id="toBranch" required>
                                            <option value="">Select Branch</option>
                                            <?php foreach ($branches as $branch): ?>
                                            <option value="<?php echo $branch['id']; ?>">
                                                <?php echo htmlspecialchars($branch['name']); ?>
                                                <?php if ($branch['location']): ?> - <?php echo htmlspecialchars($branch['location']); ?><?php endif; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Driver</label>
                                        <select class="form-select" id="driverId" required>
                                            <option value="">Select Driver</option>
                                            <?php foreach ($availableDrivers as $driver): ?>
                                            <option value="<?php echo $driver['id']; ?>">
                                                <?php echo $driver['full_name']; ?>
                                                <?php if ($driver['phone']): ?> - <?php echo $driver['phone']; ?><?php endif; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Product Selection -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Select Products to Transfer</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="nav nav-pills nav-fill" id="productTabs">
                                        <li class="nav-item">
                                            <a class="nav-link active" data-bs-toggle="tab" href="#finishedProducts">
                                                Finished Products
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-bs-toggle="tab" href="#rawMaterials">
                                                Raw Materials
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-bs-toggle="tab" href="#thirdPartyProducts">
                                                Third Party Products
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-bs-toggle="tab" href="#packagingMaterials">
                                                Packaging Materials
                                            </a>
                                        </li>
                                    </ul>

                                    <div class="tab-content mt-3">
                                        <!-- Finished Products - Individual Bag Selection -->
                                        <div id="finishedProducts" class="tab-pane fade show active">
                                            <h6>Select Individual Bags by Serial Number</h6>
                                            <div class="mb-3">
                                                <label for="transferBagSearchInput" class="form-label mb-1">Search Bags by Serial</label>
                                                <div class="input-group input-group-sm" style="max-width: 360px;">
                                                    <input type="text" class="form-control" id="transferBagSearchInput" placeholder="Enter serial number">
                                                    <button type="button" class="btn btn-outline-primary" id="transferBagSearchButton">Search</button>
                                                    <button type="button" class="btn btn-outline-secondary" id="transferBagClearButton">Clear</button>
                                                </div>
                                                <div class="d-flex align-items-center gap-2 mt-2">
                                                    <button type="button" class="btn btn-outline-success btn-sm" id="transferBagScanStart">
                                                        <i class="bi bi-qr-code-scan"></i> Scan QR
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" id="transferBagScanStop" style="display: none;">
                                                        <i class="bi bi-stop-circle"></i> Stop Scan
                                                    </button>
                                                    <small class="text-muted">Tip: type the last digits or scan the QR code to select quickly.</small>
                                                </div>
                                                <div id="transferBagSuggestions" class="small text-muted border rounded mt-2" style="max-height: 140px; overflow-y: auto; display: none;"></div>
                                                <div id="transferBagScanContainer" class="mt-3" style="display: none;">
                                                    <div class="border rounded bg-light-subtle d-flex align-items-center justify-content-center mb-2" style="min-height: 40px;">
                                                        <span class="small text-muted">Align the bag QR code within the frame.</span>
                                                    </div>
                                                    <div id="transferBagScanView" class="border rounded mx-auto" style="width: 260px; height: 260px;"></div>
                                                </div>
                                                <div id="transferBagScanFeedback" class="small text-muted mt-2" style="display: none;"></div>
                                            </div>
                                            <div class="row" id="transferBagList">
                                                <?php foreach ($availableBags as $bag): ?>
                                                <div class="col-md-6 col-lg-4 mb-2 transfer-bag-option"
                                                     data-serial="<?php echo $bag['serial_number']; ?>"
                                                     data-product="<?php echo $bag['product_name']; ?> - <?php echo $bag['package_size']; ?>">
                                                    <div class="form-check">
                                                        <input class="form-check-input transfer-bag-checkbox" type="checkbox"
                                                               value="<?php echo $bag['id']; ?>"
                                                               id="bag_<?php echo $bag['id']; ?>"
                                                               data-serial="<?php echo $bag['serial_number']; ?>"
                                                               data-product="<?php echo $bag['product_name']; ?> - <?php echo $bag['package_size']; ?>">
                                                        <label class="form-check-label small" for="bag_<?php echo $bag['id']; ?>">
                                                            <strong><?php echo $bag['serial_number']; ?></strong><br>
                                                            <?php echo $bag['product_name']; ?> - <?php echo $bag['package_size']; ?><br>
                                                            <small class="text-muted">Prod: <?php echo $bag['production_date']; ?></small>
                                                        </label>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <!-- Raw Materials - Quantity Selection -->
                                        <div id="rawMaterials" class="tab-pane fade">
                                            <h6>Select Raw Materials with Quantities</h6>
                                            <?php foreach ($availableBulkItems['raw_materials'] as $item): ?>
                                            <div class="row mb-2 align-items-center">
                                                <div class="col-md-1">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                               value="<?php echo $item['id']; ?>"
                                                               id="raw_<?php echo $item['id']; ?>"
                                                               data-type="RAW_MATERIAL"
                                                               data-name="<?php echo $item['name']; ?>"
                                                               data-unit="<?php echo $item['unit_of_measure']; ?>"
                                                               data-stock="<?php echo $item['current_stock']; ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-5">
                                                    <label for="raw_<?php echo $item['id']; ?>" class="form-label mb-0">
                                                        <strong><?php echo $item['name']; ?></strong><br>
                                                        <small class="text-muted">Available: <?php echo number_format($item['current_stock']); ?> <?php echo $item['unit_of_measure']; ?></small>
                                                    </label>
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="number" class="form-control form-control-sm"
                                                           id="qty_raw_<?php echo $item['id']; ?>"
                                                           placeholder="Quantity" min="1" max="<?php echo $item['current_stock']; ?>" disabled>
                                                </div>
                                                <div class="col-md-3">
                                                    <span class="badge bg-secondary"><?php echo $item['unit_of_measure']; ?></span>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <!-- Third Party Products -->
                                        <div id="thirdPartyProducts" class="tab-pane fade">
                                            <h6>Select Third Party Products with Quantities</h6>
                                            <?php foreach ($availableBulkItems['third_party_products'] as $item): ?>
                                            <div class="row mb-2 align-items-center">
                                                <div class="col-md-1">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                               value="<?php echo $item['id']; ?>"
                                                               id="third_<?php echo $item['id']; ?>"
                                                               data-type="THIRD_PARTY_PRODUCT"
                                                               data-name="<?php echo $item['name']; ?>"
                                                               data-unit="<?php echo $item['unit_of_measure']; ?>"
                                                               data-stock="<?php echo $item['current_stock']; ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-5">
                                                    <label for="third_<?php echo $item['id']; ?>" class="form-label mb-0">
                                                        <strong><?php echo $item['name']; ?></strong><br>
                                                        <small class="text-muted">Available: <?php echo number_format($item['current_stock']); ?> <?php echo $item['unit_of_measure']; ?></small>
                                                    </label>
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="number" class="form-control form-control-sm"
                                                           id="qty_third_<?php echo $item['id']; ?>"
                                                           placeholder="Quantity" min="1" max="<?php echo $item['current_stock']; ?>" disabled>
                                                </div>
                                                <div class="col-md-3">
                                                    <span class="badge bg-secondary"><?php echo $item['unit_of_measure']; ?></span>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <!-- Packaging Materials -->
                                        <div id="packagingMaterials" class="tab-pane fade">
                                            <h6>Select Packaging Materials with Quantities</h6>
                                            <?php foreach ($availableBulkItems['packaging_materials'] as $item): ?>
                                            <div class="row mb-2 align-items-center">
                                                <div class="col-md-1">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                               value="<?php echo $item['id']; ?>"
                                                               id="pack_<?php echo $item['id']; ?>"
                                                               data-type="PACKAGING_MATERIAL"
                                                               data-name="<?php echo $item['name']; ?>"
                                                               data-unit="<?php echo $item['unit']; ?>"
                                                               data-stock="<?php echo $item['current_stock']; ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-5">
                                                    <label for="pack_<?php echo $item['id']; ?>" class="form-label mb-0">
                                                        <strong><?php echo $item['name']; ?></strong><br>
                                                        <small class="text-muted">Available: <?php echo number_format($item['current_stock']); ?> <?php echo $item['unit']; ?></small>
                                                    </label>
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="number" class="form-control form-control-sm"
                                                           id="qty_pack_<?php echo $item['id']; ?>"
                                                           placeholder="Quantity" min="1" max="<?php echo $item['current_stock']; ?>" disabled>
                                                </div>
                                                <div class="col-md-3">
                                                    <span class="badge bg-secondary"><?php echo $item['unit']; ?></span>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Transfer Notes</label>
                                <textarea class="form-control" id="transferNotes" rows="2" placeholder="Optional notes for this transfer"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-truck"></i> Create Transfer & Print Form
                            </button>
                        </form>
                    </div>

                    <!-- Pending Transfers Tab -->
                    <div id="pendingTransfers" class="tab-pane fade">
                        <div id="pendingList">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading pending transfers...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Transfer History Tab -->
                    <div id="transferHistory" class="tab-pane fade">
                        <div id="historyList">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading transfer history...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let transferBagScannerRecord = null;
        let transferScannerLibPromise = null;
        let transferBagLastScanValue = null;
        const transferBagHighlightTimers = {};

        function initTransferBagSearch() {
            const input = document.getElementById('transferBagSearchInput');
            if (!input) {
                return;
            }

            const searchButton = document.getElementById('transferBagSearchButton');
            const clearButton = document.getElementById('transferBagClearButton');
            const suggestions = document.getElementById('transferBagSuggestions');
            const scanStart = document.getElementById('transferBagScanStart');
            const scanStop = document.getElementById('transferBagScanStop');

            const applyFilter = () => {
                const value = input.value;
                filterTransferBagList(value);
                updateTransferBagSuggestions(value);
            };

            input.addEventListener('input', applyFilter);

            if (searchButton) {
                searchButton.addEventListener('click', applyFilter);
            }

            if (clearButton) {
                clearButton.addEventListener('click', () => {
                    input.value = '';
                    filterTransferBagList('');
                    updateTransferBagSuggestions('');
                    showTransferBagFeedback('');
                    input.focus();
                });
            }

            if (suggestions) {
                suggestions.addEventListener('click', (event) => {
                    const target = event.target.closest('[data-serial]');
                    if (!target) {
                        return;
                    }

                    const serial = target.dataset.serial || '';
                    if (serial) {
                        const found = selectTransferBagBySerial(serial, { updateInput: true });
                        showTransferBagFeedback(
                            found ? `Bag ${serial} selected.` : `Serial ${serial} not found.`,
                            found ? 'success' : 'danger'
                        );
                    }
                });
            }

            if (scanStart) {
                scanStart.addEventListener('click', () => startTransferBagScan());
            }

            if (scanStop) {
                scanStop.addEventListener('click', () => stopTransferBagScan());
            }

            filterTransferBagList('');
        }

        function filterTransferBagList(query) {
            const filterValue = (query || '').trim().toLowerCase();
            document.querySelectorAll('.transfer-bag-option').forEach(option => {
                const serial = (option.dataset.serial || '').toLowerCase();
                const product = (option.dataset.product || '').toLowerCase();
                const shouldShow = !filterValue || serial.includes(filterValue) || product.includes(filterValue);
                option.style.display = shouldShow ? '' : 'none';
            });
        }

        function updateTransferBagSuggestions(query) {
            const suggestions = document.getElementById('transferBagSuggestions');

            if (!suggestions) {
                return;
            }

            const filterValue = (query || '').trim().toLowerCase();
            if (!filterValue) {
                suggestions.style.display = 'none';
                suggestions.innerHTML = '';
                return;
            }

            const matches = Array.from(document.querySelectorAll('.transfer-bag-option'))
                .map(option => option.dataset.serial || '')
                .filter(serial => serial.toLowerCase().includes(filterValue))
                .slice(0, 10);

            if (matches.length === 0) {
                suggestions.style.display = 'none';
                suggestions.innerHTML = '';
                return;
            }

            suggestions.innerHTML = matches
                .map(serial => `<div class="py-1 px-2 border-bottom" data-serial="${serial}" style="cursor: pointer;">${serial}</div>`)
                .join('');
            suggestions.style.display = 'block';
        }

        function selectTransferBagBySerial(serial, options = {}) {
            const settings = {
                updateInput: options.updateInput !== false,
                highlight: options.highlight !== false,
                scroll: options.scroll !== false
            };

            const cleanedSerial = (serial || '').trim();
            if (!cleanedSerial) {
                return false;
            }

            const normalized = cleanedSerial.toLowerCase();
            const checkbox = Array.from(document.querySelectorAll('.transfer-bag-checkbox'))
                .find(cb => (cb.dataset.serial || '').toLowerCase() === normalized);

            if (!checkbox) {
                return false;
            }

            checkbox.checked = true;

            if (settings.updateInput) {
                const input = document.getElementById('transferBagSearchInput');
                if (input) {
                    input.value = cleanedSerial;
                    filterTransferBagList(cleanedSerial);
                    updateTransferBagSuggestions(cleanedSerial);
                }
            }

            const container = checkbox.closest('.transfer-bag-option');
            if (container && settings.highlight) {
                const key = normalized;
                if (transferBagHighlightTimers[key]) {
                    clearTimeout(transferBagHighlightTimers[key]);
                }
                container.style.backgroundColor = '#f0f6ff';
                transferBagHighlightTimers[key] = setTimeout(() => {
                    container.style.backgroundColor = '';
                    delete transferBagHighlightTimers[key];
                }, 1500);
            }

            if (settings.scroll && container) {
                container.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else if (settings.scroll) {
                checkbox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            return true;
        }

        function showTransferBagFeedback(message, variant = 'info') {
            const feedback = document.getElementById('transferBagScanFeedback');

            if (!feedback) {
                return;
            }

            if (!message) {
                feedback.style.display = 'none';
                feedback.textContent = '';
                feedback.className = 'small text-muted mt-2';
                return;
            }

            const variantClass = {
                success: 'text-success',
                danger: 'text-danger',
                warning: 'text-warning',
                info: 'text-muted'
            };

            feedback.className = `small mt-2 ${variantClass[variant] || 'text-muted'}`;
            feedback.textContent = message;
            feedback.style.display = 'block';
        }

        function startTransferBagScan() {
            const startButton = document.getElementById('transferBagScanStart');
            const stopButton = document.getElementById('transferBagScanStop');
            const container = document.getElementById('transferBagScanContainer');
            const view = document.getElementById('transferBagScanView');

            if (!startButton || !container || !view) {
                return;
            }

            stopTransferBagScan().finally(() => {
                if (!startButton.dataset.originalHtml) {
                    startButton.dataset.originalHtml = startButton.innerHTML;
                }

                startButton.disabled = true;
                startButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Starting...';
                container.style.display = 'block';
                view.innerHTML = '';
                showTransferBagFeedback('Opening camera...', 'info');

                tryStartTransferBarcodeDetector()
                    .then(started => {
                        if (started) {
                            finalizeStartSuccess();
                            return null;
                        }
                        return startTransferHtml5QrCode();
                    })
                    .then(started => {
                        if (started === null) {
                            return;
                        }
                        if (started) {
                            finalizeStartSuccess();
                        } else if (started === false) {
                            throw new Error('No available scanning method on this device.');
                        }
                    })
                    .catch(error => {
                        startButton.disabled = false;
                        if (startButton.dataset.originalHtml) {
                            startButton.innerHTML = startButton.dataset.originalHtml;
                        }
                        startButton.style.display = 'inline-block';
                        if (stopButton) {
                            stopButton.style.display = 'none';
                        }
                        container.style.display = 'none';
                        showTransferBagFeedback(error.message || 'Unable to start QR scanner.', 'danger');
                    });
            });

            function finalizeStartSuccess() {
                startButton.disabled = false;
                startButton.style.display = 'none';
                if (startButton.dataset.originalHtml) {
                    startButton.innerHTML = startButton.dataset.originalHtml;
                }
                if (stopButton) {
                    stopButton.style.display = 'inline-block';
                }
                container.style.display = 'block';
                showTransferBagFeedback('Scanner ready. Align the QR code within the frame.', 'success');
            }
        }

        function tryStartTransferBarcodeDetector() {
            if (!('BarcodeDetector' in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                return Promise.resolve(false);
            }

            const view = document.getElementById('transferBagScanView');

            if (!view) {
                return Promise.resolve(false);
            }

            const detectorSupportPromise = typeof BarcodeDetector.getSupportedFormats === 'function'
                ? BarcodeDetector.getSupportedFormats().then(formats => formats.includes('qr_code')).catch(() => true)
                : Promise.resolve(true);

            return detectorSupportPromise.then(isSupported => {
                if (!isSupported) {
                    return false;
                }

                const detector = new BarcodeDetector({ formats: ['qr_code'] });
                const video = document.createElement('video');
                video.setAttribute('playsinline', 'true');
                video.muted = true;
                video.autoplay = true;
                video.style.width = '100%';
                video.style.height = '100%';

                view.innerHTML = '';
                view.appendChild(video);

                return navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                    .then(stream => {
                        video.srcObject = stream;
                        return video.play().catch(() => {});
                    })
                    .then(() => {
                        let active = true;
                        let rafId = null;

                        const scannerRecord = {
                            type: 'barcode-detector',
                            stop: () => new Promise(resolve => {
                                active = false;
                                if (rafId) {
                                    cancelAnimationFrame(rafId);
                                }
                                if (video.srcObject) {
                                    video.srcObject.getTracks().forEach(track => track.stop());
                                }
                                video.pause();
                                video.srcObject = null;
                                view.innerHTML = '';
                                resolve();
                            })
                        };

                        transferBagScannerRecord = scannerRecord;

                        const scan = () => {
                            if (!active) {
                                return;
                            }

                            if (video.readyState >= 2) {
                                detector.detect(video)
                                    .then(barcodes => {
                                        if (barcodes && barcodes.length > 0) {
                                            handleTransferBagScan(barcodes[0].rawValue || '');
                                        }
                                    })
                                    .catch(() => {});
                            }

                            rafId = requestAnimationFrame(scan);
                        };

                        rafId = requestAnimationFrame(scan);
                        return true;
                    })
                    .catch(error => {
                        view.innerHTML = '';
                        showTransferBagFeedback(error.message || 'Camera access denied.', 'danger');
                        return false;
                    });
            });
        }

        function transferEnsureHtml5QrcodeLibrary() {
            if (window.Html5Qrcode) {
                return Promise.resolve();
            }

            if (transferScannerLibPromise) {
                return transferScannerLibPromise;
            }

            transferScannerLibPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://unpkg.com/html5-qrcode@2.3.10/html5-qrcode.min.js';
                script.async = true;
                script.onload = () => resolve();
                script.onerror = () => reject(new Error('Failed to load QR scanner library. Check your network or browser permissions.'));
                document.head.appendChild(script);
            });

            return transferScannerLibPromise;
        }

        function startTransferHtml5QrCode() {
            const viewId = 'transferBagScanView';
            if (!document.getElementById(viewId)) {
                return Promise.resolve(false);
            }

            return transferEnsureHtml5QrcodeLibrary()
                .then(() => {
                    const scanner = new Html5Qrcode(viewId);

                    transferBagScannerRecord = {
                        type: 'html5-qrcode',
                        stop: () => scanner.stop()
                            .then(() => scanner.clear().catch(() => {}))
                            .catch(() => scanner.clear().catch(() => {}))
                    };

                    return scanner.start(
                        { facingMode: 'environment' },
                        { fps: 10, qrbox: 220 },
                        (decodedText) => handleTransferBagScan(decodedText),
                        () => {}
                    ).then(() => true)
                    .catch(error => {
                        transferBagScannerRecord = null;
                        showTransferBagFeedback(error.message || 'Unable to start scanner.', 'danger');
                        return false;
                    });
                })
                .catch(error => {
                    showTransferBagFeedback(error.message || 'Unable to load QR scanner library.', 'danger');
                    return false;
                });
        }

        function handleTransferBagScan(decodedText) {
            const cleaned = (decodedText || '').trim();
            if (!cleaned) {
                return;
            }

            if (transferBagLastScanValue === cleaned) {
                return;
            }
            transferBagLastScanValue = cleaned;
            setTimeout(() => {
                if (transferBagLastScanValue === cleaned) {
                    transferBagLastScanValue = null;
                }
            }, 1500);

            const found = selectTransferBagBySerial(cleaned, { updateInput: true });
            if (found) {
                showTransferBagFeedback(`Bag ${cleaned} selected via scan.`, 'success');
            } else {
                showTransferBagFeedback(`Serial ${cleaned} not found in available bags.`, 'danger');
            }
        }

        function stopTransferBagScan() {
            return new Promise(resolve => {
                const record = transferBagScannerRecord;
                const startButton = document.getElementById('transferBagScanStart');
                const stopButton = document.getElementById('transferBagScanStop');
                const container = document.getElementById('transferBagScanContainer');
                const view = document.getElementById('transferBagScanView');

                const finalize = () => {
                    if (container) {
                        container.style.display = 'none';
                    }
                    if (view) {
                        view.innerHTML = '';
                    }
                    if (startButton) {
                        startButton.style.display = 'inline-block';
                        startButton.disabled = false;
                        if (startButton.dataset.originalHtml) {
                            startButton.innerHTML = startButton.dataset.originalHtml;
                        }
                    }
                    if (stopButton) {
                        stopButton.style.display = 'none';
                    }
                    transferBagLastScanValue = null;
                    showTransferBagFeedback('');
                    resolve();
                };

                if (!record) {
                    finalize();
                    return;
                }

                Promise.resolve(record.stop())
                    .catch(() => {})
                    .finally(() => {
                        transferBagScannerRecord = null;
                        finalize();
                    });
            });
        }

        window.addEventListener('beforeunload', () => {
            stopTransferBagScan();
        });

        document.addEventListener('DOMContentLoaded', function() {
            initTransferBagSearch();

            const transferTabs = document.getElementById('transferTabs');
            if (transferTabs) {
                transferTabs.addEventListener('shown.bs.tab', (event) => {
                    const target = event.target;
                    if (target && target.getAttribute('href') !== '#finishedProducts') {
                        stopTransferBagScan();
                    }
                });
            }

            // Enable/disable quantity inputs based on checkbox selection
            document.querySelectorAll('input[type="checkbox"][data-type]').forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    const qtyInput = document.getElementById('qty_' + this.id);
                    if (qtyInput) {
                        qtyInput.disabled = !this.checked;
                        if (!this.checked) qtyInput.value = '';
                    }
                });
            });

            // Transfer form submission
            document.getElementById('transferForm').addEventListener('submit', function(e) {
                e.preventDefault();
                stopTransferBagScan();

                const toBranch = document.getElementById('toBranch').value;
                const driverId = document.getElementById('driverId').value;

                if (!toBranch || !driverId) {
                    alert('Please select destination branch and driver');
                    return;
                }

                // Collect selected bags
                const selectedBags = [];
                document.querySelectorAll('#finishedProducts input[type="checkbox"]:checked').forEach(function(checkbox) {
                    selectedBags.push(checkbox.value);
                });

                // Collect selected bulk items with quantities
                const selectedItems = [];
                document.querySelectorAll('input[type="checkbox"][data-type]:checked').forEach(function(checkbox) {
                    const qtyInput = document.getElementById('qty_' + checkbox.id);
                    const quantity = qtyInput ? qtyInput.value : 0;

                    if (quantity > 0) {
                        selectedItems.push({
                            id: checkbox.value,
                            type: checkbox.dataset.type,
                            name: checkbox.dataset.name,
                            quantity: parseInt(quantity),
                            unit: checkbox.dataset.unit
                        });
                    }
                });

                if (selectedBags.length === 0 && selectedItems.length === 0) {
                    alert('Please select at least one item to transfer');
                    return;
                }

                // Create transfer via AJAX
                const transferData = {
                    to_branch_id: toBranch,
                    driver_id: driverId,
                    selected_bags: selectedBags,
                    selected_items: selectedItems,
                    notes: document.getElementById('transferNotes').value
                };

                // Show loading state
                const submitBtn = document.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Creating Transfer...';
                submitBtn.disabled = true;

                // Make AJAX call
                fetch('<?php echo BASE_URL; ?>/inventory/ajax/create_transfer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(transferData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Transfer ${data.transfer_number} created successfully! Form ready for printing.`);

                        // Reset form
                        this.reset();
                        document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
                        document.querySelectorAll('input[type="number"]').forEach(input => {
                            input.disabled = true;
                            input.value = '';
                        });

                        // Open transfer form in new window for printing
                        if (data.transfer_id) {
                            window.open(`<?php echo BASE_URL; ?>/inventory/transfer_form.php?id=${data.transfer_id}`, '_blank');
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error creating transfer. Please try again.');
                })
                .finally(() => {
                    // Restore button
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });

            // Load initial data
            loadPendingTransfers();
            loadTransferHistory();
        });

        function loadPendingTransfers() {
            document.getElementById('pendingList').innerHTML =
                '<div class="alert alert-info">No pending transfers at this time.</div>';
        }

        function loadTransferHistory() {
            document.getElementById('historyList').innerHTML =
                '<div class="alert alert-info">No transfer history found.</div>';
        }
    </script>
</body>
</html>
