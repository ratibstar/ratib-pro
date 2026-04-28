<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/add-agent.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/add-agent.php`.
 */
require_once __DIR__ . '/../includes/config.php';

// Check if user is logged in
if (!is_authenticated()) {
    header('Location: ' . pageUrl('login.php'));
    exit();
}

$pageTitle = "Add New Agent";
$pageCss = [
    asset('css/dashboard.css'),
    asset('css/agent/agent.css'),
];

include '../includes/header.php';

$error = '';
$success = '';

// Form submission is now handled by JavaScript via API
// No inline database queries - all handled by api/agents/create.php
?>

<div class="content-container">
    <div class="page-header">
        <h1>Add New Agent</h1>
        <button class="back-btn" id="backBtn">
            <i class="fas fa-arrow-left"></i> Back to Agents
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST" class="add-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" required>
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label for="commission_rate">Commission Rate (%)</label>
                <input type="number" id="commission_rate" name="commission_rate" step="0.01" min="0" max="100" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">Add Agent</button>
                <button type="button" class="btn-secondary" id="cancelBtn">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="../js/agent/add-agent.js"></script>

<?php include '../includes/footer.php'; ?> 