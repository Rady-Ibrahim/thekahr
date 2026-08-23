@extends('layouts.app')
@section('title', 'السلف')
@section('page-title', 'السلف')

@section('content')
<div class="page-header">
    <div><h1><i class="fas fa-hand-holding-usd me-2 text-primary"></i> السلف</h1><div class="breadcrumb">إدارة سلف الموظفين</div></div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary text-nowrap" onclick="printAdvancesPDF()"><i class="fas fa-file-pdf me-1"></i> طباعة PDF</button>
        <button class="btn-primary-custom" onclick="openAddModal()"><i class="fas fa-plus me-1"></i> إضافة سلفة</button>
    </div>
</div>

<!-- FILTERS -->
<div class="section-card mb-4">
    <div class="section-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label">الموظف</label><input type="text" id="advEmp" class="form-control" placeholder="اسم الموظف..."></div>
            <div class="col-md-3"><label class="form-label">الحالة</label>
                <select id="advStatus" class="form-select"><option value="">الكل</option><option value="pending">معلق</option><option value="active">معتمد</option><option value="paid">مسدد</option><option value="partially_paid">مسدد جزئياً</option></select>
            </div>
            <div class="col-md-2"><button class="btn-primary-custom w-100 mt-4" onclick="loadAdvances()"><i class="fas fa-search me-1"></i> بحث</button></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100 mt-4" onclick="resetAdvFilter()"><i class="fas fa-undo me-1"></i> إعادة</button></div>
        </div>
    </div>
</div>

<!-- TABLE -->
<div class="section-card">
    <div class="section-header"><i class="fas fa-hand-holding-usd text-primary"></i><h5 class="section-title">قائمة السلف</h5>
        <span class="ms-auto text-muted" style="font-size:.8rem" id="advCount"></span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>الموظف</th><th>المبلغ</th><th>المتبقي</th><th>الأقساط</th><th>تاريخ الطلب</th><th>السبب</th><th>الحالة</th><th>إجراءات</th></tr></thead>
            <tbody id="advancesTable"><tr><td colspan="8" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr></tbody>
        </table>
    </div>
    <div class="section-body d-flex justify-content-between">
        <div id="advPagInfo" class="text-muted" style="font-size:.8rem"></div>
        <div id="advPagination"></div>
    </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal fade" id="advModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="advModalTitle"><i class="fas fa-hand-holding-usd me-2"></i> إضافة سلفة</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="advForm">
                    <input type="hidden" id="advId">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">الموظف *</label>
                            <div class="position-relative">
                                <input type="text" name="employee_id" id="af_emp" class="form-control" placeholder="ابحث بالاسم أو الكود..." required>
                            </div>
                        </div>
                        <div class="col-md-6"><label class="form-label">المبلغ *</label><div class="input-group"><input type="number" name="amount" id="af_amount" class="form-control" required><span class="input-group-text">ج.م</span></div></div>
                        <div class="col-md-6"><label class="form-label">عدد الأقساط (شهر)</label><input type="number" name="installments" id="af_installments" class="form-control" min="1" value="1"></div>
                        <div class="col-md-6"><label class="form-label">تاريخ الطلب</label><input type="date" name="request_date" id="af_request_date" class="form-control" value="{{ date('Y-m-d') }}"></div>
                        <div class="col-md-6"><label class="form-label">شهر البداية</label><input type="month" name="start_month" id="af_start_month" class="form-control"></div>
                        <div class="col-12"><label class="form-label">السبب</label><textarea name="reason" id="af_reason" class="form-control" rows="2"></textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom" onclick="saveAdvance()"><i class="fas fa-save me-1"></i> حفظ</button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="advDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm"><div class="modal-content">
        <div class="modal-header border-0"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>تأكيد الحذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body text-center"><i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i><p>حذف هذه السلفة نهائياً؟</p></div>
        <div class="modal-footer border-0 justify-content-center">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
            <button type="button" class="btn btn-danger" id="advDeleteBtn">حذف</button>
        </div>
    </div></div>
</div>
@endsection

@push('scripts')
<script>
let advDeleteId=null, advPage=1;
let advEmpSearch=null, advEmployees=[];
const advStatuses = { pending:'معلق', active:'معتمد', paid:'مسدد', partially_paid:'مسدد جزئياً' };
const advBadges   = { pending:'badge-pending', active:'badge-active', paid:'badge-approved', partially_paid:'badge-approved' };

