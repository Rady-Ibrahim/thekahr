@extends('layouts.app')
@section('title', 'الموافقات')
@section('page-title', 'الموافقات')

@section('content')
<div class="page-header">
    <div>
        <h1><i class="fas fa-check-double me-2 text-primary"></i> الموافقات</h1>
        <div class="breadcrumb">مراجعة واعتماد العناصر المعلقة</div>
    </div>
</div>

<!-- PENDING SUMMARY -->
<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="stat-card text-center"><div class="stat-icon mx-auto" style="background:#fff3e0;color:#e65100"><i class="fas fa-clipboard-list"></i></div><div class="stat-value text-warning" id="pendReqs">-</div><div class="stat-label">طلبات</div></div></div>
    <div class="col-md-2"><div class="stat-card text-center"><div class="stat-icon mx-auto" style="background:#e3f2fd;color:#1565c0"><i class="fas fa-coins"></i></div><div class="stat-value text-info" id="pendCols">-</div><div class="stat-label">تحصيلات</div></div></div>
    <div class="col-md-2"><div class="stat-card text-center"><div class="stat-icon mx-auto" style="background:#f3e5f5;color:#7b1fa2"><i class="fas fa-percentage"></i></div><div class="stat-value" id="pendComs" style="color:#7b1fa2">-</div><div class="stat-label">عمولات</div></div></div>
    <div class="col-md-3"><div class="stat-card text-center"><div class="stat-icon mx-auto" style="background:#e8f5e9;color:#2e7d32"><i class="fas fa-money-bill-wave"></i></div><div class="stat-value text-success" id="pendSals">-</div><div class="stat-label">رواتب</div></div></div>
    <div class="col-md-3"><div class="stat-card text-center"><div class="stat-icon mx-auto" style="background:#fce4ec;color:#c62828"><i class="fas fa-hourglass-half"></i></div><div class="stat-value text-danger" id="pendTotal">-</div><div class="stat-label">إجمالي معلق</div></div></div>
</div>

<!-- TABS -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><button class="nav-link active" onclick="showApprovalTab('requests', this)"><i class="fas fa-clipboard-list me-1"></i> الطلبات</button></li>
    <li class="nav-item"><button class="nav-link" onclick="showApprovalTab('collections', this)"><i class="fas fa-coins me-1"></i> التحصيلات</button></li>
    <li class="nav-item"><button class="nav-link" onclick="showApprovalTab('commissions', this)"><i class="fas fa-percentage me-1"></i> العمولات</button></li>
    <li class="nav-item"><button class="nav-link" onclick="showApprovalTab('salaries', this)"><i class="fas fa-money-bill-wave me-1"></i> الرواتب</button></li>
    <li class="nav-item"><button class="nav-link" onclick="showApprovalTab('history', this)"><i class="fas fa-history me-1"></i> السجل</button></li>
</ul>

<!-- REQUESTS TAB -->
<div id="approval-requests">
    <div class="section-card">
        <div class="section-header"><i class="fas fa-clipboard-list text-primary"></i><h5 class="section-title">الطلبات المعلقة</h5></div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>رقم الطلب</th><th>العميل</th><th>صاحب الطلب</th><th>المرحلة</th><th>المسؤول</th><th>الإجمالي</th><th>التاريخ</th><th>إجراءات</th></tr></thead>
                <tbody id="pendingReqsTable"><tr><td colspan="8" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- COLLECTIONS TAB -->
<div id="approval-collections" style="display:none">
    <div class="section-card">
        <div class="section-header"><i class="fas fa-coins text-primary"></i><h5 class="section-title">التحصيلات المعلقة</h5></div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>المبلغ</th><th>السائق</th><th>طريقة الدفع</th><th>التاريخ</th><th>إجراءات</th></tr></thead>
                <tbody id="pendingColsTable"><tr><td colspan="5" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- SALARIES TAB -->
