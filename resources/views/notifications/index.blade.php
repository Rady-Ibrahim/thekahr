@extends('layouts.app')
@section('title', 'الإشعارات')
@section('page-title', 'الإشعارات')

@section('content')
<div class="page-header">
    <div>
        <h1><i class="fas fa-bell me-2 text-primary"></i> الإشعارات</h1>
        <div class="breadcrumb">إرسال وعرض الإشعارات</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn-primary-custom" onclick="openSendModal()">
            <i class="fas fa-paper-plane me-1"></i> إرسال إشعار
        </button>
        <button class="btn btn-outline-secondary" onclick="markAllRead()">
            <i class="fas fa-check-double me-1"></i> تحديد الكل كمقروء
        </button>
    </div>
</div>

<div class="section-card">
    <div class="section-header">
        <i class="fas fa-bell text-primary"></i>
        <h5 class="section-title">
            <span class="cursor-pointer" onclick="switchTab('inbox')" id="tabInboxBtn" style="border-bottom:2px solid var(--primary);padding-bottom:2px">صندوق الوارد</span>
            <span class="text-muted mx-2">|</span>
            <span class="cursor-pointer text-muted" onclick="switchTab('sent')" id="tabSentBtn">الإشعارات المرسلة</span>
        </h5>
        <span class="ms-2 badge bg-danger" id="notifCountBadge" style="display:none">0</span>
    </div>
    <div id="inboxTab">
        <div id="notifList">
            <div class="text-center py-5"><div class="spinner mx-auto"></div></div>
        </div>
        <div class="section-body d-flex justify-content-center" id="notifPagination"></div>
    </div>
    <div id="sentTab" style="display:none">
        <div id="sentNotifList">
            <div class="text-center py-5"><div class="spinner mx-auto"></div></div>
        </div>
        <div class="section-body d-flex justify-content-center" id="sentPagination"></div>
    </div>
</div>

<!-- SEND MODAL -->
<div class="modal fade" id="sendNotifModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i> إرسال إشعار للموظفين</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="sendNotifForm">
                    <div class="mb-3">
                        <label class="form-label">العنوان *</label>
                        <input type="text" id="nf_title" class="form-control" required placeholder="مثال: تنفيذ مهمة عاجلة">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الرسالة *</label>
                        <textarea id="nf_message" class="form-control" rows="4" required placeholder="نص الإشعار..."></textarea>
                    </div>
                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <label class="form-label mb-0">المستلمون *</label>
                        <div class="d-flex gap-2">
                            <input type="text" id="nf_search" class="form-control form-control-sm" placeholder="بحث..." style="width:180px" oninput="searchEmployees()">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleAllEmployees(true)">تحديد الكل</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllEmployees(false)">إلغاء</button>
                        </div>
                    </div>
                    <div id="nf_employees" class="border rounded p-2" style="max-height:260px;overflow:auto">
                        <div class="text-center text-muted py-3">جاري تحميل الموظفين...</div>
                    </div>
                    <small class="text-muted" id="nf_selectedCount">0 محدد</small>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom" onclick="sendNotification()"><i class="fas fa-paper-plane me-1"></i> إرسال</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let allEmployeesForNotif = [];
let nfSearchTimer = null;
let currentTab = 'inbox';

function switchTab(tab) {
    currentTab = tab;
    document.getElementById('inboxTab').style.display = tab === 'inbox' ? '' : 'none';
    document.getElementById('sentTab').style.display = tab === 'sent' ? '' : 'none';

    const inboxBtn = document.getElementById('tabInboxBtn');
    const sentBtn = document.getElementById('tabSentBtn');
    if (tab === 'inbox') {
        inboxBtn.style.borderBottom = '2px solid var(--primary)';
        inboxBtn.style.paddingBottom = '2px';
        inboxBtn.classList.remove('text-muted');
        sentBtn.style.borderBottom = 'none';
        sentBtn.classList.add('text-muted');
        if (!document.getElementById('notifList').querySelector('.spinner')) loadNotifications();
    } else {
        sentBtn.style.borderBottom = '2px solid var(--primary)';
        sentBtn.style.paddingBottom = '2px';
        sentBtn.classList.remove('text-muted');
        inboxBtn.style.borderBottom = 'none';
        inboxBtn.classList.add('text-muted');
        loadSentNotifications();
    }
}

