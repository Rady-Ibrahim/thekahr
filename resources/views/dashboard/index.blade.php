@extends('layouts.app')

@section('title', 'لوحة التحكم الرئيسية')
@section('page-title', 'لوحة التحكم')

@section('content')

<div class="page-header">
    <div>
        <h1><i class="fas fa-tachometer-alt me-2 text-primary"></i> لوحة التحكم</h1>
        <div class="breadcrumb">آخر تحديث: <span id="lastUpdate">-</span></div>
    </div>
    <button class="btn-primary-custom" onclick="loadAll()"><i class="fas fa-sync-alt me-1"></i> تحديث</button>
</div>

<!-- ==================== EMPLOYEE METRICS ==================== -->
<h6 class="text-muted mb-3 fw-bold"><i class="fas fa-users me-1"></i> مؤشرات الموظفين</h6>
<div class="row g-3 mb-4" id="employeeMetrics">
    @php
    $empCards = [
        ['id'=>'totalEmployees',   'label'=>'إجمالي الموظفين',    'icon'=>'fas fa-users',         'color'=>'#e3f2fd','iconColor'=>'#1565c0'],
        ['id'=>'activeEmployees',  'label'=>'الموظفين النشطين',   'icon'=>'fas fa-user-check',    'color'=>'#e8f5e9','iconColor'=>'#2e7d32'],
        ['id'=>'presentToday',     'label'=>'حاضرون اليوم',       'icon'=>'fas fa-fingerprint',   'color'=>'#e8f5e9','iconColor'=>'#388e3c'],
        ['id'=>'lateToday',        'label'=>'متأخرون اليوم',      'icon'=>'fas fa-clock',         'color'=>'#fff3e0','iconColor'=>'#e65100'],
        ['id'=>'absentToday',      'label'=>'غائبون اليوم',       'icon'=>'fas fa-user-times',    'color'=>'#fce4ec','iconColor'=>'#c62828'],
        ['id'=>'noCheckout',       'label'=>'لم يسجلوا انصراف',   'icon'=>'fas fa-sign-out-alt',  'color'=>'#f3e5f5','iconColor'=>'#6a1b9a'],
    ];
    @endphp
    @foreach($empCards as $c)
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:{{ $c['color'] }}; color:{{ $c['iconColor'] }}"><i class="{{ $c['icon'] }}"></i></div>
            <div class="stat-value" id="{{ $c['id'] }}">-</div>
            <div class="stat-label">{{ $c['label'] }}</div>
        </div>
    </div>
    @endforeach
</div>

<!-- ==================== PAYROLL METRICS ==================== -->
<h6 class="text-muted mb-3 fw-bold"><i class="fas fa-money-bill-wave me-1"></i> مؤشرات الرواتب</h6>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#e8f5e9;color:#2e7d32"><i class="fas fa-money-check-alt"></i></div>
            <div class="stat-label mb-1">إجمالي رواتب الشهر (معتمدة)</div>
            <div class="stat-value" id="totalSalaryAmount" style="font-size:1.5rem">-</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#fff3e0;color:#e65100"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-label mb-1">رواتب بانتظار الاعتماد</div>
            <div class="stat-value" id="pendingSalaries" style="font-size:1.5rem;color:#e65100">-</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#e3f2fd;color:#1565c0"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label mb-1">رواتب مدفوعة</div>
            <div class="stat-value" id="paidSalaries" style="font-size:1.5rem">-</div>
        </div>
    </div>
</div>

<!-- ==================== CHARTS ROW ==================== -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="section-card h-100">
            <div class="section-header">
                <i class="fas fa-chart-pie text-primary"></i>
                <h5 class="section-title">توزيع حالات الموظفين</h5>
            </div>
            <div class="section-body">
                <div class="chart-container"><canvas id="employeeChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="section-card h-100">
            <div class="section-header">
                <i class="fas fa-user-clock text-primary"></i>
                <h5 class="section-title">حضور اليوم</h5>
            </div>
            <div class="section-body">
                <div class="chart-container"><canvas id="attendanceChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== NEAR-EXPIRY SALES RANKING ==================== -->