<div id="approval-salaries" style="display:none">
    <div class="section-card">
        <div class="section-header"><i class="fas fa-money-bill-wave text-primary"></i><h5 class="section-title">الرواتب المعلقة</h5></div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>الموظف</th><th>الشهر</th><th>صافي الراتب</th><th>إجراءات</th></tr></thead>
                <tbody id="pendingSalsTable"><tr><td colspan="4" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- COMMISSIONS TAB (الاعتماد الكلي للعمولات) -->
<div id="approval-commissions" style="display:none">
    <div class="section-card">
        <div class="section-header">
            <i class="fas fa-percentage text-primary"></i>
            <h5 class="section-title">عمولات التحصيل المعلقة</h5>
            <button class="btn btn-sm btn-success ms-auto" onclick="bulkApproveCommissions()"><i class="fas fa-check-double me-1"></i> اعتماد المحدد</button>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="comSelectAll" onchange="toggleAllCommissions(this.checked)"></th>
                        <th>الموظف</th>
                        <th>المصدر</th>
                        <th>التحصيل</th>
                        <th>النسبة</th>
                        <th>المبلغ</th>
                        <th>الشهر</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody id="pendingComsTable"><tr><td colspan="8" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- HISTORY TAB -->
<div id="approval-history" style="display:none">
    <div class="section-card">
        <div class="section-header"><i class="fas fa-history text-primary"></i><h5 class="section-title">سجل الموافقات</h5></div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>النوع</th><th>الموافق</th><th>الحالة</th><th>الملاحظات</th><th>التاريخ</th></tr></thead>
                <tbody id="historyTable"><tr><td colspan="5" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
