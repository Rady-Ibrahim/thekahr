@extends('layouts.app')
@section('title', 'الأصناف')
@section('page-title', 'الأصناف')

@section('content')
<div class="page-header">
    <div><h1><i class="fas fa-boxes me-2 text-primary"></i> الأصناف</h1><div class="breadcrumb">إدارة أصناف المخزون</div></div>
    <button class="btn-primary-custom" onclick="openAddModal()"><i class="fas fa-plus me-1"></i> إضافة صنف</button>
</div>

<!-- FILTERS -->
<div class="section-card mb-4">
    <div class="section-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label">بحث</label>
                <input type="text" id="itemSearch" class="form-control" placeholder="اسم الصنف، الكود...">
            </div>
            <div class="col-md-3"><label class="form-label">الفئة</label>
                <input type="text" id="itemCat" class="form-control" placeholder="الفئة">
            </div>
            <div class="col-md-2"><label class="form-label">الحالة</label>
                <select id="itemAvail" class="form-select"><option value="">الكل</option><option value="1">متاح</option><option value="0">غير متاح</option></select>
            </div>
            <div class="col-md-2"><button class="btn-primary-custom w-100" onclick="loadItems()"><i class="fas fa-search me-1"></i> بحث</button></div>
            <div class="col-md-1"><button class="btn btn-outline-secondary w-100" onclick="resetItemFilter()"><i class="fas fa-undo"></i></button></div>
        </div>
    </div>
</div>

<!-- TABLE -->
<div class="section-card">
    <div class="section-header"><i class="fas fa-boxes text-primary"></i><h5 class="section-title">قائمة الأصناف</h5>
        <span class="ms-auto text-muted" style="font-size:.8rem" id="itemCount"></span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>الكود</th><th>الاسم</th><th>الشركة</th><th>الفئة</th><th>الباركود</th><th>التشغيلة</th><th>الصلاحية</th><th>سعر الوحدة</th><th>الوحدة</th><th>المخزن</th><th>متاح</th><th>إجراءات</th></tr></thead>
            <tbody id="itemsTable"><tr><td colspan="12" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr></tbody>
        </table>
    </div>
    <div class="section-body d-flex justify-content-between">
        <div id="itemPagInfo" class="text-muted" style="font-size:.8rem"></div>
        <div id="itemPagination"></div>
    </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemModalTitle"><i class="fas fa-plus me-2"></i> إضافة صنف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="itemForm">
                    <input type="hidden" id="itemId">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">كود الصنف</label><input type="text" name="item_code" id="if_code" class="form-control" placeholder="ITM001"></div>
                        <div class="col-md-8"><label class="form-label">اسم الصنف *</label><input type="text" name="name" id="if_name" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">الفئة</label><input type="text" name="category" id="if_category" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">سعر الوحدة *</label>
                            <div class="input-group"><input type="number" name="price" id="if_unit_price" class="form-control" required step="0.01"><span class="input-group-text">ج.م</span></div>
                        </div>
                        <div class="col-md-4"><label class="form-label">الوحدة</label><input type="text" name="unit" id="if_unit" class="form-control" placeholder="قطعة، كيلو، لتر..."></div>
                        <div class="col-md-4"><label class="form-label">اسم الشركة</label><input type="text" name="company_name" id="if_company_name" class="form-control" placeholder="اسم الشركة المصنعة"></div>
                        <div class="col-md-4"><label class="form-label">الباركود</label><input type="text" name="barcode" id="if_barcode" class="form-control" placeholder="باركود الصنف"></div>
                        <div class="col-md-4"><label class="form-label">التشغيلة</label><input type="text" name="batch_number" id="if_batch_number" class="form-control" placeholder="رقم التشغيلة / الدفعة"></div>
                        <div class="col-md-4"><label class="form-label">الصلاحية</label><input type="date" name="expiry_date" id="if_expiry_date" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">المخزن</label><select name="warehouse_id" id="if_warehouse_id" class="form-select" data-lookup="warehouses" data-placeholder="اختر المخزن"></select></div>
                        <div class="col-md-6"><label class="form-label">متاح للبيع؟</label>
                            <select name="is_available" id="if_is_available" class="form-select"><option value="1">متاح</option><option value="0">غير متاح</option></select>
                        </div>
                        <div class="col-12"><label class="form-label">وصف</label><textarea name="description" id="if_description" class="form-control" rows="2"></textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom" onclick="saveItem()"><i class="fas fa-save me-1"></i> حفظ</button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="itemDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>تأكيد الحذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center"><i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i><p>حذف الصنف <strong id="itemDeleteName"></strong>؟</p></div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="itemDeleteBtn">حذف</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let itemDeleteId=null, itemPage=1;