async function loadNotifications(page = 1) {
    const r = await apiFetch(`/notifications?tab=inbox&per_page=20&page=${page}`);
    if (!r.success) return;
    const data = r.data;
    const unread = r.unread_count;

    const badge = document.getElementById('notifCountBadge');
    if (unread > 0) {
        badge.textContent = unread;
        badge.style.display = '';
    } else {
        badge.style.display = 'none';
    }

    const all = data.data ?? [];
    if (!all.length) {
        document.getElementById('notifList').innerHTML = '<div class="text-center py-5 text-muted"><i class="fas fa-bell-slash fa-3x mb-3"></i><br>لا توجد إشعارات</div>';
        return;
    }

    document.getElementById('notifList').innerHTML = all.map(n => `
        <div class="d-flex align-items-start p-3" style="border-right:4px solid ${!n.is_read ? '#1565c0' : 'transparent'};border-bottom:1px solid #f4f6fb;background:${!n.is_read ? '#f0f4ff' : '#fff'}">
            <div class="me-3" style="width:42px;height:42px;border-radius:12px;background:${!n.is_read ? '#e3f2fd' : '#f4f6fb'};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="fas ${!n.is_read ? 'fa-envelope' : 'fa-envelope-open'}" style="color:${!n.is_read ? '#1565c0' : '#a0aec0'}"></i>
            </div>
            <div class="flex-grow-1">
                <div class="fw-bold ${!n.is_read ? 'text-primary' : 'text-muted'}">
                    ${n.title ?? 'إشعار'}
                    ${!n.is_read ? '<span class="badge bg-warning text-dark ms-2" style="font-size:.65rem">جديد</span>' : '<span class="badge bg-secondary ms-2" style="font-size:.65rem">مقروء</span>'}
                </div>
                <div style="font-size:.875rem">${n.message || ''}</div>
                <div style="font-size:.75rem;color:#a0aec0;margin-top:4px">
                    ${n.sender ? '<span class="badge bg-primary me-1">من ' + n.sender.name + '</span>' : ''}
                    ${n.created_at ? new Date(n.created_at).toLocaleString('ar-EG') : ''}
                    ${n.read_at ? '<span class="me-2">· قرئ في ' + new Date(n.read_at).toLocaleString('ar-EG') + '</span>' : ''}
                </div>
            </div>
            <div class="d-flex gap-1 me-2">
                ${!n.is_read ? `<button class="btn btn-sm btn-outline-primary" onclick="markRead(${n.id})"><i class="fas fa-check"></i></button>` : ''}
                <button class="btn btn-sm btn-outline-danger" onclick="deleteNotif(${n.id})"><i class="fas fa-trash"></i></button>
            </div>
        </div>
    `).join('');

    const pages = [];
    for (let i = 1; i <= Math.min(data.last_page, 10); i++) {
        pages.push(`<button class="btn btn-sm ${i === data.current_page ? 'btn-primary' : 'btn-outline-primary'} mx-1" onclick="loadNotifications(${i})">${i}</button>`);
    }
    document.getElementById('notifPagination').innerHTML = pages.join('');
}

async function loadSentNotifications(page = 1) {
    const r = await apiFetch(`/notifications?tab=sent&per_page=20&page=${page}`);
    if (!r.success) return;
    const data = r.data;
    const all = data.data ?? [];

    if (!all.length) {
        document.getElementById('sentNotifList').innerHTML = '<div class="text-center py-5 text-muted"><i class="fas fa-paper-plane fa-3x mb-3"></i><br>لا توجد إشعارات مرسلة</div>';
        return;
    }

    document.getElementById('sentNotifList').innerHTML = `<table class="table table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th>المستلم</th>
                <th>العنوان</th>
                <th>الرسالة</th>
                <th>تاريخ الإرسال</th>
                <th>حالة القراءة</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        ${all.map(n => {
            const recipient = n.recipient_employee ?? {};
            const readStatus = n.is_read
                ? '<span class="badge bg-success">قُرئت</span>' + (n.read_at ? '<br><small style="font-size:.7rem">' + new Date(n.read_at).toLocaleString('ar-EG') + '</small>' : '')
                : '<span class="badge bg-danger">غير مقروءة</span>';
            return `<tr>
                <td class="align-middle">${recipient.name || '#' + n.related_id}</td>
                <td class="align-middle fw-semibold">${n.title ?? ''}</td>
                <td class="align-middle" style="max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${n.message || ''}</td>
                <td class="align-middle" style="white-space:nowrap">${n.created_at ? new Date(n.created_at).toLocaleString('ar-EG') : ''}</td>
                <td class="align-middle" style="white-space:nowrap">${readStatus}</td>
                <td class="align-middle"><button class="btn btn-sm btn-outline-danger" onclick="deleteNotif(${n.id})"><i class="fas fa-trash"></i></button></td>
            </tr>`;
        }).join('')}
        </tbody>
    </table>`;

    const pages = [];
    for (let i = 1; i <= Math.min(data.last_page, 10); i++) {
        pages.push(`<button class="btn btn-sm ${i === data.current_page ? 'btn-primary' : 'btn-outline-primary'} mx-1" onclick="loadSentNotifications(${i})">${i}</button>`);
    }
    document.getElementById('sentPagination').innerHTML = pages.join('');
}

