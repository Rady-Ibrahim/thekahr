@extends('layouts.app')
@section('title', ' تحضير الطلبيه')
@section('page-title', ' تحضير الطلبيه')

@section('content')
<div class="page-header">
    <div>
        <h1><i class="fas fa-receipt me-2 text-primary"></i> تحضير الطلبيه</h1>
        <div class="breadcrumb">ترحيل طلبات تحضير الطلبيه للمراجعة</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" onclick="loadPrepaidRequests()"><i class="fas fa-sync-alt me-1"></i> تحديث</button>
        <button class="btn-primary-custom" onclick="document.getElementById('customer_id').focus()"><i class="fas fa-plus me-1"></i> طلب جديد</button>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-paper-plane text-primary"></i>
                <h5 class="section-title">طلب جديد</h5>
            </div>
            <div class="section-body">
                <div class="alert alert-info py-2" style="font-size:.82rem">
                    الطلب يتحفظ كمسبق الدفع ويتحول مباشرة إلى موظف المراجعة المختار.
                </div>
                <form id="prepaidForm">
                    <div class="mb-3">
                        <label class="form-label">اسم العميل</label>
                        <select name="customer_id" id="customer_id" class="form-select">
                            <option value="">اختر العميل</option>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label">عدد الأصناف</label>
                            <input type="number" name="items_count" id="items_count" class="form-control" min="1" value="1">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">عدد الطلبيات</label>
                            <input type="number" name="orders_count" id="orders_count" class="form-control" min="1" value="1">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">قسم موظف المحضر (لتصفية الموظفين)</label>
                        <select id="prep_dept_id" class="form-select" onchange="filterPreparedByDept()">
                            <option value="">كل الأقسام</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">اسم موظف محضر</label>
                        <div class="position-relative">
                            <input type="text" name="prepared_by_id" id="prepared_by_search" class="form-control" placeholder="ابحث بالاسم أو الكود...">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">قسم موظف المراجعة (لتصفية الموظفين)</label>
                        <select id="rev_dept_id" class="form-select" onchange="filterReviewerByDept()">
                            <option value="">كل الأقسام</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">يرحل لموظف يراجع</label>
                        <div class="position-relative">
                            <input type="text" name="reviewer_employee_id" id="reviewer_by_search" class="form-control" placeholder="ابحث بالاسم أو الكود...">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">تاريخ ووقت البداية</label>
                        <div class="input-group">
                            <input type="datetime-local" name="started_at" id="started_at" class="form-control">
                            <button type="button" class="btn btn-outline-primary" onclick="setCurrentTime('started_at')">
                                <i class="fas fa-clock"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">تاريخ ووقت الانتهاء</label>
                        <div class="input-group">
                            <input type="datetime-local" name="ended_at" id="ended_at" class="form-control">
                            <button type="button" class="btn btn-outline-primary" onclick="setCurrentTime('ended_at')">
                                <i class="fas fa-clock"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
                    </div>

                    <button type="submit" class="btn-primary-custom w-100">
                        <i class="fas fa-share-square me-1"></i> ترحيل للمراجعة
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4">
                <div class="stat-card text-center">
                    <div class="stat-icon mx-auto" style="background:#e3f2fd;color:#1565c0"><i class="fas fa-clipboard-check"></i></div>
                    <div class="stat-value" id="underReviewCount">-</div>
                    <div class="stat-label">تحت المراجعة</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="stat-card text-center">
                    <div class="stat-icon mx-auto" style="background:#e8f5e9;color:#2e7d32"><i class="fas fa-boxes"></i></div>
                    <div class="stat-value" id="itemsTotal">-</div>
                    <div class="stat-label">إجمالي الأصناف</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="stat-card text-center">
                    <div class="stat-icon mx-auto" style="background:#fff3e0;color:#e65100"><i class="fas fa-layer-group"></i></div>
                    <div class="stat-value" id="ordersTotal">-</div>
                    <div class="stat-label">إجمالي الطلبيات</div>
                </div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-list text-primary"></i>
                <h5 class="section-title">طلبات مرحلة للمراجعة</h5>
                <button class="btn btn-sm btn-outline-primary ms-auto" onclick="loadPrepaidRequests()"><i class="fas fa-sync-alt"></i></button>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>العميل</th>
                            <th>الأصناف</th>
                            <th>الطلبيات</th>
                            <th>المحضر</th>
                            <th>المراجع</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="prepaidTable">
                        <tr><td colspan="8" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<!-- FULL EDIT REQUEST MODAL -->
