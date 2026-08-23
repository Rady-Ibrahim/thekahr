@extends('layouts.app')
@section('title', 'إدارة الموظفين')
@section('page-title', 'إدارة الموظفين')

@section('content')
<div class="page-header">
    <div>
        <h1><i class="fas fa-user-tie me-2 text-primary"></i> الموظفين</h1>
        <div class="breadcrumb">إدارة بيانات الموظفين</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-success" onclick="exportEmployees()" id="exportBtn">
            <i class="fas fa-file-excel me-1"></i> تحميل Excel
        </button>
        <button class="btn-primary-custom" onclick="openAddModal()">
            <i class="fas fa-plus me-1"></i> إضافة موظف
        </button>
    </div>
</div>

<!-- FILTERS -->
<div class="section-card mb-4">
    <div class="section-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">بحث</label>
                <div class="input-group"><span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="searchInput" class="form-control" placeholder="اسم، كود، إيميل..."></div>
            </div>
            <div class="col-md-2"><label class="form-label">الحالة</label>
                <select id="statusFilter" class="form-select">
                    <option value="">الكل</option><option value="active">نشط</option>
                    <option value="inactive">غير نشط</option><option value="on_leave">إجازة</option>
                    <option value="suspended">موقوف</option><option value="resigned">استقال</option>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">النوع</label>
                <select id="typeFilter" class="form-select">
                    <option value="">الكل</option>
                    <option value="manager">مدير</option>
                    <option value="employee">موظف عادي</option>
                    <option value="driver_representative">سائق / مندوب</option>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">القسم</label>
                <select id="deptFilter" class="form-select"><option value="">الكل</option></select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn-primary-custom flex-fill" onclick="loadEmployees()"><i class="fas fa-filter me-1"></i> بحث</button>
                <button class="btn btn-outline-secondary flex-fill" onclick="resetFilters()"><i class="fas fa-undo me-1"></i> إعادة</button>
            </div>
        </div>
    </div>
</div>

<!-- STATS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="stat-value" id="statTotal">-</div><div class="stat-label">إجمالي</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="stat-value text-success" id="statActive">-</div><div class="stat-label">نشط</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="stat-value text-warning" id="statLeave">-</div><div class="stat-label">إجازة</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="stat-value text-danger" id="statOther">-</div><div class="stat-label">غيرهم</div></div></div>
</div>

<!-- TABLE -->
<div class="section-card">
    <div class="section-header">
        <i class="fas fa-list text-primary"></i>
        <h5 class="section-title">قائمة الموظفين</h5>
        <span class="ms-auto text-muted" style="font-size:.8rem" id="totalCount"></span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>كود</th><th>الاسم</th><th>النوع</th><th>الوظيفة</th><th>القسم</th><th>المدير</th><th>الهاتف</th><th>الراتب الأساسي</th><th>تاريخ التعيين</th><th>الحالة</th><th>إجراءات</th></tr></thead>
            <tbody id="employeesTable">
                <tr><td colspan="11" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr>
            </tbody>
        </table>
    </div>
    <div class="section-body d-flex justify-content-between align-items-center">
        <div id="paginationInfo" class="text-muted" style="font-size:.8rem"></div>
        <div id="pagination"></div>
    </div>
</div>

