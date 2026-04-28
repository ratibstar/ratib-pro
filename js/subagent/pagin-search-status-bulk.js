/**
 * EN: Implements frontend interaction behavior in `js/subagent/pagin-search-status-bulk.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/subagent/pagin-search-status-bulk.js`.
 */
// Global functions for bulk actions
function activateSelected() {
  // Collect selected subagent IDs from checkboxes
  const selectedCheckboxes = document.querySelectorAll('.subagent-checkbox:checked');
  const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);

  // Check if any subagents are selected
  if (selectedIds.length === 0) {
    if (window.subagentManager) {
      window.subagentManager.showWarning('Please select at least one subagent to activate.', 'No Selection');
    } else {
      alert('Please select at least one subagent to activate.');
    }
    return;
  }

  // Confirm action
  if (!confirm(`Are you sure you want to activate ${selectedIds.length} subagent(s)?`)) {
    return;
  }

  // Retrieve existing subagents from localStorage
  let subagents = JSON.parse(localStorage.getItem("subagents")) || 
                  (window.formHandler && window.formHandler.getSubagents()) || 
                  [];
  
  // Update status for selected subagents
  subagents = subagents.map(sub => {
    if (selectedIds.includes(sub.id)) {
      sub.status = "active";
    }
    return sub;
  });
  
  // Save updated subagents to localStorage
  localStorage.setItem("subagents", JSON.stringify(subagents));
  
  // Update form handler if exists
  if (window.formHandler && window.formHandler.updateSubagents) {
    window.formHandler.updateSubagents(subagents);
  }
  
  // Refresh the table display
  if (window.paginSearchStatusBulk) {
    window.paginSearchStatusBulk.displaySubagents();
  }
  
  // Update stats
  updateSubagentStats();

  // Uncheck all checkboxes
  selectedCheckboxes.forEach(cb => cb.checked = false);

  // Show success alert
  if (window.subagentManager) {
    window.subagentManager.showSuccess(`Successfully activated ${selectedIds.length} subagent(s).`, 'Success');
  } else {
    alert(`Successfully activated ${selectedIds.length} subagent(s).`);
  }
}

function deactivateSelected() {
  // Collect selected subagent IDs from checkboxes
  const selectedCheckboxes = document.querySelectorAll('.subagent-checkbox:checked');
  const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);

  // Check if any subagents are selected
  if (selectedIds.length === 0) {
    if (window.subagentManager) {
      window.subagentManager.showWarning('Please select at least one subagent to deactivate.', 'No Selection');
    } else {
      alert('Please select at least one subagent to deactivate.');
    }
    return;
  }

  // Confirm action
  if (!confirm(`Are you sure you want to deactivate ${selectedIds.length} subagent(s)?`)) {
    return;
  }

  // Retrieve existing subagents from localStorage
  let subagents = JSON.parse(localStorage.getItem("subagents")) || 
                  (window.formHandler && window.formHandler.getSubagents()) || 
                  [];
  
  // Update status for selected subagents
  subagents = subagents.map(sub => {
    if (selectedIds.includes(sub.id)) {
      sub.status = "inactive";
    }
    return sub;
  });
  
  // Save updated subagents to localStorage
  localStorage.setItem("subagents", JSON.stringify(subagents));
  
  // Update form handler if exists
  if (window.formHandler && window.formHandler.updateSubagents) {
    window.formHandler.updateSubagents(subagents);
  }
  
  // Refresh the table display
  if (window.paginSearchStatusBulk) {
    window.paginSearchStatusBulk.displaySubagents();
  }
  
  // Update stats
  updateSubagentStats();

  // Uncheck all checkboxes
  selectedCheckboxes.forEach(cb => cb.checked = false);

  // Show success alert
  if (window.subagentManager) {
    window.subagentManager.showSuccess(`Successfully deactivated ${selectedIds.length} subagent(s).`, 'Success');
  } else {
    alert(`Successfully deactivated ${selectedIds.length} subagent(s).`);
  }
}