async function loadItems(page=1) {
    itemPage=page;
    const params = new URLSearchParams({ per_page:15, page,
        search: document.getElementById('itemSearch').value,
        category: document.getElementById('itemCat').value,
    });
    const av = document.getElementById('itemAvail').value;
    if (av !== '') params.append('is_available', av);
    const r = await apiFetch('/items?' + params);
    if (!r.success) return;
    const { data, total, current_page, last_page } = r.data;
    document.getElementById('itemCount').textContent = `إجمالي: ${total}`;
    document.getElementById('itemPagInfo').textContent = `صفحة ${current_page} من ${last_page}`;
    if (!data.length) { document.getElementById('itemsTable').innerHTML='<tr><td colspan="12" class="text-center py-4 text-muted">لا توجد أصناف</td></tr>'; return; }
    document.getElementById('itemsTable').innerHTML = data.map(i=>`
        <tr>
            <td>${i.item_code??'-'}</td>
            <td><strong>${i.name}</strong></td>
            <td>${i.company_name??'-'}</td>
            <td>${i.category??'-'}</td>
            <td>${i.barcode??'-'}</td>
            <td>${i.batch_number??'-'}</td>
            <td>${i.expiry_date??'-'}</td>
            <td class="fw-bold">${Number(i.price).toLocaleString()} ج.م</td>
            <td>${i.unit??'وحدة'}</td>
            <td>${i.warehouse?.name??'-'}</td>
            <td><span class="badge-status ${i.is_available?'badge-active':'badge-inactive'}">${i.is_available?'متاح':'غير متاح'}</span></td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-warning" onclick="openEditModal(${i.id})" title="تعديل"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger"  onclick="confirmDelete(${i.id},'${i.name.replace(/'/g,"\\'")}')"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`).join('');
    const pages=[];
    for(let i=1;i<=last_page;i++) pages.push(`<button class="btn btn-sm ${i===current_page?'btn-primary':'btn-outline-primary'} mx-1" onclick="loadItems(${i})">${i}</button>`);
    document.getElementById('itemPagination').innerHTML=pages.join('');
}

function openAddModal() {
    document.getElementById('itemId').value='';
    document.getElementById('itemForm').reset();
    document.getElementById('if_is_available').value='1';
    document.getElementById('itemModalTitle').innerHTML='<i class="fas fa-plus me-2"></i> إضافة صنف جديد';
    new bootstrap.Modal(document.getElementById('itemModal')).show();
}

async function openEditModal(id) {
    document.getElementById('itemModalTitle').innerHTML='<i class="fas fa-edit me-2"></i> تعديل الصنف';
    new bootstrap.Modal(document.getElementById('itemModal')).show();
    const r = await apiFetch('/items/'+id);
    if (!r.success) return;
    const i = r.data;
    document.getElementById('itemId').value          = i.id;
    document.getElementById('if_code').value         = i.item_code??'';
    document.getElementById('if_name').value         = i.name??'';
    document.getElementById('if_category').value     = i.category??'';
    document.getElementById('if_unit_price').value   = i.price??'';
    document.getElementById('if_unit').value         = i.unit??'';
    document.getElementById('if_company_name').value = i.company_name??'';
    document.getElementById('if_barcode').value      = i.barcode??'';
    document.getElementById('if_batch_number').value = i.batch_number??'';
    document.getElementById('if_expiry_date').value  = i.expiry_date??'';
    document.getElementById('if_warehouse_id').value = i.warehouse_id??'';
    document.getElementById('if_is_available').value = i.is_available?'1':'0';
    document.getElementById('if_description').value  = i.description??'';
}

async function saveItem() {
    const id   = document.getElementById('itemId').value;
    const data = Object.fromEntries(new FormData(document.getElementById('itemForm')));
    data.price = parseFloat(data.price);
    data.is_available = data.is_available==='1';
    ['company_name','barcode','batch_number','expiry_date','description','category','unit'].forEach(k => { if (data[k] === '') data[k] = null; });
    if (data.warehouse_id) data.warehouse_id=parseInt(data.warehouse_id); else delete data.warehouse_id;
    const r = await apiFetch(id?`/items/${id}`:'/items', { method:id?'PUT':'POST', body:JSON.stringify(data) });
    if (r.success) { bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide(); showAlert(id?'تم التحديث':'تم الإضافة'); loadItems(itemPage); }
    else showAlert(r.message||'فشل الحفظ','danger');
}

function confirmDelete(id,name) { itemDeleteId=id; document.getElementById('itemDeleteName').textContent=name; new bootstrap.Modal(document.getElementById('itemDeleteModal')).show(); }
document.getElementById('itemDeleteBtn').addEventListener('click', async () => {
    if (!itemDeleteId) return;
    const r = await apiFetch(`/items/${itemDeleteId}`, { method:'DELETE' });
    bootstrap.Modal.getInstance(document.getElementById('itemDeleteModal')).hide();
    if (r.success) { showAlert('تم الحذف'); loadItems(itemPage); } else showAlert(r.message,'danger');
    itemDeleteId=null;
});

function resetItemFilter() { ['itemSearch','itemCat','itemAvail'].forEach(id=>document.getElementById(id).value=''); loadItems(); }
document.getElementById('itemSearch').addEventListener('keypress', e=>{ if(e.key==='Enter') loadItems(); });
document.addEventListener('DOMContentLoaded', loadItems);
</script>
@endpush
