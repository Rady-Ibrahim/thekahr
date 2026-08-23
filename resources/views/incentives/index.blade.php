@extends('layouts.app')
@section('title', 'الحوافز')
@section('page-title', 'الحوافز')

@section('content')
<div class="page-header">
    <div><h1><i class="fas fa-star me-2 text-primary"></i> الحوافز</h1><div class="breadcrumb">إدارة حوافز الموظفين</div></div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary text-nowrap" onclick="printIncentivesPDF()"><i class="fas fa-file-pdf me-1"></i> طباعة PDF</button>
        <button class="btn-primary-custom" onclick="openAddModal()"><i class="fas fa-plus me-1"></i> إضافة حافز</button>
    </div>
</div>

<!-- FILTERS -->
<div class="section-card mb-4">
    <div class="section-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">الموظف</label><input type="text" id="incEmp" class="form-control" placeholder="اسم الموظف..."></div>
            <div class="col-md-2"><label class="form-label">الشهر</label><input type="number" id="incMonth" class="form-control" min="1" max="12" value="{{ date('n') }}"></div>
            <div class="col-md-2"><label class="form-label">السنة</label><input type="number" id="incYear" class="form-control" value="{{ date('Y') }}"></div>
            <div class="col-md-2"><label class="form-label">الحالة</label>
                <select id="incStatus" class="form-select"><option value="">الكل</option><option value="pending">معلق</option><option value="approved">معتمد</option><option value="rejected">مرفوض</option></select>
            </div>
            <div class="col-md-2"><button class="btn-primary-custom w-100 mt-4" onclick="loadIncentives()"><i class="fas fa-search me-1"></i> بحث</button></div>
            <div class="col-md-1"><button class="btn btn-outline-secondary w-100 mt-4" onclick="resetIncFilter()"><i class="fas fa-undo"></i></button></div>
        </div>
    </div>
</div>

<!-- TABLE -->
<div class="section-card">
    <div class="section-header"><i class="fas fa-star text-primary"></i><h5 class="section-title">قائمة الحوافز</h5>
        <span class="ms-auto text-muted" style="font-size:.8rem" id="incCount"></span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>الموظف</th><th>النوع</th><th>المبلغ</th><th>الشهر/السنة</th><th>السبب</th><th>الحالة</th><th>إجراءات</th></tr></thead>
            <tbody id="incentivesTable"><tr><td colspan="7" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr></tbody>
        </table>
    </div>
    <div class="section-body d-flex justify-content-between">
        <div id="incPagInfo" class="text-muted" style="font-size:.8rem"></div>
        <div id="incPagination"></div>
    </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal fade" id="incModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="incModalTitle"><i class="fas fa-star me-2"></i> إضافة حافز</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="incForm">
                    <input type="hidden" id="incId">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">الموظف *</label>
                            <div class="position-relative">
                                <input type="text" name="employee_id" id="if2_emp" class="form-control" placeholder="ابحث بالاسم أو الكود..." required>
                            </div>
                        </div>
                        <div class="col-md-6"><label class="form-label">نوع الحافز *</label>
                            <select name="incentive_type" id="if2_type" class="form-select" required>
                                <option value="performance">أداء</option><option value="attendance">حضور</option>
                                <option value="sales">مبيعات</option><option value="overtime">إضافي</option><option value="other">أخرى</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">المبلغ *</label><div class="input-group"><input type="number" name="amount" id="if2_amount" class="form-control" required><span class="input-group-text">ج.م</span></div></div>
                        <div class="col-md-6"><label class="form-label">الشهر *</label><input type="number" name="month" id="if2_month" class="form-control" min="1" max="12" required value="{{ date('n') }}"></div>
                        <div class="col-md-6"><label class="form-label">السنة *</label><input type="number" name="year" id="if2_year" class="form-control" required value="{{ date('Y') }}"></div>
                        <div class="col-12"><label class="form-label">السبب</label><textarea name="reason" id="if2_reason" class="form-control" rows="2"></textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom" onclick="saveIncentive()"><i class="fas fa-save me-1"></i> حفظ</button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="incDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>تأكيد الحذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center"><i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i><p>حذف هذا الحافز نهائياً؟</p></div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="incDeleteBtn">حذف</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let incDeleteId=null, incPage=1;
let incEmpSearch=null, incEmployees=[];
const incTypes = { performance:'أداء', attendance:'حضور', sales:'مبيعات', overtime:'إضافي', other:'أخرى' };

