@extends('layouts.app')
@section('title', 'المجموعات')
@section('page-title', 'المجموعات')

@push('styles')
<style>
.member-check-item:hover { background: #eef2ff; }
.member-check-item input:checked ~ span { font-weight: 600; }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1><i class="fas fa-users me-2 text-primary"></i> المجموعات</h1>
        <div class="breadcrumb">إدارة مجموعات المحادثة الجماعية</div>
    </div>
    <button class="btn-primary-custom" onclick="openAddModal()"><i class="fas fa-plus me-1"></i> إضافة مجموعة</button>
</div>

<div class="section-card mb-4">
    <div class="section-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">بحث</label>
                <input type="text" id="groupSearch" class="form-control" placeholder="اسم المجموعة">
            </div>
            <div class="col-md-3">
                <label class="form-label">الحالة</label>
                <select id="groupStatus" class="form-select">
                    <option value="">الكل</option>
                    <option value="active">نشط</option>
                    <option value="archived">مؤرشفة</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn-primary-custom w-100" onclick="loadGroups()"><i class="fas fa-search me-1"></i> بحث</button>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" onclick="resetFilters()"><i class="fas fa-undo me-1"></i> إعادة</button>
            </div>
        </div>
    </div>
</div>

<div class="section-card">
    <div class="section-header">
        <i class="fas fa-users text-primary"></i>
        <h5 class="section-title">قائمة المجموعات</h5>
        <span class="ms-auto text-muted" id="groupCount"></span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم المجموعة</th>
                    <th>المنشئ</th>
                    <th>الأعضاء</th>
                    <th>الرسائل</th>
                    <th>الحالة</th>
                    <th>تاريخ الإنشاء</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody id="groupTable">
                <tr><td colspan="8" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr>
            </tbody>
        </table>
    </div>
    <div class="section-body d-flex justify-content-between">
        <div id="groupPagInfo" class="text-muted" style="font-size:.8rem"></div>
        <div id="groupPagination"></div>
    </div>
</div>

<div class="modal fade" id="groupModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="groupModalTitle"><i class="fas fa-users me-2"></i> إضافة مجموعة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="groupForm" onsubmit="return false;">
                    <input type="hidden" id="groupId">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">اسم المجموعة *</label>
                            <input type="text" name="name" id="gf_name" class="form-control" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">الوصف</label>
                            <textarea name="description" id="gf_description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">الأعضاء <span class="text-muted" id="gf_selected_count" style="font-size:.8rem">(0)</span></label>
                            <div class="input-group mb-2">
                                <input type="text" id="gf_member_search" class="form-control" placeholder="بحث عن موظف...">
                                <button class="btn btn-outline-primary" type="button" onclick="filterMemberCheckboxes()"><i class="fas fa-search"></i></button>
                            </div>
                            <div id="gf_members_container" class="border rounded" style="max-height:200px;overflow-y:auto;padding:4px"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom" onclick="saveGroup()"><i class="fas fa-save me-1"></i> حفظ</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="groupViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i> تفاصيل المجموعة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="groupViewBody">
                <div class="text-center py-4"><div class="spinner mx-auto"></div></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="groupDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>تأكيد الحذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <p>أرشفة المجموعة <strong id="groupDeleteName"></strong>؟</p>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="groupDeleteBtn">أرشفة</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let groupPage = 1;
let groupDeleteId = null;
let employeesLookup = [];

async function loadLookups() {
    const r = await apiFetch('/employees?per_page=1000');
    employeesLookup = r.success ? (r.data?.data ?? r.data ?? []) : [];
}

async function loadGroups(page = 1) {
    groupPage = page;
    const params = new URLSearchParams({ per_page: 15, page });
    const s = document.getElementById('groupSearch').value;
    const st = document.getElementById('groupStatus').value;
    if (s) params.append('search', s);
    if (st) params.append('status', st);

    const r = await apiFetch('/chat-groups?' + params);
    if (!r.success) return;

    const { data, total, current_page, last_page } = r.data;
    document.getElementById('groupCount').textContent = `إجمالي: ${total}`;
    document.getElementById('groupPagInfo').textContent = `صفحة ${current_page} من ${last_page}`;

    if (!data.length) {
        document.getElementById('groupTable').innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">لا توجد مجموعات</td></tr>';
        return;
    }

    document.getElementById('groupTable').innerHTML = data.map((g, i) => `
        <tr>
            <td>${(page - 1) * 15 + i + 1}</td>
            <td><strong>${escapeHtml(g.name)}</strong></td>
            <td>${g.creator?.name ?? '-'}</td>
            <td><span class="badge-status badge-approved">${g.members_count ?? 0}</span></td>
            <td><span class="badge-status badge-info">${g.messages_count ?? 0}</span></td>
            <td><span class="badge-status ${g.status === 'active' ? 'badge-active' : 'badge-draft'}">${g.status === 'active' ? 'نشط' : 'مؤرشفة'}</span></td>
            <td>${g.created_at ? new Date(g.created_at).toLocaleDateString('ar-EG') : '-'}</td>
            <td>
                <button class="btn btn-sm btn-outline-info" onclick="viewGroup(${g.id})" title="عرض"><i class="fas fa-eye"></i></button>
                <button class="btn btn-sm btn-outline-warning" onclick="openEditModal(${g.id})" title="تعديل"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(${g.id}, '${escapeJs(g.name)}')" title="أرشفة"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');

    const pages = [];
    for (let i = 1; i <= Math.min(last_page, 10); i++) {
        pages.push(`<button class="btn btn-sm ${i === current_page ? 'btn-primary' : 'btn-outline-primary'} mx-1" onclick="loadGroups(${i})">${i}</button>`);
    }
    document.getElementById('groupPagination').innerHTML = pages.join('');
}

async function renderMemberCheckboxes(selectedIds = []) {
    const container = document.getElementById('gf_members_container');
    if (!employeesLookup.length) {
        const r = await apiFetch('/employees?per_page=500');
        employeesLookup = r.success ? (r.data?.data ?? r.data ?? []) : [];
        if (!employeesLookup.length) { container.innerHTML = '<div class="text-muted text-center py-3">لا يوجد موظفون</div>'; return; }
    }
    container.innerHTML = employeesLookup.map(e => {
        const checked = selectedIds.includes(e.id);
        return `<label class="d-flex align-items-center gap-2 px-2 py-1 member-check-item" style="cursor:pointer;border-radius:4px">
            <input type="checkbox" class="form-check-input m-0 member-check" value="${e.id}" ${checked ? 'checked' : ''} onchange="updateMemberCount()">
            <span style="font-size:.85rem">${escapeHtml(e.name)} <small class="text-muted">(${escapeHtml(e.employee_code || e.id)})</small></span>
        </label>`;
    }).join('');
    updateMemberCount();
}

function updateMemberCount() {
    const count = document.querySelectorAll('#gf_members_container .member-check:checked').length;
    document.getElementById('gf_selected_count').textContent = `(${count})`;
}

async function filterMemberCheckboxes() {
    const searchInput = document.getElementById('gf_member_search');
    const container = document.getElementById('gf_members_container');
    if (!container) return;
    const q = searchInput ? searchInput.value.trim() : '';
    const selected = [...document.querySelectorAll('#gf_members_container .member-check:checked')].map(cb => Number(cb.value));

    if (!q) {
        if (employeesLookup.length) { await renderMemberCheckboxes(selected); return; }
        const r = await apiFetch('/employees?per_page=500');
        employeesLookup = r.success ? (r.data?.data ?? r.data ?? []) : [];
        await renderMemberCheckboxes(selected);
        return;
    }

    const r = await apiFetch('/employees?search=' + encodeURIComponent(q) + '&per_page=100');
    if (!r.success) return;
    const results = r.data?.data ?? r.data ?? [];
    container.innerHTML = results.map(e => {
        const checked = selected.includes(e.id);
        return `<label class="d-flex align-items-center gap-2 px-2 py-1 member-check-item" style="cursor:pointer;border-radius:4px">
            <input type="checkbox" class="form-check-input m-0 member-check" value="${e.id}" ${checked ? 'checked' : ''} onchange="updateMemberCount()">
            <span style="font-size:.85rem">${escapeHtml(e.name)} <small class="text-muted">(${escapeHtml(e.employee_code || e.id)})</small></span>
        </label>`;
    }).join('');
    updateMemberCount();
}

async function openAddModal() {
    document.getElementById('groupId').value = '';
    document.getElementById('groupForm').reset();
    document.getElementById('gf_member_search').value = '';
    await renderMemberCheckboxes([]);
    document.getElementById('groupModalTitle').innerHTML = '<i class="fas fa-users me-2"></i> إضافة مجموعة جديدة';
    new bootstrap.Modal(document.getElementById('groupModal')).show();
}

async function openEditModal(id) {
    document.getElementById('groupModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i> تعديل المجموعة';
    new bootstrap.Modal(document.getElementById('groupModal')).show();

    const r = await apiFetch('/chat-groups/' + id);
    if (!r.success) return;
    const g = r.data;

    document.getElementById('groupId').value = g.id;
    document.getElementById('gf_name').value = g.name ?? '';
    document.getElementById('gf_description').value = g.description ?? '';
    document.getElementById('gf_member_search').value = '';

    const memberIds = (g.employees || []).map(e => e.id);
    await renderMemberCheckboxes(memberIds);
}

async function saveGroup() {
    const id = document.getElementById('groupId').value;
    const memberCheckboxes = document.querySelectorAll('#gf_members_container .member-check:checked');
    const memberIds = [...memberCheckboxes].map(cb => Number(cb.value));

    if (!document.getElementById('gf_name').value) {
        showAlert('اكتب اسم المجموعة', 'warning');
        return;
    }
    if (!memberIds.length) {
        showAlert('اختر عضو واحد على الأقل', 'warning');
        return;
    }

    const data = {
        name: document.getElementById('gf_name').value,
        description: document.getElementById('gf_description').value || null,
        member_ids: memberIds,
    };

    const url = id ? '/chat-groups/' + id : '/chat-groups';
    const r = await apiFetch(url, { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) });
    if (r.success) {
        bootstrap.Modal.getInstance(document.getElementById('groupModal')).hide();
        showAlert(id ? 'تم تحديث المجموعة' : 'تم إنشاء المجموعة');
        loadGroups(groupPage);
    } else {
        showAlert(r.message || 'فشل الحفظ', 'danger');
    }
}

async function viewGroup(id) {
    const modal = new bootstrap.Modal(document.getElementById('groupViewModal'));
    document.getElementById('groupViewBody').innerHTML = '<div class="text-center py-4"><div class="spinner mx-auto"></div></div>';
    modal.show();

    const r = await apiFetch('/chat-groups/' + id);
    if (!r.success) return;
    const g = r.data;

    document.getElementById('groupViewBody').innerHTML = `
        <div class="mb-3">
            <h5 class="fw-bold text-primary">${escapeHtml(g.name)}</h5>
            ${g.description ? `<p class="text-muted">${escapeHtml(g.description)}</p>` : ''}
            <p class="mb-1"><strong>المنشئ:</strong> ${g.creator?.name ?? '-'}</p>
            <p class="mb-1"><strong>عدد الرسائل:</strong> ${g.messages_count ?? 0}</p>
            <p class="mb-1"><strong>الحالة:</strong> ${g.status === 'active' ? 'نشط' : 'مؤرشفة'}</p>
        </div>
        <h6 class="fw-bold mb-2"><i class="fas fa-users me-1"></i> الأعضاء (${(g.employees || []).length})</h6>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>الاسم</th><th>الكود</th><th>الوظيفة</th><th>الدور</th></tr></thead>
                <tbody>
                    ${(g.employees || []).map(e => `
                        <tr>
                            <td>${escapeHtml(e.name)}</td>
                            <td>${escapeHtml(e.employee_code ?? '-')}</td>
                            <td>${escapeHtml(e.position ?? '-')}</td>
                            <td><span class="badge-status ${e.id === (g.creator?.id) ? 'badge-active' : 'badge-approved'}">${e.id === (g.creator?.id) ? 'منشئ' : 'عضو'}</span></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function confirmDelete(id, name) {
    groupDeleteId = id;
    document.getElementById('groupDeleteName').textContent = name;
    new bootstrap.Modal(document.getElementById('groupDeleteModal')).show();
}

document.getElementById('groupDeleteBtn').addEventListener('click', async () => {
    if (!groupDeleteId) return;
    const r = await apiFetch('/chat-groups/' + groupDeleteId, { method: 'DELETE' });
    bootstrap.Modal.getInstance(document.getElementById('groupDeleteModal')).hide();
    if (r.success) {
        showAlert('تم أرشفة المجموعة');
        loadGroups(groupPage);
    } else {
        showAlert(r.message || 'فشل الأرشفة', 'danger');
    }
    groupDeleteId = null;
});

function resetFilters() {
    document.getElementById('groupSearch').value = '';
    document.getElementById('groupStatus').value = '';
    loadGroups();
}

document.getElementById('groupSearch').addEventListener('keypress', e => { if (e.key === 'Enter') loadGroups(); });

document.addEventListener('DOMContentLoaded', async () => {
    await loadLookups();
    loadGroups();
    const searchInput = document.getElementById('gf_member_search');
    if (searchInput) {
        searchInput.addEventListener('input', filterMemberCheckboxes);
        searchInput.addEventListener('keypress', e => { if (e.key === 'Enter') filterMemberCheckboxes(); });
    }
});
</script>
@endpush
