@extends('layouts.app')
@section('title', 'المخازن')
@section('page-title', 'المخازن')

@section('content')
<div class="page-header">
    <div><h1><i class="fas fa-warehouse me-2 text-primary"></i> المخازن</h1><div class="breadcrumb">إدارة المخازن</div></div>
    <button class="btn-primary-custom" onclick="openAddModal()"><i class="fas fa-plus me-1"></i> إضافة مخزن</button>
</div>

<!-- TABLE -->
<div class="section-card">
    <div class="section-header">
        <i class="fas fa-warehouse text-primary"></i><h5 class="section-title">قائمة المخازن</h5>
        <div class="ms-auto d-flex gap-2">
            <input type="text" id="whSearch" class="form-control" style="width:200px" placeholder="بحث...">
            <button class="btn btn-sm btn-outline-primary" onclick="loadWarehouses()"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>#</th><th>اسم المخزن</th><th>الموقع</th><th>الوصف</th><th>الحالة</th><th>إجراءات</th></tr></thead>
            <tbody id="whTable"><tr><td colspan="6" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr></tbody>
        </table>
    </div>
    <div class="section-body d-flex justify-content-between">
        <div id="whPagInfo" class="text-muted" style="font-size:.8rem"></div>
        <div id="whPagination"></div>
    </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal fade" id="warehouseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="whModalTitle"><i class="fas fa-warehouse me-2"></i> إضافة مخزن</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="whForm">
                    <input type="hidden" id="whId">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">اسم المخزن *</label><input type="text" name="name" id="wf_name" class="form-control" required></div>
                        <div class="col-12"><label class="form-label">الموقع</label><input type="text" name="location" id="wf_location" class="form-control"></div>
                        <div class="col-12"><label class="form-label">الحالة</label>
                            <select name="is_active" id="wf_is_active" class="form-select"><option value="1">نشط</option><option value="0">غير نشط</option></select>
                        </div>
                        <div class="col-12"><label class="form-label">وصف</label><textarea name="description" id="wf_description" class="form-control" rows="3"></textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom" onclick="saveWarehouse()"><i class="fas fa-save me-1"></i> حفظ</button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="whDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>تأكيد الحذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center"><i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i><p>حذف المخزن <strong id="whDeleteName"></strong>؟</p></div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="whDeleteBtn">حذف</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let whDeleteId = null, whPage = 1;

async function loadWarehouses(page = 1) {
    whPage = page;
    const params = new URLSearchParams({ per_page:15, page, search: document.getElementById('whSearch').value });
    const r = await apiFetch('/warehouses?' + params);
    if (!r.success) return;
    const { data, total, current_page, last_page } = r.data;
    document.getElementById('whPagInfo').textContent = `إجمالي: ${total}`;
    if (!data.length) { document.getElementById('whTable').innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">لا توجد مخازن</td></tr>'; return; }
    document.getElementById('whTable').innerHTML = data.map((w,i) => `
        <tr>
            <td>${(page-1)*15+i+1}</td>
            <td><strong>${w.name}</strong></td>
            <td>${w.location??'-'}</td>
            <td>${w.description ? w.description.substring(0,50)+(w.description.length>50?'…':'') : '-'}</td>
            <td><span class="badge-status ${w.is_active!==false?'badge-active':'badge-inactive'}">${w.is_active!==false?'نشط':'غير نشط'}</span></td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary"  onclick="window.location='/items?warehouse_id=${w.id}'" title="الأصناف"><i class="fas fa-boxes"></i></button>
                    <button class="btn btn-sm btn-outline-warning"   onclick="openEditModal(${w.id})" title="تعديل"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger"    onclick="confirmDelete(${w.id},'${w.name.replace(/'/g,"\\'")}')"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`).join('');
    const pages=[];
    for(let i=1;i<=last_page;i++) pages.push(`<button class="btn btn-sm ${i===current_page?'btn-primary':'btn-outline-primary'} mx-1" onclick="loadWarehouses(${i})">${i}</button>`);
    document.getElementById('whPagination').innerHTML = pages.join('');
}

function openAddModal() {
    document.getElementById('whId').value = '';
    document.getElementById('whForm').reset();
    document.getElementById('wf_is_active').value = '1';
    document.getElementById('whModalTitle').innerHTML = '<i class="fas fa-warehouse me-2"></i> إضافة مخزن جديد';
    new bootstrap.Modal(document.getElementById('warehouseModal')).show();
}

async function openEditModal(id) {
    document.getElementById('whModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i> تعديل المخزن';
    new bootstrap.Modal(document.getElementById('warehouseModal')).show();
    const r = await apiFetch('/warehouses/' + id);
    if (!r.success) return;
    const w = r.data;
    document.getElementById('whId').value          = w.id;
    document.getElementById('wf_name').value       = w.name ?? '';
    document.getElementById('wf_location').value   = w.location ?? '';
    document.getElementById('wf_description').value = w.description ?? '';
    document.getElementById('wf_is_active').value  = w.is_active !== false ? '1' : '0';
}

async function saveWarehouse() {
    const id   = document.getElementById('whId').value;
    const data = Object.fromEntries(new FormData(document.getElementById('whForm')));
    data.is_active = data.is_active === '1';
    const r = await apiFetch(id?`/warehouses/${id}`:'/warehouses', { method:id?'PUT':'POST', body:JSON.stringify(data) });
    if (r.success) {
        bootstrap.Modal.getInstance(document.getElementById('warehouseModal')).hide();
        showAlert(id?'تم تحديث المخزن':'تم إضافة المخزن');
        loadWarehouses(whPage);
    } else showAlert(r.message||'فشل الحفظ', 'danger');
}

function confirmDelete(id, name) { whDeleteId=id; document.getElementById('whDeleteName').textContent=name; new bootstrap.Modal(document.getElementById('whDeleteModal')).show(); }
document.getElementById('whDeleteBtn').addEventListener('click', async () => {
    if (!whDeleteId) return;
    const r = await apiFetch(`/warehouses/${whDeleteId}`, { method:'DELETE' });
    bootstrap.Modal.getInstance(document.getElementById('whDeleteModal')).hide();
    if (r.success) { showAlert('تم الحذف'); loadWarehouses(whPage); }
    else showAlert(r.message, 'danger');
    whDeleteId = null;
});

document.getElementById('whSearch').addEventListener('keypress', e => { if(e.key==='Enter') loadWarehouses(); });
document.addEventListener('DOMContentLoaded', loadWarehouses);
</script>
@endpush
