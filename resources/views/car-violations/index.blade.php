@extends('layouts.app')
@section('title', 'مخالفات السيارات')
@section('page-title', 'مخالفات السيارات')

@section('content')
<div class="page-header">
    <div><h1><i class="fas fa-car-crash me-2 text-danger"></i> مخالفات السيارات</h1><div class="breadcrumb">إدارة مخالفات سيارات الموظفين</div></div>
    <button class="btn-primary-custom" onclick="openAddModal()"><i class="fas fa-plus me-1"></i> إضافة مخالفة</button>
</div>

<!-- FILTERS -->
<div class="section-card mb-4">
    <div class="section-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">الموظف / رقم السيارة</label><input type="text" id="cvSearch" class="form-control" placeholder="اسم الموظف أو رقم السيارة..."></div>
            <div class="col-md-2"><label class="form-label">نوع المخالفة</label>
                <select id="cvType" class="form-select"><option value="">الكل</option><option value="speeding">سرعة زائدة</option><option value="parking">مخالفة وقوف</option><option value="accident">حادث</option><option value="red_light">إشارة حمراء</option><option value="other">أخرى</option></select>
            </div>
            <div class="col-md-2"><label class="form-label">الحالة</label>
                <select id="cvStatus" class="form-select"><option value="">الكل</option><option value="pending">معلق</option><option value="deducted">تم خصمه</option><option value="waived">تم الإعفاء</option></select>
            </div>
            <div class="col-md-2"><button class="btn-primary-custom w-100 mt-4" onclick="loadViolations()"><i class="fas fa-search me-1"></i> بحث</button></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100 mt-4" onclick="resetCvFilter()"><i class="fas fa-undo me-1"></i> إعادة</button></div>
        </div>
    </div>
</div>

<!-- TABLE -->
<div class="section-card">
    <div class="section-header"><i class="fas fa-car-crash text-danger"></i><h5 class="section-title">قائمة المخالفات</h5>
        <span class="ms-auto text-muted" style="font-size:.8rem" id="cvCount"></span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>الموظف</th><th>رقم السيارة</th><th>نوع المخالفة</th><th>المبلغ</th><th>تاريخ المخالفة</th><th>وصف</th><th>الحالة</th><th>إجراءات</th></tr></thead>
            <tbody id="violationsTable"><tr><td colspan="8" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr></tbody>
        </table>
    </div>
    <div class="section-body d-flex justify-content-between">
        <div id="cvPagInfo" class="text-muted" style="font-size:.8rem"></div>
        <div id="cvPagination"></div>
    </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal fade" id="cvModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="cvModalTitle"><i class="fas fa-car-crash me-2 text-danger"></i> إضافة مخالفة</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="cvForm">
                    <input type="hidden" id="cvId">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">الموظف *</label>
                            <div class="position-relative">
                                <input type="text" name="employee_id" id="cvf_emp" class="form-control" placeholder="ابحث بالاسم أو الكود..." required>
                            </div>
                        </div>
                        <div class="col-md-6"><label class="form-label">رقم السيارة</label><input type="text" name="vehicle_number" id="cvf_car" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">نوع المخالفة *</label>
                            <select name="violation_type" id="cvf_type" class="form-select" required>
                                <option value="speeding">سرعة زائدة</option><option value="parking">مخالفة وقوف</option>
                                <option value="accident">حادث</option><option value="red_light">إشارة حمراء</option><option value="other">أخرى</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">مبلغ المخالفة *</label><div class="input-group"><input type="number" name="fine_amount" id="cvf_amount" class="form-control" required><span class="input-group-text">ج.م</span></div></div>
                        <div class="col-md-6"><label class="form-label">تاريخ المخالفة *</label><input type="date" name="violation_date" id="cvf_date" class="form-control" required value="{{ date('Y-m-d') }}"></div>
                        <div class="col-md-6"><label class="form-label">موقع المخالفة</label><input type="text" name="location" id="cvf_location" class="form-control"></div>
                        <div class="col-12"><label class="form-label">السبب</label><textarea name="reason" id="cvf_reason" class="form-control" rows="2"></textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" onclick="saveViolation()"><i class="fas fa-save me-1"></i> حفظ</button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="cvDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm"><div class="modal-content">
        <div class="modal-header border-0"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>تأكيد الحذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body text-center"><i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i><p>حذف هذه المخالفة نهائياً؟</p></div>
        <div class="modal-footer border-0 justify-content-center">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
            <button type="button" class="btn btn-danger" id="cvDeleteBtn">حذف</button>
        </div>
    </div></div>