<!-- ═══════════════ ADD / EDIT MODAL ═══════════════ -->
<div class="modal fade" id="employeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="empModalTitle"><i class="fas fa-user-plus me-2"></i> إضافة موظف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="employeeForm">
                    <input type="hidden" id="empId">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">كود الموظف *</label><input type="text" name="employee_code" id="ef_code" class="form-control" required placeholder="EMP001"></div>
                        <div class="col-md-4"><label class="form-label">الاسم الكامل *</label><input type="text" name="name" id="ef_name" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">رقم الهاتف *</label><input type="text" name="phone" id="ef_phone" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">البريد الإلكتروني</label><input type="email" name="email" id="ef_email" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">الوظيفة *</label><input type="text" name="position" id="ef_position" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">نوع الموظف *</label>
                            <select name="employee_type" id="ef_employee_type" class="form-select" required>
                                <option value="employee">موظف عادي</option>
                                <option value="manager">مدير</option>
                                <option value="driver_representative">سائق / مندوب</option>
                            </select>
                            <small class="text-muted">السائق والمندوب نفس النوع والصلاحيات</small>
                        </div>
                        <div class="col-md-6"><label class="form-label">القسم *</label>
                            <select name="department" id="ef_department" class="form-select" required><option value="">اختر القسم</option></select>
                        </div>
                        <div class="col-md-6"><label class="form-label">تاريخ التعيين *</label><input type="date" name="joining_date" id="ef_joining_date" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">الراتب الأساسي *</label>
                            <div class="input-group"><input type="number" name="base_salary" id="ef_base_salary" class="form-control" required><span class="input-group-text">ج.م</span></div>
                        </div>
                        <div class="col-md-6" id="commissionRateGroup">
                            <label class="form-label">نسبة عمولة التحصيل %</label>
                            <div class="input-group">
                                <input type="number" name="collection_commission_rate" id="ef_commission_rate" class="form-control" min="0" max="100" step="0.01" placeholder="مثال: 1 أو 0.5">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">للسائق / المندوب — تُحسب تلقائيًا عند كل تحصيل</small>
                        </div>
                        <div class="col-md-6"><label class="form-label">الحالة *</label>
                            <select name="status" id="ef_status" class="form-select" required>
                                <option value="active">نشط</option><option value="inactive">غير نشط</option>
                                <option value="on_leave">إجازة</option><option value="suspended">موقوف</option><option value="resigned">استقال</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-2 h-100 d-flex align-items-center justify-content-between gap-2">
                                <div>
                                    <label class="form-label mb-0 fw-bold">حضور مخصص بالساعات</label>
                                    <small class="text-muted d-block">تسجيل حضور/انصراف متعدد بدون ورديات</small>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_custom_attendance" id="ef_custom_attendance" onchange="toggleCustomHours()">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6" id="customHoursGroup" style="display:none">
                            <label class="form-label">الساعات المطلوبة يومياً</label>
                            <div class="input-group">
                                <input type="number" name="daily_required_hours" id="ef_daily_hours" class="form-control" min="0.5" max="24" step="0.5">
                                <span class="input-group-text"><i class="fas fa-clock"></i> ساعة</span>
                            </div>
                            <small class="text-muted">يُحسب الخصم على أساس النقص عن هذه الساعات</small>
                        </div>
                        <div class="col-md-6"><label class="form-label">رقم السيارة</label><input type="text" name="car_number" id="ef_car_number" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">رخصة القيادة</label><input type="text" name="car_license" id="ef_car_license" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">الرقم القومي</label><input type="text" name="national_id" id="ef_national_id" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">المدير المباشر</label><select name="manager_id" id="ef_manager_id" class="form-select"><option value="">بدون مدير</option></select></div>
                        <div class="col-12"><label class="form-label">ملاحظات</label><textarea name="notes" id="ef_notes" class="form-control" rows="2"></textarea></div>
                        <div class="col-md-6" id="passwordGroup">
                            <label class="form-label">كلمة المرور <span id="passwordRequired" class="text-danger">*</span></label>
                            <input type="password" name="password" id="ef_password" class="form-control" placeholder="أدخل كلمة المرور">
                        </div>
                        <div class="col-md-6" id="passwordConfirmGroup">
                            <label class="form-label">تأكيد كلمة المرور <span id="passwordConfirmRequired" class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" id="ef_password_confirmation" class="form-control" placeholder="أعد إدخال كلمة المرور">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom" onclick="saveEmployee()"><i class="fas fa-save me-1"></i> حفظ</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ VIEW CARD MODAL ═══════════════ -->
