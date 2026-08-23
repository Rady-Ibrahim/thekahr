@extends('layouts.app')
@section('title', 'التقارير')
@section('page-title', 'التقارير')

@section('content')
<div class="page-header">
    <div>
        <h1><i class="fas fa-chart-bar me-2 text-primary"></i> التقارير</h1>
        <div class="breadcrumb">التقارير والإحصاءات الشاملة</div>
    </div>
</div>

<!-- REPORT CARDS -->
<div class="row g-3 mb-4">
    @php
    $reports = [
        ['key'=>'employees',   'title'=>'تقرير الموظفين',       'icon'=>'fas fa-users',         'color'=>'#e3f2fd','iconColor'=>'#1565c0', 'desc'=>'بيانات جميع الموظفين وحالاتهم'],
        ['key'=>'attendance',  'title'=>'تقرير الحضور',         'icon'=>'fas fa-fingerprint',   'color'=>'#e8f5e9','iconColor'=>'#2e7d32', 'desc'=>'سجل حضور وانصراف الموظفين'],
        ['key'=>'salaries',    'title'=>'تقرير الرواتب',        'icon'=>'fas fa-money-bill-wave','color'=>'#e8f5e9','iconColor'=>'#388e3c', 'desc'=>'كشف رواتب الموظفين'],
        ['key'=>'incentives',  'title'=>'تقرير الحوافز',       'icon'=>'fas fa-star',          'color'=>'#f3e5f5','iconColor'=>'#6a1b9a', 'desc'=>'الحوافز والخصومات والبدلات'],
        ['key'=>'monthly',     'title'=>'الملخص الشهري',       'icon'=>'fas fa-calendar-alt',  'color'=>'#e8eaf6','iconColor'=>'#3949ab', 'desc'=>'ملخص شامل للشهر الحالي'],
    ];
    @endphp
    @foreach($reports as $r)
    <div class="col-md-3">
        <div class="stat-card" onclick="loadReport('{{ $r['key'] }}')" style="cursor:pointer">
            <div class="stat-icon" style="background:{{ $r['color'] }}; color:{{ $r['iconColor'] }}"><i class="{{ $r['icon'] }}"></i></div>
            <div class="fw-bold mb-1">{{ $r['title'] }}</div>
            <div class="stat-label">{{ $r['desc'] }}</div>
            <div class="mt-3"><button class="btn btn-sm btn-outline-secondary">عرض التقرير <i class="fas fa-arrow-left ms-1"></i></button></div>
        </div>
    </div>
    @endforeach
</div>

<!-- FILTERS -->
<div class="section-card mb-4" id="reportFilters" style="display:none">
    <div class="section-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">الشهر</label>
                <input type="number" id="rptMonth" class="form-control" min="1" max="12" value="{{ date('n') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">السنة</label>
                <input type="number" id="rptYear" class="form-control" value="{{ date('Y') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">من تاريخ</label>
                <input type="date" id="rptFrom" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">إلى تاريخ</label>
                <input type="date" id="rptTo" class="form-control">
            </div>
            <div class="col-md-2">
                <button class="btn-primary-custom w-100" onclick="reloadReport()"><i class="fas fa-search me-1"></i> تحديث</button>
            </div>
        </div>
    </div>
</div>

<!-- REPORT CONTENT -->
<div class="section-card" id="reportContent" style="display:none">
    <div class="section-header">
        <i class="fas fa-chart-bar text-primary"></i>
        <h5 class="section-title" id="reportTitle">التقرير</h5>
        <div class="ms-auto">
            <button class="btn btn-sm btn-outline-success me-2" onclick="printReport()"><i class="fas fa-print me-1"></i> طباعة</button>
        </div>
    </div>
    <div id="reportBody" class="section-body">
        <div class="text-center py-5"><div class="spinner mx-auto"></div></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const L = {
    id:'م', employee_code:'الكود', name:'الاسم', position:'الوظيفة', department:'القسم',
    status:'الحالة', joining_date:'تاريخ التعيين', base_salary:'الراتب الأساسي',
    present_days:'أيام الحضور', absent_days:'أيام الغياب', late_count:'عدد التأخير',
    total_hours:'إجمالي الساعات', manager:'المدير',
    present:'حضور', absent:'غياب', late:'تأخير', on_leave:'إجازة',
    late_minutes:'دقائق تأخير', working_hours:'ساعات العمل',
    incentive_type:'نوع الحافز', amount:'المبلغ', reason:'السبب', date:'التاريخ',
    description:'الوصف', allowance_type:'نوع البدل',
    deduction_type:'نوع الخصم', total_amount:'الإجمالي',
    advance_date:'تاريخ السلفة', remaining_amount:'المتبقي',
    installment_amount:'القسط', remaining_installments:'الأقساط المتبقية',
    remaining:'المتبقي', installments_count:'عدد الأقساط',
    violation_type:'نوع المخالفة', fine_amount:'قيمة الغرامة',
    violation_date:'تاريخ المخالفة', violation_code:'كود المخالفة',
    vehicle_number:'رقم المركبة',
    request_number:'رقم الطلب', customer_name:'العميل', company_name:'الشركة',
    warehouse:'المستودع', items_count:'عدد الأصناف', orders_count:'عدد الطلبيات',
    total_quantity:'الكمية', payment_type:'نوع الدفع',     created_at:'تاريخ الإنشاء', created_by:'بواسطة',
    updated_at:'آخر تعديل', notes:'ملاحظات',
    paid_installments:'الأقساط المدفوعة',
    collection_number:'رقم التحصيل', collected_date:'تاريخ التحصيل',
    total:'الإجمالي', count:'العدد', total_value:'القيمة',
    by_status:'حسب الحالة', by_customer:'حسب العميل',
    by_method:'حسب طريقة الدفع', by_driver:'حسب السائق', by_department:'حسب القسم',
    by_type:'حسب النوع', by_employee:'حسب الموظف',
    driver_id:'السائق', driver:'السائق', payment_method:'طريقة الدفع',
    collection_status:'حالة التحصيل',
    total_gross:'الإجمالي الخام', total_net:'الصافي',
    total_incentives:'إجمالي الحوافز', total_allowances:'إجمالي البدلات',
    total_commissions:'إجمالي العمولات', total_points_credit:'نقاط (له)',
    total_points_debit:'نقاط (عليه)', total_deductions:'إجمالي الخصومات',
    total_advances:'إجمالي السلف', total_violations:'إجمالي المخالفات',
    gross_salary:'الراتب الإجمالي', net_salary:'صافي الراتب',
    completed_deliveries:'توصيلات مكتملة',
    top_delivery:'أفضل المندوبين', top_collection:'أفضل التحصيل', top_attendance:'أفضل الحضور',
    completed:'مكتمل', failed:'فاشل', delivered:'مكتمل',
    period:'الفترة', employees:'الموظفين', requests:'الطلبات',
    collections:'التحصيلات', salary:'الرواتب', deliveries:'التوصيلات',
    active:'نشط', paid_count:'عدد المدفوع', pending:'معلق',
    month:'الشهر', year:'السنة', points:'النقاط', point_price:'سعر النقطة',
    direction:'الاتجاه', type:'النوع',
};