async function loadAdvances(page=1) {
    advPage=page;
    const params = new URLSearchParams({ per_page:15, page });
    const s=document.getElementById('advStatus').value; if(s) params.append('status',s);
    const e=document.getElementById('advEmp').value; if(e) params.append('search',e);
    const r = await apiFetch('/advances?'+params);
    if (!r.success) return;
    const { data, total, current_page, last_page } = r.data;
    document.getElementById('advCount').textContent=`إجمالي: ${total}`;
    document.getElementById('advPagInfo').textContent=`صفحة ${current_page} من ${last_page}`;
    if (!data.length) { document.getElementById('advancesTable').innerHTML='<tr><td colspan="8" class="text-center py-4 text-muted">لا توجد سلف</td></tr>'; return; }
    document.getElementById('advancesTable').innerHTML = data.map(a=>`
        <tr>
            <td>${a.employee?.name??'-'}</td>
            <td class="fw-bold">${Number(a.amount).toLocaleString()} ج.م</td>
            <td class="text-warning">${Number(a.remaining_amount??a.amount).toLocaleString()} ج.م</td>
            <td>${a.installments_count??1} شهر</td>
            <td>${a.advance_date?new Date(a.advance_date).toLocaleDateString('ar-EG'):'-'}</td>
            <td>${a.reason??'-'}</td>
            <td><span class="badge-status ${advBadges[a.status]||'badge-pending'}">${advStatuses[a.status]||a.status}</span></td>
            <td>
                <div class="d-flex gap-1 flex-wrap">
                    ${a.status==='pending'?`<button class="btn btn-sm btn-outline-warning" onclick="openEditModal(${a.id})"><i class="fas fa-edit"></i></button>`:''}
                    ${a.status==='pending'?`
                        <button class="btn btn-sm btn-success" onclick="approveAdv(${a.id})"><i class="fas fa-check"></i></button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="rejectAdv(${a.id})"><i class="fas fa-times"></i></button>
                    `:''}
                    <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(${a.id})"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`).join('');
    const pages=[];
    for(let i=1;i<=last_page;i++) pages.push(`<button class="btn btn-sm ${i===current_page?'btn-primary':'btn-outline-primary'} mx-1" onclick="loadAdvances(${i})">${i}</button>`);
    document.getElementById('advPagination').innerHTML=pages.join('');
}

function openAddModal() {
    document.getElementById('advId').value=''; document.getElementById('advForm').reset();
    document.getElementById('advModalTitle').innerHTML='<i class="fas fa-hand-holding-usd me-2"></i> إضافة سلفة جديدة';
    document.getElementById('af_request_date').value='{{ date("Y-m-d") }}';
    document.getElementById('af_installments').value='1';
    advEmpSearch.reset();
    new bootstrap.Modal(document.getElementById('advModal')).show();
}
function setAdvEmpValue(empId) {
    const emp = advEmployees.find(e => e.id === parseInt(empId));
    advEmpSearch.setValue(empId, emp ? `${emp.name}${emp.employee_code ? ' - ' + emp.employee_code : ''}` : String(empId));
}
async function openEditModal(id) {
    document.getElementById('advModalTitle').innerHTML='<i class="fas fa-edit me-2"></i> تعديل السلفة';
    new bootstrap.Modal(document.getElementById('advModal')).show();
    const r=await apiFetch('/advances/'+id); if(!r.success) return; const a=r.data;
    document.getElementById('advId').value=a.id;
    setAdvEmpValue(a.employee_id);
    document.getElementById('af_amount').value=a.amount;
    document.getElementById('af_installments').value=a.installments_count??1;
    document.getElementById('af_request_date').value=a.advance_date?a.advance_date.substring(0,10):'';
    document.getElementById('af_reason').value=a.reason??'';
}
async function saveAdvance() {
    const id=document.getElementById('advId').value;
    const data=Object.fromEntries(new FormData(document.getElementById('advForm')));
    data.amount=parseFloat(data.amount);
    data.installments_count=parseInt(data.installments||1);
    data.advance_date=data.request_date;
    data.employee_id=parseInt(data.employee_id);
    delete data.installments;
    delete data.request_date;
    if (!data.start_month) delete data.start_month;
    const r=await apiFetch(id?`/advances/${id}`:'/advances',{method:id?'PUT':'POST',body:JSON.stringify(data)});
    if(r.success){bootstrap.Modal.getInstance(document.getElementById('advModal')).hide();showAlert(id?'تم التحديث':'تم الإضافة');loadAdvances(advPage);}
    else showAlert(r.message||'فشل الحفظ','danger');
}
async function approveAdv(id) {
    const r=await apiFetch(`/advances/${id}/approve`,{method:'POST'});
    if(r.success){showAlert('تم الاعتماد');loadAdvances(advPage);}else showAlert(r.message,'danger');
}
async function rejectAdv(id) {
    const reason=prompt('سبب الرفض:'); if(!reason) return;
    const r=await apiFetch(`/advances/${id}/reject`,{method:'POST',body:JSON.stringify({reason})});
    if(r.success){showAlert('تم الرفض','warning');loadAdvances(advPage);}else showAlert(r.message,'danger');
}
function confirmDelete(id) { advDeleteId=id; new bootstrap.Modal(document.getElementById('advDeleteModal')).show(); }
document.getElementById('advDeleteBtn').addEventListener('click', async()=>{
    if(!advDeleteId) return;
    const r=await apiFetch(`/advances/${advDeleteId}`,{method:'DELETE'});
    bootstrap.Modal.getInstance(document.getElementById('advDeleteModal')).hide();
    if(r.success){showAlert('تم الحذف');loadAdvances(advPage);}else showAlert(r.message,'danger');
    advDeleteId=null;
});
function resetAdvFilter() { document.getElementById('advStatus').value=''; document.getElementById('advEmp').value=''; loadAdvances(); }