<div class="modal fade" id="viewEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-id-card me-2"></i> كارت الموظف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="employeeCardContent">
                <div class="text-center py-5"><div class="spinner mx-auto"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ DELETE MODAL ═══════════════ -->
<div class="modal fade" id="empDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>تأكيد الحذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <p>هل تريد حذف الموظف<br><strong id="empDeleteName"></strong>؟</p>
                <p class="text-muted small">لا يمكن التراجع عن هذا الإجراء</p>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="empDeleteBtn"><i class="fas fa-trash me-1"></i>حذف نهائي</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ TEAM MODAL ═══════════════ -->
<div class="modal fade" id="teamModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-users-cog me-2"></i> موظفو المدير: <span id="teamManagerName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="teamManagerId">
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="teamSearch" class="form-control" placeholder="بحث في الموظفين..." oninput="renderTeamList()">
                </div>
                <div id="teamList" class="row g-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom" onclick="saveTeam()"><i class="fas fa-save me-1"></i> حفظ الموظفين</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentPage = 1, empDeleteId = null, employeesLookup = [], managersLookup = [], teamSelectedIds = new Set(), departments = [];
const statusLabels = { active:'نشط', inactive:'غير نشط', on_leave:'إجازة', suspended:'موقوف', resigned:'استقال' };
const statusBadge  = { active:'badge-active', inactive:'badge-inactive', on_leave:'badge-approved', suspended:'badge-rejected', resigned:'badge-draft' };
const typeLabels = { manager:'مدير', employee:'موظف عادي', driver_representative:'سائق / مندوب' };
const typeBadge  = { manager:'badge-approved', employee:'badge-draft', driver_representative:'badge-active' };

// ─── LIST ─────────────────────────────────────────────
async function loadEmployees(page = 1) {
    currentPage = page;
    const params = new URLSearchParams({ per_page:15, page,
        search: document.getElementById('searchInput').value,
        status: document.getElementById('statusFilter').value,
        employee_type: document.getElementById('typeFilter').value,
        department: document.getElementById('deptFilter').value,
    });
    document.getElementById('employeesTable').innerHTML =
        '<tr><td colspan="11" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr>';
    const r = await apiFetch('/employees?' + params);
    if (!r.success) return;
    const { data, total, current_page, last_page } = r.data;
    document.getElementById('totalCount').textContent     = `إجمالي: ${total}`;
    document.getElementById('paginationInfo').textContent = `صفحة ${current_page} من ${last_page}`;
    document.getElementById('statTotal').textContent  = total;
    document.getElementById('statActive').textContent = data.filter(e=>e.status==='active').length;
    document.getElementById('statLeave').textContent  = data.filter(e=>e.status==='on_leave').length;
    document.getElementById('statOther').textContent  = data.filter(e=>!['active','on_leave'].includes(e.status)).length;
    if (!data.length) {
        document.getElementById('employeesTable').innerHTML =
            '<tr><td colspan="11" class="text-center py-4 text-muted"><i class="fas fa-inbox fa-2x d-block mb-2"></i>لا يوجد موظفون</td></tr>';
        return;
    }
    document.getElementById('employeesTable').innerHTML = data.map(e => `
        <tr>
            <td><span class="fw-bold text-primary">${e.employee_code}</span>
                ${e.is_custom_attendance ? '<br><small><span class="badge bg-info" title="حضور مخصص بالساعات"><i class="fas fa-stopwatch"></i> ساعات مرنة</span></small>' : ''}
            </td>
            <td><strong>${e.name}</strong><br><small class="text-muted">${e.email??''}</small></td>
            <td><span class="badge-status ${typeBadge[e.employee_type]||'badge-draft'}">${e.employee_type_label || typeLabels[e.employee_type] || e.employee_type || '-'}</span></td>
            <td>${e.position}</td>
            <td>${e.department}</td>
            <td>${e.manager?.name ?? '-'}</td>
            <td>${e.phone??'-'}</td>
            <td class="fw-bold">${Number(e.base_salary).toLocaleString('ar-EG')} ج.م</td>
            <td>${e.joining_date?new Date(e.joining_date).toLocaleDateString('ar-EG'):'-'}</td>
            <td><span class="badge-status ${statusBadge[e.status]||'badge-draft'}">${statusLabels[e.status]||e.status}</span></td>
            <td>
                <div class="d-flex gap-1 flex-wrap">
                    <button class="btn btn-sm btn-outline-info"    onclick="viewEmployee(${e.id})" title="عرض"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-outline-warning"  onclick="openEditModal(${e.id})" title="تعديل"><i class="fas fa-edit"></i></button>
                    ${isManagerEmployee(e) ? `<button class="btn btn-sm btn-outline-primary"  onclick="openTeamModal(${e.id},'${e.name.replace(/'/g,"\\'")}')" title="موظفو المدير"><i class="fas fa-users"></i></button>` : ''}
                    <button class="btn btn-sm btn-outline-danger"   onclick="confirmDelete(${e.id},'${e.name.replace(/'/g,"\\'")}')"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`).join('');
    const pages=[];
    for(let i=1;i<=last_page;i++) pages.push(`<button class="btn btn-sm ${i===current_page?'btn-primary':'btn-outline-primary'} mx-1" onclick="loadEmployees(${i})">${i}</button>`);
    document.getElementById('pagination').innerHTML = pages.join('');
}

