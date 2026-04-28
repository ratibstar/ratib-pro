/**
 * EN: Implements frontend interaction behavior in `js/accounting/accounting-guide.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/accounting-guide.js`.
 */
// Accounting Guide JavaScript
// Moved from inline script in pages/accounting-guide.php

// Helper function to get base URL
function getBaseUrl() {
    return (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
}

// Helper function to get API URL
function getApiUrl(endpoint) {
    return getBaseUrl() + '/api/accounting/' + endpoint;
}

// Helper function to get page URL
function getPageUrl(page) {
    return getBaseUrl() + '/pages/' + page;
}

document.addEventListener('DOMContentLoaded', async function() {
    await loadStatus();
    
    document.getElementById('setupBtn').addEventListener('click', setupAccounting);
    document.getElementById('autoSetupBtn').addEventListener('click', autoSetupEverything);
    document.getElementById('migrateBtn').addEventListener('click', migrateDebitCredit);
    document.getElementById('checkTablesBtn').addEventListener('click', checkTableStructure);
    document.getElementById('autoLinkBtn').addEventListener('click', autoLinkAllTransactions);
    document.getElementById('recalculateBtn').addEventListener('click', recalculateAllBalances);
    document.getElementById('refreshStatusBtn').addEventListener('click', loadStatus);
});

async function checkTableStructure() {
    const btn = document.getElementById('checkTablesBtn');
    const resultDiv = document.getElementById('setupResult');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    resultDiv.classList.add('error-visible');
    resultDiv.classList.remove('error-hidden');
    resultDiv.innerHTML = '<p class="accounting-guide-text-secondary">Checking table structures...</p>';
    
    try {
        const response = await fetch(getApiUrl('check-table-structure.php'), {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            let html = `
                <div class="accounting-guide-result-box accounting-guide-result-box-blue">
                    <h4><i class="fas fa-table"></i> Table Structure Check</h4>
                    <div class="accounting-guide-result-margin">
                        <p><strong>Summary:</strong></p>
                        <ul class="accounting-guide-result-list-wrap">
                            <li>Tables Checked: ${data.summary.total_tables_checked}</li>
                            <li>Tables Exist: ${data.summary.tables_exist}</li>
                            <li>Tables with Debit Column: ${data.summary.tables_with_debit}</li>
                            <li>Tables with Credit Column: ${data.summary.tables_with_credit}</li>
                        </ul>
                    </div>
                    <div style="margin-top: 20px;">
                        <p><strong>Detailed Table Status:</strong></p>
                        <div style="max-height: 500px; overflow-y: auto; margin-top: 10px;">
            `;
            
            for (const [tableName, tableInfo] of Object.entries(data.tables)) {
                const statusColor = tableInfo.exists ? (tableInfo.has_debit && tableInfo.has_credit ? 'green' : 'orange') : 'red';
                const statusIcon = tableInfo.exists ? (tableInfo.has_debit && tableInfo.has_credit ? '✓' : '⚠') : '✗';
                const statusText = tableInfo.exists ? (tableInfo.has_debit && tableInfo.has_credit ? 'Complete' : 'Missing Debit/Credit') : 'Not Found';
                
                html += `
                    <div class="accounting-guide-table-status-item">
                        <strong class="${statusColor === 'green' ? 'accounting-guide-status-green' : statusColor === 'orange' ? 'accounting-guide-status-orange' : 'accounting-guide-status-red'}">${statusIcon} ${tableName}</strong>
                        <span class="accounting-guide-status-secondary">${statusText}</span>
                        ${tableInfo.exists ? `
                            <div class="accounting-guide-status-col-detail">
                                Columns: ${tableInfo.column_count} | 
                                Has Debit: ${tableInfo.has_debit ? '✓ ' + (tableInfo.debit_column || '') : '✗'} | 
                                Has Credit: ${tableInfo.has_credit ? '✓ ' + (tableInfo.credit_column || '') : '✗'}
                            </div>
                            ${tableInfo.column_names && tableInfo.column_names.length > 0 ? `
                                <div style="margin-top: 5px; font-size: 10px; color: var(--text-muted, #64748b); font-family: monospace;">
                                    Columns: ${tableInfo.column_names.slice(0, 10).join(', ')}${tableInfo.column_names.length > 10 ? '...' : ''}
                                </div>
                            ` : ''}
                            ${!tableInfo.has_debit || !tableInfo.has_credit ? `
                                <div class="accounting-guide-status-warn">
                                    ⚠ Run "Add Debit/Credit Columns" to add missing columns
                                </div>
                            ` : ''}
                        ` : ''}
                    </div>
                `;
            }
            
            html += `
                        </div>
                    </div>
                </div>
            `;
            
            resultDiv.innerHTML = html;
        } else {
            resultDiv.innerHTML = `
                <div class="accounting-guide-result accounting-guide-result-error">
                    <h4 class="small"><i class="fas fa-exclamation-circle"></i> Check Error</h4>
                    <p>${data.message}</p>
                </div>
            `;
        }
    } catch (error) {
        resultDiv.innerHTML = `
            <div class="accounting-guide-result accounting-guide-result-error">
                <h4 class="small"><i class="fas fa-exclamation-circle"></i> Network Error</h4>
                <p>Error: ${error.message}</p>
            </div>
        `;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-search"></i> Check Table Structure';
    }
}

async function migrateDebitCredit() {
    const btn = document.getElementById('migrateBtn');
    const resultDiv = document.getElementById('setupResult');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Migrating...';
    resultDiv.classList.add('error-visible');
    resultDiv.classList.remove('error-hidden');
    resultDiv.innerHTML = '<p class="accounting-guide-text-secondary">Adding debit/credit columns to all accounting tables...</p>';
    
    try {
        const response = await fetch(getApiUrl('migrate-add-debit-credit.php'), {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="accounting-guide-result accounting-guide-result-success">
                    <h4><i class="fas fa-check-circle"></i> Migration Complete!</h4>
                    <p class="accounting-guide-text-large">✅ All tables now have debit/credit columns</p>
                    <ul class="accounting-guide-result-list">
                        ${data.changes.map(change => `<li>${change}</li>`).join('')}
                    </ul>
                </div>
            `;
            await loadStatus();
        } else {
            resultDiv.innerHTML = `
                <div class="accounting-guide-result accounting-guide-result-error">
                    <h4 class="small"><i class="fas fa-exclamation-circle"></i> Migration Error</h4>
                    <p>${data.message}</p>
                    ${data.errors && data.errors.length > 0 ? '<ul class="accounting-guide-result-list">' + data.errors.map(e => `<li>${e}</li>`).join('') + '</ul>' : ''}
                </div>
            `;
        }
    } catch (error) {
        resultDiv.innerHTML = `
            <div class="accounting-guide-result accounting-guide-result-error">
                <h4 class="small"><i class="fas fa-exclamation-circle"></i> Network Error</h4>
                <p>Error: ${error.message}</p>
            </div>
        `;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-database"></i> Add Debit/Credit Columns';
    }
}

async function autoSetupEverything() {
    const btn = document.getElementById('autoSetupBtn');
    const resultDiv = document.getElementById('setupResult');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Auto-Setting Up Everything...';
    resultDiv.classList.add('error-visible');
    resultDiv.classList.remove('error-hidden');
    resultDiv.innerHTML = '<p class="accounting-guide-text-secondary">Setting up tables, accounts, and linking all transactions automatically...</p>';
    
    try {
        const response = await fetch(getApiUrl('auto-setup-all.php'), {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="accounting-guide-result accounting-guide-result-success">
                    <h4><i class="fas fa-check-circle"></i> Automatic Setup Complete!</h4>
                    <div class="accounting-guide-result-content">
                        <p class="accounting-guide-text-large">✅ Everything is now automated!</p>
                        <ul class="accounting-guide-result-list">
                            <li><strong>Tables:</strong> ${data.tables.length} tables ready</li>
                            <li><strong>Accounts:</strong> ${data.accountsCreated} accounts created</li>
                            <li><strong>Transactions:</strong> ${data.transactionsLinked} transactions auto-linked</li>
                        </ul>
                    </div>
                    <div class="accounting-guide-info-box">
                        <p>🎉 Your accounting system is now fully automatic!</p>
                        <p class="small">
                            All new transactions will be automatically linked to accounts based on entity type (agent, subagent, worker, HR).
                        </p>
                    </div>
                </div>
            `;
            await loadStatus();
        } else {
            let errorMsg = data.message || 'Unknown error';
            resultDiv.innerHTML = `
                <div class="accounting-guide-result accounting-guide-result-error">
                    <h4 class="small"><i class="fas fa-exclamation-circle"></i> Auto-Setup Error</h4>
                    <p>${errorMsg}</p>
                    ${data.errors && data.errors.length > 0 ? '<ul class="accounting-guide-result-list">' + data.errors.map(e => `<li>${e}</li>`).join('') + '</ul>' : ''}
                </div>
            `;
        }
    } catch (error) {
        resultDiv.innerHTML = `
            <div class="accounting-guide-result accounting-guide-result-error">
                <h4 class="small"><i class="fas fa-exclamation-circle"></i> Network Error</h4>
                <p>Error: ${error.message}</p>
            </div>
        `;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-robot"></i> Auto-Setup Everything';
    }
}

async function recalculateAllBalances() {
    const btn = document.getElementById('recalculateBtn');
    const resultDiv = document.getElementById('setupResult');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recalculating...';
    resultDiv.classList.add('error-visible');
    resultDiv.classList.remove('error-hidden');
    resultDiv.innerHTML = '<p class="accounting-guide-text-secondary">Recalculating all balances and totals...</p>';
    
    try {
        const response = await fetch(getApiUrl('recalculate-all-balances.php'), {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            let resultsHtml = '';
            if (data.results && data.results.length > 0) {
                resultsHtml = '<ul class="accounting-guide-result-list">';
                data.results.forEach(result => {
                    resultsHtml += `<li>${result}</li>`;
                });
                resultsHtml += '</ul>';
            }
            
            let summaryHtml = '';
            if (data.summary) {
                summaryHtml = `<div class="accounting-guide-summary-box">
                    <strong>Summary:</strong> ${data.summary.successful} successful, ${data.summary.failed} failed out of ${data.summary.total_operations} operations
                </div>`;
            }
            
            resultDiv.innerHTML = `
                <div class="accounting-guide-result accounting-guide-result-purple">
                    <h4 class="small"><i class="fas fa-check-circle"></i> Recalculation Complete!</h4>
                    <p>All balances and totals have been recalculated successfully.</p>
                    ${resultsHtml}
                    ${summaryHtml}
                </div>
            `;
            await loadStatus();
        } else {
            let errorHtml = data.message || 'Unknown error';
            if (data.errors && data.errors.length > 0) {
                errorHtml += '<ul class="accounting-guide-result-list">';
                data.errors.forEach(error => {
                    errorHtml += `<li>${error}</li>`;
                });
                errorHtml += '</ul>';
            }
            
            resultDiv.innerHTML = `
                <div class="accounting-guide-result accounting-guide-result-error">
                    <h4 class="small"><i class="fas fa-exclamation-circle"></i> Recalculation Error</h4>
                    <p>${errorHtml}</p>
                </div>
            `;
        }
    } catch (error) {
        resultDiv.innerHTML = `
            <div class="accounting-guide-result accounting-guide-result-error">
                <h4 class="small"><i class="fas fa-exclamation-circle"></i> Network Error</h4>
                <p>Error: ${error.message}</p>
            </div>
        `;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-calculator"></i> Recalculate All Balances';
    }
}

async function autoLinkAllTransactions() {
    const btn = document.getElementById('autoLinkBtn');
    const resultDiv = document.getElementById('setupResult');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Linking...';
    resultDiv.classList.add('error-visible');
    resultDiv.classList.remove('error-hidden');
    resultDiv.innerHTML = '<p class="accounting-guide-text-secondary">Linking all transactions to accounts...</p>';
    
    try {
        const response = await fetch(getApiUrl('auto-link-all-transactions.php'), {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="accounting-guide-result accounting-guide-result-success">
                    <h4 class="small"><i class="fas fa-check-circle"></i> Auto-Linking Successful!</h4>
                    <p class="accounting-guide-text-large">
                        ${data.linked} transactions linked to accounts
                    </p>
                    ${data.skipped > 0 ? `<p class="accounting-guide-skipped">${data.skipped} transactions skipped</p>` : ''}
                    ${data.errors && data.errors.length > 0 ? `
                        <details class="accounting-guide-details">
                            <summary>View errors (${data.errors.length})</summary>
                            <ul class="accounting-guide-result-list">
                                ${data.errors.slice(0, 10).map(e => `<li>${e}</li>`).join('')}
                            </ul>
                        </details>
                    ` : ''}
                    <p style="margin-top: 15px; font-size: 0.9em;">
                        Now you can filter transactions by account in the General Ledger!
                    </p>
                </div>
            `;
            await loadStatus();
        } else {
            let errorMsg = data.message || 'Unknown error';
            let debugInfo = '';
            if (data.debug) {
                debugInfo = `<div class="accounting-guide-debug-info">
                    <strong>Debug Info:</strong><br>
                    Session ID: ${data.debug.session_id || 'N/A'}<br>
                    Has User ID: ${data.debug.has_user_id ? 'Yes' : 'No'}<br>
                    Has Logged In: ${data.debug.has_logged_in ? 'Yes' : 'No'}<br>
                    Logged In Value: ${data.debug.logged_in_value || 'N/A'}<br>
                    ${data.debug.user_id_value ? `User ID Value: ${data.debug.user_id_value}` : ''}
                </div>`;
            }
            
            resultDiv.innerHTML = `
                <div class="accounting-guide-result accounting-guide-result-error">
                    <h4 class="small"><i class="fas fa-exclamation-circle"></i> Auto-Linking Error</h4>
                    <p>${errorMsg}</p>
                    ${debugInfo}
                    <p style="margin-top: 15px; font-size: 0.9em;">
                        <strong>Solution:</strong> Please make sure you are logged in, then refresh this page and try again.
                        <br><br>
                        <a href="${getPageUrl('login.php')}" style="color: var(--accent-blue, #3b82f6); text-decoration: underline;">
                            Click here to log in
                        </a>
                    </p>
                </div>
            `;
        }
    } catch (error) {
        resultDiv.innerHTML = `
            <div class="accounting-guide-result accounting-guide-result-error">
                <h4 class="small"><i class="fas fa-exclamation-circle"></i> Network Error</h4>
                <p>Error: ${error.message}</p>
            </div>
        `;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-link"></i> Auto-Link All Transactions';
    }
}

async function setupAccounting() {
    const btn = document.getElementById('setupBtn');
    const resultDiv = document.getElementById('setupResult');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Setting up...';
    resultDiv.classList.add('error-visible');
    resultDiv.classList.remove('error-hidden');
    resultDiv.innerHTML = '<p class="accounting-guide-text-secondary">Setting up accounting system...</p>';
    
    try {
        // Ensure credentials are included for session cookies
        const response = await fetch(getApiUrl('setup-professional-accounting.php'), {
            method: 'GET',
            credentials: 'include', // Important: include cookies
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="accounting-guide-result-box-green">
                    <h4><i class="fas fa-check-circle"></i> Setup Successful!</h4>
                    <ul class="accounting-guide-result-list-wrap">
                        ${data.created.map(item => `<li>${item}</li>`).join('')}
                        ${data.linked ? data.linked.map(item => `<li>${item}</li>`).join('') : ''}
                    </ul>
                    <p style="margin-top: 15px; font-weight: 600;">${data.accountsAdded} accounts ready to use!</p>
                </div>
            `;
            await loadStatus();
        } else {
            let errorMsg = data.message || 'Unknown error';
            let debugInfo = '';
            if (data.debug) {
                debugInfo = `<div class="accounting-guide-debug-info">
                    <strong>Debug Info:</strong><br>
                    Session ID: ${data.debug.session_id || 'N/A'}<br>
                    Has User ID: ${data.debug.has_user_id ? 'Yes' : 'No'}<br>
                    Has Logged In: ${data.debug.has_logged_in ? 'Yes' : 'No'}<br>
                    Logged In Value: ${data.debug.logged_in_value || 'N/A'}<br>
                    ${data.debug.user_id_value ? `User ID Value: ${data.debug.user_id_value}` : ''}
                </div>`;
            }
            
            resultDiv.innerHTML = `
                <div class="accounting-guide-result-box-red">
                    <h4><i class="fas fa-exclamation-circle"></i> Setup Error</h4>
                    <p>${errorMsg}</p>
                    ${debugInfo}
                    ${data.errors ? '<ul class="accounting-guide-result-list-wrap">' + data.errors.map(e => `<li>${e}</li>`).join('') + '</ul>' : ''}
                    <p style="margin-top: 15px; font-size: 0.9em;">
                        <strong>Solution:</strong> Please make sure you are logged in, then refresh this page and try again.
                        <br><br>
                        <a href="${getPageUrl('login.php')}" style="color: var(--accent-blue, #3b82f6); text-decoration: underline;">
                            Click here to log in
                        </a>
                    </p>
                </div>
            `;
        }
    } catch (error) {
        resultDiv.innerHTML = `
            <div style="padding: 15px; background: rgba(239, 68, 68, 0.1); border: 1px solid var(--accent-red, #ef4444); 
                        border-radius: 6px; color: var(--accent-red, #ef4444);">
                <h4 style="margin-top: 0;"><i class="fas fa-exclamation-circle"></i> Network Error</h4>
                <p>Error: ${error.message}</p>
                <p style="margin-top: 10px; font-size: 0.9em;">
                    Please check your internet connection and try again.
                </p>
            </div>
        `;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-magic"></i> Setup Complete Accounting System';
    }
}

async function loadStatus() {
    try {
        // Load status from link-transactions API
        const response = await fetch(getApiUrl('link-transactions-to-accounts.php'));
        const data = await response.json();
        
        // Also check table structure for debit/credit columns
        const structureResponse = await fetch(getApiUrl('check-table-structure.php'));
        const structureData = await structureResponse.json();
        
        if (data.success) {
            const info = data.info;
            let html = `
                <div class="accounting-guide-result-box accounting-guide-result-box-blue">
                    <h4><i class="fas fa-info-circle"></i> Current Status</h4>
                    <div class="accounting-guide-result-margin">
                        <p><strong>Account Linking:</strong></p>
                        <ul class="accounting-guide-result-list-wrap">
                            <li>Total Transactions: ${info.total_transactions || 0}</li>
                            <li>Linked Transactions: ${info.linked_transactions || 0}</li>
                            <li>Unlinked Transactions: ${info.unlinked_transactions || 0}</li>
                            <li>Link Percentage: ${info.link_percentage || 0}%</li>
                        </ul>
                    </div>
            `;
            
            if (structureData.success && structureData.summary) {
                html += `
                    <div style="margin-top: 20px;">
                        <p><strong>Table Structure:</strong></p>
                        <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                            <li>Tables Checked: ${structureData.summary.total_tables_checked}</li>
                            <li>Tables with Debit/Credit: ${structureData.summary.tables_with_debit}</li>
                        </ul>
                    </div>
                `;
            }
            
            html += `</div>`;
            
            const statusDiv = document.getElementById('currentStatus');
            if (statusDiv) {
                statusDiv.querySelector('div').innerHTML = html;
            }
        }
    } catch (error) {
        console.error('Error loading status:', error);
        const statusDiv = document.getElementById('currentStatus');
        if (statusDiv) {
            statusDiv.querySelector('div').innerHTML =
                '<p class="accounting-guide-text-muted">Error loading status. Please refresh the page.</p>';
        }
    }
}

