@extends('layouts.app')
@section('title', 'إدارة الطلبات')
@section('page-title', 'إدارة الطلبات')

@section('content')
<div class="page-header">
    <div><h1><i class="fas fa-clipboard-list me-2 text-primary"></i> الطلبات</h1><div class="breadcrumb">إدارة طلبات البيع</div></div>
    <button class="btn-primary-custom" onclick="openAddModal()"><i class="fas fa-plus me-1"></i> إضافة طلب</button>
</div>

<!-- STATUS TABS -->
<div class="section-card mb-4">
    <div class="section-body">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="text-muted ms-auto me-2" style="font-size:.8rem">فلتر الحالة:</span>
            <button class="btn btn-sm btn-outline-secondary active-tab" onclick="filterStatus('')"         id="tab-all">الكل</button>
            <button class="btn btn-sm btn-outline-secondary" onclick="filterStatus('draft')"       id="tab-draft">مسودة</button>
            <button class="btn btn-sm btn-outline-primary"  onclick="filterStatus('prepared')"     id="tab-prepared">تم التحضير</button>
            <button class="btn btn-sm btn-outline-info"     onclick="filterStatus('under_review')" id="tab-under_review">مراجعة</button>
            <button class="btn btn-sm btn-outline-success"  onclick="filterStatus('approved')"    id="tab-approved">معتمد</button>
            <button class="btn btn-sm btn-outline-danger"   onclick="filterStatus('rejected')"    id="tab-rejected">مرفوض</button>
            <button class="btn btn-sm btn-outline-dark"     onclick="filterStatus('delivered')"   id="tab-delivered">مسلّم</button>
        </div>
        <hr class="my-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4"><input type="text" id="reqSearch" class="form-control" placeholder="بحث باسم العميل، رقم الطلب..."></div>
            <div class="col-md-3"><input type="date" id="reqDate" class="form-control"></div>
            <div class="col-md-2"><button class="btn-primary-custom w-100" onclick="loadRequests()"><i class="fas fa-search me-1"></i> بحث</button></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="resetReqFilter()"><i class="fas fa-undo me-1"></i> إعادة</button></div>
        </div>
    </div>
</div>

<!-- TABLE -->
<div class="section-card">
    <div class="section-header"><i class="fas fa-clipboard-list text-primary"></i><h5 class="section-title">قائمة الطلبات</h5>
        <span class="ms-auto text-muted" style="font-size:.8rem" id="reqCount"></span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>رقم الطلب</th><th>العميل</th><th>صاحب الطلب</th><th>المراجع</th><th>عدد الأصناف</th><th>المجموع</th><th>تاريخ الطلب</th><th>الحالة</th><th>إجراءات</th></tr></thead>
            <tbody id="reqTable"><tr><td colspan="9" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr></tbody>
        </table>
    </div>
    <div class="section-body d-flex justify-content-between">
        <div id="reqPagInfo" class="text-muted" style="font-size:.8rem"></div>
        <div id="reqPagination"></div>
    </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal fade" id="reqModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="reqModalTitle"><i class="fas fa-plus me-2"></i> إضافة طلب</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="reqForm">
                    <input type="hidden" id="reqId">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4"><label class="form-label">العميل *</label><select name="customer_id" id="rqf_customer" class="form-select" data-lookup="customers" data-placeholder="اختر العميل" required></select></div>
                        <div class="col-md-4"><label class="form-label">المندوب</label><select name="employee_id" id="rqf_emp" class="form-select" data-lookup="employees" data-placeholder="اختر المندوب"></select></div>
                        <div class="col-md-4"><label class="form-label">موظف المراجعة *</label><select name="reviewer_employee_id" id="rqf_reviewer" class="form-select" data-lookup="employees" data-placeholder="اختر موظف المراجعة" required></select></div>
                        <div class="col-md-4"><label class="form-label">تاريخ التسليم المطلوب</label><input type="date" name="delivery_date" id="rqf_delivery_date" class="form-control"></div>
                        <div class="col-12"><label class="form-label">ملاحظات</label><textarea name="notes" id="rqf_notes" class="form-control" rows="2"></textarea></div>
                    </div>
                    <hr>
                    <h6 class="fw-bold mb-3"><i class="fas fa-boxes me-2"></i>أصناف الطلب</h6>
                    <div id="itemsContainer"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addItemRow()"><i class="fas fa-plus me-1"></i> إضافة صنف</button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-outline-primary" onclick="saveRequest('draft')"><i class="fas fa-save me-1"></i> حفظ مسودة</button>
                <button type="button" class="btn-primary-custom" onclick="saveRequest('under_review')"><i class="fas fa-paper-plane me-1"></i> إرسال</button>
            </div>
        </div>
    </div>