async function loadIncentives(page=1) {
    incPage=page;
    const params = new URLSearchParams({ per_page:15, page });
    const s = document.getElementById('incStatus').value;
    const m = document.getElementById('incMonth').value;
    const y = document.getElementById('incYear').value;
    const e = document.getElementById('incEmp').value;
    if (s) params.append('status',s);
    if (m) params.append('month',m);
    if (y) params.append('year',y);
    if (e) params.append('search',e);
    const r = await apiFetch('/incentives?'+params);
    if (!r.success) return;
    const { data, total, current_page, last_page } = r.data;
    document.getElementById('incCount').textContent=`إجمالي: ${total}`;
    document.getElementById('incPagInfo').textContent=`صفحة ${current_page} من ${last_page}`;
    if (!data.length) { document.getElementById('incentivesTable').innerHTML='<tr><td colspan="7" class="text-center py-4 text-muted">لا توجد حوافز</td></tr>'; return; }
    document.getElementById('incentivesTable').innerHTML = data.map(i=>`
        <tr>
            <td>${i.employee?.name??'-'}</td>
            <td>${incTypes[i.incentive_type]||i.incentive_type}</td>
            <td class="fw-bold text-success">${Number(i.amount).toLocaleString()} ج.م</td>
            <td>${i.month}/${i.year}</td>
            <td>${i.reason??'-'}</td>
            <td><span class="badge-status ${i.status==='approved'?'badge-active':i.status==='rejected'?'badge-rejected':'badge-pending'}">${i.status==='approved'?'معتمد':i.status==='rejected'?'مرفوض':'معلق'}</span></td>
            <td>
                <div class="d-flex gap-1 flex-wrap">
                    ${i.status==='pending'?`<button class="btn btn-sm btn-outline-warning" onclick="openEditModal(${i.id})" title="تعديل"><i class="fas fa-edit"></i></button>`:'' }
                    ${i.status==='pending'?`
                        <button class="btn btn-sm btn-success" onclick="approveInc(${i.id})"><i class="fas fa-check"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="rejectInc(${i.id})"><i class="fas fa-times"></i></button>
                    `:''}
                    <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(${i.id})"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`).join('');
    const pages=[];
    for(let i=1;i<=last_page;i++) pages.push(`<button class="btn btn-sm ${i===current_page?'btn-primary':'btn-outline-primary'} mx-1" onclick="loadIncentives(${i})">${i}</button>`);
    document.getElementById('incPagination').innerHTML=pages.join('');
}

function openAddModal() {
    document.getElementById('incId').value='';
    document.getElementById('incForm').reset();
    document.getElementById('incModalTitle').innerHTML='<i class="fas fa-star me-2"></i> إضافة حافز جديد';
    document.getElementById('if2_month').value='{{ date("n") }}';
    document.getElementById('if2_year').value='{{ date("Y") }}';
    incEmpSearch.reset();
    new bootstrap.Modal(document.getElementById('incModal')).show();
}

function setIncEmpValue(empId) {
    const emp = incEmployees.find(e => e.id === parseInt(empId));
    incEmpSearch.setValue(empId, emp ? `${emp.name}${emp.employee_code ? ' - ' + emp.employee_code : ''}` : String(empId));
}

async function openEditModal(id) {
    document.getElementById('incModalTitle').innerHTML='<i class="fas fa-edit me-2"></i> تعديل الحافز';
    new bootstrap.Modal(document.getElementById('incModal')).show();
    const r = await apiFetch('/incentives/'+id);
    if (!r.success) return;
    const i = r.data;
    document.getElementById('incId').value       = i.id;
    setIncEmpValue(i.employee_id);
    document.getElementById('if2_type').value    = i.incentive_type;
    document.getElementById('if2_amount').value  = i.amount;
    document.getElementById('if2_month').value   = i.month;
    document.getElementById('if2_year').value    = i.year;
    document.getElementById('if2_reason').value  = i.reason??'';
}

async function saveIncentive() {
    const id   = document.getElementById('incId').value;
    const data = Object.fromEntries(new FormData(document.getElementById('incForm')));
    data.amount=parseFloat(data.amount); data.month=parseInt(data.month); data.year=parseInt(data.year); data.employee_id=parseInt(data.employee_id);
    const r = await apiFetch(id?`/incentives/${id}`:'/incentives', { method:id?'PUT':'POST', body:JSON.stringify(data) });
    if (r.success) { bootstrap.Modal.getInstance(document.getElementById('incModal')).hide(); showAlert(id?'تم التحديث':'تم الإضافة'); loadIncentives(incPage); }
    else showAlert(r.message||'فشل الحفظ','danger');
}

