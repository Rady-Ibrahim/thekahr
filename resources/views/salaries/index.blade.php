@extends('layouts.app')
@section('title', 'إدارة الرواتب')
@section('page-title', 'الرواتب')

@section('content')
<div class="page-header">
    <div>
        <h1><i class="fas fa-money-bill-wave me-2 text-primary"></i> الرواتب</h1>
        <div class="breadcrumb">حساب واعتماد رواتب الموظفين</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" onclick="calculateSalaries()"><i class="fas fa-calculator me-1"></i> حساب الرواتب</button>
        <button class="btn-primary-custom" onclick="bulkApprove()"><i class="fas fa-check-double me-1"></i> اعتماد جماعي</button>
    </div>
</div>

<!-- MONTHLY SUMMARY -->
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#e3f2fd;color:#1565c0"><i class="fas fa-users"></i></div><div class="stat-value" id="salEmpCount">-</div><div class="stat-label">موظفين محسوبة رواتبهم</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#e8f5e9;color:#2e7d32"><i class="fas fa-money-bill"></i></div><div class="stat-value" id="salGross" style="font-size:1.3rem">-</div><div class="stat-label">إجمالي الرواتب</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#e8f5e9;color:#388e3c"><i class="fas fa-hand-holding-usd"></i></div><div class="stat-value" id="salNet" style="font-size:1.3rem">-</div><div class="stat-label">صافي الرواتب</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#fff3e0;color:#e65100"><i class="fas fa-hourglass-half"></i></div><div class="stat-value" id="salPending">-</div><div class="stat-label">معلقة</div></div></div>
</div>

<!-- FILTERS -->
<div class="section-card mb-4">
    <div class="section-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">الشهر</label>
                <input type="number" id="salMonth" class="form-control" min="1" max="12" value="{{ date('n') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">السنة</label>
                <input type="number" id="salYear" class="form-control" value="{{ date('Y') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">الحالة</label>
                <select id="salStatus" class="form-select">
                    <option value="">الكل</option>
                    <option value="draft">مسودة</option>
                    <option value="approved">معتمدة</option>
                    <option value="paid">مدفوعة</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">الموظف</label>
                <input type="text" id="salEmpSearch" class="form-control" placeholder="بحث باسم الموظف">
            </div>
            <div class="col-md-2">
                <button class="btn-primary-custom w-100" onclick="loadSalaries()"><i class="fas fa-search me-1"></i> بحث</button>
            </div>
        </div>
    </div>
</div>

<!-- SALARIES TABLE -->
<div class="section-card">
    <div class="section-header">
        <i class="fas fa-table text-primary"></i>
        <h5 class="section-title">كشف الرواتب</h5>
        <div class="ms-auto d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" onclick="printSalariesPDF()"><i class="fas fa-file-pdf me-1"></i> طباعة PDF</button>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportSalaries()"><i class="fas fa-download me-1"></i> تصدير</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
                    <th>الموظف</th>
                    <th>الأساسي</th>
                    <th>الحوافز</th>
                    <th>البدلات</th>
                    <th>العمولات</th>
                    <th>نقاط (له)</th>
                    <th>نقاط (عليه)</th>
                    <th>خصومات</th>
                    <th>سلف</th>
                    <th>صافي</th>
                    <th>الحالة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody id="salariesTable">
                <tr><td colspan="13" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr>
            </tbody>
        </table>
    </div>
    <div class="section-body d-flex justify-content-between">
        <div id="salPagInfo" class="text-muted" style="font-size:.8rem"></div>
        <div id="salPagination"></div>
    </div>
</div>

<!-- SALARY DETAIL MODAL -->
<div class="modal fade" id="salaryDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-invoice-dollar me-2"></i> تفاصيل الراتب</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="salaryDetailBody">
                <div class="text-center py-4"><div class="spinner mx-auto"></div></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let selectedSalaries = new Set();
