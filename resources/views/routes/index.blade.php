@extends('layouts.app')
@section('title', 'خطوط السير')
@section('page-title', 'خطوط السير')

@section('content')
<div class="page-header">
    <div>
        <h1><i class="fas fa-route me-2 text-primary"></i> خطوط السير</h1>
        <div class="breadcrumb">إنشاء خطوط سير من العملاء بالترتيب وربط الطلبيات</div>
    </div>
    <button class="btn-primary-custom" onclick="openAddModal()"><i class="fas fa-plus me-1"></i> إضافة خط</button>
</div>

<div class="section-card mb-4">
    <div class="section-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label">بحث</label>
                <input type="text" id="routeSearch" class="form-control" placeholder="اسم الخط أو الكود">
            </div>
            <div class="col-md-3">
                <label class="form-label">الحالة</label>
                <select id="routeStatus" class="form-select">
                    <option value="">الكل</option>
                    <option value="active">نشط</option>
                    <option value="inactive">غير نشط</option>
                    <option value="archived">مؤرشف</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn-primary-custom w-100" onclick="loadRoutes()"><i class="fas fa-search me-1"></i> بحث</button>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" onclick="resetRouteFilters()"><i class="fas fa-undo me-1"></i> إعادة</button>
            </div>
        </div>
    </div>
</div>

<!-- TABLE -->
<div class="section-card">
    <div class="section-header">
        <i class="fas fa-route text-primary"></i>
        <h5 class="section-title">قائمة خطوط السير</h5>
        <span class="ms-auto text-muted" style="font-size:.8rem" id="routeCount"></span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم الخط</th>
                    <th>العربية</th>
                    <th>السائق</th>
                    <th>المندوب</th>
                    <th>العملاء</th>
                    <th>الحالة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody id="routeTable">
                <tr><td colspan="8" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr>
            </tbody>
        </table>
    </div>
    <div class="section-body d-flex justify-content-between">
        <div id="routePagInfo" class="text-muted" style="font-size:.8rem"></div>
        <div id="routePagination"></div>
    </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal fade" id="routeModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="routeModalTitle"><i class="fas fa-route me-2"></i> إضافة خط سير</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="routeForm">
                    <input type="hidden" id="routeId">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">اسم الخط *</label>
                            <input type="text" name="route_name" id="rf_route_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">رقم العربية</label>
                            <input type="text" name="vehicle_number" id="rf_vehicle_number" class="form-control" placeholder="مثال: س ر ب 1234">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">الحالة</label>
                            <select name="status" id="rf_status" class="form-select">
                                <option value="active">نشط</option>
                                <option value="inactive">غير نشط</option>
                                <option value="archived">مؤرشف</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">اسم السائق</label>
                            <div class="ac-container">
                                <input type="text" class="form-control" id="rf_driver_input" placeholder="ابحث عن سائق..." autocomplete="off">
                                <input type="hidden" name="driver_id" id="rf_driver_id">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">اسم المندوب</label>
                            <div class="ac-container">
                                <input type="text" class="form-control" id="rf_sales_rep_input" placeholder="ابحث عن مندوب..." autocomplete="off">
                                <input type="hidden" name="sales_rep_id" id="rf_sales_rep_id">
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold mb-1"><i class="fas fa-store me-2 text-primary"></i>العملاء بالترتيب</h6>
                            <div class="text-muted" style="font-size:.82rem">لكل عميل اختار طلبية أو أكثر، وعدد الطرود، والمبلغ أو البضاعة اختياري.</div>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm ms-auto" onclick="addStopRow()"><i class="fas fa-plus me-1"></i> إضافة عميل</button>
                    </div>

                    <div id="stopsContainer"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom" onclick="saveRoute()"><i class="fas fa-save me-1"></i> حفظ خط السير</button>
            </div>
        </div>
    </div>
</div>

<!-- VIEW STOPS MODAL -->
<div class="modal fade" id="routeViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-list-ol me-2"></i> ترتيب العملاء</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="routeViewBody">
                <div class="text-center py-4"><div class="spinner mx-auto"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="routeDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>تأكيد الحذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center"><i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i><p>حذف الخط <strong id="routeDeleteName"></strong>؟</p></div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="routeDeleteBtn">حذف</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let routeDeleteId = null;