async function approveInc(id) {
    const r = await apiFetch(`/incentives/${id}/approve`, { method:'POST' });
    if (r.success) { showAlert('تم الاعتماد'); loadIncentives(incPage); } else showAlert(r.message,'danger');
}
async function rejectInc(id) {
    const reason=prompt('سبب الرفض:'); if(!reason) return;
    const r = await apiFetch(`/incentives/${id}/reject`, { method:'POST', body:JSON.stringify({reason}) });
    if (r.success) { showAlert('تم الرفض','warning'); loadIncentives(incPage); } else showAlert(r.message,'danger');
}

function confirmDelete(id) { incDeleteId=id; new bootstrap.Modal(document.getElementById('incDeleteModal')).show(); }
document.getElementById('incDeleteBtn').addEventListener('click', async()=>{
    if (!incDeleteId) return;
    const r = await apiFetch(`/incentives/${incDeleteId}`, { method:'DELETE' });
    bootstrap.Modal.getInstance(document.getElementById('incDeleteModal')).hide();
    if (r.success) { showAlert('تم الحذف'); loadIncentives(incPage); } else showAlert(r.message,'danger');
    incDeleteId=null;
});

function resetIncFilter() { ['incEmp','incStatus'].forEach(id=>document.getElementById(id).value=''); loadIncentives(); }

async function printIncentivesPDF() {
    const params = new URLSearchParams({ per_page: 1000 });
    const s = document.getElementById('incStatus').value; if (s) params.append('status',s);
    const m = document.getElementById('incMonth').value;   if (m) params.append('month',m);
    const y = document.getElementById('incYear').value;    if (y) params.append('year',y);
    const e = document.getElementById('incEmp').value;     if (e) params.append('search',e);

    let res;
    try { res = await apiFetch('/incentives?'+params); } catch (e) { showAlert('حدث خطأ أثناء تحميل البيانات للطباعة','danger'); return; }
    if (!res.success) { showAlert(res.message||'فشل تحميل البيانات','danger'); return; }

    const data = res.data?.data || [];
    const empName = e ? (data[0]?.employee?.name || '') : '';
    const totalAmount = data.reduce((sum, i) => sum + Number(i.amount || 0), 0);

    const rows = data.map((i, idx) => `
        <tr>
            <td>${idx+1}</td>
            <td><b>${escHtml(i.employee?.name || '—')}</b><br><span style="color:#6b7280;font-size:11px">${escHtml(i.employee?.employee_code || '')}</span></td>
            <td>${incTypes[i.incentive_type] || i.incentive_type || '-'}</td>
            <td class="pos">${Number(i.amount).toLocaleString()}</td>
            <td>${i.month}/${i.year}</td>
            <td>${escHtml(i.reason || '-')}</td>
            <td>${i.status==='approved'?'معتمد':i.status==='rejected'?'مرفوض':'معلق'}</td>
        </tr>`).join('');

    const metaParts = [
        empName ? `الموظف: ${empName}` : 'كل الموظفين',
        s ? `الحالة: ${s==='approved'?'معتمد':s==='rejected'?'مرفوض':'معلق'}` : null,
        m ? `الشهر: ${m}` : null,
        y ? `السنة: ${y}` : null,
    ].filter(Boolean).join(' | ');

    const body = `
        <div class="ph"><h1>تقرير الحوافز</h1><div class="meta">${escHtml(metaParts)}</div></div>
        <div class="sum">
            <div class="box"><div class="lbl">إجمالي السجلات</div><div class="val">${data.length}</div></div>
            <div class="box"><div class="lbl">إجمالي قيمة الحوافز</div><div class="val pos">${totalAmount.toLocaleString()} ج.م</div></div>
        </div>
        <table>
            <thead><tr><th>#</th><th>الموظف</th><th>النوع</th><th>المبلغ</th><th>الشهر/السنة</th><th>السبب</th><th>الحالة</th></tr></thead>
            <tbody>${rows || '<tr><td colspan="7" style="text-align:center;color:#6b7280">لا توجد بيانات مطابقة</td></tr>'}</tbody>
        </table>`;

    printHTML('تقرير الحوافز', body);
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

async function initIncEmpSearch() {
    incEmpSearch = createSearchableSelect(document.getElementById('if2_emp'), 'employees');
    incEmployees = await getLookupRows('employees');
    incEmpSearch.setItems(incEmployees);
}

document.addEventListener('DOMContentLoaded', () => { initIncEmpSearch(); loadIncentives(); });
</script>
@endpush
