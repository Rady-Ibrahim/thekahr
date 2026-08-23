@extends('layouts.app')
@section('title', 'الحضور والانصراف')
@section('page-title', 'الحضور والانصراف')

@section('content')
<div class="page-header">
    <div>
        <h1><i class="fas fa-fingerprint me-2 text-primary"></i> الحضور والانصراف</h1>
        <div class="breadcrumb">تتبع حضور الموظفين</div>
    </div>
    <div class="d-flex gap-2">
        <a href="/shifts" class="btn btn-outline-primary"><i class="fas fa-clock me-1"></i> إدارة الورديات</a>
        <button class="btn btn-outline-primary" onclick="openLeaveModal()"><i class="fas fa-calendar-plus me-1"></i> طلب إجازة</button>
        <button class="btn-primary-custom" onclick="openAddAttModal()"><i class="fas fa-plus me-1"></i> إدخال حضور</button>
    </div>
</div>

<!-- TODAY SUMMARY -->
<div class="row g-3 mb-4" id="todaySummary">
    <div class="col-6 col-md-3"><div class="stat-card text-center" style="cursor:pointer" onclick="filterByStatus('present')" title="عرض الحاضرين"><div class="stat-icon mx-auto" style="background:#e8f5e9;color:#2e7d32"><i class="fas fa-user-check"></i></div><div class="stat-value" id="attPresent">-</div><div class="stat-label">حاضر</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center" style="cursor:pointer" onclick="filterByStatus('absent')" title="عرض الغائبين"><div class="stat-icon mx-auto" style="background:#fce4ec;color:#c62828"><i class="fas fa-user-times"></i></div><div class="stat-value" id="attAbsent">-</div><div class="stat-label">غائب</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center" style="cursor:pointer" onclick="filterByStatus('late')" title="عرض المتأخرين"><div class="stat-icon mx-auto" style="background:#fff3e0;color:#e65100"><i class="fas fa-clock"></i></div><div class="stat-value" id="attLate">-</div><div class="stat-label">متأخر</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center" style="cursor:pointer" onclick="filterByStatus('on_leave')" title="عرض في إجازة"><div class="stat-icon mx-auto" style="background:#e3f2fd;color:#1565c0"><i class="fas fa-umbrella-beach"></i></div><div class="stat-value" id="attLeave">-</div><div class="stat-label">إجازة</div></div></div>
</div>

<!-- DEDUCTION POLICY (DYNAMIC) -->
<div class="section-card mb-4">
    <div class="section-body">
        <div class="row g-3 align-items-center">
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon mb-0" style="width:46px;height:46px;background:#fff3e0;color:#e65100"><i class="fas fa-business-time"></i></div>
                    <div>
                        <div class="fw-bold text-primary">نظام خصم التأخير</div>
                        <div class="text-muted" style="font-size:.82rem">حسب وردية الموظف - يتم تطبيقه عند حساب المرتب</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label mb-1">الوردية النشطة</label>
                <select id="activeShiftSelect" class="form-select form-select-sm" onchange="loadShiftInfo()">
                    <option value="">تحميل...</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1">بداية العمل</label>
                <input type="time" id="shiftStartDisplay" class="form-control" value="{{ config('hr.working_hours.check_in_time', '08:00') }}" disabled>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1">سماح التأخير</label>
                <div class="input-group">
                    <input type="number" id="shiftGraceDisplay" class="form-control" value="{{ config('hr.working_hours.late_threshold_minutes', 15) }}" disabled>
                    <span class="input-group-text">دقيقة</span>
                </div>
            </div>
            <div class="col-6 col-md-1">
                <div class="badge-status badge-pending d-inline-flex align-items-center gap-2 mt-4">
                    <i class="fas fa-coins"></i>
                    ينخصم من المرتب
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CUSTOM FLEXIBLE ATTENDANCE (نظام الحضور المخصص بالساعات) -->
<div class="section-card mb-4" id="customPunchCard" style="display:none">
    <div class="section-header">
        <i class="fas fa-stopwatch text-info"></i>
        <h5 class="section-title">البصمة المرنة — الحضور بالساعات</h5>
        <div class="ms-auto d-flex align-items-center gap-2">
            <select id="customEmpSelect" class="form-select form-select-sm" style="min-width:200px" onchange="loadCustomSummary()"></select>
            <button class="btn btn-success btn-sm" id="btnCustomCheckIn" onclick="customPunch('in')"><i class="fas fa-fingerprint me-1"></i> حضور</button>
            <button class="btn btn-danger btn-sm" id="btnCustomCheckOut" onclick="customPunch('out')"><i class="fas fa-sign-out-alt me-1"></i> انصراف</button>
        </div>
    </div>
    <div class="section-body">
        <div class="row g-3 align-items-center mb-3">
            <div class="col-6 col-md-3">
                <div class="stat-card text-center py-2">
                    <div class="stat-value" id="custWorked">-</div>
                    <div class="stat-label">ساعات اليوم</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card text-center py-2">
                    <div class="stat-value text-primary" id="custRequired">-</div>
                    <div class="stat-label">المطلوب يومياً</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card text-center py-2">
                    <div class="stat-value text-warning" id="custRemaining">-</div>
                    <div class="stat-label">المتبقي (دقيقة)</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card text-center py-2">
                    <div class="stat-value" id="custOpenTimer" style="font-variant-numeric:tabular-nums">--:--:--</div>
                    <div class="stat-label" id="custStatusLabel">الجلسة الحالية</div>
                </div>
            </div>
        </div>
        <div class="progress mb-3" style="height:10px">
            <div class="progress-bar bg-info" id="custProgressBar" role="progressbar" style="width:0%"></div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size:.85rem">
                <thead class="table-light"><tr><th>#</th><th>حضور</th><th>انصراف</th><th>المدة</th><th>الحالة</th><th></th></tr></thead>
                <tbody id="customSessionsTable"><tr><td colspan="6" class="text-center text-muted py-3">لا توجد جلسات اليوم</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- FILTERS -->
