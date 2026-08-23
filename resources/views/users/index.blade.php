@extends('layouts.app')
@section('title', 'المستخدمين')
@section('page-title', 'المستخدمين')

@section('content')
<div class="page-header">
    <div>
        <h1><i class="fas fa-users-cog me-2 text-primary"></i> المستخدمين وصلاحيات التابات</h1>
        <div class="breadcrumb">إضافة مستخدمين وتحديد التابات الظاهرة لهم</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <input type="text" class="form-control" id="searchInput" placeholder="بحث..." style="width:200px" oninput="loadUsers()">
        <button class="btn-primary-custom" onclick="openCreateModal()"><i class="fas fa-plus me-1"></i> إضافة مستخدم</button>
    </div>
</div>

<div class="section-card">
    <div class="section-header">
        <i class="fas fa-list text-primary"></i>
        <h5 class="section-title">المستخدمين</h5>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>البريد</th>
                    <th>الجوال</th>
                    <th>التابات الممنوحة</th>
                    <th>الحالة</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="usersTable"><tr><td colspan="6" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr></tbody>
        </table>
    </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> مستخدم جديد</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">الاسم</label>
                    <input class="form-control" id="cName">
                </div>
                <div class="mb-3">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input class="form-control" id="cEmail" type="email">
                </div>
                <div class="mb-3">
                    <label class="form-label">الجوال</label>
                    <input class="form-control" id="cPhone">
                </div>
                <div class="mb-3">
                    <label class="form-label">كلمة المرور</label>
                    <input class="form-control" id="cPass" type="password">
                </div>
                <div class="mb-3">
                    <label class="form-label">تأكيد كلمة المرور</label>
                    <input class="form-control" id="cPassConfirm" type="password">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button class="btn-primary-custom" onclick="createUser()"><i class="fas fa-save me-1"></i> حفظ</button>
            </div>
        </div>
    </div>
</div>

<!-- Permissions Modal -->
<div class="modal fade" id="permModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-shield-alt me-2"></i> التابات الظاهرة - <span id="permUserName"></span></h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="permUserId">
                <div class="text-center mb-3">
                    <label class="form-check-label fw-bold ms-3">تحديد الكل</label>
                    <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleAll()" style="transform:scale(1.3)">
                </div>
                <div id="permsContainer"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button class="btn-primary-custom" onclick="savePerms()"><i class="fas fa-save me-1"></i> حفظ</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let sidebarSections = [];
let lastUserId = null;

async function loadUsers() {
    const s = document.getElementById('searchInput').value;
    const r = await apiFetch('/users?search=' + encodeURIComponent(s));
    if (!r.success) return;
    const users = r.data?.data ?? [];
    const tbody = document.getElementById('usersTable');
    tbody.innerHTML = users.map(u => {
        const uc = u.permissions?.length || 0;
        const st = u.is_active ? '<span class="badge badge-active">نشط</span>' : '<span class="badge badge-inactive">غير نشط</span>';
        return `<tr>
            <td><strong>${u.name}</strong>${u.employee ? '<br><small class="text-muted">' + u.employee.name + '</small>' : ''}</td>
            <td>${u.email || '-'}</td>
            <td>${u.phone || '-'}</td>
            <td>${uc > 0 ? '<span class="badge badge-primary">' + uc + ' تاب</span>' : '<span class="text-muted">بدون تابات</span>'}</td>
            <td>${st}</td>
            <td><button class="btn btn-sm btn-outline-primary" onclick="openPerms(${u.id})"><i class="fas fa-shield-alt"></i> التابات</button></td>
        </tr>`;
    }).join('');
}

function openCreateModal() {
    ['cName','cEmail','cPhone','cPass','cPassConfirm'].forEach(id => document.getElementById(id).value = '');
    lastUserId = null;
    new bootstrap.Modal(document.getElementById('createModal')).show();
}

async function createUser() {
    const name = document.getElementById('cName').value.trim();
    const email = document.getElementById('cEmail').value.trim();
    const phone = document.getElementById('cPhone').value.trim();
    const pass = document.getElementById('cPass').value;
    const pass2 = document.getElementById('cPassConfirm').value;
    if (!name || !email || !pass) { showAlert('الاسم، البريد، وكلمة المرور مطلوبة', 'danger'); return; }
    if (pass !== pass2) { showAlert('كلمة المرور غير متطابقة', 'danger'); return; }
    const r = await apiFetch('/users', {
        method: 'POST',
        body: JSON.stringify({ name, email, phone, password: pass, password_confirmation: pass2 }),
    });
    if (!r.success) { showAlert(r.message, 'danger'); return; }
    showAlert('تم إنشاء المستخدم', 'success');
    bootstrap.Modal.getInstance(document.getElementById('createModal')).hide();
    lastUserId = r.data?.id;
    loadUsers();
    setTimeout(() => { if (lastUserId) { openPerms(lastUserId); lastUserId = null; } }, 400);
}

async function openPerms(userId) {
    const [uRes, sRes] = await Promise.all([
        apiFetch('/users/' + userId),
        apiFetch('/users/sidebar-permissions'),
    ]);
    if (!uRes.success || !sRes.success) return;
    const user = uRes.data;
    sidebarSections = sRes.data;
    document.getElementById('permUserId').value = userId;
    document.getElementById('permUserName').textContent = user.name;
    const userPermNames = (user.permissions || []).map(p => p.name);
    renderPerms(userPermNames);
    new bootstrap.Modal(document.getElementById('permModal')).show();
}

function renderPerms(userPermNames) {
    const container = document.getElementById('permsContainer');
    container.innerHTML = '';
    let allChecked = true;
    sidebarSections.forEach(section => {
        const div = document.createElement('div');
        div.className = 'mb-3';
        let html = '<h6 class="text-primary border-bottom pb-1">' + section.section + '</h6><div class="row">';
        section.perms.forEach(p => {
            const checked = userPermNames.includes(p.name) ? 'checked' : '';
            if (!checked) allChecked = false;
            html += '<div class="col-md-4 col-sm-6 mb-1"><div class="form-check">' +
                '<input class="form-check-input perm-cb" type="checkbox" value="' + p.name + '" id="p_' + p.name + '" ' + checked + '>' +
                '<label class="form-check-label" for="p_' + p.name + '">' + p.label + '</label></div></div>';
        });
        html += '</div>';
        div.innerHTML = html;
        container.appendChild(div);
    });
    document.getElementById('selectAll').checked = allChecked;
}

function toggleAll() {
    const checked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = checked);
}

let allPermsMap = null;
async function getAllPermsMap() {
    if (allPermsMap) return allPermsMap;
    const r = await apiFetch('/users/all-permissions');
    if (!r.success) return {};
    const map = {};
    (r.data || []).forEach(p => map[p.name] = p.id);
    allPermsMap = map;
    return map;
}

async function savePerms() {
    const userId = document.getElementById('permUserId').value;
    const checked = document.querySelectorAll('.perm-cb:checked');
    const permNames = Array.from(checked).map(cb => cb.value);
    const map = await getAllPermsMap();
    const ids = permNames.map(n => map[n]).filter(id => id != null);

    const r = await apiFetch('/users/' + userId + '/permissions', {
        method: 'PUT',
        body: JSON.stringify({ permissions: ids }),
    });
    if (!r.success) { showAlert(r.message, 'danger'); return; }
    showAlert('تم حفظ التابات', 'success');
    bootstrap.Modal.getInstance(document.getElementById('permModal')).hide();
    loadUsers();
}

document.addEventListener('DOMContentLoaded', loadUsers);
</script>
@endpush