// ─── ADD ──────────────────────────────────────────────
function openAddModal() {
    document.getElementById('empId').value = '';
    document.getElementById('employeeForm').reset();
    renderManagerOptions();
    document.getElementById('empModalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i> إضافة موظف جديد';
    document.getElementById('ef_status').value = 'active';
    document.getElementById('ef_employee_type').value = 'employee';
    document.getElementById('ef_commission_rate').value = '';
    document.getElementById('ef_custom_attendance').checked = false;
    document.getElementById('ef_daily_hours').value = '';
    toggleCommissionRateField();
    toggleCustomHours();
    document.getElementById('ef_password').required = true;
    document.getElementById('ef_password_confirmation').required = true;
    document.getElementById('passwordRequired').style.display = '';
    document.getElementById('passwordConfirmRequired').style.display = '';
    new bootstrap.Modal(document.getElementById('employeeModal')).show();
}

// ─── EDIT ─────────────────────────────────────────────
async function openEditModal(id) {
    document.getElementById('empModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i> تعديل بيانات الموظف';
    new bootstrap.Modal(document.getElementById('employeeModal')).show();
    const r = await apiFetch('/employees/' + id);
    if (!r.success) { showAlert('فشل تحميل البيانات', 'danger'); return; }
    const e = r.data;
    renderManagerOptions(e.id);
    document.getElementById('empId').value          = e.id;
    document.getElementById('ef_code').value        = e.employee_code ?? '';
    document.getElementById('ef_name').value        = e.name ?? '';
    document.getElementById('ef_phone').value       = e.phone ?? '';
    document.getElementById('ef_email').value       = e.email ?? '';
    document.getElementById('ef_position').value    = e.position ?? '';
    document.getElementById('ef_department').value  = e.department ?? '';
    document.getElementById('ef_employee_type').value = e.employee_type ?? 'employee';
    document.getElementById('ef_joining_date').value = e.joining_date ? e.joining_date.substring(0,10) : '';
    document.getElementById('ef_base_salary').value = e.base_salary ?? '';
    document.getElementById('ef_commission_rate').value = e.collection_commission_rate ?? '';
    document.getElementById('ef_status').value      = e.status ?? 'active';
    toggleCommissionRateField();
    document.getElementById('ef_car_number').value  = e.car_number ?? '';
    document.getElementById('ef_car_license').value = e.car_license ?? '';
    document.getElementById('ef_national_id').value = e.national_id ?? '';
    document.getElementById('ef_manager_id').value  = e.reporting_manager_id ?? e.manager_id ?? '';
    document.getElementById('ef_notes').value       = e.notes ?? '';
    document.getElementById('ef_custom_attendance').checked = !!e.is_custom_attendance;
    document.getElementById('ef_daily_hours').value = e.daily_required_hours ?? '';
    toggleCustomHours();
    // Password optional in edit mode
    document.getElementById('ef_password').value = '';
    document.getElementById('ef_password_confirmation').value = '';
    document.getElementById('ef_password').required = false;
    document.getElementById('ef_password_confirmation').required = false;
    document.getElementById('passwordRequired').style.display = 'none';
    document.getElementById('passwordConfirmRequired').style.display = 'none';
}

// ─── SAVE ─────────────────────────────────────────────
async function saveEmployee() {
    const id   = document.getElementById('empId').value;
    const data = Object.fromEntries(new FormData(document.getElementById('employeeForm')));
    data.base_salary = parseFloat(data.base_salary);
    if (data.collection_commission_rate !== undefined && data.collection_commission_rate !== '') {
        data.collection_commission_rate = parseFloat(data.collection_commission_rate);
    } else {
        data.collection_commission_rate = null;
    }
    if (data.manager_id) data.manager_id = parseInt(data.manager_id); else delete data.manager_id;
    delete data.is_manager;

    // Custom attendance toggle + required hours
    data.is_custom_attendance = document.getElementById('ef_custom_attendance').checked;
    if (data.is_custom_attendance && data.daily_required_hours !== '' && data.daily_required_hours !== undefined) {
        data.daily_required_hours = parseFloat(data.daily_required_hours);
    } else {
        data.daily_required_hours = null;
    }

    const password = data.password;
    const passwordConfirmation = data.password_confirmation;

    if (id) {
        // Edit: remove password from main request
        delete data.password;
        delete data.password_confirmation;
    }

    const r = await apiFetch(id ? `/employees/${id}` : '/employees', {
        method: id ? 'PUT' : 'POST',
        body: JSON.stringify(data),
    });

    if (!r.success) {
        const msgs = r.errors ? Object.values(r.errors).flat().join('<br>') : (r.message || 'فشل الحفظ');
        showAlert(msgs, 'danger');
        return;
    }

    // If editing and password was provided, reset password separately
    if (id && password) {
        const pr = await apiFetch(`/employees/${id}/reset-password`, {
            method: 'POST',
            body: JSON.stringify({ password, password_confirmation: passwordConfirmation }),
        });
        if (!pr.success) {
            showAlert('تم تحديث البيانات لكن فشل تغيير كلمة المرور: ' + (pr.message || ''), 'warning');
            bootstrap.Modal.getInstance(document.getElementById('employeeModal')).hide();
            loadEmployees(currentPage);
            return;
        }
    }

    bootstrap.Modal.getInstance(document.getElementById('employeeModal')).hide();
    showAlert(id ? 'تم تحديث بيانات الموظف' : 'تم إضافة الموظف بنجاح');
    await loadEmployeeLookups();
    loadEmployees(currentPage);
}

async function loadEmployeeLookups() {
    const [employeesR, managersR] = await Promise.all([
        apiFetch('/employees?per_page=1000'),
        apiFetch('/employees/managers'),
    ]);
    employeesLookup = employeesR.success ? (employeesR.data?.data ?? []) : [];
    managersLookup = managersR.success ? (managersR.data ?? []) : employeesLookup.filter(isManagerEmployee);
    renderManagerOptions(document.getElementById('empId').value || null);
}

async function loadDepartments() {
    const r = await apiFetch('/departments');
    if (!r.success) return;
    departments = r.data ?? [];
    const deptFilter = document.getElementById('deptFilter');
    const efDepartment = document.getElementById('ef_department');
    const currentFilter = deptFilter.value;
    const currentForm = efDepartment.value;
    const opts = departments.map(d => `<option value="${d.name}">${d.name}</option>`).join('');
    deptFilter.innerHTML = '<option value="">الكل</option>' + opts;
    efDepartment.innerHTML = '<option value="">اختر القسم</option>' + opts;
    if (currentFilter) deptFilter.value = currentFilter;
    if (currentForm) efDepartment.value = currentForm;
}

function renderManagerOptions(excludeId = null) {
    const select = document.getElementById('ef_manager_id');
    if (!select) return;
    const currentValue = select.value;
    const excluded = excludeId ? parseInt(excludeId) : null;
    const managerIds = new Set(managersLookup.map(e => e.id));
    if (currentValue) managerIds.add(parseInt(currentValue));
    const options = employeesLookup.filter(e => managerIds.has(e.id));
    select.innerHTML = '<option value="">بدون مدير</option>' + options
        .filter(e => e.id !== excluded)
        .map(e => `<option value="${e.id}">${e.name} - ${e.position ?? ''}</option>`)
        .join('');
    if (currentValue) select.value = currentValue;
}

function isManagerEmployee(employee) {
    if (employee.employee_type === 'manager' || employee.is_manager) return true;
    const roles = employee.user?.roles ?? [];
    return roles.some(role => role.name === 'manager' || role.name?.endsWith('_manager')) || Number(employee.subordinates_count ?? 0) > 0;
}

async function openTeamModal(managerId, managerName) {
    document.getElementById('teamManagerId').value = managerId;
    document.getElementById('teamManagerName').textContent = managerName;
    document.getElementById('teamSearch').value = '';
    document.getElementById('teamList').innerHTML = '<div class="col-12 text-center py-4"><div class="spinner mx-auto"></div></div>';
    new bootstrap.Modal(document.getElementById('teamModal')).show();

    if (!employeesLookup.length) await loadEmployeeLookups();
    const r = await apiFetch(`/employees/${managerId}/subordinates`);
    teamSelectedIds = new Set((r.data ?? []).map(e => e.id));
    renderTeamList();
}

function renderTeamList() {
    const managerId = parseInt(document.getElementById('teamManagerId').value || 0);
    const term = (document.getElementById('teamSearch').value || '').toLowerCase();
    const rows = employeesLookup
        .filter(e => e.id !== managerId)
        .filter(e => !term || `${e.name} ${e.employee_code} ${e.position} ${e.department}`.toLowerCase().includes(term));

    document.getElementById('teamList').innerHTML = rows.length ? rows.map(e => `
        <div class="col-md-6">
            <label class="border rounded p-2 w-100 d-flex align-items-center gap-2" style="cursor:pointer">
                <input class="form-check-input m-0" type="checkbox" value="${e.id}" ${teamSelectedIds.has(e.id) ? 'checked' : ''} onchange="toggleTeamEmployee(${e.id}, this.checked)">
                <span>
                    <strong>${e.name}</strong>
                    <small class="text-muted d-block">${e.employee_code ?? '-'} - ${e.position ?? '-'}</small>
                </span>
            </label>
        </div>
    `).join('') : '<div class="col-12 text-center text-muted py-4">لا يوجد موظفون</div>';
}

function toggleTeamEmployee(id, checked) {
    if (checked) teamSelectedIds.add(id);
    else teamSelectedIds.delete(id);
}

async function saveTeam() {
    const managerId = document.getElementById('teamManagerId').value;
    const r = await apiFetch(`/employees/${managerId}/subordinates`, {
        method: 'PUT',
        body: JSON.stringify({ employee_ids: Array.from(teamSelectedIds) }),
    });
    if (!r.success) { showAlert(r.message || 'فشل حفظ الموظفين', 'danger'); return; }
    bootstrap.Modal.getInstance(document.getElementById('teamModal')).hide();
    showAlert('تم تحديث موظفي المدير');
    await loadEmployeeLookups();
    loadEmployees(currentPage);
}

// ─── DELETE ───────────────────────────────────────────
function confirmDelete(id, name) {
    empDeleteId = id;
    document.getElementById('empDeleteName').textContent = name;
    new bootstrap.Modal(document.getElementById('empDeleteModal')).show();
}
document.getElementById('empDeleteBtn').addEventListener('click', async () => {
    if (!empDeleteId) return;
    const r = await apiFetch(`/employees/${empDeleteId}`, { method:'DELETE' });
    bootstrap.Modal.getInstance(document.getElementById('empDeleteModal')).hide();
    if (r.success) { showAlert('تم حذف الموظف'); loadEmployees(currentPage); }
    else showAlert(r.message||'فشل الحذف', 'danger');
    empDeleteId = null;
});

// ─── VIEW CARD ────────────────────────────────────────
async function viewEmployee(id) {
    const modal = new bootstrap.Modal(document.getElementById('viewEmployeeModal'));
    document.getElementById('employeeCardContent').innerHTML = '<div class="text-center py-5"><div class="spinner mx-auto"></div></div>';
    modal.show();
    const [empR, salR] = await Promise.all([apiFetch(`/employees/${id}`), apiFetch(`/employees/${id}/salary-history`)]);
    const e = empR.data, salary = salR.data?.[0];
    document.getElementById('employeeCardContent').innerHTML = `
    <div class="row g-3">
        <div class="col-md-4">
            <div class="section-card p-3 text-center">
                <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#1a237e,#1abc9c);color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;margin:0 auto 16px">${e.name.charAt(0)}</div>
                <h5 class="mb-1">${e.name}</h5><p class="text-muted mb-1">${e.position}</p>
                <p class="text-muted mb-2" style="font-size:.8rem">${e.department}</p>
                <span class="badge-status ${typeBadge[e.employee_type]||'badge-draft'} me-1">${e.employee_type_label || typeLabels[e.employee_type] || '-'}</span>
                <span class="badge-status ${statusBadge[e.status]||'badge-draft'}">${statusLabels[e.status]||e.status}</span>
                <hr>
                <div class="text-start" style="font-size:.85rem">
                    <p class="mb-1"><i class="fas fa-id-card me-2 text-primary"></i>${e.employee_code}</p>
                    <p class="mb-1"><i class="fas fa-phone me-2 text-primary"></i>${e.phone??'-'}</p>
                    <p class="mb-1"><i class="fas fa-envelope me-2 text-primary"></i>${e.email??'-'}</p>
                    <p class="mb-1"><i class="fas fa-calendar me-2 text-primary"></i>${e.joining_date?new Date(e.joining_date).toLocaleDateString('ar-EG'):'-'}</p>
                    <p class="mb-0"><i class="fas fa-car me-2 text-primary"></i>${e.car_number??'-'}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="section-card p-3 mb-3">
                <h6 class="fw-bold mb-3"><i class="fas fa-money-bill-wave me-2 text-success"></i>آخر راتب</h6>
                ${salary?`
                    <div class="d-flex justify-content-between mb-1"><span class="text-muted">أساسي:</span><span>${Number(salary.base_salary).toLocaleString()} ج.م</span></div>
                    <div class="d-flex justify-content-between mb-1"><span class="text-muted">إجمالي:</span><span>${Number(salary.gross_salary??0).toLocaleString()} ج.م</span></div>
                    <div class="d-flex justify-content-between fw-bold border-top pt-2"><span>صافي:</span><span class="text-success">${Number(salary.net_salary).toLocaleString()} ج.م</span></div>
                `:'<p class="text-muted text-center mb-0">لا يوجد راتب محسوب</p>'}
            </div>
            <div class="section-card p-3">
                <h6 class="fw-bold mb-2 text-warning"><i class="fas fa-exchange-alt me-2"></i>تغيير الحالة</h6>
                <div class="d-grid gap-1">
                    <button class="btn btn-success btn-sm" onclick="changeStatus(${e.id},'active')"><i class="fas fa-check me-1"></i>نشط</button>
                    <button class="btn btn-warning btn-sm" onclick="changeStatus(${e.id},'on_leave')"><i class="fas fa-umbrella-beach me-1"></i>إجازة</button>
                    <button class="btn btn-danger btn-sm"  onclick="changeStatus(${e.id},'suspended')"><i class="fas fa-ban me-1"></i>موقوف</button>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="section-card p-3">
                <h6 class="fw-bold mb-3"><i class="fas fa-cogs me-2 text-primary"></i>إجراءات سريعة</h6>
                <div class="d-grid gap-2">
                    <button class="btn btn-warning btn-sm" onclick="bootstrap.Modal.getInstance(document.getElementById('viewEmployeeModal')).hide();setTimeout(()=>openEditModal(${e.id}),400)"><i class="fas fa-edit me-1"></i>تعديل البيانات</button>
                    <button class="btn btn-outline-info    btn-sm" onclick="window.location='/attendance?employee_id=${e.id}'"><i class="fas fa-clock me-1"></i>سجل الحضور</button>
                    <button class="btn btn-outline-success btn-sm" onclick="window.location='/incentives?employee_id=${e.id}'"><i class="fas fa-star me-1"></i>الحوافز</button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="window.location='/advances?employee_id=${e.id}'"><i class="fas fa-hand-holding-usd me-1"></i>السلف</button>
                    <button class="btn btn-outline-primary btn-sm" onclick="window.location='/salaries?employee_id=${e.id}'"><i class="fas fa-money-bill-wave me-1"></i>الرواتب</button>
                </div>
            </div>
        </div>
    </div>`;
}

async function changeStatus(id, status) {
    const r = await apiFetch(`/employees/${id}/status`, { method:'PUT', body:JSON.stringify({ status }) });
    if (r.success) {
        bootstrap.Modal.getInstance(document.getElementById('viewEmployeeModal')).hide();
        showAlert('تم تحديث الحالة'); loadEmployees(currentPage);
    } else showAlert(r.message, 'danger');
}

function resetFilters() { ['searchInput','statusFilter','typeFilter','deptFilter'].forEach(id=>document.getElementById(id).value=''); loadEmployees(); }

// ─── EXPORT EXCEL ─────────────────────────────────────
async function exportEmployees() {
    const btn = document.getElementById('exportBtn');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جارٍ التحضير...';
    try {
        const params = new URLSearchParams({
            search: document.getElementById('searchInput').value,
            status: document.getElementById('statusFilter').value,
            employee_type: document.getElementById('typeFilter').value,
            department: document.getElementById('deptFilter').value,
        });
        const res = await fetch(API_BASE + '/employees/export?' + params, {
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + TOKEN,
            },
        });
        if (!res.ok) throw new Error('فشل تحميل الملف');
        const blob = await res.blob();
        const disposition = res.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="?(.+?)"?$/);
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = match ? match[1] : `employees_${new Date().toISOString().slice(0,10)}.xlsx`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(a.href);
    } catch (e) {
        showAlert(e.message || 'حدث خطأ أثناء تحميل الملف', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

function toggleCommissionRateField() {
    const type = document.getElementById('ef_employee_type').value;
    const group = document.getElementById('commissionRateGroup');
    if (!group) return;
    group.style.display = type === 'driver_representative' ? '' : 'none';
    if (type !== 'driver_representative') {
        // keep value if switching back, but optional clear is nicer for non-drivers
    }
}

function toggleCustomHours() {
    const checked = document.getElementById('ef_custom_attendance').checked;
    document.getElementById('customHoursGroup').style.display = checked ? '' : 'none';
    if (!checked) document.getElementById('ef_daily_hours').value = '';
}

document.getElementById('ef_employee_type').addEventListener('change', toggleCommissionRateField);
document.getElementById('searchInput').addEventListener('keypress', e => { if(e.key==='Enter') loadEmployees(); });
document.addEventListener('DOMContentLoaded', async () => {
    toggleCommissionRateField();
    await Promise.all([loadEmployeeLookups(), loadDepartments()]);
    loadEmployees();
});
</script>
@endpush