<div class="section-card mb-4">
    <div class="section-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">الموظف</label>
                <input type="text" id="empSearch" class="form-control" placeholder="بحث باسم الموظف">
            </div>
            <div class="col-md-2">
                <label class="form-label">الحالة</label>
                <select id="attStatus" class="form-select">
                    <option value="">الكل</option>
                    <option value="present">حاضر</option>
                    <option value="absent">غائب</option>
                    <option value="late">متأخر</option>
                    <option value="on_leave">إجازة</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">الوردية</label>
                <select id="shiftFilter" class="form-select">
                    <option value="">الكل</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">التاريخ من</label>
                <input type="date" id="dateFrom" class="form-control" value="{{ date('Y-m-01') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">إلى</label>
                <input type="date" id="dateTo" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-1">
                <button class="btn-primary-custom w-100" onclick="applyFilters()"><i class="fas fa-search me-1"></i> بحث</button>
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary w-100" onclick="resetAttFilters()"><i class="fas fa-undo"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- LEAVE REQUESTS -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><button class="nav-link active" onclick="showTab('attendance', this)"><i class="fas fa-list me-1"></i> سجل الحضور</button></li>
    <li class="nav-item"><button class="nav-link" onclick="showTab('leaves', this)"><i class="fas fa-calendar-times me-1"></i> طلبات الإجازات</button></li>
    <li class="nav-item"><button class="nav-link" onclick="showTab('early', this)"><i class="fas fa-sign-out-alt me-1"></i> طلبات الانصراف المبكر</button></li>
</ul>

<div id="tab-attendance">
    <div class="section-card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>التاريخ</th>
                        <th>الوردية</th>
                        <th>الحضور</th>
                        <th>الانصراف</th>
                        <th>تأخير</th>
                        <th>انصراف مبكر</th>
                        <th>الخصم</th>
                        <th>ساعات العمل</th>
                        <th>الساعات المرنة</th>
                        <th>الحالة</th>
                        <th>الموقع</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody id="attTable">
                    <tr><td colspan="13" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="section-body d-flex justify-content-between">
            <div id="attPagInfo" class="text-muted" style="font-size:.8rem"></div>
            <div id="attPagination"></div>
        </div>
    </div>
</div>

<div id="tab-leaves" style="display:none">
    <div class="section-card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>الموظف</th><th>نوع الإجازة</th><th>من</th><th>إلى</th><th>السبب</th><th>الحالة</th><th>إجراءات</th></tr>
                </thead>
                <tbody id="leavesTable">
                    <tr><td colspan="7" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="section-body d-flex justify-content-between">
            <div id="leavesPagInfo" class="text-muted" style="font-size:.8rem"></div>
            <div id="leavesPagination"></div>
        </div>
    </div>
</div>

<div id="tab-early" style="display:none">
    <div class="section-card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>الموظف</th><th>نوع الطلب</th><th>من</th><th>إلى</th><th>السبب</th><th>الحالة</th><th>إجراءات</th></tr>
                </thead>
                <tbody id="earlyTable">
                    <tr><td colspan="7" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="section-body d-flex justify-content-between">
            <div id="earlyPagInfo" class="text-muted" style="font-size:.8rem"></div>
            <div id="earlyPagination"></div>
        </div>
    </div>
</div>

<!-- ═══ ADD ATTENDANCE MODAL ═══ -->
<div class="modal fade" id="attAddModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="attAddTitle"><i class="fas fa-fingerprint me-2"></i> إدخال حضور</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="attForm">
                    <input type="hidden" id="attId">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">الموظف *</label><select name="employee_id" id="atf_emp" class="form-select" data-lookup="employees" data-placeholder="اختر الموظف" required></select></div>
                        <div class="col-md-6"><label class="form-label">التاريخ *</label><input type="date" name="date" id="atf_date" class="form-control" required value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}"></div>
                        <div class="col-md-6"><label class="form-label">الحالة *</label>
                            <select name="status" id="atf_status" class="form-select" required>
                                <option value="present">حاضر</option><option value="absent">غائب</option>
                                <option value="late">متأخر</option><option value="on_leave">إجازة</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">الوردية</label>
                            <select name="shift_id" id="atf_shift" class="form-select">
                                <option value="">الوردية الافتراضية</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">وقت الحضور</label><input type="time" name="check_in_time" id="atf_in" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">وقت الانصراف</label><input type="time" name="check_out_time" id="atf_out" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">دقائق التأخير</label><input type="number" name="late_minutes" id="atf_late" class="form-control" min="0" placeholder="تلقائي من وقت الحضور"></div>
                        <div class="col-md-6"><label class="form-label">نوع الخصم المتوقع</label><input type="text" id="atf_deduction_preview" class="form-control" value="لا يوجد خصم" disabled></div>
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0" style="font-size:.82rem" id="shiftInfoAlert">
                                بداية العمل {{ config('hr.working_hours.check_in_time', '08:00') }}، سماح {{ config('hr.working_hours.late_threshold_minutes', 15) }} دقيقة.
                            </div>
                        </div>
                        <div class="col-12"><label class="form-label">ملاحظات</label><textarea name="notes" id="atf_notes" class="form-control" rows="2"></textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom" onclick="saveAttendance()"><i class="fas fa-save me-1"></i> حفظ</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ ADD LEAVE MODAL ═══ -->