async function loadPending() {
    const r = await apiFetch('/approvals/pending');
    if (!r.success) return;
    const d = r.data;
    const s = r.summary;

    document.getElementById('pendReqs').textContent  = s.pending_requests ?? 0;
    document.getElementById('pendCols').textContent  = s.pending_collections ?? 0;
    document.getElementById('pendComs').textContent  = s.pending_commissions ?? 0;
    document.getElementById('pendSals').textContent  = s.pending_salaries ?? 0;
    document.getElementById('pendTotal').textContent = s.total_pending ?? 0;
    const pendingCount = document.getElementById('pendingCount');
    if (pendingCount) pendingCount.textContent = s.total_pending ?? 0;

    // Requests
    const reqs = d.requests ?? [];
    document.getElementById('pendingReqsTable').innerHTML = reqs.length ? reqs.map(req => `
        <tr>
            <td>#${req.request_number ?? req.id}</td>
            <td>${req.customer?.name ?? '-'}</td>
            <td>${req.created_by?.name ?? req.prepared_by?.name ?? '-'}</td>
            <td><span class="badge-status ${req.pending_approval_type === 'reviewer_request_review' ? 'badge-pending' : 'badge-approved'}">${req.pending_approval_type === 'reviewer_request_review' ? 'مراجعة موظف' : 'اعتماد مدير'}</span></td>
            <td>${req.pending_approver?.name ?? req.reviewer_employee?.name ?? req.created_by?.manager?.name ?? '-'}</td>
            <td class="fw-bold">${Number(req.total_amount ?? 0).toLocaleString()} ج.م</td>
            <td>${req.created_at ? new Date(req.created_at).toLocaleDateString('ar-EG') : '-'}</td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-success" onclick="approveRequestStage(${req.id}, '${req.pending_approval_type}')"><i class="fas fa-check"></i> اعتماد</button>
                    <button class="btn btn-sm btn-danger" onclick="rejectRequestStage(${req.id}, '${req.pending_approval_type}')"><i class="fas fa-times"></i> رفض</button>
                </div>
            </td>
        </tr>
    `).join('') : '<tr><td colspan="8" class="text-center py-4 text-muted">لا توجد طلبات معلقة</td></tr>';

    // Collections
    const cols = d.collections ?? [];
    document.getElementById('pendingColsTable').innerHTML = cols.length ? cols.map(c => `
        <tr>
            <td class="fw-bold text-success">${Number(c.total_amount ?? c.amount ?? 0).toLocaleString()} ج.م</td>
            <td>${c.driver?.name ?? '-'}</td>
            <td>${c.payment_method ?? '-'}</td>
            <td>${c.created_at ? new Date(c.created_at).toLocaleDateString('ar-EG') : '-'}</td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-success" onclick="approveItem('collection', ${c.id})"><i class="fas fa-check"></i> اعتماد</button>
                    <button class="btn btn-sm btn-danger" onclick="rejectItem('collection', ${c.id})"><i class="fas fa-times"></i> رفض</button>
                </div>
            </td>
        </tr>
    `).join('') : '<tr><td colspan="5" class="text-center py-4 text-muted">لا توجد تحصيلات معلقة</td></tr>';

    // Salaries
    const sals = d.salaries ?? [];
    document.getElementById('pendingSalsTable').innerHTML = sals.length ? sals.map(s => `
        <tr>
            <td>${s.employee?.name ?? '-'}</td>
            <td>${s.month}/${s.year}</td>
            <td class="fw-bold text-primary">${Number(s.net_salary).toLocaleString()} ج.م</td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-success" onclick="approveSalary(${s.id})"><i class="fas fa-check"></i> اعتماد</button>
                    <button class="btn btn-sm btn-primary" onclick="paySalary(${s.id})"><i class="fas fa-money-bill"></i> صرف</button>
                </div>
            </td>
        </tr>
    `).join('') : '<tr><td colspan="4" class="text-center py-4 text-muted">لا توجد رواتب معلقة</td></tr>';

    // Commissions
    const coms = d.commissions ?? [];
    document.getElementById('pendingComsTable').innerHTML = coms.length ? coms.map(c => `
        <tr>
            <td><input type="checkbox" class="com-check" value="${c.id}"></td>
            <td>${c.employee?.name ?? '-'}</td>
            <td><span class="badge-status ${c.source === 'collection' ? 'badge-active' : 'badge-draft'}">${c.source === 'collection' ? 'تحصيل' : 'يدوي'}</span></td>
            <td>${c.collection?.collection_number ?? '-'}</td>
            <td>${c.commission_rate != null ? Number(c.commission_rate) + '%' : '-'}</td>
            <td class="fw-bold text-success">${Number(c.amount ?? 0).toLocaleString()} ج.م</td>
            <td>${c.month}/${c.year}</td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-success" onclick="approveCommission(${c.id})"><i class="fas fa-check"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="rejectCommission(${c.id})"><i class="fas fa-times"></i></button>
                </div>
            </td>
        </tr>
    `).join('') : '<tr><td colspan="8" class="text-center py-4 text-muted">لا توجد عمولات معلقة</td></tr>';
}

async function approveItem(type, id) {
    const notes = prompt('ملاحظات (اختياري):') ?? '';
    let url, body;
    if (type === 'request')    { url = `/requests/${id}/approve`;    body = { notes }; }
    if (type === 'collection') {
        const actual = prompt('المبلغ الفعلي (اتركه فارغ لاعتماد المبلغ المسجل):');
        url = `/collections/${id}/approve`;
        body = { notes };
        if (actual !== null && actual !== '') body.actual_amount = parseFloat(actual);
    }
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(body) });
    if (r.success) { showAlert('تم الاعتماد'); loadPending(); }
    else showAlert(r.message, 'danger');
}

async function approveRequestStage(id, stage) {
    const notes = prompt('ملاحظات (اختياري):') ?? '';
    const url = stage === 'reviewer_request_review'
        ? `/requests/${id}/reviewer-approve`
        : `/requests/${id}/manager-approve`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify({ notes }) });
    if (r.success) { showAlert(stage === 'reviewer_request_review' ? 'تمت مراجعة الطلب وإرساله للمدير' : 'تم اعتماد الطلب'); loadPending(); }
    else showAlert(r.message, 'danger');
}

