<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/accounting-guide.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/accounting-guide.php`.
 */
/**
 * Professional Accounting System - Complete Guide & Documentation
 * This page explains the complete accounting system and how everything works together
 */

require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect to login or show message
    require_once __DIR__ . '/../includes/config.php';
    header('Location: ' . pageUrl('login.php') . '?redirect=' . urlencode(pageUrl('accounting-guide.php')));
    exit;
}

$pageTitle = "Professional Accounting System - Complete Guide";
require_once __DIR__ . '/../includes/config.php';
$pageCss = [
    asset('css/accounting/accounting-guide.css') . "?v=" . time()
];
include '../includes/header.php';
?>

<div class="accounting-guide-container">
    <div class="accounting-guide-card">
        <h1 class="accounting-guide-title">
            <i class="fas fa-book accounting-guide-title-icon"></i>
            Professional Accounting System - Complete Guide
        </h1>
        
        <!-- Quick Setup -->
        <div class="accounting-guide-quick-setup">
            <h2 class="accounting-guide-section-title">
                <i class="fas fa-rocket"></i> Quick Setup
            </h2>
            <p class="accounting-guide-text">
                Click the button below to set up the complete professional accounting system automatically:
            </p>
            <div class="accounting-guide-buttons-wrapper">
                <button id="setupBtn" class="btn btn-primary accounting-guide-button">
                    <i class="fas fa-magic"></i> Setup Complete Accounting System
                </button>
                <button id="autoSetupBtn" class="btn btn-success accounting-guide-button accounting-guide-button-success">
                    <i class="fas fa-robot"></i> Auto-Setup Everything
                </button>
                <button id="migrateBtn" class="btn btn-warning accounting-guide-button accounting-guide-button-warning">
                    <i class="fas fa-database"></i> Add Debit/Credit Columns
                </button>
                <button id="checkTablesBtn" class="btn btn-info accounting-guide-button accounting-guide-button-info">
                    <i class="fas fa-search"></i> Check Table Structure
                </button>
                <button id="autoLinkBtn" class="btn btn-secondary accounting-guide-button">
                    <i class="fas fa-link"></i> Auto-Link All Transactions
                </button>
                <button id="recalculateBtn" class="btn btn-primary accounting-guide-button accounting-guide-button-purple">
                    <i class="fas fa-calculator"></i> Recalculate All Balances
                </button>
            </div>
            <div id="setupResult" class="accounting-guide-setup-result"></div>
        </div>
        
        <!-- System Overview -->
        <div class="accounting-guide-section">
            <h2 class="accounting-guide-section-title-large">
                <i class="fas fa-info-circle"></i> System Overview
            </h2>
            <div class="accounting-guide-text-content">
                <p>This is a <strong>double-entry bookkeeping system</strong> that follows professional accounting standards:</p>
                <ul class="accounting-guide-list">
                    <li><strong>Double-Entry:</strong> Every transaction has both a debit and credit entry</li>
                    <li><strong>Chart of Accounts:</strong> Organized by Asset, Liability, Equity, Income, Expense</li>
                    <li><strong>Entity Integration:</strong> All agents, subagents, workers, and HR transactions are tracked</li>
                    <li><strong>General Ledger:</strong> Complete record of all financial transactions</li>
                    <li><strong>Account Filtering:</strong> View transactions by specific accounts</li>
                </ul>
            </div>
        </div>
        
        <!-- How It Works -->
        <div class="accounting-guide-section">
            <h2 class="accounting-guide-section-title-large">
                <i class="fas fa-cogs"></i> How It Works
            </h2>
            
            <div class="accounting-guide-grid">
                <div class="accounting-guide-card-item">
                    <h3 class="accounting-guide-card-title-blue">
                        <i class="fas fa-users"></i> Entity Transactions
                    </h3>
                    <p class="accounting-guide-card-text">
                        When you create a transaction for an <strong>Agent</strong>, <strong>Subagent</strong>, <strong>Worker</strong>, or <strong>HR</strong>, 
                        it's automatically recorded in <code>financial_transactions</code> table.
                    </p>
                </div>
                
                <div class="accounting-guide-card-item">
                    <h3 class="accounting-guide-card-title-green">
                        <i class="fas fa-link"></i> Account Linking
                    </h3>
                    <p class="accounting-guide-card-text">
                        Each transaction is linked to an account via <code>transaction_lines</code> table. 
                        This allows filtering by account in the General Ledger.
                    </p>
                </div>
                
                <div class="accounting-guide-card-item">
                    <h3 class="accounting-guide-card-title-purple">
                        <i class="fas fa-book"></i> General Ledger
                    </h3>
                    <p class="accounting-guide-card-text">
                        The General Ledger shows all transactions from both <code>journal_entries</code> and 
                        <code>financial_transactions</code> tables, with proper debit/credit columns.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Account Mapping Guide -->
        <div class="accounting-guide-section">
            <h2 class="accounting-guide-section-title-large">
                <i class="fas fa-map"></i> Account Mapping Guide
            </h2>
            <div class="accounting-guide-table-wrapper">
                <table class="accounting-guide-table">
                    <thead>
                        <tr>
                            <th>Entity Type</th>
                            <th>Transaction Type</th>
                            <th>Debit Account</th>
                            <th>Credit Account</th>
                            <th>Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Agent Income -->
                        <tr>
                            <td><strong>Agent</strong></td>
                            <td><span class="accounting-guide-income-color">Income</span></td>
                            <td>1100 Cash</td>
                            <td>4300 Agent Revenue</td>
                            <td class="accounting-guide-table-example">Agent receives payment</td>
                        </tr>
                        <!-- Agent Expense -->
                        <tr>
                            <td><strong>Agent</strong></td>
                            <td><span class="accounting-guide-expense-color">Expense</span></td>
                            <td>5500 Agent Payments</td>
                            <td>1100 Cash</td>
                            <td class="accounting-guide-table-example">Pay agent commission</td>
                        </tr>
                        <!-- Subagent Income -->
                        <tr>
                            <td><strong>Subagent</strong></td>
                            <td><span class="accounting-guide-income-color">Income</span></td>
                            <td>1100 Cash</td>
                            <td>4400 Subagent Revenue</td>
                            <td class="accounting-guide-table-example">Subagent receives payment</td>
                        </tr>
                        <!-- Subagent Expense -->
                        <tr>
                            <td><strong>Subagent</strong></td>
                            <td><span class="accounting-guide-expense-color">Expense</span></td>
                            <td>5600 Subagent Payments</td>
                            <td>1100 Cash</td>
                            <td class="accounting-guide-table-example">Pay subagent commission</td>
                        </tr>
                        <!-- Worker Income -->
                        <tr>
                            <td><strong>Worker</strong></td>
                            <td><span class="accounting-guide-income-color">Income</span></td>
                            <td>1100 Cash</td>
                            <td>4500 Worker Revenue</td>
                            <td class="accounting-guide-table-example">Worker receives payment</td>
                        </tr>
                        <!-- Worker Expense -->
                        <tr>
                            <td><strong>Worker</strong></td>
                            <td><span class="accounting-guide-expense-color">Expense</span></td>
                            <td>5700 Worker Payments</td>
                            <td>1100 Cash</td>
                            <td class="accounting-guide-table-example">Pay worker salary</td>
                        </tr>
                        <!-- HR Income -->
                        <tr>
                            <td><strong>HR</strong></td>
                            <td><span class="accounting-guide-income-color">Income</span></td>
                            <td>1100 Cash</td>
                            <td>4600 HR Revenue</td>
                            <td class="accounting-guide-table-example">HR receives payment</td>
                        </tr>
                        <!-- HR Expense -->
                        <tr>
                            <td><strong>HR</strong></td>
                            <td><span class="accounting-guide-expense-color">Expense</span></td>
                            <td>5800 HR Payments</td>
                            <td>1100 Cash</td>
                            <td class="accounting-guide-table-example">Pay HR salary</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Database Structure -->
        <div class="accounting-guide-section">
            <h2 class="accounting-guide-section-title-large">
                <i class="fas fa-database"></i> Database Structure
            </h2>
            <div class="accounting-guide-status-wrapper">
                <div class="accounting-guide-database-grid">
                    <div class="accounting-guide-database-card accounting-guide-database-card-blue">
                        <h4 class="accounting-guide-database-title accounting-guide-database-title-blue">financial_accounts</h4>
                        <p class="accounting-guide-database-text">
                            Chart of accounts (Asset, Liability, Equity, Income, Expense)
                        </p>
                    </div>
                    <div class="accounting-guide-database-card accounting-guide-database-card-green">
                        <h4 class="accounting-guide-database-title accounting-guide-database-title-green">financial_transactions</h4>
                        <p class="accounting-guide-database-text">
                            Entity transactions (agents, subagents, workers, HR)
                        </p>
                    </div>
                    <div class="accounting-guide-database-card accounting-guide-database-card-purple">
                        <h4 class="accounting-guide-database-title accounting-guide-database-title-purple">transaction_lines</h4>
                        <p class="accounting-guide-database-text">
                            Links transactions to accounts (for filtering)
                        </p>
                    </div>
                    <div class="accounting-guide-database-card accounting-guide-database-card-yellow">
                        <h4 class="accounting-guide-database-title accounting-guide-database-title-yellow">journal_entries</h4>
                        <p class="accounting-guide-database-text">
                            Manual journal entries (double-entry)
                        </p>
                    </div>
                    <div class="accounting-guide-database-card accounting-guide-database-card-teal">
                        <h4 class="accounting-guide-database-title accounting-guide-database-title-teal">journal_entry_lines</h4>
                        <p class="accounting-guide-database-text">
                            Debit/credit lines for journal entries
                        </p>
                    </div>
                    <div class="accounting-guide-database-card accounting-guide-database-card-red">
                        <h4 class="accounting-guide-database-title accounting-guide-database-title-red">entity_transactions</h4>
                        <p class="accounting-guide-database-text">
                            Links transactions to entities (agent, subagent, worker, HR)
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- How to Use -->
        <div class="accounting-guide-section">
            <h2 class="accounting-guide-section-title-large">
                <i class="fas fa-question-circle"></i> How to Use
            </h2>
            <div class="accounting-guide-text-content">
                <h3 class="accounting-guide-subtitle">Step 1: Setup</h3>
                <ol class="accounting-guide-ordered-list">
                    <li>Click "Setup Complete Accounting System" button above</li>
                    <li>This creates all tables and accounts</li>
                    <li>Auto-links existing transactions to accounts</li>
                </ol>
                
                <h3 class="accounting-guide-subtitle">Step 2: Link Transactions</h3>
                <ol class="accounting-guide-ordered-list">
                    <li>Go to "Link Transactions to Accounts" page</li>
                    <li>Select a transaction and an account</li>
                    <li>Click "Link Transaction to Account"</li>
                    <li>Now you can filter by account in General Ledger</li>
                </ol>
                
                <h3 class="accounting-guide-subtitle">Step 3: View General Ledger</h3>
                <ol class="accounting-guide-ordered-list">
                    <li>Open General Ledger from Accounting page</li>
                    <li>Select "All Accounts" to see everything</li>
                    <li>Select a specific account to filter</li>
                    <li>Use date filters and search as needed</li>
                </ol>
            </div>
        </div>
        
        <!-- Current Status -->
        <div id="currentStatus" class="accounting-guide-section">
            <h2 class="accounting-guide-section-title-large">
                <i class="fas fa-chart-line"></i> Current System Status
            </h2>
            <div class="accounting-guide-status-wrapper">
                <p class="accounting-guide-text-content">Loading status...</p>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="accounting-guide-action-buttons">
            <a href="<?php echo pageUrl('accounting.php'); ?>" class="btn btn-secondary accounting-guide-action-button">
                <i class="fas fa-arrow-left"></i> Back to Accounting
            </a>
            <a href="<?php echo pageUrl('link-transactions.php'); ?>" class="btn btn-primary accounting-guide-action-button">
                <i class="fas fa-link"></i> Link Transactions to Accounts
            </a>
            <button id="refreshStatusBtn" class="btn btn-secondary accounting-guide-action-button">
                <i class="fas fa-sync"></i> Refresh Status
            </button>
        </div>
    </div>
</div>

<script src="../js/accounting/accounting-guide.js?v=<?php echo time(); ?>"></script>

<?php include '../includes/footer.php'; ?>