<div class="modal fade" id="leaveAddModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i> طلب إجازة</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="leaveForm">
                    <div class="row g-3">
                        <div class="col-12" id="lf_emp_wrap"><label class="form-label">الموظف *</label><select name="employee_id" id="lf_emp" class="form-select" data-lookup="employees" data-placeholder="اختر الموظف"></select></div>
                        <div class="col-md-6"><label class="form-label">نوع الإجازة *</label>
                            <select name="request_type" id="lf_type" class="form-select" required>
                                <option value="sick">مرضية</option><option value="leave">إجازة</option>
                                <option value="late">تأخير</option><option value="early">انصراف مبكر</option><option value="excuse">عذر</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">من تاريخ *</label><input type="date" name="from_date" id="lf_start" class="form-control" required value="{{ date('Y-m-d') }}"></div>
                        <div class="col-md-6"><label class="form-label">إلى تاريخ *</label><input type="date" name="to_date" id="lf_end" class="form-control" required value="{{ date('Y-m-d') }}"></div>
                        <div class="col-12"><label class="form-label">السبب *</label><textarea name="reason" id="lf_reason" class="form-control" rows="2" required></textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="saveLeave()"><i class="fas fa-save me-1"></i> إرسال الطلب</button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="attDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm"><div class="modal-content">
        <div class="modal-header border-0"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>تأكيد الحذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body text-center"><i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i><p>حذف سجل الحضور هذا؟</p></div>
        <div class="modal-footer border-0 justify-content-center">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
            <button type="button" class="btn btn-danger" id="attDeleteBtn">حذف</button>
        </div>
    </div></div>
</div>

<!-- ═══ SESSIONS BREAKDOWN MODAL ═══ -->
<div class="modal fade" id="sessionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i> جلسات اليوم — <span id="sessionsEmpName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="sessionsAttId">
                <div class="row g-2 mb-3" id="sessionsTotals"></div>
                <table class="data-table">
                    <thead><tr><th>#</th><th>حضور</th><th>انصراف</th><th>المدة</th><th>المصدر</th><th>ملاحظات</th><th>إجراءات</th></tr></thead>
                    <tbody id="sessionsTableBody"></tbody>
                </table>
                <hr>
                <div class="row g-2 align-items-end" id="addSessionForm">
                    <div class="col-md-4"><label class="form-label">حضور</label><input type="time" id="ns_in" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">انصراف</label><input type="time" id="ns_out" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">ملاحظات</label><input type="text" id="ns_notes" class="form-control"></div>
                    <div class="col-md-1"><button class="btn-primary-custom w-100" onclick="addSession()"><i class="fas fa-plus"></i></button></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const attBadge = { present:'badge-active', absent:'badge-rejected', late:'badge-pending', on_leave:'badge-approved' };
const attLabel = { present:'حاضر', absent:'غائب', late:'متأخر', early_leave:'انصراف مبكر', on_leave:'إجازة', excused:'معذور' };
const hoursStatusLabels = { fulfilled:'استكمل الساعات', shortfall:'نقص ساعات', overtime:'ساعات إضافية' };
const hoursStatusBadges = { fulfilled:'badge-active', shortfall:'badge-rejected', overtime:'badge-approved' };
function hoursStatusLabel(s) { return s ? (hoursStatusLabels[s] || s) : '-'; }
function hoursStatusBadge(s) { return s ? (hoursStatusBadges[s] || 'badge-draft') : 'badge-draft'; }

// ═══ CUSTOM FLEXIBLE ATTENDANCE WIDGET ══════════════════
let customTimerInterval = null;
let customEmployees = [];

async function initCustomAttendance() {
    // Load active employees into the picker; auto-detect current user's employee.
    const r = await apiFetch('/employees?per_page=1000&status=active');
    if (!r.success) return;
    customEmployees = r.data?.data ?? [];

    let meId = null;
    try {
        const me = await apiFetch('/auth/me');
        if (me.success) meId = me.data?.employee?.id ?? null;
    } catch (e) {}

    const sel = document.getElementById('customEmpSelect');
    const customOnly = customEmployees.filter(e => e.is_custom_attendance);
    if (!customOnly.length) { document.getElementById('customPunchCard').style.display = 'none'; return; }

    document.getElementById('customPunchCard').style.display = '';
    sel.innerHTML = '<option value="">اختر الموظف</option>' + customOnly.map(e =>
        `<option value="${e.id}">${e.name} (${e.employee_code})</option>`).join('');

    const preselect = meId && customOnly.some(e => e.id === meId) ? meId : customOnly[0].id;
    sel.value = String(preselect);
    await loadCustomSummary();
}

function fmtMinutes(mins) {
    mins = Math.max(0, Math.round(mins));
    return `${Math.floor(mins / 60)}:${String(mins % 60).padStart(2,'0')}`;
}

async function loadCustomSummary() {
    const empId = document.getElementById('customEmpSelect').value;
    if (!empId) return;
    stopCustomTimer();
    document.getElementById('custOpenTimer').textContent = '--:--:--';
    document.getElementById('custStatusLabel').textContent = 'الجلسة الحالية';

    const r = await apiFetch('/attendance/custom/today?employee_id=' + empId);
    if (!r.success) { showAlert(r.message || 'فشل تحميل الملخص', 'danger'); return; }
    renderCustomSummary(r.data);
}

