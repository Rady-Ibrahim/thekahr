@extends('layouts.app')
@section('title', 'كشف حساب موظف')
@section('page-title', 'كشف حساب موظف')

@section('content')
<div class="page-header">
    <div><h1><i class="fas fa-file-invoice me-2 text-primary"></i> كشف حساب موظف</h1><div class="breadcrumb">عرض كشف حساب موظف لكامل المعاملات المالية</div></div>
    <button class="btn btn-sm btn-outline-primary text-nowrap" onclick="printStatementPDF()" id="fsPrintBtn" style="display:none" title="طباعة PDF">
        <i class="fas fa-file-pdf me-1"></i> طباعة PDF
    </button>
</div>

<!-- FILTERS -->
<div class="section-card mb-4">
    <div class="section-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">الموظف *</label>
                <div class="position-relative">
                    <input type="text" name="employee_id" id="fs_emp_search" class="form-control" placeholder="ابحث بالاسم أو الكود..." required>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">الشهر</label>
                <input type="number" id="fs_month" class="form-control" min="1" max="12" value="{{ date('n') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">السنة</label>
                <input type="number" id="fs_year" class="form-control" value="{{ date('Y') }}">
            </div>
            <div class="col-md-2">
                <button class="btn-primary-custom w-100" onclick="loadStatement()"><i class="fas fa-search me-1"></i> عرض</button>
            </div>
        </div>
    </div>
</div>

<!-- RESULTS -->
<div id="statementContainer" style="display:none">
    <!-- Employee info -->
    <div class="section-card mb-4">
        <div class="section-header"><i class="fas fa-user-tie text-primary"></i><h5 class="section-title" id="fsEmpName">-</h5></div>
        <div class="section-body">
            <div class="row g-3 text-center">
                <div class="col-md-3"><div class="stat-card"><div class="stat-value" id="fsBaseSalary">-</div><div class="stat-label">الراتب الأساسي</div></div></div>
                <div class="col-md-3"><div class="stat-card"><div class="stat-value text-success" id="fsTotalAdditions">-</div><div class="stat-label">الإضافات</div></div></div>
                <div class="col-md-3"><div class="stat-card"><div class="stat-value text-danger" id="fsTotalDeductions">-</div><div class="stat-label">الخصومات</div></div></div>
                <div class="col-md-3"><div class="stat-card"><div class="stat-value fw-bold" id="fsNet">-</div><div class="stat-label">الصافي</div></div></div>
            </div>
        </div>
    </div>

    <!-- Salary Detail -->
    <div class="section-card mb-4" id="fsSalarySection" style="display:none">
        <div class="section-header"><i class="fas fa-money-bill-wave text-primary"></i><h5 class="section-title">تفاصيل الراتب</h5></div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>النوع</th><th>الاسم</th><th>السبب</th><th>المبلغ</th></tr></thead>
                <tbody id="fsSalaryComponents"></tbody>
            </table>
        </div>
    </div>

    <!-- Incentives -->
    <div class="section-card mb-4" id="fsIncentivesSection">
        <div class="section-header"><i class="fas fa-star text-warning"></i><h5 class="section-title">الحوافز</h5><span class="ms-auto text-muted" id="fsIncCount"></span></div>
        <div class="table-responsive">
            <table class="data-table"><thead><tr><th>النوع</th><th>السبب</th><th>المبلغ</th><th>الحالة</th></tr></thead><tbody id="fsIncentives"></tbody></table>
        </div>
    </div>

    <!-- Allowances -->
    <div class="section-card mb-4" id="fsAllowancesSection">
        <div class="section-header"><i class="fas fa-gift text-success"></i><h5 class="section-title">البدلات</h5><span class="ms-auto text-muted" id="fsAllCount"></span></div>
        <div class="table-responsive">
            <table class="data-table"><thead><tr><th>النوع</th><th>السبب</th><th>المبلغ</th><th>الحالة</th></tr></thead><tbody id="fsAllowances"></tbody></table>
        </div>
    </div>

    <!-- Points -->
    <div class="section-card mb-4" id="fsPointsSection">
        <div class="section-header"><i class="fas fa-star-half-alt text-primary"></i><h5 class="section-title">النقاط</h5><span class="ms-auto text-muted" id="fsPtsCount"></span></div>
        <div class="table-responsive">
            <table class="data-table"><thead><tr><th>النوع</th><th>السبب</th><th>النقاط</th><th>المبلغ</th></tr></thead><tbody id="fsPoints"></tbody></table>
        </div>
    </div>

    <!-- Deductions -->
    <div class="section-card mb-4" id="fsDeductionsSection">
        <div class="section-header"><i class="fas fa-minus-circle text-danger"></i><h5 class="section-title">الخصومات</h5><span class="ms-auto text-muted" id="fsDedCount"></span></div>
        <div class="table-responsive">
            <table class="data-table"><thead><tr><th>النوع</th><th>السبب</th><th>المبلغ</th><th>الحالة</th></tr></thead><tbody id="fsDeductions"></tbody></table>
        </div>
    </div>

    <!-- Advances -->
    <div class="section-card mb-4" id="fsAdvancesSection">
        <div class="section-header"><i class="fas fa-hand-holding-usd text-warning"></i><h5 class="section-title">السلف</h5><span class="ms-auto text-muted" id="fsAdvCount"></span></div>
        <div class="table-responsive">
            <table class="data-table"><thead><tr><th>السبب</th><th>المبلغ</th><th>المتبقي</th><th>الحالة</th></tr></thead><tbody id="fsAdvances"></tbody></table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const salStatuses = { draft:'مسودة', pending_approval:'بانتظار الاعتماد', approved:'معتمد', paid:'مدفوع', rejected:'مرفوض' };
