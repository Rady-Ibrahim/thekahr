@extends('layouts.app')
@section('title', 'العمولات')
@section('page-title', 'عمولات المبيعات')

@section('content')
<div class="page-header">
    <div><h1><i class="fas fa-percentage me-2 text-primary"></i> عمولات المبيعات</h1><div class="breadcrumb">إدارة عمولات الموظفين</div></div>
    <button class="btn-primary-custom" onclick="openAddModal()"><i class="fas fa-plus me-1"></i> إضافة عمولة</button>
</div>

<!-- FILTERS -->
<div class="section-card mb-4">
    <div class="section-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label">الموظف</label><input type="text" id="comEmp" class="form-control" placeholder="اسم الموظف..."></div>
            <div class="col-md-2"><label class="form-label">الشهر</label><input type="number" id="comMonth" class="form-control" min="1" max="12" value="{{ date('n') }}"></div>
            <div class="col-md-2"><label class="form-label">السنة</label><input type="number" id="comYear" class="form-control" value="{{ date('Y') }}"></div>
            <div class="col-md-2"><button class="btn-primary-custom w-100 mt-4" onclick="loadCommissions()"><i class="fas fa-search me-1"></i> بحث</button></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100 mt-4" onclick="resetComFilter()"><i class="fas fa-undo me-1"></i> إعادة</button></div>
        </div>
    </div>
</div>

<!-- TABLE -->
<div class="section-card">
    <div class="section-header"><i class="fas fa-percentage text-primary"></i><h5 class="section-title">قائمة العمولات</h5>
        <span class="ms-auto text-muted" style="font-size:.8rem" id="comCount"></span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>الموظف</th><th>إجمالي المبيعات</th><th>نسبة العمولة</th><th>مبلغ العمولة</th><th>الشهر/السنة</th><th>السبب</th><th>الحالة</th><th>إجراءات</th></tr></thead>
            <tbody id="commissionsTable">                <tr><td colspan="8" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr></tbody>
        </table>
    </div>
    <div class="section-body d-flex justify-content-between">
        <div id="comPagInfo" class="text-muted" style="font-size:.8rem"></div>
        <div id="comPagination"></div>
    </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal fade" id="comModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="comModalTitle"><i class="fas fa-percentage me-2"></i> إضافة عمولة</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="comForm">
                    <input type="hidden" id="comId">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">الموظف *</label><select name="employee_id" id="cf2_emp" class="form-select" data-lookup="employees" data-placeholder="اختر الموظف" required></select></div>
                        <div class="col-md-6"><label class="form-label">إجمالي المبيعات *</label><div class="input-group"><input type="number" name="total_sales" id="cf2_sales" class="form-control" required><span class="input-group-text">ج.م</span></div></div>
                        <div class="col-md-6"><label class="form-label">نسبة العمولة % *</label><div class="input-group"><input type="number" name="commission_rate" id="cf2_rate" class="form-control" required step="0.01" min="0" max="100"><span class="input-group-text">%</span></div></div>
                        <div class="col-md-6"><label class="form-label">مبلغ العمولة</label><div class="input-group"><input type="number" name="commission_amount" id="cf2_amount" class="form-control"><span class="input-group-text">ج.م</span></div></div>
                        <div class="col-md-6"><label class="form-label">طريقة الاحتساب</label>
                            <select name="calculation_method" id="cf2_method" class="form-select">
                                <option value="percentage">نسبة مئوية</option><option value="fixed">مبلغ ثابت</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">الشهر *</label><input type="number" name="month" id="cf2_month" class="form-control" min="1" max="12" required value="{{ date('n') }}"></div>
                        <div class="col-md-6"><label class="form-label">السنة *</label><input type="number" name="year" id="cf2_year" class="form-control" required value="{{ date('Y') }}"></div>
                        <div class="col-12"><label class="form-label">السبب</label><textarea name="reason" id="cf2_reason" class="form-control" rows="2"></textarea></div>
                        <div class="col-12"><label class="form-label">ملاحظات</label><textarea name="notes" id="cf2_notes" class="form-control" rows="2"></textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom" onclick="saveCommission()"><i class="fas fa-save me-1"></i> حفظ</button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="comDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm"><div class="modal-content">
        <div class="modal-header border-0"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>تأكيد الحذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body text-center"><i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i><p>حذف هذه العمولة نهائياً؟</p></div>
        <div class="modal-footer border-0 justify-content-center">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
            <button type="button" class="btn btn-danger" id="comDeleteBtn">حذف</button>
        </div>
    </div></div>
</div>
@endsection

@push('scripts')
<script>
let comDeleteId=null, comPage=1;