let routePage = 1;
let stopRowCounter = 0;
let customersLookup = [];
let requestsLookup = [];
let driverAutocomplete, delegateAutocomplete;

const routeStatusLabel = { active: 'نشط', inactive: 'غير نشط', archived: 'مؤرشف' };
const routeStatusBadge = { active: 'badge-active', inactive: 'badge-inactive', archived: 'badge-draft' };

async function initRouteLookups() {
    const [customers, requests] = await Promise.all([
        apiFetch('/customers?per_page=1000&status=active'),
        apiFetch('/requests?per_page=100'),
    ]);
    customersLookup = customers.success ? (customers.data.data || []) : [];
    requestsLookup = requests.success ? (requests.data.data || []) : [];

    driverAutocomplete = createSearchableSelect(
        document.getElementById('rf_driver_input'), 'drivers'
    );
    delegateAutocomplete = createSearchableSelect(
        document.getElementById('rf_sales_rep_input'), 'delegates'
    );
}

async function loadRoutes(page = 1) {
    routePage = page;
    const params = new URLSearchParams({ per_page: 15, page });
    const search = document.getElementById('routeSearch').value;
    const status = document.getElementById('routeStatus').value;
    if (search) params.append('search', search);
    if (status) params.append('status', status);

    const r = await apiFetch('/routes?' + params);
    if (!r.success) return;

    const { data, total, current_page, last_page } = r.data;
    document.getElementById('routeCount').textContent = `إجمالي: ${total}`;
    document.getElementById('routePagInfo').textContent = `صفحة ${current_page} من ${last_page}`;

    if (!data.length) {
        document.getElementById('routeTable').innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">لا توجد خطوط سير</td></tr>';
        return;
    }

    document.getElementById('routeTable').innerHTML = data.map((route, i) => `
        <tr>
            <td>${(page - 1) * 15 + i + 1}</td>
            <td><strong>${escapeHtml(route.route_name ?? route.route_code ?? '-')}</strong><br><small class="text-muted">${escapeHtml(route.route_code ?? '')}</small></td>
            <td>${escapeHtml(route.vehicle_number ?? '-')}</td>
            <td>${escapeHtml(route.driver?.name ?? '-')}</td>
            <td>${escapeHtml(route.sales_rep?.name ?? '-')}</td>
            <td><span class="badge-status badge-approved">${route.stops_count ?? 0} عميل</span></td>
            <td><span class="badge-status ${routeStatusBadge[route.status] || 'badge-draft'}">${routeStatusLabel[route.status] || route.status}</span></td>
            <td>
                <div class="d-flex gap-1 flex-wrap">
                    <button class="btn btn-sm btn-outline-info" onclick="viewStops(${route.id})" title="العملاء"><i class="fas fa-list-ol"></i></button>
                    <button class="btn btn-sm btn-outline-success" onclick="dispatchRoute(${route.id})" title="ترحيل للتسليمات"><i class="fas fa-paper-plane"></i></button>
                    <button class="btn btn-sm btn-outline-warning" onclick="openEditModal(${route.id})" title="تعديل"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(${route.id}, '${escapeJs(route.route_name ?? route.route_code ?? '')}')" title="حذف"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>
    `).join('');

    const pages = [];
    for (let i = 1; i <= Math.min(last_page, 10); i++) {
        pages.push(`<button class="btn btn-sm ${i === current_page ? 'btn-primary' : 'btn-outline-primary'} mx-1" onclick="loadRoutes(${i})">${i}</button>`);
    }
    document.getElementById('routePagination').innerHTML = pages.join('');
}

function openAddModal() {
    document.getElementById('routeId').value = '';
    document.getElementById('routeForm').reset();
    document.getElementById('rf_status').value = 'active';
    document.getElementById('stopsContainer').innerHTML = '';
    stopRowCounter = 0;
    if (driverAutocomplete) driverAutocomplete.reset();
    if (delegateAutocomplete) delegateAutocomplete.reset();
    addStopRow();
    document.getElementById('routeModalTitle').innerHTML = '<i class="fas fa-route me-2"></i> إضافة خط سير جديد';
    new bootstrap.Modal(document.getElementById('routeModal')).show();
}