<div class="modal fade" id="editRequestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>تعديل الطلب</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editRequestId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">اسم العميل</label>
                        <select id="edit_customer_id" class="form-select"><option value="">اختر العميل</option></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">عدد الأصناف</label>
                        <input type="number" id="edit_items_count" class="form-control" min="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">عدد الطلبيات</label>
                        <input type="number" id="edit_orders_count" class="form-control" min="1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">اسم موظف محضر</label>
                        <div class="position-relative">
                            <input type="text" id="edit_prepared_by_search" class="form-control" placeholder="ابحث بالاسم أو الكود...">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">يرحل لموظف يراجع</label>
                        <div class="position-relative">
                            <input type="text" id="edit_reviewer_by_search" class="form-control" placeholder="ابحث بالاسم أو الكود...">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">تاريخ ووقت البداية</label>
                        <div class="input-group">
                            <input type="datetime-local" id="edit_started_at" class="form-control">
                            <button type="button" class="btn btn-outline-primary" onclick="setCurrentTime('edit_started_at')"><i class="fas fa-clock"></i></button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">تاريخ ووقت الانتهاء</label>
                        <div class="input-group">
                            <input type="datetime-local" id="edit_ended_at" class="form-control">
                            <button type="button" class="btn btn-outline-primary" onclick="setCurrentTime('edit_ended_at')"><i class="fas fa-clock"></i></button>
                        </div>
                    </div>
                    <div class="col-12">
                        <div id="editDuration" class="alert alert-info py-2" style="font-size:.85rem"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">ملاحظات</label>
                        <textarea id="edit_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom" onclick="saveEditRequest()">حفظ التعديلات</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function setCurrentTime(fieldId) {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;
    const local = new Date(now - offset);
    document.getElementById(fieldId).value = local.toISOString().slice(0, 16);
}

const requestStatusLabel = {
    draft: 'مسودة',
    prepared: 'تم التحضير',
    under_review: 'تحت المراجعة',
    approved: 'معتمد',
    rejected: 'مرفوض',
    ready_for_delivery: 'جاهز للتسليم',
    in_delivery: 'في الطريق',
    delivered: 'تم التسليم',
    collected: 'تم التحصيل',
    closed: 'مغلق'
};

const requestStatusBadge = {
    draft: 'badge-draft',
    prepared: 'badge-approved',
    under_review: 'badge-pending',
    approved: 'badge-active',
    rejected: 'badge-rejected',
    ready_for_delivery: 'badge-approved',
    in_delivery: 'badge-approved',
    delivered: 'badge-active',
    collected: 'badge-active',
    closed: 'badge-active'
};

let allActiveEmployees = [];
let preparedSearch = null;
let reviewerSearch = null;
let editPreparedSearch = null;
let editReviewerSearch = null;

async function initEmployeeSearchables() {
    preparedSearch   = createSearchableSelect(document.getElementById('prepared_by_search'), 'employees');
    reviewerSearch   = createSearchableSelect(document.getElementById('reviewer_by_search'), 'employees');
    editPreparedSearch = createSearchableSelect(document.getElementById('edit_prepared_by_search'), 'employees');
    editReviewerSearch = createSearchableSelect(document.getElementById('edit_reviewer_by_search'), 'employees');

    const rows = await getLookupRows('employees');
    allActiveEmployees = rows.filter(e => e.status === 'active');
    preparedSearch.setItems(allActiveEmployees);
    reviewerSearch.setItems(allActiveEmployees);
    editPreparedSearch.setItems(allActiveEmployees);
    editReviewerSearch.setItems(allActiveEmployees);
}

function filterPreparedByDept() {
    const dept = document.getElementById('prep_dept_id').value;
    preparedSearch.setItems(dept ? allActiveEmployees.filter(e => (e.department || '') === dept) : allActiveEmployees);
    preparedSearch.reset();
}

function filterReviewerByDept() {
    const dept = document.getElementById('rev_dept_id').value;
    reviewerSearch.setItems(dept ? allActiveEmployees.filter(e => (e.department || '') === dept) : allActiveEmployees);
    reviewerSearch.reset();
}

function setEmployeeValue(searchable, empId) {
    if (!empId) { searchable.reset(); return; }
    const emp = allActiveEmployees.find(e => e.id === parseInt(empId));
    searchable.setValue(empId, emp ? `${emp.name} - ${emp.employee_code ?? emp.id}` : String(empId));
}

