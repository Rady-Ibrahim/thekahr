@extends('layouts.app')
@section('title', 'إدارة العملاء')
@section('page-title', 'العملاء')

@section('content')
<div class="page-header">
    <div><h1><i class="fas fa-store me-2 text-primary"></i> العملاء</h1><div class="breadcrumb">إدارة بيانات العملاء</div></div>
    <button class="btn-primary-custom" onclick="openAddModal()"><i class="fas fa-plus me-1"></i> إضافة عميل</button>
</div>

<!-- SEARCH -->
<div class="section-card mb-4">
    <div class="section-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-5"><label class="form-label">بحث</label>
                <div class="input-group"><span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="custSearch" class="form-control" placeholder="اسم، هاتف، عنوان..."></div>
            </div>
            <div class="col-md-2"><label class="form-label">الحالة</label>
                <select id="custStatus" class="form-select"><option value="">الكل</option><option value="active">نشط</option><option value="inactive">غير نشط</option></select>
            </div>
            <div class="col-md-2"><button class="btn-primary-custom w-100" onclick="loadCustomers()"><i class="fas fa-search me-1"></i> بحث</button></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="resetCust()"><i class="fas fa-undo me-1"></i> إعادة</button></div>
        </div>
    </div>
</div>

<!-- TABLE -->
<div class="section-card">
    <div class="section-header"><i class="fas fa-list text-primary"></i><h5 class="section-title">قائمة العملاء</h5>
        <span class="ms-auto text-muted" style="font-size:.8rem" id="custCount"></span></div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>كود العميل</th><th>اسم العميل</th><th>الهاتف</th><th>البريد</th><th>العنوان</th><th>الحالة</th><th>إجراءات</th></tr></thead>
            <tbody id="custTable"><tr><td colspan="7" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr></tbody>
        </table>
    </div>
    <div class="section-body d-flex justify-content-between">
        <div id="custPagInfo" class="text-muted" style="font-size:.8rem"></div>
        <div id="custPagination"></div>
    </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="custModalTitle"><i class="fas fa-store me-2"></i> إضافة عميل</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="customerForm">
                    <input type="hidden" id="custId">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">اسم العميل *</label><input type="text" name="name" id="cf_name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">رقم الهاتف</label><input type="text" name="phone" id="cf_phone" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">البريد الإلكتروني</label><input type="email" name="email" id="cf_email" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">رقم هاتف إضافي</label><input type="text" name="phone_alternative" id="cf_phone2" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">الحالة</label>
                            <select name="status" id="cf_status" class="form-select">
                                <option value="active">نشط</option><option value="inactive">غير نشط</option>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">العنوان</label><textarea name="address" id="cf_address" class="form-control" rows="2"></textarea></div>
                        <div class="col-12"><label class="form-label">ملاحظات</label><textarea name="notes" id="cf_notes" class="form-control" rows="2"></textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom" onclick="saveCustomer()"><i class="fas fa-save me-1"></i> حفظ</button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="custDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>تأكيد الحذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <p>حذف العميل <strong id="custDeleteName"></strong>؟</p>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="custDeleteBtn">حذف</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let custDeleteId = null, custPage = 1;