async function openSendModal() {
    document.getElementById('sendNotifForm').reset();
    document.getElementById('nf_selectedCount').textContent = '0 محدد';
    new bootstrap.Modal(document.getElementById('sendNotifModal')).show();
    await loadEmployeesForNotif();
}

async function loadEmployeesForNotif() {
    const box = document.getElementById('nf_employees');
    box.innerHTML = '<div class="text-center text-muted py-3">جاري التحميل...</div>';
    const r = await apiFetch('/employees?per_page=1000&status=active');
    if (!r.success) {
        box.innerHTML = '<div class="text-danger p-2">فشل تحميل الموظفين</div>';
        return;
    }
    allEmployeesForNotif = r.data?.data ?? r.data ?? [];
    renderEmployeeChecks(allEmployeesForNotif);
}

function searchEmployees() {
    clearTimeout(nfSearchTimer);
    nfSearchTimer = setTimeout(async () => {
        const q = (document.getElementById('nf_search').value || '').trim();
        if (!q) { await loadEmployeesForNotif(); return; }

        const box = document.getElementById('nf_employees');
        box.innerHTML = '<div class="text-center text-muted py-3">جاري البحث...</div>';
        const r = await apiFetch('/employees?per_page=1000&status=active&search=' + encodeURIComponent(q));
        if (!r.success) { box.innerHTML = '<div class="text-danger p-2">فشل البحث</div>'; return; }
        const matches = r.data?.data ?? r.data ?? [];

        if (!matches.length) {
            box.innerHTML = '<div class="text-muted p-2">لا توجد نتائج مطابقة</div>';
            return;
        }
        renderEmployeeChecks(matches);
    }, 300);
}

function renderEmployeeChecks(list) {
    const box = document.getElementById('nf_employees');
    if (!list.length) {
        box.innerHTML = '<div class="text-muted p-2">لا يوجد موظفون</div>';
        return;
    }
    box.innerHTML = list.map(e => `
        <label class="d-flex align-items-center gap-2 py-1 px-1 emp-check-row" data-name="${escapeHtml((e.name || '').toLowerCase())}" style="cursor:pointer;border-bottom:1px solid #f0f0f0">
            <input type="checkbox" class="nf-emp-check" value="${e.id}" onchange="updateSelectedCount()">
            <span class="fw-semibold">${escapeHtml(e.name)}</span>
            <small class="text-muted ms-auto">${escapeHtml(e.employee_code ?? '')} · ${escapeHtml(e.employee_type_label || e.employee_type || '')}</small>
        </label>
    `).join('');
    updateSelectedCount();
}

function toggleAllEmployees(checked) {
    document.querySelectorAll('.emp-check-row').forEach(row => {
        if (row.style.display === 'none') return;
        const cb = row.querySelector('.nf-emp-check');
        if (cb) cb.checked = checked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const n = document.querySelectorAll('.nf-emp-check:checked').length;
    document.getElementById('nf_selectedCount').textContent = n + ' محدد';
}

async function sendNotification() {
    const title = document.getElementById('nf_title').value.trim();
    const message = document.getElementById('nf_message').value.trim();
    const employee_ids = [...document.querySelectorAll('.nf-emp-check:checked')].map(cb => parseInt(cb.value));

    if (!title || !message) { showAlert('العنوان والرسالة مطلوبان', 'danger'); return; }
    if (!employee_ids.length) { showAlert('اختر موظف واحد على الأقل', 'warning'); return; }

    const r = await apiFetch('/notifications/send', {
        method: 'POST',
        body: JSON.stringify({ title, message, employee_ids, notification_type: 'hr_direct' }),
    });

    if (r.success) {
        bootstrap.Modal.getInstance(document.getElementById('sendNotifModal')).hide();
        showAlert(r.message || 'تم الإرسال');
        if (currentTab === 'inbox') loadNotifications(); else loadSentNotifications();
    } else {
        showAlert(r.message || 'فشل الإرسال', 'danger');
    }
}

async function markRead(id) {
    await apiFetch(`/notifications/${id}/read`, { method: 'POST' });
    if (currentTab === 'inbox') loadNotifications();
}

async function markAllRead() {
    const r = await apiFetch('/notifications/read-all', { method: 'POST' });
    if (r.success) { showAlert('تم تحديد الكل كمقروء'); if (currentTab === 'inbox') loadNotifications(); }
}

async function deleteNotif(id) {
    await apiFetch(`/notifications/${id}`, { method: 'DELETE' });
    if (currentTab === 'inbox') loadNotifications();
    else loadSentNotifications();
}

document.addEventListener('DOMContentLoaded', () => loadNotifications());
</script>
@endpush