async function loadCommissions(page=1) {
    comPage=page;
    const params = new URLSearchParams({ per_page:15, page });
    const m=document.getElementById('comMonth').value; if(m) params.append('month',m);
    const y=document.getElementById('comYear').value; if(y) params.append('year',y);
    const e=document.getElementById('comEmp').value; if(e) params.append('search',e);
    const r = await apiFetch('/commissions?'+params);
    if (!r.success) return;
    const { data, total, current_page, last_page } = r.data;
    document.getElementById('comCount').textContent=`إجمالي: ${total}`;
    document.getElementById('comPagInfo').textContent=`صفحة ${current_page} من ${last_page}`;
    if (!data.length) { document.getElementById('commissionsTable').innerHTML='<tr><td colspan="8" class="text-center py-4 text-muted">لا توجد عمولات</td></tr>'; return; }
    document.getElementById('commissionsTable').innerHTML = data.map(c=>`
        <tr>
            <td>${c.employee?.name??'-'}</td>
            <td>${c.total_sales?Number(c.total_sales).toLocaleString()+' ج.م':'-'}</td>
            <td>${c.commission_rate??0}%</td>
            <td class="fw-bold text-success">${Number(c.amount ?? 0).toLocaleString()} ج.م</td>
            <td>${c.month}/${c.year}</td>
            <td>${c.reason??'-'}</td>
            <td><span class="badge-status ${c.status==='approved'?'badge-active':c.status==='rejected'?'badge-rejected':'badge-pending'}">${c.status==='approved'?'معتمد':c.status==='rejected'?'مرفوض':'معلق'}</span></td>
            <td>
                <div class="d-flex gap-1 flex-wrap">
                    <button class="btn btn-sm btn-outline-warning" onclick="openEditModal(${c.id})"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(${c.id})"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`).join('');
    const pages=[];
    for(let i=1;i<=last_page;i++) pages.push(`<button class="btn btn-sm ${i===current_page?'btn-primary':'btn-outline-primary'} mx-1" onclick="loadCommissions(${i})">${i}</button>`);
    document.getElementById('comPagination').innerHTML=pages.join('');
}

function openAddModal() {
    document.getElementById('comId').value=''; document.getElementById('comForm').reset();
    document.getElementById('comModalTitle').innerHTML='<i class="fas fa-percentage me-2"></i> إضافة عمولة جديدة';
    document.getElementById('cf2_month').value='{{ date("n") }}';     document.getElementById('cf2_year').value='{{ date("Y") }}';
    new bootstrap.Modal(document.getElementById('comModal')).show();
}
async function openEditModal(id) {
    document.getElementById('comModalTitle').innerHTML='<i class="fas fa-edit me-2"></i> تعديل العمولة';
    new bootstrap.Modal(document.getElementById('comModal')).show();
    const r=await apiFetch('/commissions/'+id); if(!r.success) return; const c=r.data;
    document.getElementById('comId').value=c.id;
    document.getElementById('cf2_emp').value=c.employee_id;
    document.getElementById('cf2_sales').value=c.total_sales??'';
    document.getElementById('cf2_rate').value=c.commission_rate??'';
    document.getElementById('cf2_amount').value=c.amount??'';
    document.getElementById('cf2_method').value=c.calculation_method??'percentage';
    document.getElementById('cf2_month').value=c.month;
    document.getElementById('cf2_year').value=c.year;
    document.getElementById('cf2_notes').value=c.notes??'';
    document.getElementById('cf2_reason').value=c.reason??'';
}
async function saveCommission() {
    const id=document.getElementById('comId').value;
    const data=Object.fromEntries(new FormData(document.getElementById('comForm')));
    if(data.total_sales) data.total_sales=parseFloat(data.total_sales);
    if(data.commission_rate) data.commission_rate=parseFloat(data.commission_rate);
    if(data.commission_amount) data.amount=parseFloat(data.commission_amount);
    delete data.commission_amount;
    delete data.calculation_method;
    if(data.notes) data.description=data.notes;
    delete data.notes;
    data.month=parseInt(data.month); data.year=parseInt(data.year); data.employee_id=parseInt(data.employee_id);
    const r=await apiFetch(id?`/commissions/${id}`:'/commissions',{method:id?'PUT':'POST',body:JSON.stringify(data)});
    if(r.success){bootstrap.Modal.getInstance(document.getElementById('comModal')).hide();showAlert(id?'تم التحديث':'تم الإضافة');loadCommissions(comPage);}
    else showAlert(r.message||'فشل الحفظ','danger');
}
function confirmDelete(id) { comDeleteId=id; new bootstrap.Modal(document.getElementById('comDeleteModal')).show(); }
document.getElementById('comDeleteBtn').addEventListener('click', async()=>{
    if(!comDeleteId) return;
    const r=await apiFetch(`/commissions/${comDeleteId}`,{method:'DELETE'});
    bootstrap.Modal.getInstance(document.getElementById('comDeleteModal')).hide();
    if(r.success){showAlert('تم الحذف');loadCommissions(comPage);}else showAlert(r.message,'danger');
    comDeleteId=null;
});
function resetComFilter() {
    document.getElementById('comEmp').value='';
    document.getElementById('comMonth').value='{{ date("n") }}'; document.getElementById('comYear').value='{{ date("Y") }}';
    loadCommissions();
}
document.addEventListener('DOMContentLoaded', loadCommissions);
</script>
@endpush