async function openEditModal(id) {
    document.getElementById('routeModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i> تعديل خط السير';
    document.getElementById('stopsContainer').innerHTML = '<div class="text-center py-3"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></div>';
    new bootstrap.Modal(document.getElementById('routeModal')).show();

    const r = await apiFetch('/routes/' + id);
    if (!r.success) return;
    const route = r.data;

    document.getElementById('routeId').value = route.id;
    document.getElementById('rf_route_name').value = route.route_name ?? '';
    document.getElementById('rf_vehicle_number').value = route.vehicle_number ?? '';
    document.getElementById('rf_status').value = route.status ?? 'active';
    if (driverAutocomplete) driverAutocomplete.setValue(route.driver_id ?? '', route.driver?.name ?? '');
    if (delegateAutocomplete) delegateAutocomplete.setValue(route.sales_rep_id ?? '', route.sales_rep?.name ?? '');
    document.getElementById('stopsContainer').innerHTML = '';
    stopRowCounter = 0;
    (route.stops || []).forEach(stop => addStopRow(stop));
    if (!(route.stops || []).length) addStopRow();
    refreshStopOrders();
}

function addStopRow(stop = null) {
    const index = stopRowCounter++;
    const div = document.createElement('div');
    div.className = 'section-card mb-3 route-stop-row';
    div.dataset.row = index;
    div.innerHTML = `
        <div class="section-body">
            <div class="d-flex align-items-center mb-3">
                <span class="badge-status badge-approved stop-order">عميل</span>
                <div class="ms-auto d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveStop(this, -1)" title="أعلى"><i class="fas fa-arrow-up"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveStop(this, 1)" title="أسفل"><i class="fas fa-arrow-down"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeStop(this)" title="حذف"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">اسم العميل *</label>
                    <div class="ac-container">
                        <input type="text" class="form-control stop-customer-input" placeholder="ابحث عن عميل..." autocomplete="off" data-customer-id="${stop?.customer_id ?? ''}">
                        <input type="hidden" class="stop-customer" name="customer_id">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">الطلبيات المرتبطة</label>
                    <select class="form-select stop-requests" multiple size="3"></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">المبلغ المفروض</label>
                    <input type="number" class="form-control stop-amount" min="0" step="0.01" value="${stop?.expected_amount ?? ''}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">صناديق</label>
                    <input type="number" class="form-control stop-boxes" min="0" value="${stop?.boxes_count ?? 0}" oninput="calcPackages(this)">
                </div>
                <div class="col-md-3">
                    <label class="form-label">كراتين</label>
                    <input type="number" class="form-control stop-cartons" min="0" value="${stop?.cartons_count ?? 0}" oninput="calcPackages(this)">
                </div>
                <div class="col-md-3">
                    <label class="form-label">أشرطة</label>
                    <input type="number" class="form-control stop-bundles" min="0" value="${stop?.bundles_count ?? 0}" oninput="calcPackages(this)">
                </div>
                <div class="col-md-3">
                    <label class="form-label">عدد الطرود</label>
                    <input type="number" class="form-control stop-packages" min="0" readonly value="${stop?.packages_count ?? 0}">
                </div>
                <div class="col-md-8">
                    <label class="form-label">البضاعة / ملاحظات اختيارية</label>
                    <input type="text" class="form-control stop-goods" value="${escapeAttr(stop?.goods_notes ?? '')}" placeholder="مثال: 3 كراتين، تبريد، مرتجع...">
                </div>
                <div class="col-md-4">
                    <label class="form-label">حالة التسليم</label>
                    <select class="form-select stop-status">
                        <option value="pending" ${!stop || stop.delivery_status === 'pending' ? 'selected' : ''}>معلق</option>
                        <option value="delivered" ${stop?.delivery_status === 'delivered' ? 'selected' : ''}>اتسلم</option>
                        <option value="not_delivered" ${stop?.delivery_status === 'not_delivered' ? 'selected' : ''}>لم تسلم</option>
                    </select>
                </div>
            </div>
        </div>
    `;
    document.getElementById('stopsContainer').appendChild(div);
    const input = div.querySelector('.stop-customer-input');
    createSearchableSelect(input, 'customers', {
        onSelect: () => renderRequestOptions(div)
    });
    if (stop?.customer_id) {
        const label = customersLookup.find(c => String(c.id) === String(stop.customer_id));
        const fullLabel = label ? `${label.name}${label.company_name ? ' - ' + label.company_name : ''}` : '';
        input.value = fullLabel;
        input.dataset.customerId = stop.customer_id;
        input.closest('.ac-container').querySelector('.stop-customer').value = stop.customer_id;
    }
    renderRequestOptions(div, stop?.request_ids || []);
    refreshStopOrders();
}

function renderRequestOptions(row, selectedIds = []) {
    const customerId = row.querySelector('.stop-customer')?.value || '';
    const select = row.querySelector('.stop-requests');
    const selected = selectedIds.map(String);
    const matching = requestsLookup.filter(req => !customerId || String(req.customer_id) === String(customerId));
    select.innerHTML = matching.map(req => {
        const isSelected = selected.includes(String(req.id)) ? 'selected' : '';
        const label = `${req.request_number ?? '#' + req.id} - ${requestStatusText(req.status)} - ${Number(req.total_amount || 0).toLocaleString()} ج.م`;
        return `<option value="${req.id}" ${isSelected}>${escapeHtml(label)}</option>`;
    }).join('');
}

function requestStatusText(status) {
    return {
        draft: 'مسودة',
        prepared: 'تم التحضير',
        under_review: 'تحت المراجعة',
        approved: 'معتمد',
        ready_for_delivery: 'جاهز',
        in_delivery: 'في الطريق',
        delivered: 'تم التسليم',
        collected: 'تم التحصيل',
        rejected: 'مرفوض'
    }[status] || status || '-';
}

function moveStop(button, direction) {
    const row = button.closest('.route-stop-row');
    const sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
    if (!sibling) return;
    if (direction < 0) row.parentNode.insertBefore(row, sibling);
    else row.parentNode.insertBefore(sibling, row);
    refreshStopOrders();
}

function removeStop(button) {
    const rows = document.querySelectorAll('.route-stop-row');
    if (rows.length === 1) {
        showAlert('يجب وجود عميل واحد على الأقل', 'warning');
        return;
    }
    button.closest('.route-stop-row').remove();
    refreshStopOrders();
}

function calcPackages(input) {
    const row = input.closest('.route-stop-row');
    const boxes = Number(row.querySelector('.stop-boxes').value || 0);
    const cartons = Number(row.querySelector('.stop-cartons').value || 0);
    const bundles = Number(row.querySelector('.stop-bundles').value || 0);
    row.querySelector('.stop-packages').value = boxes + cartons + bundles;
}

function refreshStopOrders() {
    document.querySelectorAll('.route-stop-row').forEach((row, index) => {
        row.querySelector('.stop-order').textContent = `عميل ${index + 1}`;
    });
}

async function saveRoute() {
    const id = document.getElementById('routeId').value;
    const data = {
        route_name: document.getElementById('rf_route_name').value || null,
        vehicle_number: document.getElementById('rf_vehicle_number').value || null,
        driver_id: driverAutocomplete ? Number(driverAutocomplete.getValue()) || null : null,
        sales_rep_id: delegateAutocomplete ? Number(delegateAutocomplete.getValue()) || null : null,
        status: document.getElementById('rf_status').value || 'active',
        stops: collectStops()
    };

    if (!data.route_name) {
        showAlert('اكتب اسم الخط', 'warning');
        return;
    }
    if (!data.stops.length) {
        showAlert('أضف عميل واحد على الأقل', 'warning');
        return;
    }

    const url = id ? `/routes/${id}/with-stops` : '/routes/with-stops';
    const r = await apiFetch(url, { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) });
    if (r.success) {
        bootstrap.Modal.getInstance(document.getElementById('routeModal')).hide();
        showAlert(id ? 'تم تحديث خط السير' : 'تم إضافة خط السير');
        loadRoutes(routePage);
    } else {
        showAlert(r.message || 'فشل حفظ خط السير', 'danger');
    }
}