async function loadCustomers(page = 1) {
    custPage = page;
    const params = new URLSearchParams({ per_page:15, page,
        search: document.getElementById('custSearch').value,
    });
    const s = document.getElementById('custStatus').value;
    if (s !== '') params.append('status', s);

    const r = await apiFetch('/customers?' + params);
    if (!r.success) return;
    const { data, total, current_page, last_page } = r.data;
    document.getElementById('custCount').textContent = `إجمالي: ${total}`;
    document.getElementById('custPagInfo').textContent = `صفحة ${current_page} من ${last_page}`;

    if (!data.length) { document.getElementById('custTable').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">لا يوجد عملاء</td></tr>'; return; }
    document.getElementById('custTable').innerHTML = data.map((c,i) => `
        <tr>
            <td><span class="badge bg-light text-primary">${c.customer_code ?? 'CUS-' + String(c.id).padStart(5,'0')}</span></td>
            <td><strong>${c.name}</strong></td>
            <td>${c.phone??'-'}</td>
            <td>${c.email??'-'}</td>
            <td>${c.address ? c.address.substring(0,40)+(c.address.length>40?'…':'') : '-'}</td>
            <td><span class="badge-status ${c.status==='active'?'badge-active':'badge-inactive'}">${c.status==='active'?'نشط':(c.status==='blacklisted'?'محظور':'غير نشط')}</span></td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-warning" onclick="openEditModal(${c.id})" title="تعديل"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-info"    onclick="window.location='/requests?customer_id=${c.id}'" title="الطلبات"><i class="fas fa-clipboard-list"></i></button>
                    <button class="btn btn-sm btn-outline-danger"  onclick="confirmDelete(${c.id},'${c.name.replace(/'/g,"\\'")}')"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`).join('');

    const pages=[];
    for(let i=1;i<=last_page;i++) pages.push(`<button class="btn btn-sm ${i===current_page?'btn-primary':'btn-outline-primary'} mx-1" onclick="loadCustomers(${i})">${i}</button>`);
    document.getElementById('custPagination').innerHTML = pages.join('');
}

function openAddModal() {
    document.getElementById('custId').value = '';
    document.getElementById('customerForm').reset();
    document.getElementById('custModalTitle').innerHTML = '<i class="fas fa-store me-2"></i> إضافة عميل جديد';
    document.getElementById('cf_status').value = 'active';
    new bootstrap.Modal(document.getElementById('customerModal')).show();
}

async function openEditModal(id) {
    document.getElementById('custModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i> تعديل بيانات العميل';
    new bootstrap.Modal(document.getElementById('customerModal')).show();
    const r = await apiFetch('/customers/' + id);
    if (!r.success) return;
    const c = r.data;
    document.getElementById('custId').value       = c.id;
    document.getElementById('cf_name').value      = c.name ?? '';
    document.getElementById('cf_phone').value     = c.phone ?? '';
    document.getElementById('cf_phone2').value    = c.phone_alternative ?? '';
    document.getElementById('cf_email').value     = c.email ?? '';
    document.getElementById('cf_status').value    = c.status ?? 'active';
    document.getElementById('cf_address').value   = c.address ?? '';
    document.getElementById('cf_notes').value     = c.notes ?? '';
}

async function saveCustomer() {
    const id   = document.getElementById('custId').value;
    const data = Object.fromEntries(new FormData(document.getElementById('customerForm')));

    const r = await apiFetch(id ? `/customers/${id}` : '/customers', { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) });
    if (r.success) {
        bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide();
        showAlert(id ? 'تم تحديث بيانات العميل' : 'تم إضافة العميل');
        loadCustomers(custPage);
    } else showAlert(r.message || 'فشل الحفظ', 'danger');
}

function confirmDelete(id, name) {
    custDeleteId = id;
    document.getElementById('custDeleteName').textContent = name;
    new bootstrap.Modal(document.getElementById('custDeleteModal')).show();
}
document.getElementById('custDeleteBtn').addEventListener('click', async () => {
    if (!custDeleteId) return;
    const r = await apiFetch(`/customers/${custDeleteId}`, { method: 'DELETE' });
    bootstrap.Modal.getInstance(document.getElementById('custDeleteModal')).hide();
    if (r.success) { showAlert('تم الحذف'); loadCustomers(custPage); }
    else showAlert(r.message, 'danger');
    custDeleteId = null;
});

function resetCust() { document.getElementById('custSearch').value=''; document.getElementById('custStatus').value=''; loadCustomers(); }
document.getElementById('custSearch').addEventListener('keypress', e => { if(e.key==='Enter') loadCustomers(); });
document.addEventListener('DOMContentLoaded', loadCustomers);
</script>
@endpush
