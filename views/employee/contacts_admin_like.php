<?php
// Employee Contacts (Admin-like UI)
// Auth: employee roles only
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/login'));
    exit;
}

// Permissions and DB
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../models/EmployeePermission.php";

$database = new Database();
$db = $database->getConnection();

$permissionModel = new EmployeePermission($db);
$permissions = $permissionModel->getUserPermissions($_SESSION["user_id"]);

if (!function_exists('hasPermission')) {
    function hasPermission($permissions, $permission) {
        return isset($permissions[$permission]) && $permissions[$permission];
    }
}

if (!hasPermission($permissions, 'can_upload_contacts')) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/email-dashboard'));
    exit;
}

// Stats similar to admin
try {
    $stmt = $db->query("SELECT COUNT(*) FROM email_recipients");
    $totalContacts = (int)$stmt->fetchColumn();
    $activeContacts = $totalContacts;
    $stmt = $db->query("SELECT COUNT(*) FROM email_recipients WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $newThisMonth = (int)$stmt->fetchColumn();
    $deletedContacts = 0;
} catch (Throwable $e) {
    $totalContacts = 0;
    $activeContacts = 0;
    $newThisMonth = 0;
    $deletedContacts = 0;
}

include __DIR__ . "/../components/header.php";
include __DIR__ . "/../components/employee-sidebar.php";
?>

<style>
.loading-overlay { position: absolute; inset: 0; background: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.contact-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display:flex; align-items:center; justify-content:center; font-weight: bold; font-size: 14px; }
.status-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
.filter-card { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 0.5rem; }
.search-box { position: relative; }
.search-box .form-control { padding-left: 2.5rem; }
.search-box .bi { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #6c757d; }
.action-buttons .btn { margin-right: 0.25rem; }
.alert.position-fixed { box-shadow: 0 4px 12px rgba(0,0,0,0.15); border: none; border-radius: 8px; }
.alert-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; }
.alert-danger { background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); color: white; }
.table-responsive { border-radius: 0.5rem; overflow: hidden; }
.pagination-info { font-size: 0.875rem; color: #6c757d; }
.filter-active { background-color: #e7f3ff !important; border-color: #0066cc !important; }
.active-filters-badge { background-color: #0066cc; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; margin-left: 8px; }
</style>

<div class="main-content" style="margin-left: 260px; padding: 20px;">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0"><i class="bi bi-people me-2"></i>Contacts Management</h1>
                        <p class="text-muted mb-0">Manage your contact database with ease</p>
                    </div>
                    <div>
                        <?php if (hasPermission($permissions, 'can_upload_contacts')): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">
                            <i class="bi bi-plus-circle me-2"></i>Add Contact
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="bi bi-upload me-2"></i>Import
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 bg-primary text-white"><div class="card-body"><div class="d-flex justify-content-between"><div><h4 class="mb-0" id="totalContacts"><?php echo number_format($totalContacts); ?></h4><small>Total Contacts</small></div><div class="align-self-center"><i class="bi bi-people fs-1"></i></div></div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-success text-white"><div class="card-body"><div class="d-flex justify-content-between"><div><h4 class="mb-0"><?php echo number_format($activeContacts); ?></h4><small>Active Contacts</small></div><div class="align-self-center"><i class="bi bi-check-circle fs-1"></i></div></div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-warning text-white"><div class="card-body"><div class="d-flex justify-content-between"><div><h4 class="mb-0"><?php echo number_format($newThisMonth); ?></h4><small>New This Month</small></div><div class="align-self-center"><i class="bi bi-plus-circle fs-1"></i></div></div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-info text-white"><div class="card-body"><div class="d-flex justify-content-between"><div><h4 class="mb-0"><?php echo number_format($deletedContacts); ?></h4><small>Deleted Contacts</small></div><div class="align-self-center"><i class="bi bi-trash fs-1"></i></div></div></div></div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card filter-card"><div class="card-body"><div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" class="form-control" id="searchInput" placeholder="Search contacts...">
                            <div class="form-text" id="searchHelp" style="display:none;"><small>Press Enter to search or wait for auto-search</small></div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <select class="form-select" id="sortBy">
                                    <option value="created_at">Sort by Date (Newest)</option>
                                    <option value="name">Sort by Name (A-Z)</option>
                                    <option value="email">Sort by Email (A-Z)</option>
                                    <option value="company">Sort by Company (A-Z)</option>
                                    <option value="dot">Sort by DOT Number</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button class="btn btn-outline-secondary w-100" onclick="clearFilters()"><i class="bi bi-x-circle me-2"></i>Clear</button>
                            </div>
                        </div>
                    </div>
                </div></div></div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Contact List</h5>
                        <div class="d-flex align-items-center">
                            <span class="pagination-info me-3">&nbsp;</span>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportContacts()"><i class="bi bi-download me-1"></i>Export</button>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="bulkDelete()"><i class="bi bi-trash me-1"></i>Delete</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                                        <th>Contact</th>
                                        <th>Email</th>
                                        <th>Company</th>
                                        <th>DOT</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th width="150">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="contactsTableBody">
                                    <tr id="loadingRow" style="display:none;"><td colspan="8" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><div class="mt-2">Loading contacts...</div></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <select class="form-select form-select-sm" id="perPageSelect" style="width:auto;" onchange="changePerPage(this.value)">
                                    <option value="10">10 per page</option>
                                    <option value="25">25 per page</option>
                                    <option value="50" selected>50 per page</option>
                                    <option value="100">100 per page</option>
                                </select>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Contact Modal -->
<div class="modal fade" id="addContactModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Contact</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form id="addContactForm"><div class="modal-body"><div class="row">
        <div class="col-md-6 mb-3"><label for="name" class="form-label">Full Name *</label><input type="text" class="form-control" id="name" name="name" required></div>
        <div class="col-md-6 mb-3"><label for="email" class="form-label">Email Address *</label><input type="email" class="form-control" id="email" name="email" required></div>
    </div><div class="row">
        <div class="col-md-6 mb-3"><label for="company" class="form-label">Company Name</label><input type="text" class="form-control" id="company" name="company"></div>
        <div class="col-md-6 mb-3"><label for="dot" class="form-label">DOT Number</label><input type="text" class="form-control" id="dot" name="dot"></div>
    </div>
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i><strong>Note:</strong> Only name and email are required fields. Company and DOT number are optional.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Contact</button></div>
    </form>
</div></div></div>

<!-- Edit Contact Modal -->
<div class="modal fade" id="editContactModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Contact</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form id="editContactForm"><input type="hidden" id="editContactId" name="id"><div class="modal-body"><div class="row">
        <div class="col-md-6 mb-3"><label for="editName" class="form-label">Full Name *</label><input type="text" class="form-control" id="editName" name="name" required></div>
        <div class="col-md-6 mb-3"><label for="editEmail" class="form-label">Email Address *</label><input type="email" class="form-control" id="editEmail" name="email" required></div>
    </div><div class="row">
        <div class="col-md-6 mb-3"><label for="editCompany" class="form-label">Company Name</label><input type="text" class="form-control" id="editCompany" name="company"></div>
        <div class="col-md-6 mb-3"><label for="editDot" class="form-label">DOT Number</label><input type="text" class="form-control" id="editDot" name="dot"></div>
    </div>
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i><strong>Note:</strong> Only name and email are required fields. Company and DOT number are optional.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Update Contact</button></div>
    </form>
</div></div></div>

<!-- View Contact Modal -->
<div class="modal fade" id="viewContactModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-eye me-2"></i>View Contact Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="row">
        <div class="col-md-6 mb-3"><label class="form-label fw-bold">Full Name</label><div class="form-control-plaintext border bg-light rounded p-2" id="viewName">-</div></div>
        <div class="col-md-6 mb-3"><label class="form-label fw-bold">Email Address</label><div class="form-control-plaintext border bg-light rounded p-2" id="viewEmail">-</div></div>
    </div><div class="row">
        <div class="col-md-6 mb-3"><label class="form-label fw-bold">Company Name</label><div class="form-control-plaintext border bg-light rounded p-2" id="viewCompany">-</div></div>
        <div class="col-md-6 mb-3"><label class="form-label fw-bold">DOT Number</label><div class="form-control-plaintext border bg-light rounded p-2" id="viewDot">-</div></div>
    </div><div class="row">
        <div class="col-md-6 mb-3"><label class="form-label fw-bold">Contact ID</label><div class="form-control-plaintext border bg-light rounded p-2" id="viewContactId">-</div></div>
        <div class="col-md-6 mb-3"><label class="form-label fw-bold">Date Added</label><div class="form-control-plaintext border bg-light rounded p-2" id="viewDateAdded">-</div></div>
    </div>
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i><strong>Contact Information:</strong> This is a read-only view of the contact details.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-upload me-2"></i>Import Contacts</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form id="importForm" enctype="multipart/form-data"><div class="modal-body">
        <div id="step1" class="import-step">
            <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i><strong>Supported Formats:</strong> CSV, XLSX, XLS files with headers: Name, Email, Company, DOT</div>
            <div class="row"><div class="col-md-8"><div class="mb-3"><label for="importFile" class="form-label">Select File</label><input type="file" class="form-control" id="importFile" name="importFile" accept=".csv,.xlsx,.xls" required><div class="form-text">Maximum file size: 10MB</div></div></div><div class="col-md-4"><div class="mb-3"><label class="form-label">Download Template</label><div><button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="downloadTemplate('csv')"><i class="bi bi-download me-2"></i>CSV Template</button></div><div class="mt-2"><button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="downloadTemplate('xlsx')"><i class="bi bi-download me-2"></i>Excel Template</button></div></div></div></div>
            <div class="mb-3"><div class="form-check"><input class="form-check-input" type="checkbox" id="skipHeader" checked><label class="form-check-label" for="skipHeader">Skip first row (header row)</label></div></div>
        </div>
        <div id="step2" class="import-step" style="display:none;"><h6 class="mb-3">Preview Import Data</h6><div class="table-responsive" style="max-height:300px; overflow-y:auto;"><table class="table table-sm table-bordered" id="previewTable"><thead class="table-light"><tr><th>Name</th><th>Email</th><th>Company</th><th>DOT</th><th>Status</th></tr></thead><tbody id="previewTableBody"></tbody></table></div><div class="mt-3"><div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><strong>Total Records:</strong> <span id="totalRecords">0</span> | <strong>Valid Records:</strong> <span id="validRecords">0</span> | <strong>Invalid Records:</strong> <span id="invalidRecords">0</span></div></div></div>
        <div id="step3" class="import-step" style="display:none;"><h6 class="mb-3">Import Progress</h6><div class="progress mb-3"><div class="progress-bar" id="importProgress" role="progressbar" style="width:0%"></div></div><div id="importStatus" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Importing...</span></div><div class="mt-2">Processing contacts...</div></div></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-outline-primary" id="prevStep" style="display:none;"><i class="bi bi-arrow-left me-2"></i>Previous</button><button type="button" class="btn btn-primary" id="nextStep"><i class="bi bi-arrow-right me-2"></i>Next</button><button type="submit" class="btn btn-success" id="importBtn" style="display:none;"><i class="bi bi-upload me-2"></i>Import Contacts</button></div>
    </form>
</div></div></div>

<!-- Contact History Modal (placeholder) -->
<div class="modal fade" id="historyModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Contact History</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="timeline"><div class="timeline-item"><div class="timeline-marker bg-primary"></div><div class="timeline-content"><h6>Contact Created</h6><p class="text-muted mb-1">—</p><p>Contact was added to the system</p></div></div></div></div>
</div></div></div>

<script>
// Debounced Search & Filters
let searchDebounceTimer; const searchInput=document.getElementById('searchInput');
searchInput.addEventListener('input', e=>{ const term=e.target.value.trim(); document.getElementById('searchHelp').style.display = term? 'block':'none'; clearTimeout(searchDebounceTimer); searchDebounceTimer=setTimeout(()=>performSearch(),300); });
searchInput.addEventListener('keypress', e=>{ if(e.key==='Enter'){ e.preventDefault(); clearTimeout(searchDebounceTimer); performSearch(); }});
document.getElementById('statusFilter').addEventListener('change', performSearch);
document.getElementById('sortBy').addEventListener('change', performSearch);
function performSearch(){ const s=document.getElementById('searchInput').value.trim(); const st=document.getElementById('statusFilter').value; const sb=document.getElementById('sortBy').value; updateFilterVisuals(); loadContacts(1, getPerPage(), s, st, '', sb); }
function updateFilterVisuals(){ const si=document.getElementById('searchInput'); si.classList.toggle('filter-active', !!si.value.trim()); const sf=document.getElementById('statusFilter'); sf.classList.toggle('filter-active', !!sf.value); const sb=document.getElementById('sortBy'); sb.classList.toggle('filter-active', sb.value!=='created_at'); updateActiveFilterCount(); }
function updateActiveFilterCount(){ let n=0; if(document.getElementById('searchInput').value.trim()) n++; if(document.getElementById('statusFilter').value) n++; if(document.getElementById('sortBy').value!=='created_at') n++; let badge=document.getElementById('activeFiltersBadge'); const clearBtn=document.querySelector('button[onclick="clearFilters()"]'); if(n>0){ if(!badge){ badge=document.createElement('span'); badge.id='activeFiltersBadge'; badge.className='active-filters-badge'; clearBtn.parentNode.insertBefore(badge, clearBtn); } badge.textContent = `${n} active`; badge.style.display='inline-block'; } else if(badge){ badge.style.display='none'; } }
function clearFilters(){ document.getElementById('searchInput').value=''; document.getElementById('statusFilter').value=''; document.getElementById('sortBy').value='created_at'; document.getElementById('searchHelp').style.display='none'; updateFilterVisuals(); loadContacts(1, getPerPage(), '', '', '', 'created_at'); }

// Select all
document.getElementById('selectAll').addEventListener('change', function(){ document.querySelectorAll('.contact-checkbox').forEach(cb=>cb.checked=this.checked); });

// Load contacts
let currentRequest=null;
function loadContacts(page=1, perPage=50, search='', status='', company='', sortBy='created_at'){
  const loadingRow=document.getElementById('loadingRow'); const tbody=document.getElementById('contactsTableBody'); const info=document.querySelector('.pagination-info');
  if(currentRequest){ currentRequest.abort(); }
  loadingRow.style.display='table-row';
  tbody.querySelectorAll('tr:not(#loadingRow)').forEach(r=>r.remove());
  const params=new URLSearchParams({ action:'list_all', page, per_page: perPage });
  if(search) params.append('search', search);
  if(status) params.append('status', status);
  if(company) params.append('company', company);
  if(sortBy){ params.append('sort_by', sortBy); params.append('sort_direction', sortBy==='created_at' ? 'DESC' : 'ASC'); }
  const controller=new AbortController(); currentRequest=controller;
  fetch(`/api/contacts_api.php?${params.toString()}`, { signal: controller.signal })
    .then(r=>{ if(!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
    .then(d=>{ loadingRow.style.display='none'; currentRequest=null; if(d.success){ displayContacts(d.data); updatePagination(d.pagination); updateStats(d.pagination.total); if(info && d.pagination){ const start=d.pagination.total>0? (d.pagination.current_page-1)*d.pagination.per_page + 1: 0; const end=Math.min(start + d.data.length - 1, d.pagination.total); info.textContent = d.pagination.total>0 ? `Showing ${start}-${end} of ${d.pagination.total} contacts` : 'No contacts found'; } } else { tbody.innerHTML = `<tr><td colspan="8" class="text-center"><div class='alert alert-danger mb-0'><i class='bi bi-exclamation-triangle me-2'></i>${d.error||'Failed to load contacts'}</div></td></tr>`; } })
    .catch(err=>{ if(err.name==='AbortError') return; loadingRow.style.display='none'; currentRequest=null; tbody.innerHTML = `<tr><td colspan='8' class='text-center'><div class='alert alert-danger mb-0'><i class='bi bi-exclamation-triangle me-2'></i>Failed to load contacts. Please try again.</div></td></tr>`; });
}
function displayContacts(list){ const tbody=document.getElementById('contactsTableBody'); tbody.querySelectorAll('tr:not(#loadingRow)').forEach(r=>r.remove()); const loading=document.getElementById('loadingRow'); if(loading) loading.style.display='none'; if(!list || list.length===0){ const tr=document.createElement('tr'); tr.innerHTML = `<td colspan='8' class='text-center text-muted py-4'>No contacts found</td>`; tbody.appendChild(tr); return; } list.forEach(c=>{ const tr=document.createElement('tr'); tr.innerHTML = `
  <td><div class='form-check'><input class='form-check-input contact-checkbox' type='checkbox' value='${c.id}'></div></td>
  <td><div class='d-flex align-items-center'><div class='contact-avatar me-2'>${getInitials(c.name)}</div><div><div class='fw-bold'>${c.name}</div></div></div></td>
  <td>${c.email||'N/A'}</td>
  <td>${c.company||'N/A'}</td>
  <td>${c.dot||'N/A'}</td>
  <td><span class='badge bg-${getStatusColor(c.status)} status-badge'>${c.status||'active'}</span></td>
  <td>${formatDate(c.created_at)}</td>
  <td><div class='action-buttons'>
    <button class='btn btn-sm btn-outline-primary' onclick='viewContact(${c.id})'><i class='bi bi-eye'></i></button>
    <button class='btn btn-sm btn-outline-secondary' onclick='editContact(${c.id})'><i class='bi bi-pencil'></i></button>
    <button class='btn btn-sm btn-outline-info' onclick='viewHistory(${c.id})'><i class='bi bi-clock-history'></i></button>
    <button class='btn btn-sm btn-outline-danger' onclick='deleteContact(${c.id})'><i class='bi bi-trash'></i></button>
  </div></td>`; tbody.appendChild(tr); }); }
function updatePagination(p){ const el=document.getElementById('pagination'); if(!el||!p) return; el.style.display = p.total_pages>1? '':'none'; let html=''; html += `<li class='page-item ${p.current_page<=1?'disabled':''}'><a class='page-link' href='#' onclick='changePage(${p.current_page-1}); return false;'><i class='bi bi-chevron-left'></i></a></li>`; const max=5; let s=Math.max(1, p.current_page-Math.floor(max/2)); let e=Math.min(p.total_pages, s+max-1); if(e-s<max-1){ s=Math.max(1, e-max+1);} if(s>1){ html+=`<li class='page-item'><a class='page-link' href='#' onclick='changePage(1); return false;'>1</a></li>`; if(s>2){ html+="<li class='page-item disabled'><span class='page-link'>...</span></li>"; } } for(let i=s;i<=e;i++){ html += `<li class='page-item ${i===p.current_page?'active':''}'><a class='page-link' href='#' onclick='changePage(${i}); return false;'>${i}</a></li>`; } if(e<p.total_pages){ if(e<p.total_pages-1){ html+="<li class='page-item disabled'><span class='page-link'>...</span></li>"; } html += `<li class='page-item'><a class='page-link' href='#' onclick='changePage(${p.total_pages}); return false;'>${p.total_pages}</a></li>`; } html += `<li class='page-item ${p.current_page>=p.total_pages?'disabled':''}'><a class='page-link' href='#' onclick='changePage(${p.current_page+1}); return false;'><i class='bi bi-chevron-right'></i></a></li>`; el.innerHTML = html; }
function changePage(page){ const s=document.getElementById('searchInput').value.trim(); const st=document.getElementById('statusFilter').value; const sb=document.getElementById('sortBy').value; loadContacts(page, getPerPage(), s, st, '', sb); }
function updateStats(total){ const el=document.getElementById('totalContacts'); if(el) el.textContent = total; }
function getStatusColor(status){ switch(status){ case 'active': return 'success'; case 'inactive': return 'secondary'; case 'pending': return 'warning'; default: return 'primary'; } }
function formatDate(s){ if(!s) return 'N/A'; const d=new Date(s); return d.toLocaleDateString(); }
function getInitials(name){ if(!name) return 'NA'; return name.split(' ').map(w=>w.charAt(0)).join('').toUpperCase().substring(0,2); }

// Actions
function viewContact(id){ const m=new bootstrap.Modal(document.getElementById('viewContactModal')); ['viewName','viewEmail','viewCompany','viewDot','viewContactId','viewDateAdded'].forEach(id2=>document.getElementById(id2).textContent='Loading...'); m.show(); fetch(`/api/contacts_api.php?action=view&id=${id}`).then(r=>r.json()).then(d=>{ if(d.success){ const c=d.data; document.getElementById('viewName').textContent=c.name||'-'; document.getElementById('viewEmail').textContent=c.email||'-'; document.getElementById('viewCompany').textContent=c.company||'-'; document.getElementById('viewDot').textContent=c.dot||'-'; document.getElementById('viewContactId').textContent=c.id||'-'; let date='-'; if(c.created_at){ const dt=new Date(c.created_at); date = dt.toLocaleDateString()+ ' ' + dt.toLocaleTimeString(); } else if(c.date_added){ const dt=new Date(c.date_added); date = dt.toLocaleDateString()+ ' ' + dt.toLocaleTimeString(); } document.getElementById('viewDateAdded').textContent=date; } else { showAlert('Error loading contact details: ' + (d.message||'Unknown error'), 'danger'); } }).catch(()=>{ showAlert('Network error while loading contact details', 'danger'); }); }
function editContact(id){ const btn=document.querySelector(`button[onclick="editContact(${id})"]`); const orig=btn?.innerHTML; if(btn){ btn.innerHTML='<i class="bi bi-hourglass-split"></i>'; btn.disabled=true; } fetch(`/api/contacts_api.php?action=view&id=${id}`).then(r=>r.json()).then(d=>{ if(d.success){ const c=d.data; document.getElementById('editContactId').value=c.id; document.getElementById('editName').value=c.name||''; document.getElementById('editEmail').value=c.email||''; document.getElementById('editCompany').value=c.company||''; document.getElementById('editDot').value=c.dot||''; new bootstrap.Modal(document.getElementById('editContactModal')).show(); } else { alert('Error: ' + (d.error || 'Failed to load contact data')); } }).catch(()=>{ alert('Error: Failed to load contact data. Please try again.'); }).finally(()=>{ if(btn){ btn.innerHTML=orig; btn.disabled=false; } }); }
function viewHistory(id){ new bootstrap.Modal(document.getElementById('historyModal')).show(); }
function deleteContact(id){ if(confirm('Are you sure you want to delete this contact?')){ const btn=document.querySelector(`button[onclick="deleteContact(${id})"]`); const orig=btn?.innerHTML; if(btn){ btn.innerHTML='<i class="bi bi-hourglass-split"></i>'; btn.disabled=true; } fetch(`/api/contacts_api.php?action=delete&id=${id}`, { method:'DELETE', headers:{ 'Content-Type':'application/json' } }).then(r=>r.json()).then(d=>{ if(d.success){ alert('Contact deleted successfully!'); const row=btn?.closest('tr'); if(row) row.remove(); loadContacts(); window.location.reload(); } else { alert('Error: ' + (d.error || d.message || 'Failed to delete contact')); if(btn){ btn.innerHTML=orig; btn.disabled=false; } } }).catch(()=>{ alert('Error: Failed to delete contact. Please try again.'); if(btn){ btn.innerHTML=orig; btn.disabled=false; } }); } }
function bulkDelete(){ const sel=document.querySelectorAll('.contact-checkbox:checked'); if(sel.length===0){ alert('Please select contacts to delete'); return; } if(confirm(`Are you sure you want to delete ${sel.length} contact(s)?`)){ const ids=Array.from(sel).map(cb=>cb.value); const btn=document.querySelector('button[onclick="bulkDelete()"]'); const orig=btn?.innerHTML; if(btn){ btn.innerHTML='<i class="bi bi-hourglass-split me-2"></i>Deleting...'; btn.disabled=true; } fetch('/api/contacts_api.php?action=bulk_delete', { method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ ids }) }).then(r=>r.json()).then(d=>{ if(d.success){ alert(`Successfully deleted ${d.deleted_count} contact(s)!`); window.location.reload(); loadContacts(); } else { alert('Error: ' + (d.error || d.message || 'Failed to delete contacts')); } }).catch(()=>{}).finally(()=>{ if(btn){ btn.innerHTML=orig; btn.disabled=false; } }); } }
function exportContacts(){ const sel=document.querySelectorAll('.contact-checkbox:checked'); const btn=document.querySelector('button[onclick="exportContacts()"]'); const orig=btn?.innerHTML; if(sel.length===0){ if(confirm('No contacts selected. Do you want to export all contacts?')){ return exportAllContacts(); } else { alert('Please select contacts to export or choose "Export All"'); return; } } const ids = Array.from(sel).map(cb=>cb.value); if(btn){ btn.innerHTML='<i class="bi bi-hourglass-split me-1"></i>Downloading...'; btn.disabled=true; } const fd=new FormData(); ids.forEach(id=>fd.append('contact_ids[]', id)); fetch('/api/contacts_api.php?action=export_contacts', { method:'POST', body: fd }).then(r=>{ if(r.ok) return r.blob(); throw new Error('Export failed'); }).then(blob=>{ const url=URL.createObjectURL(blob); const a=document.createElement('a'); a.href=url; a.download = `contacts_export_${new Date().toISOString().slice(0,10)}.xlsx`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url); alert(`Successfully exported ${sel.length} contacts!`); }).catch(()=>{ alert('Failed to export contacts. Please try again.'); }).finally(()=>{ if(btn){ btn.innerHTML=orig; btn.disabled=false; } }); }
function exportAllContacts(){ const btn=document.querySelector('button[onclick="exportContacts()"]'); const orig=btn?.innerHTML; if(btn){ btn.innerHTML='<i class="bi bi-hourglass-split me-1"></i>Downloading All...'; btn.disabled=true; } fetch('/api/contacts_api.php?action=export_contacts', { method:'POST', body: new FormData() }).then(r=>{ if(r.ok) return r.blob(); throw new Error('Export failed'); }).then(blob=>{ const url=URL.createObjectURL(blob); const a=document.createElement('a'); a.href=url; a.download = `all_contacts_export_${new Date().toISOString().slice(0,10)}.xlsx`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url); alert('Successfully exported all contacts!'); }).catch(()=>{ alert('Failed to export contacts. Please try again.'); }).finally(()=>{ if(btn){ btn.innerHTML=orig; btn.disabled=false; } }); }

// Add Contact form
document.getElementById('addContactForm').addEventListener('submit', function(e){ e.preventDefault(); const data={ name: document.getElementById('name').value.trim(), email: document.getElementById('email').value.trim(), company: document.getElementById('company').value.trim(), dot: document.getElementById('dot').value.trim() }; if(!data.name||!data.email){ alert('Name and email are required fields.'); return; } const emailRegex=/^[^\s@]+@[^\s@]+\.[^\s@]+$/; if(!emailRegex.test(data.email)){ alert('Please enter a valid email address.'); return; } const btn=this.querySelector('button[type="submit"]'); const orig=btn.innerHTML; btn.innerHTML='<i class="bi bi-hourglass-split me-2"></i>Saving...'; btn.disabled=true; fetch('/api/contacts_api.php?action=create_contact', { method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify(data) }).then(r=>r.json()).then(d=>{ if(d.success){ alert('Contact created successfully! Contact ID: ' + d.data.id); const m=bootstrap.Modal.getInstance(document.getElementById('addContactModal')); m?.hide(); this.reset(); loadContacts(); window.location.reload(); } else { alert('Error: ' + (d.error || d.message || 'Failed to create contact')); } }).catch(()=>{}).finally(()=>{ btn.innerHTML=orig; btn.disabled=false; }); });

// Edit Contact form
document.getElementById('editContactForm').addEventListener('submit', function(e){ e.preventDefault(); const data={ id: document.getElementById('editContactId').value, name: document.getElementById('editName').value.trim(), email: document.getElementById('editEmail').value.trim(), company: document.getElementById('editCompany').value.trim(), dot: document.getElementById('editDot').value.trim() }; if(!data.name||!data.email){ alert('Name and email are required fields.'); return; } const emailRegex=/^[^\s@]+@[^\s@]+\.[^\s@]+$/; if(!emailRegex.test(data.email)){ alert('Please enter a valid email address.'); return; } const btn=this.querySelector('button[type="submit"]'); const orig=btn.innerHTML; btn.innerHTML='<i class="bi bi-hourglass-split me-2"></i>Updating...'; btn.disabled=true; fetch('/api/contacts_api.php?action=update', { method:'PUT', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify(data) }).then(r=>r.json()).then(d=>{ if(d.success){ alert('Contact updated successfully!'); const m=bootstrap.Modal.getInstance(document.getElementById('editContactModal')); m?.hide(); this.reset(); loadContacts(); window.location.reload(); } else { alert('Error: ' + (d.error || d.message || 'Failed to update contact')); } }).catch(()=>{ alert('Error: Failed to update contact. Please try again.'); }).finally(()=>{ btn.innerHTML=orig; btn.disabled=false; }); });

// Import logic
let currentStep=1; function downloadTemplate(type){ const a=document.createElement('a'); a.href=`/api/contacts_api.php?action=download_template&type=${type}`; a.download=`contacts_template.${type}`; document.body.appendChild(a); a.click(); a.remove(); }
document.getElementById('nextStep').addEventListener('click', function(){ if(currentStep===1){ const fi=document.getElementById('importFile'); if(!fi.files[0]){ alert('Please select a file to import.'); return; } previewImportFile(fi.files[0]); showStep(2); } else if(currentStep===2){ showStep(3); startImport(); } });
document.getElementById('prevStep').addEventListener('click', function(){ if(currentStep===2){ showStep(1);} else if(currentStep===3){ showStep(2);} });
function showStep(step){ document.querySelectorAll('.import-step').forEach(el=>el.style.display='none'); document.getElementById(`step${step}`).style.display='block'; document.getElementById('prevStep').style.display = step===1? 'none':'inline-block'; document.getElementById('nextStep').style.display = step===3? 'none':'inline-block'; document.getElementById('importBtn').style.display = 'none'; currentStep=step; }
function previewImportFile(file){ const fd=new FormData(); fd.append('file', file); fd.append('action','preview_import'); fd.append('skip_header', document.getElementById('skipHeader').checked ? '1':'0'); fetch('/api/contacts_api.php', { method:'POST', body: fd }).then(r=>r.json()).then(d=>{ if(d.success){ displayPreview(d.data); updatePreviewStats(d.stats); } else { alert('Error: ' + (d.error || 'Failed to preview file')); } }).catch(()=>{ alert('Error previewing file. Please try again.'); }); }
function displayPreview(data){ const tbody=document.getElementById('previewTableBody'); tbody.innerHTML=''; data.slice(0,10).forEach(row=>{ const tr=document.createElement('tr'); tr.innerHTML = `<td>${row.name||''}</td><td>${row.email||''}</td><td>${row.company||''}</td><td>${row.dot||''}</td><td><span class='badge bg-${row.isValid?'success':'danger'}'>${row.isValid?'Valid':'Invalid'}</span></td>`; tbody.appendChild(tr); }); if(data.length>10){ const tr=document.createElement('tr'); tr.innerHTML = `<td colspan='5' class='text-center text-muted'>... and ${data.length-10} more records</td>`; tbody.appendChild(tr); } }
function updatePreviewStats(stats){ document.getElementById('totalRecords').textContent = stats.total||0; document.getElementById('validRecords').textContent = stats.valid||0; document.getElementById('invalidRecords').textContent = stats.invalid||0; }
function startImport(){ const fd=new FormData(); fd.append('action','import_contacts'); fd.append('skip_header', document.getElementById('skipHeader').checked ? '1':'0'); const fi=document.getElementById('importFile'); fd.append('file', fi.files[0]); fetch('/api/contacts_api.php', { method:'POST', body: fd }).then(r=>r.json()).then(d=>{ if(d.success){ document.getElementById('importStatus').innerHTML = `<div class='alert alert-success'><i class='bi bi-check-circle me-2'></i>Import completed successfully! ${d.imported} contacts imported.</div>`; window.location.reload(); setTimeout(()=>{ const m=bootstrap.Modal.getInstance(document.getElementById('importModal')); m?.hide(); loadContacts(); },2000); } else { document.getElementById('importStatus').innerHTML = `<div class='alert alert-danger'><i class='bi bi-exclamation-triangle me-2'></i>Import failed: ${d.error || 'Unknown error'}</div>`; } }).catch(()=>{ document.getElementById('importStatus').innerHTML = `<div class='alert alert-danger'><i class='bi bi-exclamation-triangle me-2'></i>Network error during import. Please try again.</div>`; }); }

function changePerPage(v){ loadContacts(1, parseInt(v,10) || 50, document.getElementById('searchInput').value.trim(), document.getElementById('statusFilter').value, '', document.getElementById('sortBy').value); }
function getPerPage(){ return parseInt(document.getElementById('perPageSelect')?.value || '50', 10); }
function loadFilterOptions(){ fetch('/get_filter_options.php').then(r=>r.json()).then(d=>{ if(d.success && d.statusCounts){ const sf=document.getElementById('statusFilter'); sf.innerHTML = `<option value="">All Status (${d.statusCounts.all})</option><option value="active">Active (${d.statusCounts.active})</option><option value="inactive">Inactive (${d.statusCounts.inactive})</option><option value="pending">Pending (${d.statusCounts.pending})</option>`; } }).catch(()=>{}); }
function showAlert(message, type='info', duration=5000){ const existing=document.querySelector('.alert.position-fixed'); if(existing) existing.remove(); const el=document.createElement('div'); el.className = `alert alert-${type} position-fixed`; el.style.cssText='top:20px; right:20px; z-index:9999; min-width:300px; box-shadow:0 4px 6px rgba(0,0,0,0.1);'; el.innerHTML = `<div class='d-flex align-items-center'><i class='bi bi-${type==='success'?'check-circle': type==='danger'?'exclamation-triangle':'info-circle'} me-2'></i><div class='flex-grow-1'>${message}</div><button type='button' class='btn-close' onclick='this.parentElement.parentElement.remove()'></button></div>`; document.body.appendChild(el); setTimeout(()=>{ if(el.parentElement) el.remove(); }, duration); }

document.addEventListener('DOMContentLoaded', ()=>{ loadFilterOptions(); loadContacts(); document.getElementById('searchInput').focus(); });
</script>

<?php include __DIR__ . "/../components/footer.php"; ?>