<div class="row g-3 mb-4" id="nearExpiryWidget" style="display:none">
    <div class="col-md-4">
        <div class="stat-card text-center h-100">
            <div class="stat-icon mx-auto mb-2" style="background:#fff3e0;color:#e65100"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-label mb-1">أصناف قاربة الانتهاء (30 يوم)</div>
            <div class="stat-value text-warning" id="neExpiringSoon">-</div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="section-card h-100">
            <div class="section-header d-flex align-items-center">
                <i class="fas fa-trophy text-warning"></i>
                <h5 class="section-title mb-0">أبطال مبيعات المنتجات قاربة الانتهاء - {{ date('n/Y') }}</h5>
                <a href="/near-expiry" class="ms-auto small text-decoration-none">التفاصيل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="table-responsive" style="max-height:260px;overflow-y:auto;">
                <table class="data-table">
                    <thead><tr><th>#</th><th>الموظف</th><th>وحدات</th><th>الحافز</th></tr></thead>
                    <tbody id="neLeaderboardBody">
                        <tr><td colspan="4" class="text-center py-3 text-muted">جارٍ التحميل...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let empChart, attChart;

async function loadAll() {
    document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString('ar-EG');
    await Promise.all([loadMetrics(), loadCharts(), loadNearExpiryWidget()]);
}

async function loadMetrics() {
    const r = await apiFetch('/dashboard/metrics');
    if (!r.success) return;
    const d = r.data;

    // Employee
    document.getElementById('totalEmployees').textContent  = d.employees.total;
    document.getElementById('activeEmployees').textContent = d.employees.active;
    document.getElementById('presentToday').textContent    = d.employees.present_today;
    document.getElementById('lateToday').textContent       = d.employees.late_today;
    document.getElementById('absentToday').textContent     = d.employees.absent_today;
    document.getElementById('noCheckout').textContent      = d.employees.no_checkout;

    // Payroll
    document.getElementById('totalSalaryAmount').textContent = Number(d.payroll.total_salary_amount || 0).toLocaleString('ar-EG') + ' ج.م';
    document.getElementById('pendingSalaries').textContent   = d.payroll.pending_salaries;
    document.getElementById('paidSalaries').textContent      = d.payroll.paid_salaries;

    if (d.near_expiry) {
        document.getElementById('neExpiringSoon').textContent = d.near_expiry.expiring_soon ?? 0;
    }
}

async function loadCharts() {
    const [empR, attR] = await Promise.all([
        apiFetch('/dashboard/employees-chart'),
        apiFetch('/dashboard/attendance-chart'),
    ]);
    // Employee chart
    if (empR.success) {
        const d = empR.data;
        if (empChart) empChart.destroy();
        empChart = new Chart(document.getElementById('employeeChart'), {
            type: 'doughnut',
            data: {
                labels: ['نشط', 'غير نشط', 'في إجازة', 'موقوف', 'استقالة'],
                datasets: [{ data: [d.active, d.inactive, d.on_leave, d.suspended, d.resigned], backgroundColor: ['#2e7d32','#f57f17','#1565c0','#c62828','#757575'], borderWidth: 0 }]
            },
            options: { plugins: { legend: { position: 'bottom', labels: { font: { family: 'Cairo' } } } }, cutout: '65%' }
        });
    }

    // Attendance chart
    if (attR.success) {
        const d = attR.data;
        if (attChart) attChart.destroy();
        attChart = new Chart(document.getElementById('attendanceChart'), {
            type: 'pie',
            data: {
                labels: ['حاضر', 'غائب', 'متأخر', 'إجازة'],
                datasets: [{ data: [d.present, d.absent, d.late, d.on_leave], backgroundColor: ['#2e7d32','#c62828','#f57f17','#1565c0'], borderWidth: 0 }]
            },
            options: { plugins: { legend: { position: 'bottom', labels: { font: { family: 'Cairo' } } } } }
        });
    }
}

async function loadNearExpiryWidget() {
    try {
        const res = await apiFetch('/near-expiry-sales/leaderboard?limit=5');
        if (!res.success) return;

        const rows = res.data || [];
        const widget = document.getElementById('nearExpiryWidget');
        if (!rows.length) { widget.style.display = 'none'; return; }
        widget.style.display = '';

        const medals = { 1: '🥇', 2: '🥈', 3: '🥉' };
        document.getElementById('neLeaderboardBody').innerHTML = rows.map(l => `
            <tr ${res.my_rank && res.my_rank.rank === l.rank ? 'style="background:#eef2ff"' : ''}>
                <td><span class="rank-medal">${medals[l.rank] || l.rank}</span></td>
                <td><strong>${escapeHtml(l.employee_name)}</strong>
                    <div class="text-muted" style="font-size:.72rem">${escapeHtml(l.position || '')}</div></td>
                <td>${l.total_quantity}</td>
                <td class="fw-bold text-success">${Number(l.total_incentive).toLocaleString()} ج.م</td>
            </tr>`).join('');
    } catch (e) {
        document.getElementById('nearExpiryWidget').style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', loadAll);
</script>
@endpush
