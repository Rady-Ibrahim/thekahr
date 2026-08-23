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

<!-- ==================== OPERATIONS METRICS ==================== -->
<h6 class="text-muted mb-3 fw-bold"><i class="fas fa-cogs me-1"></i> مؤشرات التشغيل</h6>
<div class="row g-3 mb-4">
    @php
    $opCards = [
        ['id'=>'newRequests',    'label'=>'طلبات جديدة',       'icon'=>'fas fa-plus-circle',  'color'=>'#e3f2fd','iconColor'=>'#1565c0'],
        ['id'=>'preparedReqs',   'label'=>'قيد التحضير',       'icon'=>'fas fa-tools',        'color'=>'#fff8e1','iconColor'=>'#f57f17'],
        ['id'=>'underReview',    'label'=>'تحت المراجعة',      'icon'=>'fas fa-search',       'color'=>'#fff3e0','iconColor'=>'#e65100'],
        ['id'=>'approvedReqs',   'label'=>'معتمدة',            'icon'=>'fas fa-check-circle', 'color'=>'#e8f5e9','iconColor'=>'#2e7d32'],
        ['id'=>'readyDelivery',  'label'=>'جاهزة للتسليم',    'icon'=>'fas fa-box',          'color'=>'#e8eaf6','iconColor'=>'#3949ab'],
        ['id'=>'deliveredReqs',  'label'=>'تم تسليمها',        'icon'=>'fas fa-truck',        'color'=>'#e0f7fa','iconColor'=>'#00838f'],
    ];
    @endphp
    @foreach($opCards as $c)
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:{{ $c['color'] }}; color:{{ $c['iconColor'] }}"><i class="{{ $c['icon'] }}"></i></div>
            <div class="stat-value" id="{{ $c['id'] }}">-</div>
            <div class="stat-label">{{ $c['label'] }}</div>
        </div>
    </div>
    @endforeach
</div>

<!-- ==================== COLLECTIONS & APPROVALS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#e0f7fa;color:#00695c"><i class="fas fa-coins"></i></div>
            <div class="stat-label mb-1">تحصيلات اليوم</div>
            <div class="stat-value" id="collectedToday" style="font-size:1.5rem">-</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#e8f5e9;color:#2e7d32"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-label mb-1">تحصيلات الشهر</div>
            <div class="stat-value" id="collectedMonth" style="font-size:1.5rem">-</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#fff3e0;color:#e65100"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-label mb-1">موافقات معلقة</div>
            <div class="stat-value" id="pendingApprovals" style="font-size:1.5rem;color:#e65100">-</div>
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
                <i class="fas fa-chart-donut text-primary"></i>
                <h5 class="section-title">حالات الطلبات</h5>
            </div>
            <div class="section-body">
                <div class="chart-container"><canvas id="requestsChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<!-- Collections 12-month Chart -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-chart-line text-primary"></i>
                <h5 class="section-title">التحصيلات الشهرية (آخر 12 شهر)</h5>
            </div>
            <div class="section-body">
                <div style="height:260px"><canvas id="collectionsChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== ATTENDANCE & PERFORMANCE ==================== -->
<div class="row g-3">
    <div class="col-md-5">
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
    <div class="col-md-7">
        <div class="section-card h-100">
            <div class="section-header">
                <i class="fas fa-trophy text-primary"></i>
                <h5 class="section-title">أفضل المندوبين تسليماً</h5>
            </div>
            <div class="section-body p-0 table-responsive">
                <table class="data-table">
                    <thead><tr><th>#</th><th>الاسم</th><th>القسم</th><th>التسليمات</th></tr></thead>
                    <tbody id="topDriversTable">
                        <tr><td colspan="4" class="text-center py-3 text-muted">جاري التحميل...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let empChart, reqChart, collChart, attChart;

async function loadAll() {
    document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString('ar-EG');
    await Promise.all([loadMetrics(), loadCharts(), loadPerformance()]);
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

    // Operations
    document.getElementById('newRequests').textContent   = d.operations.new_requests;
    document.getElementById('preparedReqs').textContent  = d.operations.prepared_requests;
    document.getElementById('underReview').textContent   = d.operations.under_review;
    document.getElementById('approvedReqs').textContent  = d.operations.approved_requests;
    document.getElementById('readyDelivery').textContent = d.operations.ready_for_delivery;
    document.getElementById('deliveredReqs').textContent = d.operations.delivered_requests;

    // Collections
    document.getElementById('collectedToday').textContent = Number(d.collections.collected_today).toLocaleString('ar-EG') + ' ج.م';
    document.getElementById('collectedMonth').textContent = Number(d.collections.collected_month).toLocaleString('ar-EG') + ' ج.م';

    // Approvals
    document.getElementById('pendingApprovals').textContent = d.approvals.pending_approvals;
    document.getElementById('pendingCount').textContent     = d.approvals.pending_approvals;
}

async function loadCharts() {
    const [empR, reqR, colR, attR] = await Promise.all([
        apiFetch('/dashboard/employees-chart'),
        apiFetch('/dashboard/requests-chart'),
        apiFetch('/dashboard/collections-chart'),
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

    // Requests chart
    if (reqR.success) {
        const d = reqR.data;
        if (reqChart) reqChart.destroy();
        reqChart = new Chart(document.getElementById('requestsChart'), {
            type: 'doughnut',
            data: {
                labels: ['مسودة', 'تحت المراجعة', 'معتمدة', 'مسلمة', 'مرفوضة'],
                datasets: [{ data: [d.draft, d.under_review, d.approved, d.delivered, d.rejected], backgroundColor: ['#9c27b0','#f57f17','#2e7d32','#1565c0','#c62828'], borderWidth: 0 }]
            },
            options: { plugins: { legend: { position: 'bottom', labels: { font: { family: 'Cairo' } } } }, cutout: '65%' }
        });
    }

    // Collections chart
    if (colR.success) {
        const labels = Object.keys(colR.data);
        const values = Object.values(colR.data);
        if (collChart) collChart.destroy();
        collChart = new Chart(document.getElementById('collectionsChart'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{ label: 'التحصيلات', data: values, backgroundColor: 'rgba(44,62,140,.7)', borderRadius: 6 }]
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
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

async function loadPerformance() {
    const r = await apiFetch('/dashboard/performance-metrics');
    if (!r.success) return;
    const tbody = document.getElementById('topDriversTable');
    if (!r.data.length) { tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">لا توجد بيانات</td></tr>'; return; }
    tbody.innerHTML = r.data.map((d, i) => `
        <tr>
            <td><span class="fw-bold text-primary">${i+1}</span></td>
            <td>${d.name}</td>
            <td>${d.position ?? '-'}</td>
            <td><span class="badge-status badge-approved">${d.deliveries}</span></td>
        </tr>
    `).join('');
}

document.addEventListener('DOMContentLoaded', loadAll);
</script>
@endpush