function renderCustomSummary(d) {
    const workedH = Number(d.total_worked_hours ?? 0).toFixed(2);
    document.getElementById('custWorked').textContent = `${workedH} س`;
    document.getElementById('custRequired').textContent = `${Number(d.daily_required_hours ?? 0).toFixed(2)} س`;
    document.getElementById('custRemaining').textContent = d.remaining_minutes ?? 0;

    const pct = d.daily_required_hours > 0 ? Math.min(100, (Number(d.total_worked_hours ?? 0) / Number(d.daily_required_hours)) * 100) : 0;
    document.getElementById('custProgressBar').style.width = pct.toFixed(1) + '%';
    document.getElementById('custProgressBar').className = 'progress-bar ' +
        (pct >= 100 ? 'bg-success' : pct >= 50 ? 'bg-info' : 'bg-warning');

    const statusEl = document.getElementById('custStatusLabel');
    statusEl.innerHTML = d.hours_status === 'fulfilled' ? '<span class="text-success">تم استيفاء الساعات ✓</span>'
        : d.hours_status === 'overtime' ? `<span class="text-success">إضافي ${fmtMinutes(d.overtime_minutes ?? 0)}</span>`
        : d.hours_status === 'shortfall' ? `<span class="text-danger">نقص ${fmtMinutes(d.remaining_minutes ?? 0)}${d.shortfall_deduction_amount > 0 ? ` (خصم ~${Number(d.shortfall_deduction_amount).toLocaleString()})` : ''}</span>`
        : 'لم يسجل بعد';

    // Open session → live timer
    stopCustomTimer();
    if (d.open_session) {
        let elapsed = d.elapsed_open_session_minutes ?? 0;
        const tickStart = Date.now();
        const render = () => {
            const totalSec = Math.floor(elapsed * 60) + Math.floor((Date.now() - tickStart) / 1000);
            const h = String(Math.floor(totalSec / 3600)).padStart(2,'0');
            const m = String(Math.floor((totalSec % 3600) / 60)).padStart(2,'0');
            const s = String(totalSec % 60).padStart(2,'0');
            document.getElementById('custOpenTimer').textContent = `${h}:${m}:${s}`;
            document.getElementById('custStatusLabel').innerHTML = '<span class="text-info"><i class="fas fa-circle-notch fa-spin"></i> جلسة جارية…</span>';
        };
        render();
        customTimerInterval = setInterval(render, 1000);
        document.getElementById('btnCustomCheckIn').disabled = true;
        document.getElementById('btnCustomCheckOut').disabled = false;
    } else {
        document.getElementById('btnCustomCheckIn').disabled = false;
        document.getElementById('btnCustomCheckOut').disabled = true;
    }

    // Sessions table
    const rows = d.sessions ?? [];
    document.getElementById('customSessionsTable').innerHTML = rows.length ? rows.map((s, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${s.check_in_time ?? '-'}</td>
            <td>${s.check_out_time ?? '-'}</td>
            <td class="fw-bold">${s.duration_minutes ? fmtMinutes(s.duration_minutes) : (s.is_open ? '<span class="text-info">جارية</span>' : '-')}</td>
            <td><small>${{ mobile:'تطبيق', admin:'إدارة', manual:'يدوي' }[s.source] ?? s.source ?? '-'}</small></td>
            <td><small class="text-muted">${s.notes ?? ''}</small></td>
        </tr>`).join('')
        : '<tr><td colspan="6" class="text-center text-muted py-3">لا توجد جلسات اليوم</td></tr>';
}

function stopCustomTimer() {
    if (customTimerInterval) { clearInterval(customTimerInterval); customTimerInterval = null; }
}

async function customPunch(type) {
    const empId = document.getElementById('customEmpSelect').value;
    if (!empId) { showAlert('اختر الموظف أولاً', 'warning'); return; }
    const btn = type === 'in' ? document.getElementById('btnCustomCheckIn') : document.getElementById('btnCustomCheckOut');
    btn.disabled = true;
    const r = await apiFetch(`/attendance/check-${type}`, {
        method: 'POST',
        body: JSON.stringify({ employee_id: parseInt(empId) }),
    });
    if (!r.success) {
        showAlert(r.message || 'فشل التسجيل', 'danger');
        btn.disabled = false;
        return;
    }
    showAlert(r.message);
    await loadCustomSummary();
}

// ═══ SESSIONS BREAKDOWN MODAL ═══════════════════════════
let sessionsAttId = null;

async function openSessionsModal(attId) {
    sessionsAttId = attId;
    new bootstrap.Modal(document.getElementById('sessionsModal')).show();
    await refreshSessionsModal();
}

async function refreshSessionsModal() {
    const r = await apiFetch('/attendance/' + sessionsAttId + '/sessions');
    if (!r.success) { showAlert(r.message || 'فشل التحميل', 'danger'); return; }
    const { attendance, sessions, totals } = r.data;
    document.getElementById('sessionsEmpName').textContent =
        `${attendance.employee?.name ?? '-'} — ${(attendance.attendance_date ?? '').substring(0,10)}`;

    const t = totals ?? {};
    document.getElementById('sessionsTotals').innerHTML = [
        ['المعمل به', `${Number(t.total_worked_hours ?? 0).toFixed(2)} س`, 'text-primary'],
        ['المطلوب', t.required_hours != null ? `${Number(t.required_hours).toFixed(2)} س` : '-', ''],
        ['الحالة', hoursStatusLabel(t.hours_status), t.hours_status === 'shortfall' ? 'text-danger' : t.hours_status === 'fulfilled' ? 'text-success' : ''],
        ['الخصم', t.deduction_amount > 0 ? `- ${Number(t.deduction_amount).toLocaleString()} ج.م` : '-', t.deduction_amount > 0 ? 'text-danger fw-bold' : ''],
    ].map(([label, val, cls]) => `
        <div class="col-6 col-md-3"><div class="stat-card text-center py-2">
            <div class="stat-value ${cls}" style="font-size:1.1rem">${val}</div>
            <div class="stat-label">${label}</div>
        </div></div>`).join('');

    document.getElementById('sessionsTableBody').innerHTML = sessions.length ? sessions.map((s, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${s.check_in_time ?? '-'}</td>
            <td>${s.check_out_time ?? '-'}</td>
            <td class="fw-bold">${s.duration_minutes ? fmtMinutes(s.duration_minutes) : (s.is_open ? '<span class="text-info">جارية</span>' : '-')}</td>
            <td><small>${{ mobile:'تطبيق', admin:'إدارة', manual:'يدوي' }[s.source] ?? s.source ?? '-'}</small></td>
            <td><small class="text-muted">${s.notes ?? '-'}</small></td>
            <td>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteSession(${s.id})" title="حذف الجلسة"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`).join('')
        : '<tr><td colspan="7" class="text-center text-muted py-3">لا توجد جلسات مسجلة</td></tr>';
}

async function addSession() {
    const body = {};
    if (document.getElementById('ns_in').value) body.check_in_time = document.getElementById('ns_in').value;
    if (document.getElementById('ns_out').value) body.check_out_time = document.getElementById('ns_out').value;
    if (document.getElementById('ns_notes').value) body.notes = document.getElementById('ns_notes').value;
    const r = await apiFetch(`/attendance/${sessionsAttId}/sessions`, { method: 'POST', body: JSON.stringify(body) });
    if (!r.success) { showAlert(r.message || 'فشل الإضافة', 'danger'); return; }
    ['ns_in','ns_out','ns_notes'].forEach(id => document.getElementById(id).value = '');
    showAlert('تمت إضافة الجلسة');
    await Promise.all([refreshSessionsModal(), loadCustomSummary(), loadAttendance()]);
}

async function deleteSession(logId) {
    if (!confirm('حذف هذه الجلسة وإعادة حساب الإجماليات؟')) return;
    const r = await apiFetch(`/attendance/sessions/${logId}`, { method: 'DELETE' });
    if (!r.success) { showAlert(r.message || 'فشل الحذف', 'danger'); return; }
    showAlert('تم حذف الجلسة');
    await Promise.all([refreshSessionsModal(), loadCustomSummary(), loadAttendance()]);
}

const deductionLabels = {
    minutes: 'دقائق', quarter_day: 'ربع يوم', half_day: 'نصف يوم',
    full_day: 'يوم كامل', percentage: 'نسبة مئوية', fixed_amount: 'مبلغ ثابت'
};
const workStartTime = '{{ config("hr.working_hours.check_in_time", "08:00") }}';

let ACTIVE_TAB = 'attendance';
const DEFAULT_DATE_FROM = '{{ date('Y-m-01') }}';
const DEFAULT_DATE_TO = '{{ date('Y-m-d') }}';
let dateFilterTouched = false;
const attStatusOptions = [
    { value: '', label: 'الكل' },
    { value: 'present', label: 'حاضر' },
    { value: 'absent', label: 'غائب' },
    { value: 'late', label: 'متأخر' },
    { value: 'on_leave', label: 'إجازة' },
];
const leaveStatusOptions = [
    { value: '', label: 'الكل' },
    { value: 'pending', label: 'معلق' },
    { value: 'approved', label: 'معتمد' },
    { value: 'rejected', label: 'مرفوض' },
];

function setStatusOptions(options) {
    const sel = document.getElementById('attStatus');
    sel.innerHTML = options.map(o => `<option value="${o.value}">${o.label}</option>`).join('');
}

async function loadShiftInfo() {
    const sel = document.getElementById('activeShiftSelect');
    const val = sel.value;
    if (!val) return;
    const r = await apiFetch('/shifts/' + val);
    if (!r.success) return;
    const s = r.data;
    document.getElementById('shiftStartDisplay').value = s.start_time?.substring(0,5) || workStartTime;
    document.getElementById('shiftGraceDisplay').value = s.grace_period_minutes || 0;
}

async function loadShiftSelect() {
    const r = await apiFetch('/shifts');
    if (!r.success) return;
    const all = r.data?.data ?? r.data ?? [];
    const sel = document.getElementById('activeShiftSelect');
    const sel2 = document.getElementById('atf_shift');
    const sel3 = document.getElementById('shiftFilter');
    const opts = '<option value="">اختر الوردية</option>' + all.filter(s => s.is_active).map(s =>
        `<option value="${s.id}">${s.name} (${s.start_time?.substring(0,5)} - ${s.end_time?.substring(0,5)})</option>`
    ).join('');
    sel.innerHTML = '<option value="">عرض الوردية</option>' + opts.substring(22);
    sel2.innerHTML = '<option value="">الوردية الافتراضية</option>' + opts.substring(22);
    sel3.innerHTML = '<option value="">الكل</option>' + opts.substring(22);
    if (all.length) { sel.value = all[0].id; loadShiftInfo(); }
}

async function loadTodaySummary() {
    const r = await apiFetch('/attendance/today-summary');
    if (!r.success) return;
    const d = r.data;
    document.getElementById('attPresent').textContent = d.present ?? 0;
    document.getElementById('attAbsent').textContent  = d.absent ?? 0;
    document.getElementById('attLate').textContent    = d.late ?? 0;
    document.getElementById('attLeave').textContent   = d.on_leave ?? 0;
}

async function loadAttendance(page = 1) {
    const params = new URLSearchParams({ per_page: 20, page });
    const s = document.getElementById('attStatus').value;
    const e = document.getElementById('empSearch').value;
    const f = document.getElementById('dateFrom').value;
    const t = document.getElementById('dateTo').value;
    const sh = document.getElementById('shiftFilter').value;
    if (s) params.append('status', s);
    if (e) params.append('search', e);
    if (f) params.append('date_from', f);
    if (t) params.append('date_to', t);
    if (sh) params.append('shift_id', sh);

    const r = await apiFetch('/attendance?' + params);
    if (!r.success) return;
    const data = r.data;
    document.getElementById('attPagInfo').textContent = `إجمالي: ${data.total}`;
    const all = data.data;
    if (!all.length) {
        document.getElementById('attTable').innerHTML = '<tr><td colspan="13" class="text-center py-4 text-muted">لا توجد سجلات</td></tr>';
        return;
    }
    document.getElementById('attTable').innerHTML = all.map(a => `
        <tr>
            <td>${a.employee?.name ?? '-'}</td>
            <td>${a.attendance_date ? a.attendance_date.substring(0,10) : '-'}</td>
            <td>${a.shift?.name ? `<span class="badge bg-light text-dark">${a.shift.name}</span>` : '-'}</td>
            <td>${a.check_in_time ?? '-'}</td>
            <td>${a.check_out_time ?? '-'}</td>
            <td>${lateText(a.late_minutes ?? 0, a.applied_late_deduction_type)}</td>
            <td>${a.early_exit_minutes ? earlyText(a.early_exit_minutes, a.applied_early_deduction_type) : '-'}</td>
            <td>${a.salary_deduction_amount > 0
                ? `<span class="fw-bold text-danger">-${Number(a.salary_deduction_amount).toLocaleString()} ج.م</span><br><small class="text-muted">${a.salary_deduction_label ?? ''}</small>`
                : '-'}</td>
            <td>${a.actual_worked_hours ? Number(a.actual_worked_hours).toFixed(2) : (a.working_hours ?? '-')}${(a.hours_status || a.logs_count > 0) ? `<br><small class="text-muted"><i class="fas fa-layer-group"></i> ${a.logs_count ?? 0} جلسة${a.required_hours ? ` / مطلوب ${Number(a.required_hours).toFixed(2)} س` : ''}</small>` : ''}</td>
            <td>${a.hours_status
                ? `<span class="badge-status ${hoursStatusBadge(a.hours_status)}">${hoursStatusLabel(a.hours_status)}</span><button class="btn btn-sm btn-outline-info d-block mt-1 w-100" onclick="openSessionsModal(${a.id})" title="عرض الجلسات"><i class="fas fa-list me-1"></i> الجلسات</button>`
                : '<span class="text-muted">-</span>'}</td>
            <td><span class="badge-status ${attBadge[a.status] || 'badge-draft'}">${attLabel[a.status] || a.status}</span></td>
            <td>${a.check_in_latitude ? `<span class="badge bg-info"><i class="fas fa-map-marker-alt"></i> GPS</span>` : '-'}</td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-warning" onclick="openEditAttModal(${a.id})" title="تعديل"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger"  onclick="confirmDeleteAtt(${a.id})" title="حذف"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>
    `).join('');
    const pages = [];
    for (let i = 1; i <= Math.min(data.last_page, 10); i++) {
        pages.push(`<button class="btn btn-sm ${i === data.current_page ? 'btn-primary' : 'btn-outline-primary'} mx-1" onclick="loadAttendance(${i})">${i}</button>`);
    }
    document.getElementById('attPagination').innerHTML = pages.join('');
}

const LEAVE_TYPE_LABELS = { sick:'مرضية', leave:'إجازة', late:'تأخير', early:'انصراف مبكر', excuse:'عذر' };

async function loadLeaves(page = 1, requestType = null) {
    const isEarly = requestType === 'early';
    const tableId = isEarly ? 'earlyTable' : 'leavesTable';
    const pagInfoId = isEarly ? 'earlyPagInfo' : 'leavesPagInfo';
    const paginationId = isEarly ? 'earlyPagination' : 'leavesPagination';
    const params = new URLSearchParams({ per_page: 20, page });
    const s = document.getElementById('attStatus').value;
    const e = document.getElementById('empSearch').value;
    const f = document.getElementById('dateFrom').value;
    const t = document.getElementById('dateTo').value;
    if (requestType) params.append('request_type', requestType);
    if (s) params.append('status', s);
    if (e) params.append('search', e);
    if (dateFilterTouched) {
        if (f) params.append('date_from', f);
        if (t) params.append('date_to', t);
    }

    const r = await apiFetch('/attendance/leave-requests?' + params);
    if (!r.success) return;
    const data = r.data;
    document.getElementById(pagInfoId).textContent = `إجمالي: ${data.total}`;
    const all = data.data ?? [];
    if (!all.length) {
        document.getElementById(tableId).innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">لا توجد ${isEarly ? 'طلبات انصراف مبكر' : 'طلبات إجازة'}</td></tr>`;
        return;
    }
    document.getElementById(tableId).innerHTML = all.map(l => `
        <tr>
            <td>${l.employee?.name ?? '-'}</td>
            <td>${LEAVE_TYPE_LABELS[l.request_type] ?? l.request_type ?? '-'}</td>
            <td>${l.from_date ? l.from_date.substring(0,10) : '-'}</td>
            <td>${l.to_date ? l.to_date.substring(0,10) : '-'}</td>
            <td>${l.reason ?? '-'}</td>
            <td><span class="badge-status ${l.approval_status === 'approved' ? 'badge-active' : l.approval_status === 'rejected' ? 'badge-rejected' : 'badge-pending'}">${l.approval_status === 'approved' ? 'معتمد' : l.approval_status === 'rejected' ? 'مرفوض' : 'معلق'}</span></td>
            <td>${l.approval_status === 'pending' ? `
                <button class="btn btn-sm btn-outline-success" onclick="approveLeave(${l.id})"><i class="fas fa-check"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="rejectLeave(${l.id})"><i class="fas fa-times"></i></button>
            ` : '-'}</td>
        </tr>
    `).join('');
    const pages = [];
    for (let i = 1; i <= Math.min(data.last_page, 10); i++) {
        pages.push(`<button class="btn btn-sm ${i === data.current_page ? 'btn-primary' : 'btn-outline-primary'} mx-1" onclick="loadLeaves(${i}, '${requestType ?? ''}')">${i}</button>`);
    }
    document.getElementById(paginationId).innerHTML = pages.join('');
}

async function approveLeave(id) {
    const r = await apiFetch(`/attendance/leave-requests/${id}/approve`, { method: 'POST', body: JSON.stringify({ status: 'approved' }) });
    if (r.success) { showAlert('تم اعتماد الإجازة'); reloadRequests(); }
    else showAlert(r.message, 'danger');
}

async function rejectLeave(id) {
    const reason=prompt('سبب رفض الإجازة:'); if(!reason) return;
    const r = await apiFetch(`/attendance/leave-requests/${id}/approve`, { method: 'POST', body: JSON.stringify({ status: 'rejected', notes: reason }) });
    if (r.success) { showAlert('تم رفض الإجازة','warning'); reloadRequests(); }
    else showAlert(r.message, 'danger');
}

function reloadRequests() {
    if (ACTIVE_TAB === 'early') loadLeaves(1, 'early'); else loadLeaves();
}

// ─── ADD/EDIT ATTENDANCE ────────────────────────────────
let attDeleteId=null;

function openAddAttModal() {
    document.getElementById('attId').value=''; document.getElementById('attForm').reset();
    document.getElementById('attAddTitle').innerHTML='<i class="fas fa-fingerprint me-2"></i> إدخال حضور يدوي';
    document.getElementById('atf_date').value='{{ date("Y-m-d") }}';
    document.getElementById('atf_status').value='present';
    document.getElementById('atf_late').value='';
    updateDeductionPreview();
    updateShiftAlert();
    new bootstrap.Modal(document.getElementById('attAddModal')).show();
}

async function openEditAttModal(id) {
    document.getElementById('attAddTitle').innerHTML='<i class="fas fa-edit me-2"></i> تعديل سجل الحضور';
    new bootstrap.Modal(document.getElementById('attAddModal')).show();
    const r=await apiFetch('/attendance/'+id); if(!r.success) return; const a=r.data;
    document.getElementById('attId').value=a.id;
    document.getElementById('atf_emp').value=a.employee_id;
    document.getElementById('atf_date').value=a.attendance_date?a.attendance_date.substring(0,10):'';
    document.getElementById('atf_status').value=a.status;
    document.getElementById('atf_shift').value=a.shift_id||'';
    document.getElementById('atf_in').value=timeOnly(a.check_in_time);
    document.getElementById('atf_out').value=timeOnly(a.check_out_time);
    document.getElementById('atf_late').value=a.late_minutes??0;
    document.getElementById('atf_notes').value=a.notes??'';
    updateDeductionPreview();
    updateShiftAlert();
}

async function saveAttendance() {
    const id=document.getElementById('attId').value;
    const data=Object.fromEntries(new FormData(document.getElementById('attForm')));
    data.employee_id=parseInt(data.employee_id);
    if(data.late_minutes === '') delete data.late_minutes;
    else data.late_minutes=parseInt(data.late_minutes||0);
    if(!data.check_in_time) delete data.check_in_time;
    if(!data.check_out_time) delete data.check_out_time;
    if(!data.notes) delete data.notes;
    if(!data.shift_id) delete data.shift_id;
    else data.shift_id=parseInt(data.shift_id);
    const r=await apiFetch(id?`/attendance/${id}`:'/attendance',{method:id?'PUT':'POST',body:JSON.stringify(data)});
    if(r.success){bootstrap.Modal.getInstance(document.getElementById('attAddModal')).hide();showAlert(id?'تم التحديث':'تم الإضافة');loadAttendance();}
    else showAlert(r.message||'فشل الحفظ','danger');
}

function confirmDeleteAtt(id) { attDeleteId=id; new bootstrap.Modal(document.getElementById('attDeleteModal')).show(); }
document.getElementById('attDeleteBtn').addEventListener('click', async()=>{
    if(!attDeleteId) return;
    const r=await apiFetch(`/attendance/${attDeleteId}`,{method:'DELETE'});
    bootstrap.Modal.getInstance(document.getElementById('attDeleteModal')).hide();
    if(r.success){showAlert('تم الحذف');loadAttendance();}else showAlert(r.message,'danger');
    attDeleteId=null;
});

// ─── ADD LEAVE ───────────────────────────────────────────
let CAN_MANAGE_LEAVES = false;
async function detectLeaveRole() {
    try {
        const r = await apiFetch('/auth/me');
        if (!r.success || !r.data?.user) return;
        const roles = r.data.user.roles?.map(x => x.name) ?? [];
        CAN_MANAGE_LEAVES = roles.some(n => ['super_admin','admin','hr_manager','manager','finance_manager','operations_manager','delivery_manager','warehouse_manager','approver_level_1','approver_level_2','approver_level_3'].includes(n));
    } catch (e) { CAN_MANAGE_LEAVES = false; }
    document.getElementById('lf_emp_wrap').style.display = CAN_MANAGE_LEAVES ? '' : 'none';
    if (!CAN_MANAGE_LEAVES) document.getElementById('lf_emp').value = '';
}

function openLeaveModal() {
    document.getElementById('leaveForm').reset();
    document.getElementById('lf_start').value='{{ date("Y-m-d") }}';
    document.getElementById('lf_end').value='{{ date("Y-m-d") }}';
    detectLeaveRole();
    new bootstrap.Modal(document.getElementById('leaveAddModal')).show();
}
async function saveLeave() {
    const data=Object.fromEntries(new FormData(document.getElementById('leaveForm')));
    if(data.employee_id) data.employee_id=parseInt(data.employee_id);
    else delete data.employee_id;
    const r=await apiFetch('/attendance/request-leave',{method:'POST',body:JSON.stringify(data)});
    if(r.success){bootstrap.Modal.getInstance(document.getElementById('leaveAddModal')).hide();showAlert('تم إرسال طلب الإجازة');reloadRequests();}
    else showAlert(r.message||'فشل الإرسال','danger');
}

function applyFilters() {
    if (ACTIVE_TAB === 'leaves') loadLeaves();
    else if (ACTIVE_TAB === 'early') loadLeaves(1, 'early');
    else loadAttendance();
}

function filterByStatus(status) {
    if (ACTIVE_TAB !== 'attendance') {
        const btn = document.querySelector('.nav-tabs .nav-link[onclick*="attendance"]');
        showTab('attendance', btn);
    }
    setStatusOptions(attStatusOptions);
    document.getElementById('empSearch').value = '';
    document.getElementById('attStatus').value = status;
    document.getElementById('shiftFilter').value = '';
    document.getElementById('dateFrom').value = DEFAULT_DATE_FROM;
    document.getElementById('dateTo').value = DEFAULT_DATE_TO;
    dateFilterTouched = false;
    loadTodayList(status);
}

async function loadTodayList(status) {
    const r = await apiFetch('/attendance/today-summary');
    if (!r.success) return;
    const list = r.data.lists?.[status] ?? [];
    document.getElementById('attPagInfo').textContent = `إجمالي اليوم: ${list.length}`;
    document.getElementById('attPagination').innerHTML = '';
    if (!list.length) {
        document.getElementById('attTable').innerHTML = '<tr><td colspan="13" class="text-center py-4 text-muted">لا توجد سجلات</td></tr>';
        return;
    }
    document.getElementById('attTable').innerHTML = list.map(a => `
        <tr>
            <td>${a.name ?? '-'}</td>
            <td>${'{{ date("Y-m-d") }}'}</td>
            <td>-</td>
            <td>${a.check_in_time ?? '-'}</td>
            <td>${a.check_out_time ?? '-'}</td>
            <td>${a.late_minutes != null ? `${a.late_minutes} دقيقة` : '-'}</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td><span class="text-muted">-</span></td>
            <td><span class="badge-status ${attBadge[status] || 'badge-draft'}">${attLabel[status] || status}</span></td>
            <td>-</td>
            <td>-</td>
        </tr>
    `).join('');
    document.getElementById('tab-attendance').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function showTab(tab, btn) {
    ACTIVE_TAB = tab;
    document.getElementById('tab-attendance').style.display = tab === 'attendance' ? '' : 'none';
    document.getElementById('tab-leaves').style.display    = tab === 'leaves' ? '' : 'none';
    document.getElementById('tab-early').style.display    = tab === 'early' ? '' : 'none';
    document.querySelectorAll('.nav-tabs .nav-link').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    setStatusOptions(tab === 'attendance' ? attStatusOptions : leaveStatusOptions);
    if (tab === 'attendance') loadAttendance();
    else if (tab === 'early') loadLeaves(1, 'early');
    else loadLeaves();
}

function resetAttFilters() {
    document.getElementById('empSearch').value = '';
    document.getElementById('attStatus').value = '';
    document.getElementById('shiftFilter').value = '';
    document.getElementById('dateFrom').value = DEFAULT_DATE_FROM;
    document.getElementById('dateTo').value = DEFAULT_DATE_TO;
    dateFilterTouched = false;
    setStatusOptions(ACTIVE_TAB === 'attendance' ? attStatusOptions : leaveStatusOptions);
    applyFilters();
}

function timeOnly(value) {
    if (!value) return '';
    if (value.includes('T')) return new Date(value).toTimeString().slice(0,5);
    return value.substring(0,5);
}

function minutesBetween(start, actual) {
    if (!actual) return 0;
    const [sh, sm] = start.split(':').map(Number);
    const [ah, am] = actual.split(':').map(Number);
    return Math.max(0, (ah * 60 + am) - (sh * 60 + sm));
}

function lateText(minutes, deductionType) {
    const value = Number(minutes || 0);
    if (deductionType && deductionType !== 'minutes') {
        return `${value} د - ${deductionLabels[deductionType] || deductionType}`;
    }
    return `${value} دقيقة`;
}

function earlyText(minutes, deductionType) {
    const value = Number(minutes || 0);
    if (deductionType && deductionType !== 'minutes') {
        return `${value} د - ${deductionLabels[deductionType] || deductionType}`;
    }
    return `${value} دقيقة`;
}

function updateDeductionPreview() {
    const late = Number(document.getElementById('atf_late').value || 0);
    const preview = document.getElementById('atf_deduction_preview');
    if (!preview) return;
    if (late > 0) {
        const halfDayAfterMinutes = 120;
        if (late >= halfDayAfterMinutes) {
            preview.value = 'خصم نصف يوم من المرتب';
            preview.classList.add('text-danger', 'fw-bold');
        } else if (late >= 30) {
            preview.value = 'خصم ربع يوم من المرتب';
            preview.classList.add('text-danger', 'fw-bold');
        } else {
            preview.value = `خصم ${late} دقيقة من المرتب`;
            preview.classList.remove('text-danger');
            preview.classList.add('fw-bold');
        }
    } else {
        preview.value = 'لا يوجد خصم';
        preview.classList.remove('text-danger', 'fw-bold');
    }
}

function updateLateFromTime() {
    const checkIn = document.getElementById('atf_in').value;
    if (!checkIn) return;
    const late = minutesBetween(workStartTime, checkIn);
    document.getElementById('atf_late').value = late;
    if (late > 0) document.getElementById('atf_status').value = 'late';
    updateDeductionPreview();
}

function updateShiftAlert() {
    const shiftId = document.getElementById('atf_shift').value;
    const alertEl = document.getElementById('shiftInfoAlert');
    if (shiftId) {
        apiFetch('/shifts/' + shiftId).then(r => {
            if (r.success) {
                const s = r.data;
                alertEl.innerHTML = `الوردية: ${s.name} | بداية ${s.start_time?.substring(0,5)} | نهاية ${s.end_time?.substring(0,5)} | سماح ${s.grace_period_minutes} دقيقة`;
            }
        });
    } else {
        alertEl.innerHTML = `بداية العمل ${workStartTime}، سماح {{ (int) config('hr.working_hours.late_threshold_minutes', 15) }} دقيقة.`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('atf_in').addEventListener('change', updateLateFromTime);
    document.getElementById('atf_late').addEventListener('input', updateDeductionPreview);
    document.getElementById('atf_shift').addEventListener('change', updateShiftAlert);
    document.getElementById('dateFrom').addEventListener('change', () => { dateFilterTouched = true; });
    document.getElementById('dateTo').addEventListener('change', () => { dateFilterTouched = true; });
    loadTodaySummary();
    loadAttendance();
    loadShiftSelect();
    initCustomAttendance();
});
</script>
@endpush
