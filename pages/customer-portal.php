<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/customer-portal.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/customer-portal.php`.
 */
/**
 * Customer Portal - For registered agencies to check their registration status
 * Login using email address
 */
require_once __DIR__ . '/../includes/config.php';

$error = '';
$success = '';

// Use the same DB source as registration API (prefer control DB for registration requests).
if (!function_exists('getCustomerPortalDbConn')) {
    function getCustomerPortalDbConn() {
        static $portalConn = null;
        if ($portalConn instanceof mysqli) {
            return $portalConn;
        }

        if (defined('CONTROL_PANEL_DB_NAME') && CONTROL_PANEL_DB_NAME !== '' && defined('DB_HOST') && defined('DB_USER')) {
            $ctrlDb = CONTROL_PANEL_DB_NAME;
            $mainDb = defined('DB_NAME') ? DB_NAME : '';
            if ($ctrlDb !== $mainDb) {
                try {
                    $ctrlConn = @new mysqli(DB_HOST, DB_USER, defined('DB_PASS') ? DB_PASS : '', $ctrlDb, defined('DB_PORT') ? (int)DB_PORT : 3306);
                    if ($ctrlConn && !$ctrlConn->connect_error) {
                        $ctrlConn->set_charset('utf8mb4');
                        $portalConn = $ctrlConn;
                        return $portalConn;
                    }
                    if ($ctrlConn) $ctrlConn->close();
                } catch (Throwable $e) {
                    // Fall through to default connection.
                }
            }
        }

        $portalConn = $GLOBALS['conn'] ?? null;
        return $portalConn;
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Email address is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        $conn = getCustomerPortalDbConn();
        if ($conn) {
            $stmt = $conn->prepare("SELECT id, agency_name, contact_email, plan, status, created_at, notes FROM control_registration_requests WHERE LOWER(TRIM(contact_email)) = LOWER(TRIM(?)) ORDER BY created_at DESC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $registration = $result->fetch_assoc();
                    // Store canonical email from DB for consistent session lookups.
                    $_SESSION['customer_email'] = $registration['contact_email'];
                    $_SESSION['customer_registration_id'] = $registration['id'];
                    $_SESSION['customer_logged_in'] = true;
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $error = 'No registration found with this email address. Please check your email or register first.';
                }
                $stmt->close();
            }
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['customer_email']);
    unset($_SESSION['customer_registration_id']);
    unset($_SESSION['customer_logged_in']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$isLoggedIn = isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true;
$registration = null;
$agencyState = null;
$agencyFlags = [];

if ($isLoggedIn && isset($_SESSION['customer_email'])) {
    $conn = getCustomerPortalDbConn();
    if ($conn) {
        $email = $_SESSION['customer_email'];
        $stmt = $conn->prepare("SELECT * FROM control_registration_requests WHERE LOWER(TRIM(contact_email)) = LOWER(TRIM(?)) ORDER BY created_at DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $registration = $result->fetch_assoc();
                $createdAgencyId = (int)($registration['created_agency_id'] ?? 0);
                if ($createdAgencyId > 0) {
                    $hasIsActive = false;
                    $hasIsSuspended = false;
                    $colRes = @$conn->query("SHOW COLUMNS FROM control_agencies");
                    if ($colRes) {
                        while ($col = $colRes->fetch_assoc()) {
                            if (($col['Field'] ?? '') === 'is_active') $hasIsActive = true;
                            if (($col['Field'] ?? '') === 'is_suspended') $hasIsSuspended = true;
                        }
                    }
                    if ($hasIsActive || $hasIsSuspended) {
                        $fields = ['id'];
                        if ($hasIsActive) $fields[] = 'is_active';
                        if ($hasIsSuspended) $fields[] = 'is_suspended';
                        $aStmt = $conn->prepare("SELECT " . implode(', ', $fields) . " FROM control_agencies WHERE id = ? LIMIT 1");
                        if ($aStmt) {
                            $aStmt->bind_param("i", $createdAgencyId);
                            $aStmt->execute();
                            $aRes = $aStmt->get_result();
                            if ($aRes && $aRes->num_rows > 0) {
                                $agencyState = $aRes->fetch_assoc();
                                if (array_key_exists('is_active', $agencyState) && (int)$agencyState['is_active'] === 0) {
                                    $agencyFlags[] = ['label' => 'Inactive', 'class' => 'v-danger'];
                                }
                                if (array_key_exists('is_suspended', $agencyState) && (int)$agencyState['is_suspended'] === 1) {
                                    $agencyFlags[] = ['label' => 'Suspended', 'class' => 'v-danger'];
                                }
                            }
                            $aStmt->close();
                        }
                    }
                }
            }
            $stmt->close();
        }
    }
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . dirname(dirname($_SERVER['SCRIPT_NAME']));
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal - Ratib Program</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php $cpCssV = (int) (@filemtime(__DIR__ . '/../css/pages/customer-portal.css') ?: time()); ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/css/pages/customer-portal.css?v=<?php echo $cpCssV; ?>">
</head>
<body>
    <div class="portal-container">
        <div class="portal-header">
            <h1><i class="fas fa-user-circle me-2"></i>Customer Portal</h1>
            <p class="portal-header-sub">Check your registration status and follow up on your request</p>
        </div>

        <?php if (!$isLoggedIn): ?>
        <!-- Login Form -->
        <div class="portal-card">
            <h3 class="mb-4"><i class="fas fa-sign-in-alt me-2"></i>Login to Your Portal</h3>
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control portal-form-input" placeholder="Enter the email you used for registration" required>
                    <small class="text-muted">Use the email address you provided during registration</small>
                </div>
                <button type="submit" class="btn btn-primary w-100 portal-btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>
            <div class="mt-4 text-center">
                <p class="text-muted">Don't have a registration? <a href="<?php echo htmlspecialchars($baseUrl); ?>/pages/home.php?open=register" class="portal-link-accent">Register here</a></p>
            </div>
        </div>
        <?php else: ?>
        <!-- Registration Status -->
        <div class="portal-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="fas fa-building me-2"></i>Registration Status</h3>
                <a href="?logout=1" class="btn btn-sm btn-logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
            </div>
            
            <?php if ($registration): ?>
            <div class="mb-4">
                <span class="status-badge status-<?php echo htmlspecialchars($registration['status']); ?>">
                    <?php echo strtoupper(htmlspecialchars($registration['status'])); ?>
                </span>
            </div>
            <?php if (!empty($agencyFlags)): ?>
            <div class="portal-status-cards">
                <?php foreach ($agencyFlags as $flag): ?>
                <div class="portal-status-card">
                    <div class="k">Account Flag</div>
                    <div class="v <?php echo htmlspecialchars($flag['class']); ?>"><?php echo htmlspecialchars($flag['label']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <span class="info-label">Agency Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($registration['agency_name']); ?></span>
            </div>
            <?php if (!empty($registration['agency_id'])): ?>
            <div class="info-row">
                <span class="info-label">Agency ID:</span>
                <span class="info-value"><?php echo htmlspecialchars($registration['agency_id']); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($registration['country_name'])): ?>
            <div class="info-row">
                <span class="info-label">Country:</span>
                <span class="info-value"><?php echo htmlspecialchars($registration['country_name']); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value"><?php echo htmlspecialchars($registration['contact_email']); ?></span>
            </div>
            <?php if (!empty($registration['contact_phone'])): ?>
            <div class="info-row">
                <span class="info-label">Phone:</span>
                <span class="info-value"><?php echo htmlspecialchars($registration['contact_phone']); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label">Plan:</span>
                <span class="info-value"><?php echo strtoupper(htmlspecialchars($registration['plan'])); ?><?php if (!empty($registration['plan_amount'])): ?> — $<?php echo number_format($registration['plan_amount'], 2); ?><?php if (!empty($registration['years'])): ?> for <?php echo (int)$registration['years']; ?> year<?php echo (int)$registration['years'] > 1 ? 's' : ''; ?><?php endif; ?><?php endif; ?></span>
            </div>
            <?php if (!empty($registration['payment_status']) || !empty($registration['payment_method'])): ?>
            <div class="info-row">
                <span class="info-label">Payment Status:</span>
                <span class="info-value">
                    <?php 
                    $payStatus = $registration['payment_status'] ?? 'unpaid';
                    $payMethod = $registration['payment_method'] ?? '';
                    echo ucfirst(htmlspecialchars($payStatus));
                    if ($payMethod) {
                        echo ' (' . htmlspecialchars($payMethod) . ')';
                    }
                    ?>
                </span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label">Registration Date:</span>
                <span class="info-value"><?php echo date('F j, Y g:i A', strtotime($registration['created_at'])); ?></span>
            </div>
            <?php if (!empty($registration['reviewed_at'])): ?>
            <div class="info-row">
                <span class="info-label">Reviewed Date:</span>
                <span class="info-value"><?php echo date('F j, Y g:i A', strtotime($registration['reviewed_at'])); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($registration['notes'])): ?>
            <div class="info-row">
                <span class="info-label">Notes:</span>
                <span class="info-value"><?php echo nl2br(htmlspecialchars($registration['notes'])); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="mt-4 p-3 portal-message-box">
                <?php if ($registration['status'] === 'pending'): ?>
                <p class="mb-0"><i class="fas fa-clock me-2 status-icon-pending"></i><strong>Your registration is pending review.</strong> We will review your request and contact you soon via email.</p>
                <?php elseif ($registration['status'] === 'approved'): ?>
                <p class="mb-0"><i class="fas fa-check-circle me-2 status-icon-approved"></i><strong>Your registration has been approved!</strong> You will receive further instructions via email.</p>
                <?php elseif ($registration['status'] === 'rejected'): ?>
                <p class="mb-0"><i class="fas fa-times-circle me-2 status-icon-rejected"></i><strong>Your registration was not approved.</strong> Please contact us for more information.</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">Registration information not found.</div>
            <?php endif; ?>
        </div>
        
        <div class="portal-card">
            <h4 class="mb-3"><i class="fas fa-headset me-2"></i>Need Help?</h4>
            <p class="portal-help-muted">If you have any questions about your registration, please contact us:</p>
            <div class="help-contact-list">
                <div class="help-contact-item">
                    <span class="help-icon help-icon-phone"><i class="fas fa-phone-alt"></i></span>
                    <a href="tel:+966599863868" class="help-link-phone">+966 59 986 3868</a>
                </div>
                <div class="help-contact-item">
                    <span class="help-icon help-icon-whatsapp"><i class="fab fa-whatsapp"></i></span>
                    <a href="https://wa.me/966599863868" target="_blank" rel="noopener noreferrer" class="help-link-whatsapp">WhatsApp: +966 59 986 3868</a>
                </div>
                <div class="help-contact-item">
                    <span class="help-icon help-icon-email"><i class="fas fa-envelope"></i></span>
                    <a href="mailto:ratibstar@gmail.com" class="help-link-email">ratibstar@gmail.com</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