</div>
@endsection

@push('scripts')
<script>
let cvDeleteId=null, cvPage=1;
let cvEmpSearch=null, cvEmployees=[];
const cvTypes   = { speeding:'سرعة زائدة', parking:'وقوف خاطئ', accident:'حادث', red_light:'إشارة حمراء', other:'أخرى' };
const cvStatuses = { pending:'معلق', deducted:'تم الخصم', waived:'إعفاء' };
const cvBadges   = { pending:'badge-pending', deducted:'badge-rejected', waived:'badge-approved' };

async function loadViolations(page=1) {
    cvPage=page;
    const params = new URLSearchParams({ per_page:15, page });
    const s=document.getElementById('cvSearch').value; if(s) params.append('search',s);
    const t=document.getElementById('cvType').value; if(t) params.append('violation_type',t);
    const st=document.getElementById('cvStatus').value; if(st) params.append('status',st);
    const r = await apiFetch('/car-violations?'+params);
    if (!r.success) return;
    const { data, total, current_page, last_page } = r.data;
    document.getElementById('cvCount').textContent=`إجمالي: ${total}`;
    document.getElementById('cvPagInfo').textContent=`صفحة ${current_page} من ${last_page}`;
    if (!data.length) { document.getElementById('violationsTable').innerHTML='<tr><td colspan="8" class="text-center py-4 text-muted">لا توجد مخالفات</td></tr>'; return; }
    document.getElementById('violationsTable').innerHTML = data.map(v=>`
        <tr>
            <td>${v.employee?.name??'-'}</td>
            <td><span class="fw-bold">${v.vehicle_number??v.employee?.car_number??'-'}</span></td>
            <td>${cvTypes[v.violation_type]||v.violation_type}</td>
            <td class="fw-bold text-danger">${Number(v.fine_amount).toLocaleString()} ج.م</td>
            <td>${v.violation_date?new Date(v.violation_date).toLocaleDateString('ar-EG'):'-'}</td>
            <td>${v.reason?v.reason.substring(0,40)+(v.reason.length>40?'…':''):'-'}</td>
            <td><span class="badge-status ${cvBadges[v.status]||'badge-pending'}">${cvStatuses[v.status]||v.status}</span></td>
            <td>
                <div class="d-flex gap-1 flex-wrap">
                    <button class="btn btn-sm btn-outline-warning" onclick="openEditModal(${v.id})"><i class="fas fa-edit"></i></button>
                    ${v.status==='pending'?`
                        <button class="btn btn-sm btn-outline-danger" onclick="deductViol(${v.id})" title="خصم"><i class="fas fa-minus-circle"></i></button>
                        <button class="btn btn-sm btn-outline-success" onclick="waiveViol(${v.id})" title="إعفاء"><i class="fas fa-times-circle"></i></button>
                    `:''}
                    <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(${v.id})"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`).join('');
    const pages=[];
    for(let i=1;i<=last_page;i++) pages.push(`<button class="btn btn-sm ${i===current_page?'btn-primary':'btn-outline-primary'} mx-1" onclick="loadViolations(${i})">${i}</button>`);
    document.getElementById('cvPagination').innerHTML=pages.join('');
}

