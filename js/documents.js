/**
 * EN: Implements frontend interaction behavior in `js/documents.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/documents.js`.
 */
// Documents Management JavaScript

// Helper function to get API base URL
function getApiBase() {
    return (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '');
}

// Helper function to get base URL
function getBaseUrl() {
    return (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
}

// Global functions that need to be accessible from onclick handlers
function showDocumentModal(documentId) {
    // Create document viewer modal
    const modal = document.createElement('div');
    modal.id = 'documentViewerModal';
    modal.className = 'modal show';
    modal.innerHTML = `
        <div class="modal-content modal-90">
            <div class="modal-header">
                <h2>📄 Document Viewer</h2>
                <span class="close" data-action="close-document-viewer">&times;</span>
            </div>
            <div class="modal-body">
                <div id="documentContent" class="doc-content-center">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i> Loading document...
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add event listener for close button
    const closeBtn = modal.querySelector('[data-action="close-document-viewer"]');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeDocumentViewer);
    }
    
    // Load document content
    loadDocumentContent(documentId);
}

async function loadDocumentContent(documentId) {
    const container = document.getElementById('documentContent');
    
    try {
        const response = await fetch(`${getApiBase()}/view-document.php?id=${documentId}`);
        const responseText = await response.text();
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            throw new Error('Server returned invalid JSON');
        }
        
        if (data.success && data.document) {
            const doc = data.document;
            container.innerHTML = `
                <div class="document-viewer">
                    <div class="document-header">
                        <h3>${doc.title}</h3>
                        <p><strong>Type:</strong> ${doc.document_type}</p>
                        <p><strong>Employee:</strong> ${doc.employee_name || 'N/A'}</p>
                        <p><strong>Department:</strong> ${doc.department}</p>
                        <p><strong>Issue Date:</strong> ${doc.issue_date}</p>
                        <p><strong>Status:</strong> <span class="status-badge ${doc.status}">${doc.status}</span></p>
                    </div>
                    <div class="document-preview">
                        ${doc.file_path ? `
                            <iframe src="${getBaseUrl()}/${doc.file_path}" class="doc-iframe"></iframe>
                        ` : `
                            <div class="no-preview">
                                <i class="fas fa-file"></i>
                                <p>No preview available</p>
                                <a href="${getBaseUrl()}/${doc.file_path}" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-download"></i> Download Document
                                </a>
                            </div>
                        `}
                    </div>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Document not found or error loading document</p>
                    <p>${data.message || 'Unknown error'}</p>
                </div>
            `;
        }
    } catch (error) {
        container.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Error loading document</p>
            </div>
        `;
    }
}

function closeDocumentViewer() {
    const modal = document.getElementById('documentViewerModal');
    if (modal) {
        modal.remove();
    }
}

function viewDocument(documentId) {
    showDocumentModal(documentId);
}

// Documents Modal Functions
function openDocumentsModal() {
    const modal = document.getElementById('documentsModal');
    if (modal) {
        modal.classList.add('show');
        modal.classList.remove('hidden', 'd-none');
        loadEmployees();
        loadDocumentsList();
    }
}

function closeDocumentsModal() {
    const modal = document.getElementById('documentsModal');
    if (modal) {
        modal.classList.remove('show');
        modal.classList.add('hidden', 'd-none');
    }
}

function switchDocumentsTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById(tabName + 'Tab').classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
    
    // Load data for the tab
    if (tabName === 'list') {
        loadDocumentsList();
    }
}

// Load employees for dropdown
async function loadEmployees() {
    try {
        const response = await fetch(getApiBase() + '/hr/employees.php?action=list');
        const data = await response.json();
        
        const select = document.getElementById('employee_id');
        select.innerHTML = '<option value="">Select Employee</option>';
        
        if (data.success && data.data) {
            data.data.forEach(employee => {
                const option = document.createElement('option');
                option.value = employee.id;
                option.textContent = employee.name || employee.employee_name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading employees:', error);
    }
}

// Load documents list
async function loadDocumentsList() {
    const container = document.getElementById('documentsList');
    container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading documents...</div>';
    
    try {
        const response = await fetch(getApiBase() + '/hr/documents.php?action=list&limit=10');
        const data = await response.json();
        
        if (data.success && data.data && data.data.length > 0) {
            container.innerHTML = data.data.map(doc => `
                <div class="document-item">
                    <div class="document-info">
                        <h4>${doc.title}</h4>
                        <p><strong>Type:</strong> ${doc.document_type}</p>
                        <p><strong>Employee:</strong> ${doc.employee_name || 'N/A'}</p>
                        <p><strong>Department:</strong> ${doc.department}</p>
                        <p><strong>Issue Date:</strong> ${doc.issue_date}</p>
                        <p><strong>Status:</strong> <span class="status-badge ${doc.status}">${doc.status}</span></p>
                    </div>
                    <div class="document-actions">
                        <button class="btn btn-sm btn-primary" data-action="view-document" data-document-id="${doc.id}">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn btn-sm btn-danger" data-action="delete-document" data-document-id="${doc.id}">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<div class="no-documents"><i class="fas fa-file"></i><p>No documents found</p></div>';
        }
    } catch (error) {
        console.error('Error loading documents:', error);
        container.innerHTML = '<div class="error-message"><i class="fas fa-exclamation-triangle"></i><p>Error loading documents</p></div>';
    }
}

function refreshDocumentsList() {
    loadDocumentsList();
}

// Delete document function
async function deleteDocument(documentId) {
    if (!confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch(`${getApiBase()}/hr/documents.php?action=delete&id=${documentId}`, {
            method: 'GET'
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Document deleted successfully
            loadDocumentsList(); // Refresh the list
        } else {
            console.error('Error: ' + (result.message || 'Failed to delete document'));
        }
    } catch (error) {
        console.error('Error:', error);
        console.error('Error deleting document');
    }
}

// Initialize document functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle document form submission
    const documentsForm = document.getElementById('documentsForm');
    if (documentsForm) {
        documentsForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(documentsForm);
            const submitBtn = documentsForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch(getApiBase() + '/hr/documents.php?action=add', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Document uploaded successfully
                    documentsForm.reset();
                    loadDocumentsList();
                } else {
                    console.error('Error: ' + (result.message || 'Failed to upload document'));
                }
            } catch (error) {
                console.error('Error:', error);
                console.error('Error uploading document');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    }

    // Handle document action buttons (event delegation for dynamically created buttons)
    document.addEventListener('click', function(e) {
        const viewBtn = e.target.closest('[data-action="view-document"]');
        const deleteBtn = e.target.closest('[data-action="delete-document"]');
        
        if (viewBtn) {
            const documentId = viewBtn.getAttribute('data-document-id');
            if (documentId) {
                viewDocument(parseInt(documentId));
            }
        }
        
        if (deleteBtn) {
            const documentId = deleteBtn.getAttribute('data-document-id');
            if (documentId) {
                deleteDocument(parseInt(documentId));
            }
        }
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const documentsModal = document.getElementById('documentsModal');
        const documentViewerModal = document.getElementById('documentViewerModal');
        
        if (event.target === documentsModal) {
            closeDocumentsModal();
        }
        
        if (event.target === documentViewerModal) {
            closeDocumentViewer();
        }
    });
});