async function rejectRequestStage(id, stage) {
    const reason = prompt('سبب الرفض:');
    if (!reason) return;
    const url = stage === 'reviewer_request_review'
        ? `/requests/${id}/reviewer-reject`
        : `/requests/${id}/manager-reject`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify({ reason, rejection_reason: reason }) });
    if (r.success) { showAlert('تم رفض الطلب', 'warning'); loadPending(); }
    else showAlert(r.message, 'danger');
}

async function rejectItem(type, id) {
    const reason = prompt('سبب الرفض:');
    if (!reason) return;
    let url;
    if (type === 'request')    url = `/requests/${id}/reject`;
    if (type === 'collection') url = `/collections/${id}/reject`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify({ reason }) });
    if (r.success) { showAlert('تم الرفض', 'warning'); loadPending(); }
    else showAlert(r.message, 'danger');
}

async function approveSalary(id) {
    const r = await apiFetch(`/salaries/${id}/approve`, { method: 'POST' });
    if (r.success) { showAlert('تم اعتماد الراتب'); loadPending(); }
    else showAlert(r.message, 'danger');
}

async function paySalary(id) {
    if (!confirm('هل تريد صرف هذا الراتب؟')) return;
    const r = await apiFetch(`/salaries/${id}/pay`, { method: 'POST' });
    if (r.success) { showAlert('تم الصرف'); loadPending(); }
    else showAlert(r.message, 'danger');
}

async function loadHistory() {
    const r = await apiFetch('/approvals/history');
    if (!r.success) return;
    const all = r.data?.data ?? r.data ?? [];
    document.getElementById('historyTable').innerHTML = all.length ? all.map(h => `
        <tr>
            <td>${h.approvable_type?.split('\\').pop() ?? '-'}</td>
            <td>${h.approver?.name ?? '-'}</td>
            <td><span class="badge-status ${h.status === 'approved' ? 'badge-active' : 'badge-rejected'}">${h.status === 'approved' ? 'معتمد' : 'مرفوض'}</span></td>
            <td>${h.notes ?? '-'}</td>
            <td>${h.created_at ? new Date(h.created_at).toLocaleString('ar-EG') : '-'}</td>
        </tr>
    `).join('') : '<tr><td colspan="5" class="text-center py-4 text-muted">لا توجد سجلات</td></tr>';
}

async function approveCommission(id) {
    const r = await apiFetch(`/commissions/${id}/approve`, { method: 'POST' });
    if (r.success) { showAlert('تم اعتماد العمولة'); loadPending(); }
    else showAlert(r.message, 'danger');
}

async function rejectCommission(id) {
    if (!confirm('رفض هذه العمولة؟')) return;
    const r = await apiFetch(`/commissions/${id}/reject`, { method: 'POST' });
    if (r.success) { showAlert('تم رفض العمولة', 'warning'); loadPending(); }
    else showAlert(r.message, 'danger');
}

function toggleAllCommissions(checked) {
    document.querySelectorAll('.com-check').forEach(cb => { cb.checked = checked; });
}

async function bulkApproveCommissions() {
    const ids = [...document.querySelectorAll('.com-check:checked')].map(cb => parseInt(cb.value));
    if (!ids.length) { showAlert('اختر عمولة واحدة على الأقل', 'warning'); return; }
    if (!confirm(`اعتماد ${ids.length} عمولة؟`)) return;
    const r = await apiFetch('/commissions/bulk-approve', { method: 'POST', body: JSON.stringify({ commission_ids: ids }) });
    if (r.success) { showAlert(r.message || 'تم الاعتماد الجماعي'); loadPending(); }
    else showAlert(r.message, 'danger');
}

function showApprovalTab(tab, btn) {
    ['requests','collections','salaries','commissions','history'].forEach(t => {
        const el = document.getElementById('approval-' + t);
        if (el) el.style.display = t === tab ? '' : 'none';
    });
    document.querySelectorAll('.nav-tabs .nav-link').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    if (tab === 'history') loadHistory();
}

document.addEventListener('DOMContentLoaded', loadPending);
</script>
@endpush