const salBadge = { draft:'badge-draft', approved:'badge-approved', paid:'badge-active' };
const salLabel = { draft:'مسودة', approved:'معتمدة', paid:'مدفوعة' };
const attBadge = { present:'badge-active', absent:'badge-rejected', late:'badge-pending', on_leave:'badge-approved' };
const attLabel = { present:'حاضر', absent:'غائب', late:'متأخر', early_leave:'انصراف مبكر', on_leave:'إجازة', excused:'معذور' };
const deductionLabels = {
    minutes: 'دقائق', quarter_day: 'ربع يوم', half_day: 'نصف يوم',
    full_day: 'يوم كامل', percentage: 'نسبة مئوية', fixed_amount: 'مبلغ ثابت'
};

async function loadSummary() {
    const month = document.getElementById('salMonth').value;
    const year  = document.getElementById('salYear').value;
    const r = await apiFetch(`/salaries/monthly-summary?month=${month}&year=${year}`);
    if (!r.success) return;
    const d = r.data;
    document.getElementById('salEmpCount').textContent = d.employee_count ?? '-';
    document.getElementById('salGross').textContent    = Number(d.total_gross ?? 0).toLocaleString('ar-EG') + ' ج.م';
    document.getElementById('salNet').textContent      = Number(d.total_net ?? 0).toLocaleString('ar-EG') + ' ج.م';
    document.getElementById('salPending').textContent  = d.pending_count ?? '-';
}