function deleteSelected() {
  // Collect selected subagent IDs from checkboxes
  const selectedCheckboxes = document.querySelectorAll('.subagent-checkbox:checked');
  const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);

  // Check if any subagents are selected
  if (selectedIds.length === 0) {
    if (window.subagentManager) {
      window.subagentManager.showWarning('Please select at least one subagent to delete.', 'No Selection');
    } else {
      alert('Please select at least one subagent to delete.');
    }
    return;
  }

  // Confirm action
  if (!confirm(`Are you sure you want to delete ${selectedIds.length} subagent(s)? This action cannot be undone.`)) {
    return;
  }

  // Retrieve existing subagents from localStorage
  let subagents = JSON.parse(localStorage.getItem("subagents")) || 
                  (window.formHandler && window.formHandler.getSubagents()) || 
                  [];
  
  // Remove selected subagents
  subagents = subagents.filter(sub => !selectedIds.includes(sub.id));
  
  // Save updated subagents to localStorage
  localStorage.setItem("subagents", JSON.stringify(subagents));
  
  // Update form handler if exists
  if (window.formHandler && window.formHandler.updateSubagents) {
    window.formHandler.updateSubagents(subagents);
  }
  
  // Refresh the table display
  if (window.paginSearchStatusBulk) {
    window.paginSearchStatusBulk.displaySubagents();
  }
  
  // Update stats
  updateSubagentStats();

  // Show success alert
  if (window.subagentManager) {
    window.subagentManager.showSuccess(`Successfully deleted ${selectedIds.length} subagent(s).`, 'Success');
  } else {
    alert(`Successfully deleted ${selectedIds.length} subagent(s).`);
  }
}

function updateSubagentStats() {
  // Retrieve subagents from localStorage
  const subagents = JSON.parse(localStorage.getItem("subagents")) || 
                    (window.formHandler && window.formHandler.getSubagents()) || 
                    [];
  
  // Get total counts - Remove the Math.min() that was limiting to 5
  const totalCount = subagents.length;
  const activeCount = subagents.filter(sub => sub.status === 'active').length;
  const inactiveCount = subagents.filter(sub => sub.status === 'inactive').length;

  // Update DOM elements
  const totalCountEl = document.getElementById('totalCount');
  const activeCountEl = document.getElementById('activeCount');
  const inactiveCountEl = document.getElementById('inactiveCount');

  if (totalCountEl) totalCountEl.textContent = totalCount;
  if (activeCountEl) activeCountEl.textContent = activeCount;
  if (inactiveCountEl) inactiveCountEl.textContent = inactiveCount;

  // Update form handler if exists
  if (window.formHandler && window.formHandler.updateStats) {
    window.formHandler.updateStats({
      total: totalCount,
      active: activeCount,
      inactive: inactiveCount
    });
  }
}

function updateBulkActionButtonState() {
  const selectedCheckboxes = document.querySelectorAll('.subagent-checkbox:checked');
  const bulkActionButtons = document.querySelectorAll('.bulk-btn');
  const selectAllCheckbox = document.querySelector('#selectAll');
  
  const hasSelected = selectedCheckboxes.length > 0;
  
  bulkActionButtons.forEach(btn => {
    btn.disabled = !hasSelected;
    btn.classList.toggle('disabled', !hasSelected);
  });

  // Update select all checkbox state if all checkboxes are selected
  if (selectAllCheckbox) {
    const allCheckboxes = document.querySelectorAll('.subagent-checkbox');
    selectAllCheckbox.checked = selectedCheckboxes.length === allCheckboxes.length;
  }
}

function toggleSelectAll(checkbox) {
  const checkboxes = document.querySelectorAll('.subagent-checkbox');
  
  checkboxes.forEach(cb => {
    cb.checked = checkbox.checked;
  });

  // Update bulk action button state
  updateBulkActionButtonState();
}

// Add this to your JavaScript file
function toggleAll() {
    const selectAllCheckbox = document.getElementById('selectAllSubagents');
    const checkboxes = document.querySelectorAll('.subagent-table tbody input[type="checkbox"]');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    // Update bulk action buttons state
    updateBulkActionButtons();
}

function updateBulkActionButtons() {
    const checkboxes = document.querySelectorAll('.subagent-table tbody input[type="checkbox"]:checked');
    const bulkButtons = document.querySelectorAll('.bulk-btn');
    
    bulkButtons.forEach(button => {
        button.disabled = checkboxes.length === 0;
    });
}

// Add event listeners to individual checkboxes
document.querySelectorAll('.subagent-table tbody input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', updateBulkActionButtons);
});