</div>

<!-- VIEW MODAL -->
<div class="modal fade" id="reqViewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-clipboard me-2"></i> تفاصيل الطلب</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="reqViewContent"><div class="text-center py-4"><div class="spinner mx-auto"></div></div></div>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="reqDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm"><div class="modal-content">
        <div class="modal-header border-0"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>تأكيد الحذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body text-center"><i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i><p>حذف هذا الطلب نهائياً؟</p></div>
        <div class="modal-footer border-0 justify-content-center">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
            <button type="button" class="btn btn-danger" id="reqDeleteBtn">حذف</button>
        </div>
    </div></div>
</div>
@endsection

@push('scripts')
<script>
let reqDeleteId=null, reqPage=1, currentStatus='', requestEmployees = [];
const reqStatusLabels = { draft:'مسودة', prepared:'تم التحضير', under_review:'تحت المراجعة', approved:'معتمد', rejected:'مرفوض', ready_for_delivery:'جاهز للتسليم', in_delivery:'في الطريق', delivered:'مسلّم', collected:'تم التحصيل', closed:'مغلق' };
const reqStatusBadge  = { draft:'badge-draft', prepared:'badge-approved', under_review:'badge-pending', approved:'badge-active', rejected:'badge-rejected', ready_for_delivery:'badge-approved', in_delivery:'badge-approved', delivered:'badge-active', collected:'badge-active', closed:'badge-active' };

function filterStatus(status) {
    currentStatus = status;
    document.querySelectorAll('.active-tab').forEach(b=>b.classList.remove('active-tab','btn-primary'));
    const tab = document.getElementById('tab-'+(status||'all'));
    if(tab) { tab.classList.add('active-tab'); }
    loadRequests();
}