let currentReport = '';
const reportTitles = {
    employees: 'تقرير الموظفين', attendance: 'تقرير الحضور',
    salaries: 'تقرير الرواتب',
    incentives: 'تقرير الحوافز', monthly: 'الملخص الشهري'
};

function t(key) { return L[key] || key.replace(/_/g,' '); }

function fmt(v) {
    if (v === null || v === undefined) return '-';
    if (typeof v === 'number') return Number(v).toLocaleString('ar-EG');
    if (typeof v === 'object') return '';
    return String(v);
}

function renderSummary(obj) {
    let html = '<div class="row g-3">';
    Object.entries(obj).forEach(([k, v]) => {
        if (typeof v === 'object' && v !== null) {
            const inner = Object.entries(v).map(([sk, sv]) =>
                `<div class="d-flex justify-content-between border-bottom py-1"><span>${t(sk)}</span><span class="fw-bold">${fmt(sv)}</span></div>`
            ).join('');
            html += `<div class="col-md-4"><div class="stat-card"><div class="stat-label fw-bold mb-2">${t(k)}</div>${inner}</div></div>`;
        } else {
            html += `<div class="col-md-3"><div class="stat-card text-center"><div class="fw-bold fs-4">${fmt(v)}</div><div class="stat-label">${t(k)}</div></div></div>`;
        }
    });
    html += '</div>';
    return html;
}

function renderTable(rows) {
    const cols = Object.keys(rows[0]);
    let html = `<div class="table-responsive"><table class="data-table"><thead><tr>${cols.map(c => `<th>${t(c)}</th>`).join('')}</tr></thead><tbody>`;
    rows.forEach(row => {
        html += `<tr>${cols.map(c => `<td>${fmt(row[c])}</td>`).join('')}</tr>`;
    });
    html += '</tbody></table></div>';
    return html;
}

async function loadReport(key) {
    currentReport = key;
    document.getElementById('reportFilters').style.display = '';
    document.getElementById('reportContent').style.display = '';
    document.getElementById('reportTitle').textContent = reportTitles[key] || key;
    document.getElementById('reportBody').innerHTML = '<div class="text-center py-5"><div class="spinner mx-auto"></div></div>';

    const month = document.getElementById('rptMonth').value;
    const year  = document.getElementById('rptYear').value;
    const from  = document.getElementById('rptFrom').value;
    const to    = document.getElementById('rptTo').value;
    const params = new URLSearchParams({ month, year });
    if (from) params.append('date_from', from);
    if (to)   params.append('date_to', to);

    let url = key === 'monthly' ? '/reports/monthly-summary' : '/reports/' + key;

    const r = await apiFetch(url + '?' + params);
    if (!r.success) { document.getElementById('reportBody').innerHTML = `<div class="alert alert-danger">${r.message}</div>`; return; }

    renderReport(key, r.data);
}

function renderReport(key, data) {
    if (!data || (Array.isArray(data) && !data.length)) {
        document.getElementById('reportBody').innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2"></i><br>لا توجد بيانات</div>';
        return;
    }

    // Performance report has nested named arrays
    if (key === 'performance') {
        let html = '';
        Object.entries(data).forEach(([section, list]) => {
            if (!Array.isArray(list) || !list.length) return;
            html += `<h6 class="fw-bold text-primary mt-3 mb-2"><i class="fas fa-trophy me-1"></i> ${t(section)}</h6>`;
            html += renderTable(list);
        });
        document.getElementById('reportBody').innerHTML = html || '<div class="text-center text-muted py-4">لا توجد بيانات أداء</div>';
        return;
    }

    // Monthly summary: nested object
    if (key === 'monthly') {
        document.getElementById('reportBody').innerHTML = renderSummary(data);
        return;
    }

    // Standard reports use data.data (pagination wrapper) or data (direct array)
    const rows = (data.data ?? data);
    if (!Array.isArray(rows) || !rows.length) {
        document.getElementById('reportBody').innerHTML = renderSummary(rows);
        return;
    }

    document.getElementById('reportBody').innerHTML = renderTable(rows);
}

function reloadReport() { if (currentReport) loadReport(currentReport); }
function printReport() { window.print(); }
</script>
@endpush