let lastStatement = null;
let fsEmpSearch = null;
let fsEmployees = [];

async function initFsEmpSearch() {
    fsEmpSearch = createSearchableSelect(document.getElementById('fs_emp_search'), 'employees');
    fsEmployees = await getLookupRows('employees');
    fsEmpSearch.setItems(fsEmployees);
}

function setFsEmpValue(empId) {
    if (!empId) { fsEmpSearch.reset(); return; }
    const emp = fsEmployees.find(e => e.id === parseInt(empId));
    fsEmpSearch.setValue(empId, emp ? `${emp.name}${emp.employee_code ? ' - ' + emp.employee_code : ''}` : String(empId));
}

async function loadStatement() {
    const empId = fsEmpSearch.getValue();
    if (!empId) { showAlert('اختر الموظف أولاً', 'danger'); return; }
    const month = document.getElementById('fs_month').value;
    const year = document.getElementById('fs_year').value;

    const r = await apiFetch(`/employees/${empId}/financial-statement?month=${month}&year=${year}`);
    if (!r.success) { showAlert(r.message || 'فشل تحميل البيانات', 'danger'); return; }

    lastStatement = r;
    document.getElementById('fsPrintBtn').style.display = 'inline-flex';
    document.getElementById('statementContainer').style.display = 'block';

    // Employee info
    document.getElementById('fsEmpName').textContent = `${r.employee.name} (${r.employee.employee_code}) - ${r.employee.department??''}`;
    document.getElementById('fsBaseSalary').textContent = Number(r.employee.base_salary).toLocaleString() + ' ج.م';
    const additions = (r.summary.incentives_total||0) + (r.summary.allowances_total||0) + (r.summary.points_credit_total||0);
    const deductions = (r.summary.deductions_total||0) + (r.summary.advances_installment_total||0) + (r.summary.points_debit_total||0) + (r.summary.attendance_deduction_total||0);
    document.getElementById('fsTotalAdditions').textContent = '+' + Number(additions).toLocaleString() + ' ج.م';
    document.getElementById('fsTotalDeductions').textContent = '-' + Number(deductions).toLocaleString() + ' ج.م';
    document.getElementById('fsNet').textContent = Number(r.summary.estimated_net||0).toLocaleString() + ' ج.م';

    // Salary components
    if (r.data.salary) {
        document.getElementById('fsSalarySection').style.display = 'block';
        const comps = r.data.salary.components || [];
        document.getElementById('fsSalaryComponents').innerHTML = comps.length
            ? comps.map(c => `<tr><td>${c.component_type}</td><td>${c.component_name}</td><td>${c.reason||'-'}</td><td class="${c.amount<0?'text-danger':'text-success'}">${Number(c.amount).toLocaleString()} ج.م</td></tr>`).join('')
            : '<tr><td colspan="4" class="text-muted text-center">لا توجد مكونات</td></tr>';
    }

    // Incentives
    const inc = r.data.incentives || [];
    document.getElementById('fsIncCount').textContent = `(${inc.length})`;
    document.getElementById('fsIncentives').innerHTML = inc.length
        ? inc.map(i => `<tr><td>${i.incentive_type}</td><td>${i.reason||i.description||'-'}</td><td class="text-success">${Number(i.amount).toLocaleString()} ج.م</td><td><span class="badge-status ${i.status==='approved'?'badge-active':i.status==='rejected'?'badge-rejected':'badge-pending'}">${i.status==='approved'?'معتمد':i.status==='rejected'?'مرفوض':'معلق'}</span></td></tr>`).join('')
        : '<tr><td colspan="4" class="text-muted text-center">لا توجد حوافز</td></tr>';

    // Allowances
    const all = r.data.allowances || [];
    document.getElementById('fsAllCount').textContent = `(${all.length})`;
    document.getElementById('fsAllowances').innerHTML = all.length
        ? all.map(a => `<tr><td>${a.allowance_type}</td><td>${a.reason||'-'}</td><td class="text-success">${Number(a.amount).toLocaleString()} ج.م</td><td><span class="badge-status ${a.status==='active'?'badge-active':'badge-inactive'}">${a.status==='active'?'نشط':'غير نشط'}</span></td></tr>`).join('')
        : '<tr><td colspan="4" class="text-muted text-center">لا توجد بدلات</td></tr>';

    // Points
    const pts = r.data.points || [];
    document.getElementById('fsPtsCount').textContent = `(${pts.length})`;
    document.getElementById('fsPoints').innerHTML = pts.length
        ? pts.map(p => `<tr><td>${p.direction==='credit'?'له (+)':'عليه (-)'}</td><td>${p.reason||'-'}</td><td>${Number(p.points).toLocaleString()}</td><td class="${p.direction==='credit'?'text-success':'text-danger'}">${Number(p.total_amount).toLocaleString()} ج.م</td></tr>`).join('')
        : '<tr><td colspan="4" class="text-muted text-center">لا توجد نقاط</td></tr>';

    // Deductions
    const ded = r.data.deductions || [];
    document.getElementById('fsDedCount').textContent = `(${ded.length})`;
    document.getElementById('fsDeductions').innerHTML = ded.length
        ? ded.map(d => `<tr><td>${d.deduction_type}</td><td>${d.reason||'-'}</td><td class="text-danger">${Number(d.amount).toLocaleString()} ج.م</td><td>${d.status==='computed'
            ? '<span class="badge-status badge-active">محسوب</span>'
            : `<span class="badge-status ${d.status==='approved'?'badge-active':d.status==='rejected'?'badge-rejected':'badge-pending'}">${d.status==='approved'?'معتمد':d.status==='rejected'?'مرفوض':'معلق'}</span>`}</td></tr>`).join('')
        : '<tr><td colspan="4" class="text-muted text-center">لا توجد خصومات</td></tr>';

    // Advances
    const adv = r.data.advances || [];
    document.getElementById('fsAdvCount').textContent = `(${adv.length})`;
    document.getElementById('fsAdvances').innerHTML = adv.length
        ? adv.map(a => `<tr><td>${a.reason||'-'}</td><td class="fw-bold">${Number(a.amount).toLocaleString()} ج.م</td><td class="text-warning">${Number(a.remaining_amount).toLocaleString()} ج.م</td><td>${a.status}</td></tr>`).join('')
        : '<tr><td colspan="4" class="text-muted text-center">لا توجد سلف</td></tr>';
}