async function loadLookups() {
    const [customers, departments] = await Promise.all([
        apiFetch('/customers?per_page=100&status=active'),
        apiFetch('/departments')
    ]);

    if (customers.success) {
        const custOpts = customers.data.data.map(c => `<option value="${c.id}">${c.name}${c.company_name ? ' - ' + c.company_name : ''}</option>`).join('');
        document.getElementById('customer_id').innerHTML = '<option value="">اختر العميل</option>' + custOpts;
        const editCust = document.getElementById('edit_customer_id');
        if (editCust) editCust.innerHTML = '<option value="">اختر العميل</option>' + custOpts;
    }

    if (departments.success) {
        const deptOpts = (departments.data ?? []).map(d => `<option value="${escapeHtml(d.name)}">${escapeHtml(d.name)}</option>`).join('');
        const allDepts = '<option value="">كل الأقسام</option>' + deptOpts;
        document.getElementById('prep_dept_id').innerHTML = allDepts;
        document.getElementById('rev_dept_id').innerHTML = allDepts;
    }
}

async function loadPrepaidRequests() {
    const r = await apiFetch('/requests?payment_type=prepaid&status=under_review&per_page=50');
    if (!r.success) return;

    const rows = r.data.data ?? [];
    document.getElementById('underReviewCount').textContent = rows.length;
    document.getElementById('itemsTotal').textContent = rows.reduce((sum, req) => sum + Number(req.items_count || 0), 0);
    document.getElementById('ordersTotal').textContent = rows.reduce((sum, req) => sum + Number(req.orders_count || req.total_quantity || 0), 0);

    if (!rows.length) {
        document.getElementById('prepaidTable').innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">لا توجد طلبات مرحلة</td></tr>';
        return;
    }

    document.getElementById('prepaidTable').innerHTML = rows.map(req => `
        <tr>
            <td><strong>${req.request_number ?? '#' + req.id}</strong></td>
            <td>${req.customer?.name ?? req.customer_name ?? '-'}</td>
            <td>${req.items_count ?? 0}</td>
            <td>${req.orders_count ?? req.total_quantity ?? 0}</td>
            <td>${req.prepared_by?.name ?? '-'}</td>
            <td>${req.reviewer_employee?.name ?? req.assigned_employee?.name ?? '-'}</td>
            <td><span class="badge-status ${requestStatusBadge[req.status] || 'badge-draft'}">${requestStatusLabel[req.status] || req.status}</span></td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-info" onclick="viewPrepaid(${req.id})" title="عرض"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-outline-warning" onclick="openEditRequestModal(${req.id})" title="تعديل"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-success" onclick="approvePrepaid(${req.id})" title="اعتماد"><i class="fas fa-check"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="rejectPrepaid(${req.id})" title="رفض"><i class="fas fa-times"></i></button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function viewPrepaid(id) {
    const r = await apiFetch('/requests/' + id);
    if (!r.success) {
        showAlert(r.message || 'تعذر عرض الطلب', 'danger');
        return;
    }
    const req = r.data;
    showAlert(`الطلب ${req.request_number ?? id}: ${req.customer?.name ?? req.customer_name ?? '-'} - ${req.items_count ?? 0} أصناف / ${req.orders_count ?? req.total_quantity ?? 0} طلبيات`);
}

async function approvePrepaid(id) {
    const r = await apiFetch(`/requests/${id}/manager-approve`, { method: 'POST', body: JSON.stringify({ notes: 'اعتماد من صفحة تحضير الطلبيه' }) });
    if (r.success) {
        showAlert('تم اعتماد الطلب');
        loadPrepaidRequests();
        return;
    }
    showAlert(r.message || 'فشل اعتماد الطلب', 'danger');
}

async function rejectPrepaid(id) {
    const reason = prompt('سبب رفض الطلب:');
    if (!reason) return;
    const r = await apiFetch(`/requests/${id}/manager-reject`, { method: 'POST', body: JSON.stringify({ reason }) });
    if (r.success) {
        showAlert('تم رفض الطلب', 'warning');
        loadPrepaidRequests();
        return;
    }
    showAlert(r.message || 'فشل رفض الطلب', 'danger');
}

async function openEditRequestModal(id) {
    const r = await apiFetch('/requests/' + id);
    if (!r.success) return;
    const req = r.data;
    document.getElementById('editRequestId').value = req.id;
    document.getElementById('edit_customer_id').value = req.customer_id ?? '';
    document.getElementById('edit_items_count').value = req.items_count ?? '';
    document.getElementById('edit_orders_count').value = req.orders_count ?? req.total_quantity ?? '';
    setEmployeeValue(editPreparedSearch, req.prepared_by_id ?? '');
    setEmployeeValue(editReviewerSearch, req.reviewer_employee_id ?? '');
    document.getElementById('edit_started_at').value = req.started_at ? req.started_at.substring(0, 16) : '';
    document.getElementById('edit_ended_at').value = req.ended_at ? req.ended_at.substring(0, 16) : '';
    document.getElementById('edit_notes').value = req.notes ?? '';
    updateEditDuration();
    new bootstrap.Modal(document.getElementById('editRequestModal')).show();
}

function updateEditDuration() {
    const startedAtValue = document.getElementById('edit_started_at').value;
    if (!startedAtValue) {
        document.getElementById('editDuration').textContent = 'لم يتم تسجيل وقت بداية';
        return;
    }
    const start = new Date(startedAtValue);
    const endedAtValue = document.getElementById('edit_ended_at').value;
    const end = endedAtValue ? new Date(endedAtValue) : new Date();
    const diffMs = end - start;
    if (diffMs < 0) {
        document.getElementById('editDuration').textContent = 'وقت الانتهاء قبل وقت البداية';
        return;
    }
    const totalSec = Math.floor(diffMs / 1000);
    const h = Math.floor(totalSec / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    const parts = [];
    if (h > 0) parts.push(h + ' ساعة');
    if (m > 0) parts.push(m + ' دقيقة');
    if (s > 0) parts.push(s + ' ثانية');
    document.getElementById('editDuration').textContent = 'الفارق الزمني: ' + (parts.join(' ') || 'أقل من دقيقة');
}

async function saveEditRequest() {
    const id = document.getElementById('editRequestId').value;
    const data = {
        customer_id: document.getElementById('edit_customer_id').value || null,
        items_count: document.getElementById('edit_items_count').value ? Number(document.getElementById('edit_items_count').value) : null,
        orders_count: document.getElementById('edit_orders_count').value ? Number(document.getElementById('edit_orders_count').value) : null,
        prepared_by_id: editPreparedSearch.getValue() || null,
        reviewer_employee_id: editReviewerSearch.getValue() || null,
        started_at: document.getElementById('edit_started_at').value || null,
        ended_at: document.getElementById('edit_ended_at').value || null,
        notes: document.getElementById('edit_notes').value || null,
    };
    const r = await apiFetch('/requests/' + id, {
        method: 'PUT',
        body: JSON.stringify(data)
    });
    if (r.success) {
        bootstrap.Modal.getInstance(document.getElementById('editRequestModal')).hide();
        showAlert('تم تحديث الطلب');
        loadPrepaidRequests();
        return;
    }
    showAlert(r.message || 'فشل التحديث', 'danger');
}

document.getElementById('prepaidForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const fd = new FormData(event.target);
    const rawCustomer = fd.get('customer_id');
    const rawItems = fd.get('items_count');
    const rawOrders = fd.get('orders_count');
    const rawPrepared = fd.get('prepared_by_id');
    const rawReviewer = fd.get('reviewer_employee_id');
    const data = {
        customer_id: rawCustomer ? Number(rawCustomer) : null,
        items_count: rawItems ? Number(rawItems) : null,
        orders_count: rawOrders ? Number(rawOrders) : null,
        prepared_by_id: rawPrepared ? Number(rawPrepared) : null,
        reviewer_employee_id: rawReviewer ? Number(rawReviewer) : null,
        started_at: fd.get('started_at') || null,
        ended_at: fd.get('ended_at') || null,
        notes: fd.get('notes') || null
    };

    const r = await apiFetch('/requests/prepaid', { method: 'POST', body: JSON.stringify(data) });
    if (r.success) {
        event.target.reset();
        document.getElementById('items_count').value = 1;
        document.getElementById('orders_count').value = 1;
        document.getElementById('started_at').value = '';
        document.getElementById('ended_at').value = '';
        showAlert('تم ترحيل الطلب للمراجعة');
        loadPrepaidRequests();
    } else {
        showAlert(r.message || 'فشل ترحيل الطلب', 'danger');
    }
});

document.getElementById('edit_started_at').addEventListener('change', updateEditDuration);
document.getElementById('edit_ended_at').addEventListener('change', updateEditDuration);

document.addEventListener('DOMContentLoaded', () => {
    initEmployeeSearchables();
    loadLookups();
    loadPrepaidRequests();
    if (!document.getElementById('started_at').value) {
        setCurrentTime('started_at');
    }
});
</script>
@endpush
