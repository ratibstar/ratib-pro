/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/country-users.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/country-users.js`.
 */
(function() {
    var config = document.getElementById('control-config');
    var apiBase = (config && config.getAttribute('data-api-base')) || '';
    apiBase = apiBase.replace(/\/$/, '');
    var countrySelect = document.getElementById('countrySelect');
    var countryCardsGrid = document.getElementById('countryCardsGrid');
    var usersTableSection = document.getElementById('usersTableSection');
    var usersTableContainer = document.getElementById('usersTableContainer');
    var selectedCountryName = document.getElementById('selectedCountryName');
    var selectedCountryNameSecondary = document.getElementById('selectedCountryNameSecondary');
    var countryUsersHelper = document.getElementById('countryUsersHelper');
    var stCountriesTotal = document.getElementById('stCountriesTotal');
    var stUsersTotal = document.getElementById('stUsersTotal');
    var stCountriesWithUsers = document.getElementById('stCountriesWithUsers');
    var stCountriesWithoutUsers = document.getElementById('stCountriesWithoutUsers');
    var usersTableModalEl = document.getElementById('usersTableModal');
    var usersTableModal = (usersTableModalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(usersTableModalEl, { backdrop: true, keyboard: true }) : null;
    var userModalEl = document.getElementById('userModal');
    if (userModalEl && userModalEl.parentNode !== document.body) {
        userModalEl.classList.add('country-users-modal');
        document.body.appendChild(userModalEl);
    }
    var userModal = (userModalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(userModalEl, { backdrop: true, keyboard: true }) : null;
    var countries = [];
    var currentAgencyId = 0;

    function escapeHtml(s) {
        if (s == null || s === '') return '';
        var div = document.createElement('div');
        div.textContent = String(s);
        return div.innerHTML;
    }

    function canManageUsers() {
        if (!window.UserPermissions || typeof window.UserPermissions.hasAny !== 'function') {
            return false;
        }
        return window.UserPermissions.hasAny(['control_system_settings', 'manage_control_users', 'edit_control_system_settings']);
    }

    var urlDebug = /[?&]debug=1/.test(window.location.search);

    function apiCall(action, extra) {
        var body = { action: action, table: 'users', agency_id: currentAgencyId };
        for (var k in extra) if (extra.hasOwnProperty(k)) body[k] = extra[k];
        var needDebug = urlDebug || (action === 'create' || action === 'update' || action === 'get_all');
        var url = apiBase + '/country-users-api.php' + (needDebug ? '?debug=1' : '');
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function(r) {
            return r.text().then(function(t) {
                try { return JSON.parse(t || '{}'); } catch (e) {
                    return { success: false, message: r.ok ? 'Invalid response' : ('HTTP ' + r.status + ': ' + (t ? t.substring(0, 200) : '')) };
                }
            });
        });
    }

    function loadCountries() {
        var url = apiBase + '/get-users-per-country.php';
        return fetch(url, { credentials: 'same-origin' })
            .then(function(r) {
                if (!r.ok) return r.text().then(function(t) { throw new Error('HTTP ' + r.status + ': ' + (t ? t.substring(0, 200) : r.statusText)); });
                return r.json();
            })
            .then(function(data) {
                if (data.success && Array.isArray(data.countries)) {
                    countries = data.countries;
                    var withAgency = data.countries.filter(function(c) { return c.agency_id; });
                    var totalCountries = withAgency.length;
                    var totalUsers = withAgency.reduce(function(sum, c) { return sum + (parseInt(c.users_count, 10) || 0); }, 0);
                    var withUsers = withAgency.filter(function(c) { return (parseInt(c.users_count, 10) || 0) > 0; }).length;
                    var withoutUsers = Math.max(0, totalCountries - withUsers);
                    if (stCountriesTotal) stCountriesTotal.textContent = String(totalCountries);
                    if (stUsersTotal) stUsersTotal.textContent = String(totalUsers);
                    if (stCountriesWithUsers) stCountriesWithUsers.textContent = String(withUsers);
                    if (stCountriesWithoutUsers) stCountriesWithoutUsers.textContent = String(withoutUsers);
                    if (countrySelect) {
                        countrySelect.innerHTML = '<option value="">— Select Country —</option>' +
                            withAgency.map(function(c) {
                                var name = (c.name || '').replace(/"/g, '&quot;');
                                return '<option value="' + c.agency_id + '" data-name="' + name + '">' + (c.name || 'Unknown') + ' (' + (c.users_count || 0) + ' users)</option>';
                            }).join('');
                    }
                    if (countryCardsGrid) {
                        if (withAgency.length > 0) {
                            countryCardsGrid.innerHTML = withAgency.map(function(c) {
                                var nameText = c.name || 'Unknown';
                                var safeName = nameText.replace(/"/g, '&quot;');
                                var count = c.users_count || 0;
                                var usersLabel = count === 1 ? '1 user' : (count + ' users');
                                return '<button type="button" class="country-card" data-agency-id="' + c.agency_id + '" data-name="' + safeName + '">' +
                                    '<div class="country-card-title"><i class="fas fa-globe-asia"></i><span>' + nameText + '</span></div>' +
                                    '<div class="country-card-meta"><span class="country-card-count">' + usersLabel + '</span>' +
                                    '<span class="country-card-badge">Ratib Pro</span></div>' +
                                    '</button>';
                            }).join('');
                            countryCardsGrid.querySelectorAll('.country-card').forEach(function(card) {
                                card.addEventListener('click', function() {
                                    var id = parseInt(card.getAttribute('data-agency-id'), 10) || 0;
                                    if (!id) return;
                                    currentAgencyId = id;
                                    var name = card.getAttribute('data-name') || card.textContent || 'Users';
                                    if (selectedCountryName) selectedCountryName.textContent = name;
                                    if (selectedCountryNameSecondary) selectedCountryNameSecondary.textContent = name + ' users';
                                    if (usersTableSection) usersTableSection.classList.remove('d-none');
                                    if (countryUsersHelper) countryUsersHelper.classList.add('d-none');
                                    if (countrySelect) {
                                        countrySelect.value = String(id);
                                    }
                                    countryCardsGrid.querySelectorAll('.country-card').forEach(function(cEl) {
                                        cEl.classList.toggle('active', cEl === card);
                                    });
                                    if (usersTableModal) usersTableModal.show();
                                    loadUsers();
                                });
                            });
                        } else {
                            countryCardsGrid.innerHTML = '';
                        }
                    }
                    if (withAgency.length === 0 && data.countries.length > 0 && countryUsersHelper) {
                        var base = window.location.pathname.replace(/\/[^/]+$/, '');
                        countryUsersHelper.innerHTML = '<i class="fas fa-exclamation-triangle fa-2x mb-2 control-icon-warning"></i>' +
                            '<p class="mb-1"><strong>No agencies configured</strong></p>' +
                            '<p class="mb-0 text-muted small">Countries exist but have no agencies. Go to <a href="' + base + '/agencies.php">Manage Agencies</a> to add agencies, or run the migration scripts.</p>';
                    } else if (data.countries.length === 0 && countryUsersHelper) {
                        var base = window.location.pathname.replace(/\/[^/]+$/, '');
                        countryUsersHelper.innerHTML = '<i class="fas fa-globe-americas fa-2x mb-2 control-icon-muted"></i>' +
                            '<p class="mb-1"><strong>No countries found</strong></p>' +
                            '<p class="mb-0 text-muted small">Add countries in <a href="' + base + '/countries.php">Manage Countries</a> first.</p>';
                    }
                } else if (countryUsersHelper) {
                    countryUsersHelper.innerHTML = '<i class="fas fa-exclamation-circle fa-2x mb-2 text-danger"></i>' +
                        '<p class="mb-0 text-danger">' + (data.message || 'Failed to load countries') + '</p>';
                }
            })
            .catch(function(err) {
                var msg = err && err.message ? err.message : 'Network or parse error';
                var debugUrl = apiBase + '/get-users-per-country.php?debug=1';
                if (countryUsersHelper) {
                    countryUsersHelper.innerHTML = '<i class="fas fa-exclamation-circle fa-2x mb-2 text-danger"></i>' +
                        '<p class="mb-1 text-danger">' + msg + '</p>' +
                        '<p class="mb-0 text-muted small">API: <a href="' + debugUrl + '" target="_blank" rel="noopener">' + debugUrl + '</a></p>';
                }
                console.error('Country users load error:', err);
            });
    }

    function loadUsers() {
        if (!currentAgencyId) return;
        usersTableContainer.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading users...</div>';
        apiCall('get_all').then(function(res) {
            if (res.success) {
                var data = res.data || [];
                if (data.length === 0) {
                    usersTableContainer.innerHTML = '<div class="empty-state">No users in this country.' + (canManageUsers() ? ' Click Add User to create one.' : '') + '</div>';
                } else {
                    var thead = '<thead><tr>' +
                        '<th class="country-users-col-check"><input type="checkbox" id="selectAllUsers"></th>' +
                        '<th>Username</th><th>Name</th><th>Role</th><th>Status</th><th>Created</th><th></th></tr></thead>';
                    var tbody = '<tbody>' + data.map(function(u) {
                        var role = u.role_name || ('Role ' + (u.role_id || ''));
                        var status = (u.status || 'active').toLowerCase();
                        var created = u.created_at ? new Date(u.created_at).toLocaleDateString() : '-';
                        var uname = u.username != null ? String(u.username) : '';
                        var displayName = (u.user_display_name != null && String(u.user_display_name).trim() !== '') ? String(u.user_display_name) : (uname || '—');
                        var actions = canManageUsers()
                            ? '<button type="button" class="btn btn-sm btn-outline-primary me-1 btn-edit" data-id="' + u.user_id + '">Edit</button>' +
                              '<button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="' + u.user_id + '">Delete</button>'
                            : '';
                        return '<tr>' +
                            '<td><input type="checkbox" class="user-select" data-id="' + u.user_id + '"></td>' +
                            '<td>' + escapeHtml(uname) + '</td><td>' + escapeHtml(displayName) + '</td><td>' + escapeHtml(role) + '</td><td>' + escapeHtml(status) + '</td><td>' + escapeHtml(created) + '</td>' +
                            '<td>' + actions + '</td></tr>';
                    }).join('') + '</tbody>';
                    var bulkBar = canManageUsers() ? ('<div class="users-bulk-bar">' +
                        '<div class="form-check"><input class="form-check-input" type="checkbox" id="selectAllUsersTop">' +
                        '<label class="form-check-label ms-1" for="selectAllUsersTop">Select all</label></div>' +
                        '<div class="btn-group btn-group-sm" role="group" aria-label="Bulk actions">' +
                        '<button type="button" class="btn btn-outline-success" id="btnBulkActivate">Activate</button>' +
                        '<button type="button" class="btn btn-outline-warning" id="btnBulkSuspend">Suspend</button>' +
                        '<button type="button" class="btn btn-outline-secondary" id="btnBulkUnsuspend">Unsuspend</button>' +
                        '<button type="button" class="btn btn-outline-danger" id="btnBulkDelete">Delete selected</button>' +
                        '</div></div>') : '';
                    usersTableContainer.innerHTML = bulkBar + '<table class="table table-hover mb-0">' + thead + tbody + '</table>';
                    usersTableContainer.querySelectorAll('.btn-edit').forEach(function(btn) {
                        btn.addEventListener('click', function() { openEditModal(parseInt(btn.dataset.id, 10)); });
                    });
                    usersTableContainer.querySelectorAll('.btn-delete').forEach(function(btn) {
                        btn.addEventListener('click', function() { deleteUser(parseInt(btn.dataset.id, 10)); });
                    });
                    var selectAllTop = document.getElementById('selectAllUsersTop');
                    var selectAll = document.getElementById('selectAllUsers');
                    var rowChecks = usersTableContainer.querySelectorAll('.user-select');
                    function syncSelectAll(fromTop) {
                        var master = fromTop ? selectAllTop : selectAll;
                        var other = fromTop ? selectAll : selectAllTop;
                        if (!master) return;
                        var checked = master.checked;
                        rowChecks.forEach(function(chk) { chk.checked = checked; });
                        if (other) other.checked = checked;
                    }
                    if (selectAllTop) selectAllTop.addEventListener('change', function() { syncSelectAll(true); });
                    if (selectAll) selectAll.addEventListener('change', function() { syncSelectAll(false); });
                    function getSelectedIds() {
                        var ids = [];
                        rowChecks.forEach(function(chk) {
                            if (chk.checked) ids.push(parseInt(chk.getAttribute('data-id'), 10) || 0);
                        });
                        ids = ids.filter(function(id) { return id > 0; });
                        // If nothing is explicitly selected but there is exactly one row, act on that row
                        if (!ids.length && rowChecks.length === 1) {
                            var onlyId = parseInt(rowChecks[0].getAttribute('data-id'), 10) || 0;
                            if (onlyId > 0) ids.push(onlyId);
                        }
                        return ids;
                    }
                    function bulkChangeStatus(status) {
                        var ids = getSelectedIds();
                        if (!ids.length) { alert('Select at least one user first'); return; }
                        apiCall('bulk_update_status', { ids: ids, status: status }).then(function(res) {
                            if (res.success) {
                                loadUsers();
                            } else {
                                alert(res.message || 'Bulk status update failed');
                            }
                        }).catch(function(err) {
                            console.error('Bulk status error:', err);
                            alert('Bulk status update failed. Please try again.');
                        });
                    }
                    function bulkDelete() {
                        var ids = getSelectedIds();
                        if (!ids.length) { alert('Select at least one user first'); return; }
                        if (!confirm('Delete selected users?')) return;
                        apiCall('bulk_delete', { ids: ids }).then(function(res) {
                            if (res.success) {
                                loadUsers();
                            } else {
                                alert(res.message || 'Bulk delete failed');
                            }
                        }).catch(function(err) {
                            console.error('Bulk delete error:', err);
                            alert('Bulk delete failed. Please try again.');
                        });
                    }
                    var btnBulkActivate = document.getElementById('btnBulkActivate');
                    var btnBulkSuspend = document.getElementById('btnBulkSuspend');
                    var btnBulkUnsuspend = document.getElementById('btnBulkUnsuspend');
                    var btnBulkDelete = document.getElementById('btnBulkDelete');
                    if (btnBulkActivate) btnBulkActivate.addEventListener('click', function() { bulkChangeStatus('active'); });
                    if (btnBulkSuspend) btnBulkSuspend.addEventListener('click', function() { bulkChangeStatus('inactive'); });
                    if (btnBulkUnsuspend) btnBulkUnsuspend.addEventListener('click', function() { bulkChangeStatus('active'); });
                    if (btnBulkDelete) btnBulkDelete.addEventListener('click', bulkDelete);
                }
            } else {
                usersTableContainer.innerHTML = '<div class="empty-state text-danger">' + (res.message || 'Failed to load users') + '</div>';
            }
        }).catch(function(err) {
            usersTableContainer.innerHTML = '<div class="empty-state text-danger">Failed to load users. ' + (err && err.message ? err.message : '') + '</div>';
            console.error('Load users error:', err);
        });
    }

    function openAddModal() {
        document.getElementById('userModalTitle').textContent = 'Add User';
        document.getElementById('userId').value = '';
        document.getElementById('username').value = '';
        document.getElementById('username').readOnly = false;
        document.getElementById('password').value = '';
        document.getElementById('password').required = true;
        var passwordGroup = document.getElementById('passwordGroup');
        if (passwordGroup) passwordGroup.style.display = '';
        document.getElementById('status').value = 'active';
        apiCall('get_all', { table: 'roles' }).then(function(r) {
            var sel = document.getElementById('roleId');
            sel.innerHTML = '';
            if (r.success && Array.isArray(r.data) && r.data.length > 0) {
                r.data.forEach(function(role) {
                    var opt = document.createElement('option');
                    opt.value = role.role_id || role.id;
                    opt.textContent = role.role_name || role.name || ('Role ' + (role.role_id || role.id));
                    sel.appendChild(opt);
                });
            } else {
                var opt = document.createElement('option');
                opt.value = 1;
                opt.textContent = 'Admin';
                sel.appendChild(opt);
            }
        });
        if (userModal) userModal.show();
    }

    function openEditModal(userId) {
        document.getElementById('userModalTitle').textContent = 'Edit User';
        document.getElementById('userId').value = userId;
        document.getElementById('username').readOnly = false;
        document.getElementById('password').value = '';
        document.getElementById('password').required = false;
        var passwordGroup = document.getElementById('passwordGroup');
        if (passwordGroup) passwordGroup.style.display = '';
        apiCall('get_by_id', { id: userId }).then(function(res) {
            if (res.success && res.data) {
                document.getElementById('username').value = res.data.username || '';
                document.getElementById('status').value = (res.data.status || 'active').toLowerCase();
            }
        });
        apiCall('get_all', { table: 'roles' }).then(function(r) {
            var sel = document.getElementById('roleId');
            sel.innerHTML = '';
            if (r.success && Array.isArray(r.data) && r.data.length > 0) {
                r.data.forEach(function(role) {
                    var opt = document.createElement('option');
                    opt.value = role.role_id || role.id;
                    opt.textContent = role.role_name || role.name || ('Role ' + (role.role_id || role.id));
                    sel.appendChild(opt);
                });
            }
            apiCall('get_by_id', { id: userId }).then(function(res) {
                if (res.success && res.data) document.getElementById('roleId').value = res.data.role_id || 1;
            });
        });
        if (userModal) userModal.show();
    }

    function saveUser() {
        var userId = document.getElementById('userId').value;
        var username = document.getElementById('username').value.trim();
        var password = document.getElementById('password').value;
        var roleId = document.getElementById('roleId').value;
        var status = document.getElementById('status').value;
        if (!username) { alert('Username required'); return; }
        if (!userId && !password) { alert('Password required for new user'); return; }
        var data = { username: username, role_id: parseInt(roleId, 10) || 1, status: status };
        if (password) data.password = password;
        var action = userId ? 'update' : 'create';
        var payload = userId ? { id: parseInt(userId, 10), data: data } : { data: data };
        apiCall(action, payload).then(function(res) {
            if (res.success) {
                if (document.activeElement) document.activeElement.blur();
                if (userModal) userModal.hide();
                loadUsers();
            } else {
                alert(res.message || 'Failed to save');
            }
        }).catch(function(err) {
            console.error('Save user error:', err);
            alert('Failed to save. Please try again.');
        });
    }

    function deleteUser(userId) {
        if (!confirm('Delete this user?')) return;
        apiCall('delete', { id: userId }).then(function(res) {
            if (res.success) loadUsers();
            else alert(res.message || 'Delete failed');
        });
    }

    if (countrySelect) countrySelect.addEventListener('change', function() {
        var opt = this.options[this.selectedIndex];
        currentAgencyId = parseInt(this.value, 10) || 0;
        if (currentAgencyId) {
            var name = opt ? (opt.dataset.name || opt.textContent) : 'Users';
            if (selectedCountryName) selectedCountryName.textContent = name;
            if (selectedCountryNameSecondary) selectedCountryNameSecondary.textContent = name + ' users';
            if (usersTableSection) usersTableSection.classList.remove('d-none');
            if (countryUsersHelper) countryUsersHelper.classList.add('d-none');
            if (countryCardsGrid) {
                countryCardsGrid.querySelectorAll('.country-card').forEach(function(card) {
                    var id = parseInt(card.getAttribute('data-agency-id'), 10) || 0;
                    card.classList.toggle('active', id === currentAgencyId);
                });
            }
            if (usersTableModal) usersTableModal.show();
            loadUsers();
        } else {
            if (usersTableSection) usersTableSection.classList.add('d-none');
            if (countryUsersHelper) countryUsersHelper.classList.remove('d-none');
            if (countryCardsGrid) {
                countryCardsGrid.querySelectorAll('.country-card').forEach(function(card) {
                    card.classList.remove('active');
                });
            }
        }
    });

    var btnAddUser = document.getElementById('btnAddUser');
    var btnSaveUser = document.getElementById('btnSaveUser');
    var btnCancelUser = document.getElementById('btnCancelUser');
    if (btnAddUser) btnAddUser.addEventListener('click', openAddModal);
    if (btnSaveUser) btnSaveUser.addEventListener('click', saveUser);
    if (btnCancelUser) btnCancelUser.addEventListener('click', function() { if (userModal) userModal.hide(); });
    var userForm = document.getElementById('userForm');
    if (userForm) userForm.addEventListener('submit', function(e) { e.preventDefault(); saveUser(); });

    var urlParams = new URLSearchParams(window.location.search);
    var urlAgencyId = urlParams.get('agency_id') ? parseInt(urlParams.get('agency_id'), 10) : 0;

    loadCountries().then(function() {
        if (btnAddUser && !canManageUsers()) {
            btnAddUser.style.display = 'none';
        }
        if (urlAgencyId && countrySelect && countrySelect.querySelector('option[value="' + urlAgencyId + '"]')) {
            countrySelect.value = urlAgencyId;
            countrySelect.dispatchEvent(new Event('change'));
        }
    });
})();