function printStatementPDF() {
    const r = lastStatement;
    if (!r) { showAlert('قم بعرض كشف الحساب أولاً', 'danger'); return; }

    const s = r.summary || {};
    const emp = r.employee || {};
    const d = r.data || {};

    const additions = (s.incentives_total||0) + (s.allowances_total||0) + (s.points_credit_total||0);
    const deductions = (s.deductions_total||0) + (s.advances_installment_total||0) + (s.points_debit_total||0) + (s.attendance_deduction_total||0);
    const statusMap = { approved:'معتمد', rejected:'مرفوض', pending:'معلق', active:'نشط', computed:'محسوب' };
    const st = st2 => statusMap[st2] || st2;

    let html = `
        <div class="ph">
            <h1>كشف حساب موظف</h1>
            <div class="meta">${escapeHtml(emp.name || '')}${emp.employee_code ? ' - ' + escapeHtml(emp.employee_code) : ''}${emp.department ? ' - ' + escapeHtml(emp.department) : ''} | شهر ${r.month} / ${r.year}</div>
        </div>
        <div class="sum">
            <div class="box"><div class="lbl">الراتب الأساسي</div><div class="val">${Number(s.base_salary||0).toLocaleString()} ج.م</div></div>
            <div class="box"><div class="lbl">الإضافات</div><div class="val pos">+${Number(additions).toLocaleString()} ج.م</div></div>
            <div class="box"><div class="lbl">الخصومات</div><div class="val neg">-${Number(deductions).toLocaleString()} ج.م</div></div>
            <div class="box"><div class="lbl">الصافي</div><div class="val">${Number(s.estimated_net||0).toLocaleString()} ج.م</div></div>
        </div>`;

    if (d.salary) {
        const comps = d.salary.components || [];
        html += `<div class="h2">تفاصيل الراتب</div><table><thead><tr><th>النوع</th><th>الاسم</th><th>السبب</th><th>المبلغ</th></tr></thead><tbody>`;
        html += comps.length
            ? comps.map(c => `<tr><td>${escapeHtml(c.component_type)}</td><td>${escapeHtml(c.component_name)}</td><td>${escapeHtml(c.reason||'-')}</td><td class="${Number(c.amount)<0?'neg':'pos'}">${Number(c.amount).toLocaleString()} ج.م</td></tr>`).join('')
            : '<tr><td colspan="4" style="text-align:center;color:#6b7280">لا توجد مكونات</td></tr>';
        html += `</tbody></table>`;
    }

    const inc = d.incentives || [];
    html += `<div class="h2">الحوافز (${inc.length})</div><table><thead><tr><th>النوع</th><th>السبب</th><th>المبلغ</th><th>الحالة</th></tr></thead><tbody>`;
    html += inc.length
        ? inc.map(i => `<tr><td>${escapeHtml(i.incentive_type)}</td><td>${escapeHtml(i.reason||'-')}</td><td class="pos">${Number(i.amount).toLocaleString()} ج.م</td><td>${st(i.status)}</td></tr>`).join('')
        : '<tr><td colspan="4" style="text-align:center;color:#6b7280">لا توجد حوافز</td></tr>';
    html += `</tbody></table>`;

    const all = d.allowances || [];
    html += `<div class="h2">البدلات (${all.length})</div><table><thead><tr><th>النوع</th><th>السبب</th><th>المبلغ</th><th>الحالة</th></tr></thead><tbody>`;
    html += all.length
        ? all.map(a => `<tr><td>${escapeHtml(a.allowance_type)}</td><td>${escapeHtml(a.reason||'-')}</td><td class="pos">${Number(a.amount).toLocaleString()} ج.م</td><td>${st(a.status)}</td></tr>`).join('')
        : '<tr><td colspan="4" style="text-align:center;color:#6b7280">لا توجد بدلات</td></tr>';
    html += `</tbody></table>`;

    const pts = d.points || [];
    html += `<div class="h2">النقاط (${pts.length})</div><table><thead><tr><th>النوع</th><th>السبب</th><th>النقاط</th><th>المبلغ</th></tr></thead><tbody>`;
    html += pts.length
        ? pts.map(p => `<tr><td>${p.direction==='credit'?'له (+)':'عليه (-)'}</td><td>${escapeHtml(p.reason||'-')}</td><td>${Number(p.points).toLocaleString()}</td><td class="${p.direction==='credit'?'pos':'neg'}">${Number(p.total_amount).toLocaleString()} ج.م</td></tr>`).join('')
        : '<tr><td colspan="4" style="text-align:center;color:#6b7280">لا توجد نقاط</td></tr>';
    html += `</tbody></table>`;

    const ded = d.deductions || [];
    html += `<div class="h2">الخصومات (${ded.length})</div><table><thead><tr><th>النوع</th><th>السبب</th><th>المبلغ</th><th>الحالة</th></tr></thead><tbody>`;
    html += ded.length
        ? ded.map(x => `<tr><td>${escapeHtml(x.deduction_type)}</td><td>${escapeHtml(x.reason||'-')}</td><td class="neg">${Number(x.amount).toLocaleString()} ج.م</td><td>${st(x.status)}</td></tr>`).join('')
        : '<tr><td colspan="4" style="text-align:center;color:#6b7280">لا توجد خصومات</td></tr>';
    html += `</tbody></table>`;

    const adv = d.advances || [];
    html += `<div class="h2">السلف (${adv.length})</div><table><thead><tr><th>السبب</th><th>المبلغ</th><th>المتبقي</th><th>الحالة</th></tr></thead><tbody>`;
    html += adv.length
        ? adv.map(a => `<tr><td>${escapeHtml(a.reason||'-')}</td><td>${Number(a.amount).toLocaleString()} ج.م</td><td>${Number(a.remaining_amount).toLocaleString()} ج.م</td><td>${st(a.status)}</td></tr>`).join('')
        : '<tr><td colspan="4" style="text-align:center;color:#6b7280">لا توجد سلف</td></tr>';
    html += `</tbody></table>`;

    printHTML('كشف حساب موظف', html);
}

document.addEventListener('DOMContentLoaded', async function() {
    await initFsEmpSearch();
    // Auto-load if employee_id is pre-selected (from URL param)
    const params = new URLSearchParams(window.location.search);
    const empId = params.get('employee_id');
    if (empId) {
        setFsEmpValue(empId);
        loadStatement();
    }
});
</script>
@endpush