async function loadSalaries(page = 1) {
    const params = new URLSearchParams({ per_page: 15, page });
    params.append('month', document.getElementById('salMonth').value);
    params.append('year',  document.getElementById('salYear').value);
    const s = document.getElementById('salStatus').value;
    const e = document.getElementById('salEmpSearch').value;
    if (s) params.append('status', s);
    if (e) params.append('search', e);

    const r = await apiFetch('/salaries?' + params);
    if (!r.success) return;
    const data = r.data;
    document.getElementById('salPagInfo').textContent = `إجمالي: ${data.total}`;
    const all = data.data;
    if (!all.length) {
        document.getElementById('salariesTable').innerHTML = '<tr><td colspan="11" class="text-center py-4 text-muted"><i class="fas fa-inbox fa-2x mb-2"></i><br>لا توجد رواتب محسوبة لهذا الشهر</td></tr>';
        return;
    }
    document.getElementById('salariesTable').innerHTML = all.map(s => {
        const incentives = Number(s.total_incentives ?? 0);
        const deductions = Number(s.total_deductions ?? 0);
        const ptsCredit  = Number(s.total_points_credit ?? 0);
        const ptsDebit   = Number(s.total_points_debit ?? 0);
        const realIncentives = incentives - ptsCredit;
        const realDeductions = deductions - ptsDebit;
        return `<tr>
            <td><input type="checkbox" class="salary-check" value="${s.id}" ${selectedSalaries.has(s.id) ? 'checked' : ''} onchange="toggleSalary(${s.id})"></td>
            <td><strong>${s.employee?.name ?? '-'}</strong><br><small class="text-muted">${s.employee?.employee_code ?? '-'}</small></td>
            <td>${Number(s.base_salary).toLocaleString()}</td>
            <td class="text-success">+${realIncentives.toLocaleString()}</td>
            <td class="text-success">+${Number(s.total_allowances ?? 0).toLocaleString()}</td>
            <td class="text-success">+${Number(s.total_commissions ?? 0).toLocaleString()}</td>
            <td class="text-success">${ptsCredit > 0 ? '+'+ptsCredit.toLocaleString() : '-'}</td>
            <td class="text-danger">${ptsDebit > 0 ? '-'+ptsDebit.toLocaleString() : '-'}</td>
            <td class="text-danger">-${realDeductions.toLocaleString()}</td>
            <td class="text-danger">-${Number(s.total_advances ?? 0).toLocaleString()}</td>
            <td class="fw-bold text-primary fs-6">${Number(s.net_salary).toLocaleString()} ج.م</td>
            <td><span class="badge-status ${salBadge[s.status] || 'badge-draft'}">${salLabel[s.status] || s.status}</span></td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary" onclick="viewSalary(${s.id})" title="التفاصيل"><i class="fas fa-eye"></i></button>
                    ${s.status === 'draft' ? `<button class="btn btn-sm btn-outline-success" onclick="approveSalary(${s.id})" title="اعتماد"><i class="fas fa-check"></i></button>` : ''}
                    ${s.status === 'approved' ? `<button class="btn btn-sm btn-outline-primary" onclick="paySalary(${s.id})" title="صرف"><i class="fas fa-money-bill"></i></button>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
    const pages = [];
    for (let i = 1; i <= Math.min(data.last_page, 10); i++) {
        pages.push(`<button class="btn btn-sm ${i === data.current_page ? 'btn-primary' : 'btn-outline-primary'} mx-1" onclick="loadSalaries(${i})">${i}</button>`);
    }
    document.getElementById('salPagination').innerHTML = pages.join('');
}

function toggleSalary(id) { selectedSalaries.has(id) ? selectedSalaries.delete(id) : selectedSalaries.add(id); }
function toggleAll(cb) { document.querySelectorAll('.salary-check').forEach(c => { c.checked = cb.checked; toggleSalary(parseInt(c.value)); }); }

async function calculateSalaries() {
    if (!confirm('هل تريد حساب رواتب جميع الموظفين لهذا الشهر؟')) return;
    const month = document.getElementById('salMonth').value;
    const year  = document.getElementById('salYear').value;
    const r = await apiFetch('/salaries/calculate', { method: 'POST', body: JSON.stringify({ month: parseInt(month), year: parseInt(year) }) });
    if (r.success) { showAlert('تم حساب الرواتب بنجاح'); loadSalaries(); loadSummary(); }
    else showAlert(r.message || 'فشل الحساب', 'danger');
}

async function bulkApprove() {
    if (!selectedSalaries.size) { showAlert('اختر رواتب أولاً', 'warning'); return; }
    if (!confirm(`هل تريد اعتماد ${selectedSalaries.size} راتب؟`)) return;
    const r = await apiFetch('/salaries/bulk-approve', { method: 'POST', body: JSON.stringify({ salary_ids: [...selectedSalaries] }) });
    if (r.success) { showAlert('تم الاعتماد الجماعي بنجاح'); selectedSalaries.clear(); loadSalaries(); loadSummary(); }
    else showAlert(r.message, 'danger');
}

async function approveSalary(id) {
    const r = await apiFetch(`/salaries/${id}/approve`, { method: 'POST' });
    if (r.success) { showAlert('تم اعتماد الراتب'); loadSalaries(); }
    else showAlert(r.message, 'danger');
}

async function paySalary(id) {
    if (!confirm('هل تريد صرف هذا الراتب؟')) return;
    const r = await apiFetch(`/salaries/${id}/pay`, { method: 'POST' });
    if (r.success) { showAlert('تم صرف الراتب'); loadSalaries(); }
    else showAlert(r.message, 'danger');
}

async function viewSalary(id) {
    const modal = new bootstrap.Modal(document.getElementById('salaryDetailModal'));
    document.getElementById('salaryDetailBody').innerHTML = '<div class="text-center py-4"><div class="spinner mx-auto"></div></div>';
    modal.show();
    const r = await apiFetch('/salaries/' + id);
    if (!r.success) return;
    const s = r.data;
    const components = s.components || [];
    const attendanceComponents = components.filter(c => c.component_type === 'attendance_deduction');
    const attendanceAmount = attendanceComponents.reduce((sum, c) => sum + Math.abs(Number(c.amount || 0)), 0);

    let attendanceDetailHtml = '';
    if (attendanceAmount > 0 && s.employee?.id) {
        const attR = await apiFetch(`/attendance/monthly-report/${s.employee.id}?month=${s.month}&year=${s.year}`);
        if (attR.success && attR.data?.length) {
            const attRecords = attR.data;
            const attStats = attR.statistics || {};
            attendanceDetailHtml = `
                <div class="mt-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="text-muted mb-0"><i class="fas fa-business-time me-1"></i> تفاصيل خصم الحضور</h6>
                        <span class="text-muted" style="font-size:.8rem">إجمالي الخصم: <strong class="text-danger">${attendanceAmount.toLocaleString()} ج.م</strong></span>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-4"><div class="p-1 bg-light rounded text-center"><small class="text-muted">غياب</small><div class="fw-bold">${attStats.absent || 0}</div></div></div>
                        <div class="col-4"><div class="p-1 bg-light rounded text-center"><small class="text-muted">تأخير (دقائق)</small><div class="fw-bold">${attStats.total_late_minutes || 0}</div></div></div>
                        <div class="col-4"><div class="p-1 bg-light rounded text-center"><small class="text-muted">انصراف مبكر (د)</small><div class="fw-bold">${attStats.total_early_exit_minutes || 0}</div></div></div>
                    </div>
                    <div style="max-height:150px;overflow-y:auto">
                        <table class="data-table" style="font-size:.78rem">
                            <thead><tr><th>اليوم</th><th>الحالة</th><th>تأخير</th><th>مبكر</th><th>ساعات</th><th>نوع الخصم</th></tr></thead>
                            <tbody>
                                ${attRecords.slice(0, 31).map(a => `
                                    <tr>
                                        <td>${a.attendance_date?.substring(0,10)}</td>
                                        <td><span class="badge-status ${attBadge[a.status] || 'badge-draft'}" style="font-size:.65rem">${attLabel[a.status] || a.status}</span></td>
                                        <td>${a.late_minutes ? a.late_minutes + ' د' : '-'}</td>
                                        <td>${a.early_exit_minutes ? a.early_exit_minutes + ' د' : '-'}</td>
                                        <td>${a.actual_worked_hours ? a.actual_worked_hours.toFixed(1) : (a.working_hours ?? '-')}</td>
                                        <td>${a.applied_late_deduction_type ? (deductionLabels[a.applied_late_deduction_type] || a.applied_late_deduction_type) : '-'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
    }

    const ptsCredit  = Number(s.total_points_credit ?? 0);
    const ptsDebit   = Number(s.total_points_debit ?? 0);
    const realIncentives = Number(s.total_incentives ?? 0) - ptsCredit;
    const realDeductions = Number(s.total_deductions ?? 0) - ptsDebit;

    document.getElementById('salaryDetailBody').innerHTML = `
    <h6 class="fw-bold text-primary">${s.employee?.name ?? '-'} - ${s.month}/${s.year}</h6>
    <hr>
    ${attendanceComponents.length ? `
        <div class="alert alert-warning py-2" style="font-size:.85rem">
            <i class="fas fa-business-time me-1"></i>
            يوجد خصم حضور تلقائي ضمن الراتب: ${attendanceComponents.map(c => `${c.component_name} (${Math.abs(Number(c.amount)).toLocaleString()} ج.م)`).join('، ')}
        </div>
    ` : ''}
    <div class="row g-2 mb-3">
        <div class="col-12"><h6 class="text-muted mb-2">المستحقات</h6></div>
        <div class="col-6 col-md-2"><div class="p-2 bg-light rounded text-center"><small class="text-muted">أساسي</small><div class="fw-bold">${Number(s.base_salary).toLocaleString()} ج.م</div></div></div>
        <div class="col-6 col-md-2"><div class="p-2 bg-light rounded text-center"><small class="text-muted">حوافز</small><div class="fw-bold text-success">+${realIncentives.toLocaleString()} ج.م</div></div></div>
        <div class="col-6 col-md-2"><div class="p-2 bg-light rounded text-center"><small class="text-muted">بدلات</small><div class="fw-bold text-success">+${Number(s.total_allowances ?? 0).toLocaleString()} ج.م</div></div></div>
        <div class="col-6 col-md-2"><div class="p-2 bg-light rounded text-center"><small class="text-muted">عمولات</small><div class="fw-bold text-success">+${Number(s.total_commissions ?? 0).toLocaleString()} ج.م</div></div></div>
        <div class="col-6 col-md-2"><div class="p-2 bg-light rounded text-center"><small class="text-muted">نقاط (له)</small><div class="fw-bold text-success">${ptsCredit > 0 ? '+'+ptsCredit.toLocaleString() : '0'} ج.م</div></div></div>
        <div class="col-6 col-md-2"><div class="p-2 bg-light rounded text-center"><small class="text-muted">نقاط (عليه)</small><div class="fw-bold text-danger">${ptsDebit > 0 ? '-'+ptsDebit.toLocaleString() : '0'} ج.م</div></div></div>
        <div class="col-12 mt-2"><h6 class="text-muted mb-2">الخصومات</h6></div>
        <div class="col-6 col-md-3"><div class="p-2 bg-light rounded text-center"><small class="text-muted">خصومات</small><div class="fw-bold text-danger">-${realDeductions.toLocaleString()} ج.م</div></div></div>
        <div class="col-6 col-md-3"><div class="p-2 bg-light rounded text-center"><small class="text-muted">سلف</small><div class="fw-bold text-danger">-${Number(s.total_advances ?? 0).toLocaleString()} ج.م</div></div></div>
        <div class="col-6 col-md-3"><div class="p-2 bg-light rounded text-center"><small class="text-muted">مخالفات</small><div class="fw-bold text-danger">-${Number(s.total_violations ?? 0).toLocaleString()} ج.م</div></div></div>
        <div class="col-6 col-md-3"><div class="p-2 bg-light rounded text-center"><small class="text-muted">خصم حضور</small><div class="fw-bold text-danger">-${attendanceAmount.toLocaleString()} ج.م</div></div></div>
    </div>
    ${attendanceDetailHtml}
    ${components.length ? `
        <h6 class="text-muted mb-2">تفاصيل المكونات</h6>
        <div class="table-responsive mb-3">
            <table class="data-table">
                <thead><tr><th>النوع</th><th>الوصف</th><th>المبلغ</th></tr></thead>
                <tbody>
                    ${components.map(c => `
                        <tr>
                            <td>${componentLabel(c.component_type)}</td>
                            <td>${c.component_name ?? '-'}</td>
                            <td class="${Number(c.amount) < 0 ? 'text-danger' : 'text-success'} fw-bold">${Number(c.amount).toLocaleString()} ج.م</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    ` : ''}
    <div class="d-flex justify-content-between p-3 bg-primary text-white rounded">
        <span class="fw-bold fs-5">صافي الراتب</span>
        <span class="fw-bold fs-4">${Number(s.net_salary).toLocaleString()} ج.م</span>
    </div>`;
}

function componentLabel(type) {
    return {
        incentive: 'حافز',
        allowance: 'بدل',
        commission: 'عمولة',
        deduction: 'خصم',
        attendance_deduction: 'خصم حضور',
        advance: 'سلفة',
        violation: 'مخالفة',
        points_credit: 'نقاط (له)',
        points_debit: 'نقاط (عليه)'
    }[type] || type;
}

function exportSalaries() { window.location = `/reports/salaries?month=${document.getElementById('salMonth').value}&year=${document.getElementById('salYear').value}`; }

async function printSalariesPDF() {
    const params = new URLSearchParams({ per_page: 1000 });
    params.append('month', document.getElementById('salMonth').value);
    params.append('year',  document.getElementById('salYear').value);
    const s = document.getElementById('salStatus').value;
    const e = document.getElementById('salEmpSearch').value;
    if (s) params.append('status', s);
    if (e) params.append('search', e);

    let res;
    try { res = await apiFetch('/salaries?'+params); } catch (err) { showAlert('حدث خطأ أثناء تحميل البيانات للطباعة','danger'); return; }
    if (!res.success) { showAlert(res.message||'فشل تحميل البيانات','danger'); return; }

    const data = res.data?.data || [];
    const month = document.getElementById('salMonth').value;
    const year  = document.getElementById('salYear').value;

    let totalGross = 0, totalNet = 0;
    const rows = data.map((s, i) => {
        const incentives = Number(s.total_incentives ?? 0);
        const deductions = Number(s.total_deductions ?? 0);
        const ptsCredit  = Number(s.total_points_credit ?? 0);
        const ptsDebit   = Number(s.total_points_debit ?? 0);
        const realIncentives = incentives - ptsCredit;
        const realDeductions = deductions - ptsDebit;
        totalGross += Number(s.base_salary ?? 0) + realIncentives + Number(s.total_allowances ?? 0) + Number(s.total_commissions ?? 0) + ptsCredit;
        totalNet   += Number(s.net_salary ?? 0);
        return `
        <tr>
            <td>${i+1}</td>
            <td><b>${escHtml(s.employee?.name || '—')}</b><br><span style="color:#6b7280;font-size:11px">${escHtml(s.employee?.employee_code || '')}</span></td>
            <td>${Number(s.base_salary).toLocaleString()}</td>
            <td class="pos">+${realIncentives.toLocaleString()}</td>
            <td class="pos">+${Number(s.total_allowances ?? 0).toLocaleString()}</td>
            <td class="pos">+${Number(s.total_commissions ?? 0).toLocaleString()}</td>
            <td class="pos">${ptsCredit > 0 ? '+'+ptsCredit.toLocaleString() : '-'}</td>
            <td class="neg">${ptsDebit > 0 ? '-'+ptsDebit.toLocaleString() : '-'}</td>
            <td class="neg">-${realDeductions.toLocaleString()}</td>
            <td class="neg">-${Number(s.total_advances ?? 0).toLocaleString()}</td>
            <td><b>${Number(s.net_salary).toLocaleString()}</b></td>
            <td>${salLabel[s.status] || s.status || '-'}</td>
        </tr>`;
    }).join('');

    const metaParts = [
        `الشهر: ${month}`,
        `السنة: ${year}`,
        s ? `الحالة: ${salLabel[s] || s}` : null,
        e ? `الموظف: ${e}` : null,
    ].filter(Boolean).join(' | ');

    const body = `
        <div class="ph"><h1>كشف الرواتب</h1><div class="meta">${escHtml(metaParts)}</div></div>
        <div class="sum">
            <div class="box"><div class="lbl">عدد الرواتب</div><div class="val">${data.length}</div></div>
            <div class="box"><div class="lbl">إجمالي الرواتب</div><div class="val">${totalGross.toLocaleString()} ج.م</div></div>
            <div class="box"><div class="lbl">صافي الرواتب</div><div class="val">${totalNet.toLocaleString()} ج.م</div></div>
        </div>
        <table>
            <thead><tr>
                <th>#</th><th>الموظف</th><th>الأساسي</th><th>الحوافز</th><th>البدلات</th><th>العمولات</th><th>نقاط (له)</th><th>نقاط (عليه)</th><th>خصومات</th><th>سلف</th><th>الصافي</th><th>الحالة</th>
            </tr></thead>
            <tbody>${rows || '<tr><td colspan="12" style="text-align:center;color:#6b7280">لا توجد بيانات مطابقة</td></tr>'}</tbody>
        </table>`;

    printHTML('كشف الرواتب', body);
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', () => { loadSummary(); loadSalaries(); });
</script>
@endpush