function openAddModal() {
    document.getElementById('cvId').value=''; document.getElementById('cvForm').reset();
    document.getElementById('cvModalTitle').innerHTML='<i class="fas fa-car-crash me-2 text-danger"></i> إضافة مخالفة جديدة';
    document.getElementById('cvf_date').value='{{ date("Y-m-d") }}';
    cvEmpSearch.reset();
    new bootstrap.Modal(document.getElementById('cvModal')).show();
}
function setCvEmpValue(empId) {
    const emp = cvEmployees.find(e => e.id === parseInt(empId));
    cvEmpSearch.setValue(empId, emp ? `${emp.name}${emp.employee_code ? ' - ' + emp.employee_code : ''}` : String(empId));
}
async function openEditModal(id) {
    document.getElementById('cvModalTitle').innerHTML='<i class="fas fa-edit me-2"></i> تعديل المخالفة';
    new bootstrap.Modal(document.getElementById('cvModal')).show();
    const r=await apiFetch('/car-violations/'+id); if(!r.success) return; const v=r.data;
    document.getElementById('cvId').value=v.id;
    setCvEmpValue(v.employee_id);
    document.getElementById('cvf_car').value=v.vehicle_number??'';
    document.getElementById('cvf_type').value=v.violation_type;
    document.getElementById('cvf_amount').value=v.fine_amount;
    document.getElementById('cvf_date').value=v.violation_date?v.violation_date.substring(0,10):'';
    document.getElementById('cvf_location').value=v.location??'';
    document.getElementById('cvf_reason').value=v.reason??'';
}
async function saveViolation() {
    const id=document.getElementById('cvId').value;
    const data=Object.fromEntries(new FormData(document.getElementById('cvForm')));
    data.fine_amount=parseFloat(data.fine_amount); data.employee_id=parseInt(data.employee_id);
    if(!data.vehicle_number) delete data.vehicle_number;
    const r=await apiFetch(id?`/car-violations/${id}`:'/car-violations',{method:id?'PUT':'POST',body:JSON.stringify(data)});
    if(r.success){bootstrap.Modal.getInstance(document.getElementById('cvModal')).hide();showAlert(id?'تم التحديث':'تم الإضافة');loadViolations(cvPage);}
    else showAlert(r.message||'فشل الحفظ','danger');
}
async function deductViol(id) {
    if(!confirm('تأكيد خصم المخالفة من راتب الموظف؟')) return;
    const r=await apiFetch(`/car-violations/${id}/deduct`,{method:'POST'});
    if(r.success){showAlert('تم خصم المخالفة');loadViolations(cvPage);}else showAlert(r.message,'danger');
}
async function waiveViol(id) {
    const reason=prompt('سبب الإعفاء من المخالفة:'); if(!reason) return;
    const r=await apiFetch(`/car-violations/${id}/waive`,{method:'POST',body:JSON.stringify({reason})});
    if(r.success){showAlert('تم الإعفاء من المخالفة','warning');loadViolations(cvPage);}else showAlert(r.message,'danger');
}
function confirmDelete(id) { cvDeleteId=id; new bootstrap.Modal(document.getElementById('cvDeleteModal')).show(); }
document.getElementById('cvDeleteBtn').addEventListener('click', async()=>{
    if(!cvDeleteId) return;
    const r=await apiFetch(`/car-violations/${cvDeleteId}`,{method:'DELETE'});
    bootstrap.Modal.getInstance(document.getElementById('cvDeleteModal')).hide();
    if(r.success){showAlert('تم الحذف');loadViolations(cvPage);}else showAlert(r.message,'danger');
    cvDeleteId=null;
});
function resetCvFilter() { ['cvSearch','cvType','cvStatus'].forEach(id=>document.getElementById(id).value=''); loadViolations(); }

async function initCvEmpSearch() {
    cvEmpSearch = createSearchableSelect(document.getElementById('cvf_emp'), 'employees');
    cvEmployees = await getLookupRows('employees');
    cvEmpSearch.setItems(cvEmployees);
}

document.addEventListener('DOMContentLoaded', () => { initCvEmpSearch(); loadViolations(); });
</script>
@endpush