async function printAdvancesPDF() {
    const params = new URLSearchParams({ per_page: 1000 });
    const s=document.getElementById('advStatus').value; if(s) params.append('status',s);
    const e=document.getElementById('advEmp').value; if(e) params.append('search',e);

    let res;
    try { res = await apiFetch('/advances?'+params); } catch (e) { showAlert('حدث خطأ أثناء تحميل البيانات للطباعة','danger'); return; }
    if (!res.success) { showAlert(res.message||'فشل تحميل البيانات','danger'); return; }

    const data = res.data?.data || [];
    const empName = e ? (data[0]?.employee?.name || '') : '';
    const totalAmount = data.reduce((sum, a) => sum + Number(a.amount || 0), 0);
    const totalRemaining = data.reduce((sum, a) => sum + Number(a.remaining_amount ?? a.amount ?? 0), 0);

    const rows = data.map((a, i) => `
        <tr>
            <td>${i+1}</td>
            <td><b>${escHtml(a.employee?.name || '—')}</b><br><span style="color:#6b7280;font-size:11px">${escHtml(a.employee?.employee_code || '')}</span></td>
            <td>${Number(a.amount).toLocaleString()}</td>
            <td>${Number(a.remaining_amount ?? a.amount ?? 0).toLocaleString()}</td>
            <td>${a.installments_count ?? 1} شهر</td>
            <td>${a.advance_date ? new Date(a.advance_date).toLocaleDateString('ar-EG') : '-'}</td>
            <td>${escHtml(a.reason || '-')}</td>
            <td>${advStatuses[a.status] || a.status || '-'}</td>
        </tr>`).join('');

    const metaParts = [
        empName ? `الموظف: ${empName}` : 'كل الموظفين',
        s ? `الحالة: ${advStatuses[s] || s}` : null,
    ].filter(Boolean).join(' | ');

    const body = `
        <div class="ph"><h1>تقرير السلف</h1><div class="meta">${escHtml(metaParts)}</div></div>
        <div class="sum">
            <div class="box"><div class="lbl">إجمالي السجلات</div><div class="val">${data.length}</div></div>
            <div class="box"><div class="lbl">إجمالي قيمة السلف</div><div class="val">${totalAmount.toLocaleString()} ج.م</div></div>
            <div class="box"><div class="lbl">إجمالي المتبقي</div><div class="val neg">${totalRemaining.toLocaleString()} ج.م</div></div>
        </div>
        <table>
            <thead><tr><th>#</th><th>الموظف</th><th>المبلغ</th><th>المتبقي</th><th>الأقساط</th><th>تاريخ الطلب</th><th>السبب</th><th>الحالة</th></tr></thead>
            <tbody>${rows || '<tr><td colspan="8" style="text-align:center;color:#6b7280">لا توجد بيانات مطابقة</td></tr>'}</tbody>
        </table>`;

    printHTML('تقرير السلف', body);
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

async function initAdvEmpSearch() {
    advEmpSearch = createSearchableSelect(document.getElementById('af_emp'), 'employees');
    advEmployees = await getLookupRows('employees');
    advEmpSearch.setItems(advEmployees);
}

document.addEventListener('DOMContentLoaded', () => { initAdvEmpSearch(); loadAdvances(); });
</script>
@endpush
