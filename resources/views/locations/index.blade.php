@extends('layouts.app')
@section('title', 'مواقع العمل')
@section('page-title', 'مواقع العمل')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIINfQmxp8q6sS25N8FJCOjS0g8daWJ0lbI="
      crossorigin="">
<style>
    #locationMap {
        height: 260px;
        width: 100%;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        overflow: hidden;
    }
    .leaflet-container {
        font-family: inherit;
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1><i class="fas fa-map-marker-alt me-2 text-primary"></i> مواقع العمل</h1>
        <div class="breadcrumb">إدارة مواقع تسجيل الحضور</div>
    </div>
    <button class="btn-primary-custom" onclick="openAddModal()">
        <i class="fas fa-plus me-1"></i> إضافة موقع
    </button>
</div>

<!-- STATS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="stat-value" id="statTotal">-</div><div class="stat-label">إجمالي المواقع</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="stat-value text-success" id="statActive">-</div><div class="stat-label">نشط</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="stat-value text-muted" id="statInactive">-</div><div class="stat-label">غير نشط</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="stat-value text-info" id="statAvgRadius">-</div><div class="stat-label">متوسط النطاق (م)</div></div></div>
</div>

<!-- TABLE -->
<div class="section-card">
    <div class="section-header">
        <i class="fas fa-list-ul text-primary"></i>
        <h5 class="section-title">قائمة المواقع</h5>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم الموقع</th>
                    <th>العنوان</th>
                    <th>خط العرض</th>
                    <th>خط الطول</th>
                    <th>النطاق (م)</th>
                    <th>الحالة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody id="locationsTable">
                <tr><td colspan="8" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD / EDIT MODAL -->
<div class="modal fade" id="locationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="locModalTitle"><i class="fas fa-map-marker-alt me-2"></i> إضافة موقع</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="locationForm">
                    <input type="hidden" id="locId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">اسم الموقع *</label>
                            <input type="text" id="lf_name" class="form-control" required placeholder="مثال: المقر الرئيسي">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">العنوان</label>
                            <input type="text" id="lf_address" class="form-control" placeholder="العنوان التفصيلي">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">خط العرض (Latitude) *</label>
                            <input type="number" id="lf_latitude" class="form-control" required step="any" placeholder="30.0444">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">خط الطول (Longitude) *</label>
                            <input type="number" id="lf_longitude" class="form-control" required step="any" placeholder="31.2357">
                        </div>
                        <div class="col-12">
                            <div class="d-flex gap-2 flex-wrap mb-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="getCurrentLocation()">
                                    <i class="fas fa-location-arrow me-1"></i> موقعي الحالي
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="useMapCenter()">
                                    <i class="fas fa-crosshairs me-1"></i> استخدم مركز الخريطة
                                </button>
                                <span class="text-muted align-self-center" id="locationMapStatus" style="font-size:.85rem">اضغط على الخريطة أو اسحب العلامة لتحديد الموقع</span>
                            </div>
                            <div id="locationMap"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">نطاق التحقق (بالمتر) *</label>
                            <div class="input-group">
                                <input type="number" id="lf_radius" class="form-control" required min="10" max="10000" value="200" placeholder="200">
                                <span class="input-group-text">متر</span>
                            </div>
                            <small class="text-muted">الموظف لازم يكون في هذا النطاق عشان يسجل حضور</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الحالة</label>
                            <select id="lf_is_active" class="form-select">
                                <option value="1">نشط</option>
                                <option value="0">غير نشط</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">ملاحظات</label>
                            <textarea id="lf_notes" class="form-control" rows="2" placeholder="أي ملاحظات إضافية..."></textarea>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info d-flex align-items-center gap-2 mb-0" style="font-size:.85rem">
                                <i class="fas fa-info-circle"></i>
                                <span>الخريطة مجانية من OpenStreetMap. يمكن تحديد الموقع بالضغط على الخريطة أو زر موقعي الحالي.</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom" onclick="saveLocation()"><i class="fas fa-save me-1"></i> حفظ</button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="locDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>تأكيد الحذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <p>هل تريد حذف الموقع<br><strong id="locDeleteName"></strong>؟</p>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="locDeleteBtn"><i class="fas fa-trash me-1"></i>حذف</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>
<script>
let locDeleteId = null;
let locationMap = null, locationMarker = null, locationCircle = null;
const defaultMapPosition = [30.0444, 31.2357];

async function loadLocations() {
    document.getElementById('locationsTable').innerHTML =
        '<tr><td colspan="8" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr>';
    const r = await apiFetch('/work-locations');
    if (!r.success) return;
    const data = r.data;
    document.getElementById('statTotal').textContent   = data.length;
    document.getElementById('statActive').textContent  = data.filter(l => l.is_active).length;
    document.getElementById('statInactive').textContent = data.filter(l => !l.is_active).length;
    const avgRadius = data.length ? Math.round(data.reduce((s, l) => s + l.radius_meters, 0) / data.length) : 0;
    document.getElementById('statAvgRadius').textContent = avgRadius;

    if (!data.length) {
        document.getElementById('locationsTable').innerHTML =
            '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-map-marker-alt fa-2x d-block mb-2"></i>لا توجد مواقع مضافة</td></tr>';
        return;
    }
    document.getElementById('locationsTable').innerHTML = data.map(l => `
        <tr>
            <td>${l.id}</td>
            <td><strong>${l.name}</strong></td>
            <td>${l.address ?? '<span class="text-muted">-</span>'}</td>
            <td><code>${Number(l.latitude).toFixed(6)}</code></td>
            <td><code>${Number(l.longitude).toFixed(6)}</code></td>
            <td><span class="badge bg-info text-dark">${l.radius_meters} م</span></td>
            <td><span class="badge-status ${l.is_active ? 'badge-active' : 'badge-inactive'}">${l.is_active ? 'نشط' : 'غير نشط'}</span></td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-warning" onclick="openEditModal(${l.id})" title="تعديل"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger"  onclick="confirmDelete(${l.id},'${l.name.replace(/'/g,"\\'")}')"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`).join('');
}

function openAddModal() {
    document.getElementById('locId').value = '';
    document.getElementById('locationForm').reset();
    document.getElementById('lf_is_active').value = '1';
    document.getElementById('lf_radius').value = '200';
    document.getElementById('locModalTitle').innerHTML = '<i class="fas fa-map-marker-alt me-2"></i> إضافة موقع جديد';
    new bootstrap.Modal(document.getElementById('locationModal')).show();
    setTimeout(() => {
        initLocationMap();
        updateMapFromInputs(false);
    }, 250);
}

async function openEditModal(id) {
    document.getElementById('locModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i> تعديل الموقع';
    new bootstrap.Modal(document.getElementById('locationModal')).show();
    const r = await apiFetch('/work-locations/' + id);
    if (!r.success) { showAlert('فشل تحميل البيانات', 'danger'); return; }
    const l = r.data;
    document.getElementById('locId').value         = l.id;
    document.getElementById('lf_name').value       = l.name ?? '';
    document.getElementById('lf_address').value    = l.address ?? '';
    document.getElementById('lf_latitude').value   = l.latitude ?? '';
    document.getElementById('lf_longitude').value  = l.longitude ?? '';
    document.getElementById('lf_radius').value     = l.radius_meters ?? 200;
    document.getElementById('lf_is_active').value  = l.is_active ? '1' : '0';
    document.getElementById('lf_notes').value      = l.notes ?? '';
    setTimeout(() => {
        initLocationMap();
        updateMapFromInputs(true);
    }, 250);
}

function initLocationMap() {
    if (!window.L) {
        setMapStatus('تعذر تحميل الخريطة، تأكد من الاتصال بالإنترنت', true);
        return;
    }

    if (!locationMap) {
        locationMap = L.map('locationMap', { scrollWheelZoom: false }).setView(defaultMapPosition, 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(locationMap);

        locationMarker = L.marker(defaultMapPosition, { draggable: true }).addTo(locationMap);
        locationCircle = L.circle(defaultMapPosition, {
            radius: getRadiusValue(),
            color: '#2563eb',
            fillColor: '#3b82f6',
            fillOpacity: 0.12,
            weight: 2,
        }).addTo(locationMap);

        locationMap.on('click', event => setLocationCoordinates(event.latlng.lat, event.latlng.lng, true));
        locationMarker.on('dragend', event => {
            const latlng = event.target.getLatLng();
            setLocationCoordinates(latlng.lat, latlng.lng, false);
        });
    }

    locationMap.invalidateSize();
}

function setLocationCoordinates(lat, lng, moveMap = true) {
    const latitude = Number(lat);
    const longitude = Number(lng);
    document.getElementById('lf_latitude').value = latitude.toFixed(8);
    document.getElementById('lf_longitude').value = longitude.toFixed(8);
    updateMapPosition(latitude, longitude, moveMap);
    setMapStatus('تم تحديد الموقع');
}

function updateMapPosition(lat, lng, moveMap = false) {
    if (!locationMap || !locationMarker || !locationCircle) return;
    const point = [lat, lng];
    locationMarker.setLatLng(point);
    locationCircle.setLatLng(point);
    locationCircle.setRadius(getRadiusValue());
    if (moveMap) locationMap.setView(point, Math.max(locationMap.getZoom(), 15));
}

function updateMapFromInputs(moveMap = false) {
    initLocationMap();
    const lat = parseFloat(document.getElementById('lf_latitude').value);
    const lng = parseFloat(document.getElementById('lf_longitude').value);
    if (Number.isFinite(lat) && Number.isFinite(lng)) {
        updateMapPosition(lat, lng, moveMap);
        return;
    }
    updateMapPosition(defaultMapPosition[0], defaultMapPosition[1], true);
}

function getRadiusValue() {
    const radius = parseInt(document.getElementById('lf_radius').value || '200', 10);
    return Number.isFinite(radius) && radius > 0 ? radius : 200;
}

function useMapCenter() {
    if (!locationMap) initLocationMap();
    if (!locationMap) return;
    const center = locationMap.getCenter();
    setLocationCoordinates(center.lat, center.lng, false);
}

function getCurrentLocation() {
    if (!navigator.geolocation) {
        setMapStatus('المتصفح لا يدعم تحديد الموقع الحالي', true);
        return;
    }

    setMapStatus('جاري تحديد موقعك الحالي...');
    navigator.geolocation.getCurrentPosition(
        position => {
            setLocationCoordinates(position.coords.latitude, position.coords.longitude, true);
            setMapStatus(`تم تحديد موقعك بدقة تقريبية ${Math.round(position.coords.accuracy)} متر`);
        },
        error => {
            const messages = {
                1: 'تم رفض صلاحية الوصول للموقع من المتصفح',
                2: 'تعذر الوصول للموقع الحالي',
                3: 'انتهت مهلة تحديد الموقع',
            };
            setMapStatus(messages[error.code] || 'تعذر تحديد الموقع الحالي', true);
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}

function setMapStatus(message, isError = false) {
    const status = document.getElementById('locationMapStatus');
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('text-danger', isError);
    status.classList.toggle('text-muted', !isError);
}

async function saveLocation() {
    const id = document.getElementById('locId').value;
    const data = {
        name:          document.getElementById('lf_name').value,
        address:       document.getElementById('lf_address').value || null,
        latitude:      parseFloat(document.getElementById('lf_latitude').value),
        longitude:     parseFloat(document.getElementById('lf_longitude').value),
        radius_meters: parseInt(document.getElementById('lf_radius').value),
        is_active:     document.getElementById('lf_is_active').value === '1',
        notes:         document.getElementById('lf_notes').value || null,
    };
    const r = await apiFetch(id ? `/work-locations/${id}` : '/work-locations', {
        method: id ? 'PUT' : 'POST',
        body: JSON.stringify(data),
    });
    if (r.success) {
        bootstrap.Modal.getInstance(document.getElementById('locationModal')).hide();
        showAlert(id ? 'تم تحديث الموقع' : 'تم إضافة الموقع بنجاح');
        loadLocations();
    } else {
        const msgs = r.errors ? Object.values(r.errors).flat().join('<br>') : (r.message || 'فشل الحفظ');
        showAlert(msgs, 'danger');
    }
}

function confirmDelete(id, name) {
    locDeleteId = id;
    document.getElementById('locDeleteName').textContent = name;
    new bootstrap.Modal(document.getElementById('locDeleteModal')).show();
}
document.getElementById('locDeleteBtn').addEventListener('click', async () => {
    if (!locDeleteId) return;
    const r = await apiFetch(`/work-locations/${locDeleteId}`, { method: 'DELETE' });
    bootstrap.Modal.getInstance(document.getElementById('locDeleteModal')).hide();
    if (r.success) { showAlert('تم حذف الموقع'); loadLocations(); }
    else showAlert(r.message || 'فشل الحذف', 'danger');
    locDeleteId = null;
});

['lf_latitude', 'lf_longitude'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => updateMapFromInputs(false));
});
document.getElementById('lf_radius').addEventListener('input', () => updateMapFromInputs(false));
document.getElementById('locationModal').addEventListener('shown.bs.modal', () => {
    initLocationMap();
    updateMapFromInputs(false);
});

document.addEventListener('DOMContentLoaded', loadLocations);
</script>
@endpush