// IIFE to set up event listeners and initialization
(function() {
  const paginSearchStatusBulk = {
    formState: {
      currentPage: 1,
      perPage: 5,  // Default to 10 items per page
      searchTerm: "",
      statusFilter: "all"
    },

    // Add pagination state
    pagination: {
        currentPage: 1,
        itemsPerPage: 5,
        totalItems: 0
    },

    init() {
      this.initEntriesSelect();
      this.initSearch();
      this.initStatusFilter();
      this.displaySubagents();
      this.updateSubagentStats();
      this.initPagination();
    },

    displaySubagents() {
      const tbody = document.querySelector('#subagentTableBody');
      if (!tbody) {
        return;
      }

      // Get subagents
      let subagents = JSON.parse(localStorage.getItem('subagents')) || [];
      
      // Sort by ID in descending order
      subagents.sort((a, b) => Number(b.id) - Number(a.id));

      // Apply filters
      let filteredSubagents = [...subagents]; // Create a copy for filtering

      // Status filter
      if (this.formState.statusFilter !== 'all') {
        filteredSubagents = filteredSubagents.filter(sub => 
          sub.status === this.formState.statusFilter
        );
      }

      // Search filter
      if (this.formState.searchTerm) {
        const searchTerm = this.formState.searchTerm.toLowerCase();
        filteredSubagents = filteredSubagents.filter(sub => 
          sub.name.toLowerCase().includes(searchTerm) ||
          sub.email.toLowerCase().includes(searchTerm) ||
          sub.phone.toLowerCase().includes(searchTerm) ||
          sub.city.toLowerCase().includes(searchTerm) ||
          (sub.address && sub.address.toLowerCase().includes(searchTerm))
        );
      }

      // Pagination
      const totalItems = filteredSubagents.length;
      const totalPages = Math.ceil(totalItems / this.formState.perPage);
      const startIndex = (this.formState.currentPage - 1) * this.formState.perPage;
      const endIndex = startIndex + this.formState.perPage;
      const paginatedSubagents = filteredSubagents.slice(startIndex, endIndex);

      // Clear table
      tbody.innerHTML = '';

      // Display subagents
      paginatedSubagents.forEach(subagent => {
        const row = `
          <tr>
            <td>${subagent.formatted_id || subagent.id}</td>
            <td>${subagent.name}</td>
            <td>${subagent.email}</td>
            <td>${subagent.phone}</td>
            <td>${subagent.city}</td>
            <td>${subagent.address}</td>
            <td>${subagent.agent_name}</td>
            <td>
              <span class="status-badge ${subagent.status.toLowerCase()}">
                ${subagent.status}
              </span>
            </td>
            <td>
              <input type="checkbox" class="subagent-checkbox" value="${subagent.id}">
            </td>
            <td class="actions">
              <button data-action="view-subagent" data-subagent-id="${subagent.id}" class="btn btn-info btn-sm" title="View Details">
                <i class="fas fa-eye"></i>
              </button>
              <button data-action="edit-subagent" data-subagent-id="${subagent.id}" class="btn btn-success btn-sm" title="Edit">
                <i class="fas fa-edit"></i>
              </button>
              <button data-action="show-account-modal" data-subagent-id="${subagent.id}" class="btn btn-warning btn-sm" title="Account Settings">
                <i class="fas fa-user-cog"></i>
              </button>
              <button data-action="delete-subagent" data-subagent-id="${subagent.id}" class="btn btn-danger btn-sm" title="Delete">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
        `;
        tbody.insertAdjacentHTML('beforeend', row);
        
        // Add event listeners for action buttons (replaces inline onclick handlers)
        const lastRow = tbody.lastElementChild;
        if (lastRow) {
            lastRow.querySelectorAll('[data-action="view-subagent"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const subagentId = this.getAttribute('data-subagent-id');
                    if (paginSearchStatusBulk && typeof paginSearchStatusBulk.viewSubagent === 'function') {
                        paginSearchStatusBulk.viewSubagent(subagentId);
                    }
                });
            });
            
            lastRow.querySelectorAll('[data-action="edit-subagent"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const subagentId = this.getAttribute('data-subagent-id');
                    if (paginSearchStatusBulk && typeof paginSearchStatusBulk.editSubagent === 'function') {
                        paginSearchStatusBulk.editSubagent(subagentId);
                    }
                });
            });
            
            lastRow.querySelectorAll('[data-action="show-account-modal"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const subagentId = this.getAttribute('data-subagent-id');
                    if (paginSearchStatusBulk && typeof paginSearchStatusBulk.showAccountModal === 'function') {
                        paginSearchStatusBulk.showAccountModal(subagentId);
                    }
                });
            });
            
            lastRow.querySelectorAll('[data-action="delete-subagent"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const subagentId = this.getAttribute('data-subagent-id');
                    if (paginSearchStatusBulk && typeof paginSearchStatusBulk.deleteSubagent === 'function') {
                        paginSearchStatusBulk.deleteSubagent(subagentId);
                    }
                });
            });
        }
      });

      // Update pagination controls
      this.updatePaginationControls(totalPages, totalItems);
    },

    updatePaginationControls(totalPages, totalItems) {
      const paginationContainers = document.querySelectorAll('.pagination');
      if (!paginationContainers.length) return;

      const startItem = ((this.formState.currentPage - 1) * this.formState.perPage) + 1;
      const endItem = Math.min(startItem + this.formState.perPage - 1, totalItems);
      const currentPage = this.formState.currentPage;

      // Create page numbers HTML with moderation
      let pageNumbersHtml = '';
      
      // First page
      if (totalPages > 0) {
          pageNumbersHtml += `
              <button data-action="go-to-page" data-page="1" 
                      class="btn ${currentPage === 1 ? 'btn-primary' : 'btn-secondary'}">
                  1
              </button>
          `;
      }

      // Ellipsis after first page
      if (currentPage > 3) {
          pageNumbersHtml += '<span class="mx-1">•••</span>';
      }

      // Pages around current
      for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) {
          if (i === 1 || i === totalPages) continue;
          pageNumbersHtml += `
              <button data-action="go-to-page" data-page="${i}" 
                      class="btn ${i === currentPage ? 'btn-primary' : 'btn-secondary'}">
                  ${i}
              </button>
          `;
      }

      // Ellipsis before last page
      if (currentPage < totalPages - 2) {
          pageNumbersHtml += '<span class="mx-1">•••</span>';
      }

      // Last page
      if (totalPages > 1) {
          pageNumbersHtml += `
              <button data-action="go-to-page" data-page="${totalPages}" 
                      class="btn ${currentPage === totalPages ? 'btn-primary' : 'btn-secondary'}">
                  ${totalPages}
              </button>
          `;
      }

      const paginationHtml = `
          <div class="d-flex justify-content-between align-items-center">
              <div>
                  <span>Show</span>
                  <select class="entries-select" data-action="update-entries-per-page">
                      <option value="5" ${this.formState.perPage === 5 ? 'selected' : ''}>5</option>
                      <option value="10" ${this.formState.perPage === 10 ? 'selected' : ''}>10</option>
                      <option value="25" ${this.formState.perPage === 25 ? 'selected' : ''}>25</option>
                      <option value="50" ${this.formState.perPage === 50 ? 'selected' : ''}>50</option>
                      <option value="100" ${this.formState.perPage === 100 ? 'selected' : ''}>100</option>
                  </select>
                  <span>entries</span>
              </div>
              <div class="entries-info">
                  Showing ${startItem} to ${endItem} of ${totalItems} entries
              </div>
              <div>
                  <button data-action="prev-page" 
                          class="btn btn-secondary ${currentPage === 1 ? 'disabled' : ''}"
                          ${currentPage === 1 ? 'disabled' : ''}>
                      Previous
                  </button>
                  ${pageNumbersHtml}
                  <button data-action="next-page" 
                          class="btn btn-secondary ${currentPage >= totalPages ? 'disabled' : ''}"
                          ${currentPage >= totalPages ? 'disabled' : ''}>
                      Next
                  </button>
              </div>
          </div>
      `;

      paginationContainers.forEach(container => {
          container.innerHTML = paginationHtml;
          
          // Add event listeners for pagination buttons (replaces inline onclick handlers)
          container.querySelectorAll('[data-action="go-to-page"]').forEach(btn => {
              btn.addEventListener('click', function() {
                  const page = parseInt(this.getAttribute('data-page'));
                  if (paginSearchStatusBulk && typeof paginSearchStatusBulk.goToPage === 'function') {
                      paginSearchStatusBulk.goToPage(page);
                  }
              });
          });
          
          container.querySelectorAll('[data-action="prev-page"]').forEach(btn => {
              btn.addEventListener('click', function() {
                  if (!this.disabled && paginSearchStatusBulk && typeof paginSearchStatusBulk.prevPage === 'function') {
                      paginSearchStatusBulk.prevPage();
                  }
              });
          });
          
          container.querySelectorAll('[data-action="next-page"]').forEach(btn => {
              btn.addEventListener('click', function() {
                  if (!this.disabled && paginSearchStatusBulk && typeof paginSearchStatusBulk.nextPage === 'function') {
                      paginSearchStatusBulk.nextPage();
                  }
              });
          });
          
          container.querySelectorAll('[data-action="update-entries-per-page"]').forEach(select => {
              select.addEventListener('change', function() {
                  const value = this.value;
                  if (paginSearchStatusBulk && typeof paginSearchStatusBulk.updateEntriesPerPage === 'function') {
                      paginSearchStatusBulk.updateEntriesPerPage(value);
                  }
              });
          });
      });
    },

    initEntriesSelect() {
      const entriesSelect = document.querySelector('.entries-select');
      if (entriesSelect) {
        entriesSelect.value = this.formState.perPage;
        entriesSelect.addEventListener('change', (e) => {
          this.formState.perPage = parseInt(e.target.value);
          this.formState.currentPage = 1;
          this.displaySubagents();
        });
      }
    },

    initSearch() {
      const searchInput = document.querySelector('#subagentSearch');
      if (searchInput) {
        searchInput.value = this.formState.searchTerm;
        searchInput.addEventListener('input', (e) => {
          this.formState.searchTerm = e.target.value;
          this.formState.currentPage = 1;
          this.displaySubagents();
        });
      }
    },

    initStatusFilter() {
      const statusFilter = document.querySelector('#statusFilter');
      if (statusFilter) {
        statusFilter.value = this.formState.statusFilter;
        statusFilter.addEventListener('change', (e) => {
          this.formState.statusFilter = e.target.value;
          this.formState.currentPage = 1;
          this.displaySubagents();
        });
      }
    },

    prevPage() {
      if (this.formState.currentPage > 1) {
        this.formState.currentPage--;
        this.displaySubagents();
      }
    },

    nextPage() {
      const subagents = JSON.parse(localStorage.getItem('subagents')) || [];
      const totalPages = Math.ceil(subagents.length / this.formState.perPage);
      
      if (this.formState.currentPage < totalPages) {
        this.formState.currentPage++;
        this.displaySubagents();
      }
    },

    // Notification method - Uses modern alert system
    showNotification(title, message, type = 'success') {
      // Use modern alert if available (from subagents-data.js)
      if (window.subagentManager && typeof window.subagentManager.showModernAlert === 'function') {
        const duration = type === 'success' ? 4000 : type === 'error' ? 5000 : 4500;
        window.subagentManager.showModernAlert(message, type, title, duration);
      } else {
        // Fallback to modern alert if subagentManager not available
        this.showModernAlertFallback(message, type, title);
      }
    },

    // Fallback modern alert implementation
    showModernAlertFallback(message, type = 'info', title = 'Alert', duration = 4000) {
      const alert = document.getElementById('modernAlert');
      if (!alert) {
        return;
      }

      const alertIcon = alert.querySelector('.alert-icon i');
      const alertTitle = alert.querySelector('.alert-title');
      const alertText = alert.querySelector('.alert-text');
      const alertProgress = alert.querySelector('.alert-progress');
      const alertClose = alert.querySelector('.alert-close');

      // Set content
      alertTitle.textContent = title;
      alertText.textContent = message;

      // Set icon based on type
      const icons = {
        success: 'fas fa-check-circle',
        warning: 'fas fa-exclamation-triangle',
        error: 'fas fa-times-circle',
        info: 'fas fa-info-circle'
      };
      alertIcon.className = icons[type] || icons.info;

      // Set type class
      alert.className = `modern-alert ${type}`;

      // Remove existing close handler and add new one
      const newCloseBtn = alertClose.cloneNode(true);
      alertClose.parentNode.replaceChild(newCloseBtn, alertClose);
      newCloseBtn.addEventListener('click', () => {
        alert.classList.remove('show');
      });

      // Show alert
      alert.classList.add('show');

      // Reset progress bar - use CSS animation
      alertProgress.classList.remove('animating');
      alertProgress.style.removeProperty('--alert-duration');
      void alertProgress.offsetWidth;
      alertProgress.style.setProperty('--alert-duration', `${duration}ms`);
      alertProgress.classList.add('animating');

      // Auto hide after duration
      if (duration > 0) {
        setTimeout(() => {
          alert.classList.remove('show');
        }, duration);
      }
    },

    // View subagent details
    viewSubagent(subagentId) {
      const subagents = JSON.parse(localStorage.getItem('subagents')) || [];
      const subagent = subagents.find(s => s.id === subagentId);
      
      if (!subagent) {
        this.showNotification('Error', 'Subagent not found', 'error');
        return;
      }

      const viewModal = document.getElementById('viewModal');
      if (!viewModal) return;

      // Update view modal content
      const modalContent = viewModal.querySelector('.modal-content');
      modalContent.innerHTML = `
        <div class="modal-header">
          <div class="modal-title">
            <i class="fas fa-user"></i>
            <h2>View Subagent Details</h2>
          </div>
          <button type="button" class="close-modal" data-action="close-view-modal">×</button>
        </div>
        <div class="modal-body">
          <div class="detail-row">
            <label>ID:</label>
            <span>${subagent.id}</span>
          </div>
          <div class="detail-row">
            <label>Name:</label>
            <span>${subagent.name}</span>
          </div>
          <div class="detail-row">
            <label>Email:</label>
            <span>${subagent.email}</span>
          </div>
          <div class="detail-row">
            <label>Phone:</label>
            <span>${subagent.phone}</span>
          </div>
          <div class="detail-row">
            <label>City:</label>
            <span>${subagent.city}</span>
          </div>
          <div class="detail-row">
            <label>Address:</label>
            <span>${subagent.address || 'N/A'}</span>
          </div>
          <div class="detail-row">
            <label>Agent:</label>
            <span>${subagent.agent_name || 'N/A'}</span>
          </div>
          <div class="detail-row">
            <label>Status:</label>
            <span class="status-badge ${subagent.status}">${subagent.status}</span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="modal-btn cancel" data-action="close-view-modal">
            <i class="fas fa-times"></i> Close
          </button>
        </div>
      `;

      // Add event listeners for modal close buttons (replaces inline onclick handlers)
      viewModal.querySelectorAll('[data-action="close-view-modal"]').forEach(btn => {
          btn.addEventListener('click', function() {
              if (paginSearchStatusBulk && typeof paginSearchStatusBulk.closeModal === 'function') {
                  paginSearchStatusBulk.closeModal('viewModal');
              }
          });
      });
      
      viewModal.classList.remove('subagent-modal-hidden');
      viewModal.classList.add('subagent-modal-visible');
      setTimeout(() => viewModal.classList.add('show'), 10);
    },

    // Edit subagent
    editSubagent(subagentId) {
      const savedSubagents = localStorage.getItem('subagents');
      const subagents = savedSubagents ? JSON.parse(savedSubagents) : [];
      const subagent = subagents.find(sub => sub.id === subagentId);
      
      if (!subagent) {
        this.showNotification('Error', 'Subagent not found', 'error');
        return;
      }
      
      // Attempt to show add/edit modal using formHandler method
      if (window.formHandler && window.formHandler.showAddForm) {
        window.formHandler.showAddForm();
      } else {
        // Fallback modal display
        const editModal = document.getElementById('editForm');
        if (editModal) {
          editModal.classList.remove('subagent-modal-hidden');
          editModal.classList.add('subagent-modal-visible');
          setTimeout(() => editModal.classList.add('show'), 10);
        }
      }
      
      // Reset form
      const form = document.getElementById('subagentForm');
      if (!form) {
        return;
      }
      
      // Reset form and clear any previous validation errors
      form.reset();
      form.querySelectorAll('.invalid').forEach(el => el.classList.remove('invalid'));
      form.querySelectorAll('.error-message').forEach(el => el.classList.remove('error-visible'));
      
      // Populate form fields
      const formFields = {
        'id': subagent.id,
        'name': subagent.name,
        'email': subagent.email,
        'phone': subagent.phone,
        'city': subagent.city,
        'address': subagent.address,
        'status': subagent.status
      };
      
      Object.entries(formFields).forEach(([key, value]) => {
        const field = form.querySelector(`[name="${key}"]`);
        if (field) {
          field.value = value;
        }
      });
      
      // Set form state for editing
      if (window.formHandler) {
        window.formHandler.formState.isEditing = true;
        window.formHandler.formState.currentId = subagentId;
      }
      
      // Update form title
      const formTitle = document.getElementById('formTitle');
      if (formTitle) {
        formTitle.textContent = `Edit Subagent: ${subagent.name}`;
      }
      
      // Optional: Trigger any necessary form validation
      if (window.formHandler && window.formHandler.initFormValidation) {
        window.formHandler.initFormValidation();
      }
    },

    // Delete subagent
    deleteSubagent(subagentId) {
      // Confirm deletion
      if (!confirm('Are you sure you want to delete this subagent?')) {
        return;
      }
      
      // Find and remove the specific subagent
      const savedSubagents = localStorage.getItem('subagents');
      let subagents = savedSubagents ? JSON.parse(savedSubagents) : [];
      
      const initialLength = subagents.length;
      subagents = subagents.filter(subagent => subagent.id !== subagentId);
      
      if (subagents.length === initialLength) {
        this.showNotification('Error', 'Subagent not found', 'error');
        return;
      }
      
      // Save updated subagents
      localStorage.setItem('subagents', JSON.stringify(subagents));
      
      // Refresh display and stats
      this.displaySubagents();
      this.updateSubagentStats();
      
      // Show success notification
      this.showNotification('Success', 'Subagent deleted successfully', 'success');
    },

    // Refresh subagents display
    refreshSubagents() {
      this.displaySubagents();
      this.updateSubagentStats();
    },

    // Update subagent stats
    updateSubagentStats() {
      const savedSubagents = localStorage.getItem('subagents');
      const subagents = savedSubagents ? JSON.parse(savedSubagents) : [];
      
      const total = subagents.length;
      const active = subagents.filter(s => s.status === 'active').length;
      const inactive = subagents.filter(s => s.status === 'inactive').length;
      
      // Update the stats in the HTML
      const totalElement = document.getElementById('totalCount');
      const activeElement = document.getElementById('activeCount');
      const inactiveElement = document.getElementById('inactiveCount');
      
      if (totalElement) totalElement.textContent = total;
      if (activeElement) activeElement.textContent = active;
      if (inactiveElement) inactiveElement.textContent = inactive;
    },

    updateEntriesPerPage(value) {
      this.formState.perPage = parseInt(value);
      this.formState.currentPage = 1;
      this.displaySubagents();
    },

    goToPage(pageNumber) {
      this.formState.currentPage = pageNumber;
      this.displaySubagents();
    },

    // Add new methods for view and account functionality
    showAccountModal(subagentId) {
      const subagents = JSON.parse(localStorage.getItem('subagents')) || [];
      const subagent = subagents.find(s => s.id === subagentId);
      
      if (!subagent) {
        this.showNotification('Error', 'Subagent not found', 'error');
        return;
      }

      const accountModal = document.getElementById('accountModal');
      if (!accountModal) return;

      // Update account modal content
      const modalContent = accountModal.querySelector('.modal-content');
      modalContent.innerHTML = `
        <div class="modal-header">
          <div class="modal-title">
            <i class="fas fa-user-cog"></i>
            <h2>Account Settings - ${subagent.name}</h2>
          </div>
          <button type="button" class="close-modal" data-action="close-account-modal">×</button>
        </div>
        <div class="modal-body">
          <form id="accountForm" data-action="save-account-settings" data-subagent-id="${subagentId}">
            <div class="input-group">
              <label for="username">Username</label>
              <input type="text" id="username" name="username" class="form-input" required>
            </div>
            <div class="input-group">
              <label for="password">Password</label>
              <div class="password-input-wrapper">
                <input type="password" id="password" name="password" class="form-input" required>
                <button type="button" class="toggle-password" data-action="toggle-password-visibility">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
            </div>
            <div class="input-group">
              <label for="role">Role</label>
              <select id="role" name="role" class="form-select" required>
                <option value="subagent">Subagent</option>
                <option value="limited">Limited Access</option>
              </select>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="modal-btn cancel" data-action="close-account-modal">
            <i class="fas fa-times"></i> Cancel
          </button>
          <button type="submit" form="accountForm" class="modal-btn save">
            <i class="fas fa-save"></i> Save Changes
          </button>
        </div>
      `;

      // Add event listeners for account modal buttons (replaces inline onclick handlers)
      accountModal.querySelectorAll('[data-action="close-account-modal"]').forEach(btn => {
          btn.addEventListener('click', function() {
              if (paginSearchStatusBulk && typeof paginSearchStatusBulk.closeModal === 'function') {
                  paginSearchStatusBulk.closeModal('accountModal');
              }
          });
      });
      
      accountModal.querySelectorAll('[data-action="toggle-password-visibility"]').forEach(btn => {
          btn.addEventListener('click', function() {
              if (paginSearchStatusBulk && typeof paginSearchStatusBulk.togglePasswordVisibility === 'function') {
                  paginSearchStatusBulk.togglePasswordVisibility();
              }
          });
      });
      
      const accountForm = accountModal.querySelector('[data-action="save-account-settings"]');
      if (accountForm) {
          accountForm.addEventListener('submit', function(e) {
              e.preventDefault();
              const subagentId = this.getAttribute('data-subagent-id');
              if (paginSearchStatusBulk && typeof paginSearchStatusBulk.saveAccountSettings === 'function') {
                  paginSearchStatusBulk.saveAccountSettings(e, subagentId);
              }
          });
      }
      
      accountModal.classList.remove('subagent-modal-hidden');
      accountModal.classList.add('subagent-modal-visible');
      setTimeout(() => accountModal.classList.add('show'), 10);
    },

    closeModal(modalId) {
      const modal = document.getElementById(modalId);
      if (!modal) return;
      
      modal.classList.remove('show');
      setTimeout(() => {
        modal.classList.add('subagent-modal-hidden');
        modal.classList.remove('subagent-modal-visible');
      }, 200);
    },

    togglePasswordVisibility() {
      const passwordInput = document.getElementById('password');
      const toggleButton = document.querySelector('.toggle-password i');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleButton.classList.remove('fa-eye');
        toggleButton.classList.add('fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        toggleButton.classList.remove('fa-eye-slash');
        toggleButton.classList.add('fa-eye');
      }
    },

    saveAccountSettings(event, subagentId) {
      event.preventDefault();
      
      const form = document.getElementById('accountForm');
      const formData = new FormData(form);
      
      // Here you would typically save the account settings to your backend
      // For now, we'll just show a success message
      this.showNotification('Success', 'Account settings saved successfully', 'success');
      this.closeModal('accountModal');
    },

    updatePagination(currentPage, totalPages) {
        const maxButtons = 5; // Maximum number of page buttons to show
        const containers = ['Top', 'Bottom'];

        containers.forEach(position => {
            const pageNumbers = document.querySelector(`#pagination${position} .page-numbers`);
            let html = '';
            let startPage = 1;
            let endPage = totalPages;

            // If total pages is more than max buttons, calculate start and end pages
            if (totalPages > maxButtons) {
                const halfButtons = Math.floor(maxButtons / 2);
                
                if (currentPage <= halfButtons) {
                    // Near the start
                    endPage = maxButtons;
                } else if (currentPage + halfButtons >= totalPages) {
                    // Near the end
                    startPage = totalPages - maxButtons + 1;
                } else {
                    // In the middle
                    startPage = currentPage - halfButtons;
                    endPage = currentPage + halfButtons;
                }

                // Add first page and ellipsis if needed
                if (startPage > 1) {
                    html += `
                        <button class="page-btn" data-page="1">1</button>
                        ${startPage > 2 ? '<span class="page-ellipsis">...</span>' : ''}
                    `;
                }
            }

            // Add page numbers
            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <button class="page-btn ${i === currentPage ? 'active' : ''}" 
                            data-page="${i}" 
                            ${i === currentPage ? 'disabled' : ''}>
                        ${i}
                    </button>
                `;
            }

            // Add last page and ellipsis if needed
            if (totalPages > maxButtons && endPage < totalPages) {
                html += `
                    ${endPage < totalPages - 1 ? '<span class="page-ellipsis">...</span>' : ''}
                    <button class="page-btn" data-page="${totalPages}">${totalPages}</button>
                `;
            }

            if (pageNumbers) {
                pageNumbers.innerHTML = html;
            }

            // Update navigation buttons state
            const container = document.getElementById(`pagination${position}`);
            const firstBtn = container.querySelector('.first-page');
            const prevBtn = container.querySelector('.prev-page');
            const nextBtn = container.querySelector('.next-page');
            const lastBtn = container.querySelector('.last-page');

            if (firstBtn) firstBtn.disabled = currentPage === 1;
            if (prevBtn) prevBtn.disabled = currentPage === 1;
            if (nextBtn) nextBtn.disabled = currentPage === totalPages;
            if (lastBtn) lastBtn.disabled = currentPage === totalPages;
        });
    },

    initPagination() {
        // Add event listeners for pagination
        ['Top', 'Bottom'].forEach(position => {
            const container = document.getElementById(`pagination${position}`);
            if (!container) return;

            // Page size change
            const pageSize = document.getElementById(`pageSize${position}`);
            if (pageSize) {
                pageSize.addEventListener('change', (e) => {
                    const newSize = parseInt(e.target.value);
                    this.pagination.itemsPerPage = newSize;
                    this.pagination.currentPage = 1;
                    // Sync both dropdowns
                    document.getElementById('pageSizeTop').value = newSize;
                    document.getElementById('pageSizeBottom').value = newSize;
                    this.loadData();
                });
            }

            // Navigation buttons
            container.addEventListener('click', (e) => {
                const btn = e.target.closest('.page-btn');
                if (!btn || btn.disabled) return;

                if (btn.classList.contains('first-page')) {
                    this.goToPage(1);
                } else if (btn.classList.contains('last-page')) {
                    this.goToPage(this.getTotalPages());
                } else if (btn.classList.contains('prev-page')) {
                    this.goToPage(this.pagination.currentPage - 1);
                } else if (btn.classList.contains('next-page')) {
                    this.goToPage(this.pagination.currentPage + 1);
                } else if (btn.dataset.page) {
                    this.goToPage(parseInt(btn.dataset.page));
                }
            });
        });
    },

    getTotalPages() {
        return Math.ceil(this.pagination.totalItems / this.pagination.itemsPerPage);
    },

    loadData() {
        // Update pagination
        this.updatePagination();
        
        // Calculate offset for SQL LIMIT
        const offset = (this.pagination.currentPage - 1) * this.pagination.itemsPerPage;
        
        // Your existing data loading logic here
        // Make sure to pass offset and limit to your API
        // Example:
        // fetch(`/api/subagents/get.php?offset=${offset}&limit=${this.pagination.itemsPerPage}`)
    }
  };

  // Initialize when DOM is ready
  document.addEventListener('DOMContentLoaded', () => {
    paginSearchStatusBulk.init();
  });

  // Expose to global scope
  window.paginSearchStatusBulk = paginSearchStatusBulk;
})();

// Add these functions for bulk actions
function updateBulkButtons() {
    const checkedBoxes = document.querySelectorAll('.bulk-checkbox:checked');
    const bulkButtons = document.querySelectorAll('.bulk-btn');
    
    bulkButtons.forEach(button => {
        button.disabled = checkedBoxes.length === 0;
        button.classList.toggle('disabled', checkedBoxes.length === 0);
    });
}

function toggleAllCheckboxes(source) {
    const checkboxes = document.querySelectorAll('.bulk-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = source.checked;
    });
    updateBulkButtons();
}

// Initialize bulk actions
document.addEventListener('DOMContentLoaded', () => {
    // Add event listeners to checkboxes
    document.querySelectorAll('.bulk-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkButtons);
    });

    // Add event listener to select all checkbox
    const selectAllCheckbox = document.querySelector('.bulk-checkbox-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            toggleAllCheckboxes(this);
        });
    }

    // Initial update of button states
    updateBulkButtons();
});