async function loadRequests(page=1) {
    reqPage=page;
    const params = new URLSearchParams({ per_page:15, page });
    if(currentStatus) params.append('status',currentStatus);
    const s=document.getElementById('reqSearch').value; if(s) params.append('search',s);
    const dt=document.getElementById('reqDate').value; if(dt) params.append('date',dt);
    const r = await apiFetch('/requests?'+params);
    if (!r.success) return;
    const { data, total, current_page, last_page } = r.data;
    document.getElementById('reqCount').textContent=`إجمالي: ${total}`;
    document.getElementById('reqPagInfo').textContent=`صفحة ${current_page} من ${last_page}`;
    if (!data.length) { document.getElementById('reqTable').innerHTML='<tr><td colspan="9" class="text-center py-4 text-muted">لا توجد طلبات</td></tr>'; return; }
    document.getElementById('reqTable').innerHTML = data.map(req=>`
        <tr>
            <td><strong>#${req.id}</strong></td>
            <td>${req.customer?.name??'-'}</td>
            <td>${req.created_by?.name??req.prepared_by?.name??req.assigned_employee?.name??'-'}</td>
            <td>${req.reviewer_employee?.name??'-'}</td>
            <td>${req.items_count??req.items?.length??'-'}</td>
            <td class="fw-bold">${req.total_amount?Number(req.total_amount).toLocaleString()+' ج.م':'-'}</td>
            <td>${req.created_at?new Date(req.created_at).toLocaleDateString('ar-EG'):'-'}</td>
            <td><span class="badge-status ${reqStatusBadge[req.status]||'badge-draft'}">${reqStatusLabels[req.status]||req.status}</span></td>
            <td>
                <div class="d-flex gap-1 flex-wrap">
                    <button class="btn btn-sm btn-outline-info"    onclick="viewRequest(${req.id})" title="عرض"><i class="fas fa-eye"></i></button>
                    ${['draft','under_review'].includes(req.status)?`<button class="btn btn-sm btn-outline-warning" onclick="openEditModal(${req.id})" title="تعديل"><i class="fas fa-edit"></i></button>`:''}
                    ${req.status==='under_review'?`
                        <button class="btn btn-sm btn-success" onclick="approveReq(${req.id})"><i class="fas fa-check"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="rejectReq(${req.id})"><i class="fas fa-times"></i></button>
                    `:''}
                    ${['draft','under_review','rejected'].includes(req.status)?`<button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(${req.id})"><i class="fas fa-trash"></i></button>`:''}
                </div>
            </td>
        </tr>`).join('');
    const pages=[];
    for(let i=1;i<=last_page;i++) pages.push(`<button class="btn btn-sm ${i===current_page?'btn-primary':'btn-outline-primary'} mx-1" onclick="loadRequests(${i})">${i}</button>`);
    document.getElementById('reqPagination').innerHTML=pages.join('');
}

let itemRowCount=0;
function addItemRow(item=null) {
    const n = itemRowCount++;
    const row = document.createElement('div');
    row.className='row g-2 mb-2 item-row'; row.id=`irow_${n}`;
    row.innerHTML=`
        <div class="col-md-5"><select name="items[${n}][item_id]" class="form-select" data-lookup="items" data-placeholder="اختر الصنف" data-selected="${item?.item_id??''}" required></select></div>
        <div class="col-md-3"><div class="input-group"><input type="number" name="items[${n}][quantity]" class="form-control" placeholder="الكمية" value="${item?.quantity??1}" required><span class="input-group-text">وحدة</span></div></div>
        <div class="col-md-3"><div class="input-group"><input type="number" name="items[${n}][unit_price]" class="form-control" placeholder="السعر" value="${item?.unit_price??''}" step="0.01"><span class="input-group-text">ج.م</span></div></div>
        <div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="document.getElementById('irow_${n}').remove()"><i class="fas fa-times"></i></button></div>`;
    document.getElementById('itemsContainer').appendChild(row);
    hydrateLookupSelects(row);
}

function openAddModal() {
    document.getElementById('reqId').value=''; document.getElementById('reqForm').reset();
    renderReviewerOptions();
    document.getElementById('reqModalTitle').innerHTML='<i class="fas fa-plus me-2"></i> إضافة طلب جديد';
    document.getElementById('itemsContainer').innerHTML=''; itemRowCount=0;
    addItemRow();
    new bootstrap.Modal(document.getElementById('reqModal')).show();
}
async function openEditModal(id) {
    document.getElementById('reqModalTitle').innerHTML='<i class="fas fa-edit me-2"></i> تعديل الطلب';
    new bootstrap.Modal(document.getElementById('reqModal')).show();
    const r=await apiFetch('/requests/'+id); if(!r.success) return; const req=r.data;
    document.getElementById('reqId').value=req.id;
    document.getElementById('rqf_customer').value=req.customer_id;
    document.getElementById('rqf_emp').value=req.assigned_employee_id??req.employee_id??'';
    renderReviewerOptions();
    document.getElementById('rqf_reviewer').value=req.reviewer_employee_id??'';
    document.getElementById('rqf_delivery_date').value=req.estimated_delivery_date?req.estimated_delivery_date.substring(0,10):(req.delivery_date?req.delivery_date.substring(0,10):'');
    document.getElementById('rqf_notes').value=req.notes??'';
    document.getElementById('itemsContainer').innerHTML=''; itemRowCount=0;
    (req.items||[]).forEach(it=>addItemRow(it));
    if(!req.items?.length) addItemRow();
}
async function saveRequest(status) {
    const id=document.getElementById('reqId').value;
    const fd=new FormData(document.getElementById('reqForm'));
    const base={
        customer_id:parseInt(fd.get('customer_id')),
        employee_id:fd.get('employee_id')?parseInt(fd.get('employee_id')):null,
        reviewer_employee_id:fd.get('reviewer_employee_id')?parseInt(fd.get('reviewer_employee_id')):null,
        delivery_date:fd.get('delivery_date')||null,
        notes:fd.get('notes')||null, status,
    };
    const items=[]; let n=0;
    while(fd.get(`items[${n}][item_id]`)) {
        items.push({ item_id:parseInt(fd.get(`items[${n}][item_id]`)), quantity:parseInt(fd.get(`items[${n}][quantity]`)), unit_price:parseFloat(fd.get(`items[${n}][unit_price]`)||0) });
        n++;
    }
    base.items=items;
    const r=await apiFetch(id?`/requests/${id}`:'/requests',{method:id?'PUT':'POST',body:JSON.stringify(base)});
    if(r.success){bootstrap.Modal.getInstance(document.getElementById('reqModal')).hide();showAlert(id?'تم تحديث الطلب':'تم إضافة الطلب');loadRequests(reqPage);}
    else showAlert(r.message||'فشل الحفظ','danger');
}

async function loadRequestEmployees() {
    const r = await apiFetch('/employees?per_page=1000');
    requestEmployees = r.success ? (r.data?.data ?? []) : [];
    renderReviewerOptions();
}

function renderReviewerOptions() {
    const select = document.getElementById('rqf_reviewer');
    if (!select) return;
    const currentValue = select.value;
    select.innerHTML = '<option value="">اختر موظف المراجعة</option>' + requestEmployees
        .map(e => `<option value="${e.id}">${e.name} - ${e.position ?? ''}</option>`)
        .join('');
    if (currentValue) select.value = currentValue;
}

async function viewRequest(id) {
    const modal = new bootstrap.Modal(document.getElementById('reqViewModal'));
    document.getElementById('reqViewContent').innerHTML='<div class="text-center py-4"><div class="spinner mx-auto"></div></div>';
    modal.show();
    const r=await apiFetch('/requests/'+id); if(!r.success) return; const req=r.data;
    document.getElementById('reqViewContent').innerHTML=`
    <div class="row g-3">
        <div class="col-md-5">
            <div class="section-card p-3">
                <h6 class="fw-bold mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>معلومات الطلب</h6>
                <p class="mb-1"><strong>رقم:</strong> #${req.id}</p>
                <p class="mb-1"><strong>العميل:</strong> ${req.customer?.name??'-'}</p>
                <p class="mb-1"><strong>الهاتف:</strong> ${req.customer?.phone??'-'}</p>
                <p class="mb-1"><strong>صاحب الطلب:</strong> ${req.created_by?.name??req.prepared_by?.name??req.assigned_employee?.name??'-'}</p>
                <p class="mb-1"><strong>المراجع:</strong> ${req.reviewer_employee?.name??'-'}</p>
                <p class="mb-1"><strong>المدير:</strong> ${req.created_by?.manager?.name??'-'}</p>
                <p class="mb-1"><strong>تاريخ الطلب:</strong> ${req.created_at?new Date(req.created_at).toLocaleDateString('ar-EG'):'-'}</p>
                <p class="mb-1"><strong>تاريخ التسليم:</strong> ${req.estimated_delivery_date?new Date(req.estimated_delivery_date).toLocaleDateString('ar-EG'):(req.delivery_date?new Date(req.delivery_date).toLocaleDateString('ar-EG'):'-')}</p>
                <p class="mb-1"><strong>الحالة:</strong> <span class="badge-status ${reqStatusBadge[req.status]||'badge-draft'}">${reqStatusLabels[req.status]||req.status}</span></p>
                ${req.notes?`<p class="mb-0"><strong>ملاحظات:</strong> ${req.notes}</p>`:''}
            </div>
        </div>
        <div class="col-md-7">
            <div class="section-card p-3">
                <h6 class="fw-bold mb-3"><i class="fas fa-boxes me-2 text-success"></i>أصناف الطلب</h6>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th></tr></thead>
                        <tbody>
                            ${(req.items||[]).map(it=>`
                                <tr>
                                    <td>${it.item?.name??it.item_id}</td>
                                    <td>${it.quantity} ${it.item?.unit??'وحدة'}</td>
                                    <td>${Number(it.unit_price).toLocaleString()} ج.م</td>
                                    <td class="fw-bold">${Number(it.quantity*it.unit_price).toLocaleString()} ج.م</td>
                                </tr>`).join('')}
                        </tbody>
                        <tfoot><tr class="fw-bold"><td colspan="3">الإجمالي</td><td class="text-success">${Number(req.total_amount??0).toLocaleString()} ج.م</td></tr></tfoot>
                    </table>
                </div>
            </div>
            ${['under_review','draft'].includes(req.status)?`
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-success" onclick="bootstrap.Modal.getInstance(document.getElementById('reqViewModal')).hide();setTimeout(()=>approveReq(${req.id}),300)"><i class="fas fa-check me-1"></i>اعتماد</button>
                <button class="btn btn-danger"  onclick="bootstrap.Modal.getInstance(document.getElementById('reqViewModal')).hide();setTimeout(()=>rejectReq(${req.id}),300)"><i class="fas fa-times me-1"></i>رفض</button>
                <button class="btn btn-warning" onclick="bootstrap.Modal.getInstance(document.getElementById('reqViewModal')).hide();setTimeout(()=>openEditModal(${req.id}),300)"><i class="fas fa-edit me-1"></i>تعديل</button>
            </div>`:''}
        </div>
    </div>`;
}

async function approveReq(id) {
    const r=await apiFetch(`/requests/${id}/approve`,{method:'POST'});
    if(r.success){showAlert('تم اعتماد الطلب');loadRequests(reqPage);}else showAlert(r.message,'danger');
}
async function rejectReq(id) {
    const reason=prompt('سبب الرفض:'); if(!reason) return;
    const r=await apiFetch(`/requests/${id}/reject`,{method:'POST',body:JSON.stringify({reason})});
    if(r.success){showAlert('تم رفض الطلب','warning');loadRequests(reqPage);}else showAlert(r.message,'danger');
}
function confirmDelete(id) { reqDeleteId=id; new bootstrap.Modal(document.getElementById('reqDeleteModal')).show(); }
document.getElementById('reqDeleteBtn').addEventListener('click', async()=>{
    if(!reqDeleteId) return;
    const r=await apiFetch(`/requests/${reqDeleteId}`,{method:'DELETE'});
    bootstrap.Modal.getInstance(document.getElementById('reqDeleteModal')).hide();
    if(r.success){showAlert('تم الحذف');loadRequests(reqPage);}else showAlert(r.message,'danger');
    reqDeleteId=null;
});
function resetReqFilter() { ['reqSearch','reqDate'].forEach(id=>document.getElementById(id).value=''); currentStatus=''; filterStatus(''); }
document.getElementById('reqSearch').addEventListener('keypress', e=>{ if(e.key==='Enter') loadRequests(); });
document.addEventListener('DOMContentLoaded', async () => {
    await loadRequestEmployees();
    loadRequests();
});
</script>
@endpush