function collectStops() {
    const stops = [];
    document.querySelectorAll('.route-stop-row').forEach(row => {
        const customerId = row.querySelector('.stop-customer').value;
        if (!customerId) return;
        const requestIds = [...row.querySelector('.stop-requests').selectedOptions].map(option => Number(option.value));
        stops.push({
            customer_id: Number(customerId),
            request_ids: requestIds,
            boxes_count: Number(row.querySelector('.stop-boxes').value || 0),
            cartons_count: Number(row.querySelector('.stop-cartons').value || 0),
            bundles_count: Number(row.querySelector('.stop-bundles').value || 0),
            packages_count: Number(row.querySelector('.stop-packages').value || 0),
            expected_amount: row.querySelector('.stop-amount').value ? Number(row.querySelector('.stop-amount').value) : null,
            goods_notes: row.querySelector('.stop-goods').value || null,
            delivery_status: row.querySelector('.stop-status').value || 'pending'
        });
    });
    return stops;
}

async function viewStops(id) {
    const modal = new bootstrap.Modal(document.getElementById('routeViewModal'));
    document.getElementById('routeViewBody').innerHTML = '<div class="text-center py-4"><div class="spinner mx-auto"></div></div>';
    modal.show();
    const r = await apiFetch('/routes/' + id);
    if (!r.success) return;
    const route = r.data;
    const stops = route.stops || [];
    if (!stops.length) {
        document.getElementById('routeViewBody').innerHTML = '<div class="text-center py-4 text-muted">لا يوجد عملاء على هذا الخط</div>';
        return;
    }
    document.getElementById('routeViewBody').innerHTML = `
        <div class="mb-3">
            <div class="fw-bold text-primary">${escapeHtml(route.route_name ?? route.route_code ?? '-')}</div>
            <div class="text-muted" style="font-size:.85rem">العربية: ${escapeHtml(route.vehicle_number ?? '-')} | السائق: ${escapeHtml(route.driver?.name ?? '-')} | المندوب: ${escapeHtml(route.sales_rep?.name ?? '-')}</div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>الترتيب</th><th>العميل</th><th>الطلبيات</th><th>صناديق</th><th>كراتين</th><th>أشرطة</th><th>الطرود</th><th>المبلغ</th><th>البضاعة</th><th>الحالة</th></tr></thead>
                <tbody>
                    ${stops.map(stop => `
                        <tr>
                            <td>${stop.stop_order}</td>
                            <td>${escapeHtml(stop.customer?.name ?? '-')}</td>
                            <td>${(stop.request_ids || []).length || '-'}</td>
                            <td>${stop.boxes_count ?? 0}</td>
                            <td>${stop.cartons_count ?? 0}</td>
                            <td>${stop.bundles_count ?? 0}</td>
                            <td>${stop.packages_count ?? 0}</td>
                            <td>${stop.expected_amount ? Number(stop.expected_amount).toLocaleString() + ' ج.م' : '-'}</td>
                            <td>${escapeHtml(stop.goods_notes ?? '-')}</td>
                            <td>${stopStatusLabel(stop.delivery_status)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function dispatchRoute(id) {
    if (!confirm('ترحيل خط السير إلى التسليمات؟')) return;
    const r = await apiFetch(`/routes/${id}/dispatch`, { method: 'POST', body: JSON.stringify({}) });
    if (r.success) {
        showAlert('تم ترحيل خط السير للتسليمات');
    } else {
        showAlert(r.message || 'فشل الترحيل', 'danger');
    }
}

function stopStatusLabel(status) {
    return { pending: 'معلق', delivered: 'اتسلم', not_delivered: 'لم تسلم' }[status] || status;
}

function confirmDelete(id, name) {
    routeDeleteId = id;
    document.getElementById('routeDeleteName').textContent = name;
    new bootstrap.Modal(document.getElementById('routeDeleteModal')).show();
}

document.getElementById('routeDeleteBtn').addEventListener('click', async () => {
    if (!routeDeleteId) return;
    const r = await apiFetch(`/routes/${routeDeleteId}`, { method: 'DELETE' });
    bootstrap.Modal.getInstance(document.getElementById('routeDeleteModal')).hide();
    if (r.success) {
        showAlert('تم الحذف');
        loadRoutes(routePage);
    } else {
        showAlert(r.message || 'فشل الحذف', 'danger');
    }
    routeDeleteId = null;
});

function resetRouteFilters() {
    document.getElementById('routeSearch').value = '';
    document.getElementById('routeStatus').value = '';
    loadRoutes();
}

function valueOrNull(id) {
    const value = document.getElementById(id).value;
    return value ? Number(value) : null;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function escapeAttr(value) {
    return escapeHtml(value).replace(/"/g, '&quot;');
}

function escapeJs(value) {
    return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, ' ');
}

document.getElementById('routeSearch').addEventListener('keypress', e => { if (e.key === 'Enter') loadRoutes(); });
document.addEventListener('DOMContentLoaded', async () => {
    await initRouteLookups();
    loadRoutes();
});
</script>
@endpush
