@extends('layouts.app')

@section('title', 'الموظف المثالي')
@section('page-title', 'الموظف المثالي')

@section('content')

<style>
    .ideal-card {
        border: 1px solid #eef0f7;
        border-radius: 14px;
        padding: 16px;
        background: #fff;
        transition: box-shadow .2s;
        height: 100%;
    }
    .ideal-card.done {
        border-color: #c5e1c7;
        background: linear-gradient(135deg, #f6fdf7, #fff);
    }
    .ideal-hero {
        display: flex;
        align-items: center;
        gap: 14px;
        border-radius: 14px;
        padding: 16px 18px;
        background: linear-gradient(135deg, #fff7e6, #fff3d4);
        border: 1px solid #f3e4b8;
    }
    .ideal-hero .crown {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: linear-gradient(135deg, #f9a825, #ff8f00);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    .ideal-week-badge {
        background: #e8eaf6;
        color: #3949ab;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
    }
    .ideal-search {
        max-width: 240px;
    }
</style>

<div class="page-header">
    <div>
        <h1><i class="fas fa-trophy me-2 text-warning"></i> الموظف المثالي</h1>
        <div class="breadcrumb">اختيار الموظف المثالي أسبوعياً وشهرياً</div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <select id="idealMonth" class="form-select form-select-sm" style="width:120px">
            @for ($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" @if ($m == now()->month) selected @endif>شهر {{ $m }}</option>
            @endfor
        </select>
        <select id="idealYear" class="form-select form-select-sm" style="width:105px">
            @for ($y = now()->year - 1; $y <= now()->year + 1; $y++)
                <option value="{{ $y }}" @if ($y == now()->year) selected @endif>{{ $y }}</option>
            @endfor
        </select>
        <button class="btn-primary-custom" onclick="loadIdeal()">
            <i class="fas fa-eye me-1"></i> عرض
        </button>
    </div>
</div>

<div class="section-card">
    <div class="section-header">
        <i class="fas fa-trophy text-warning"></i>
        <h5 class="section-title">الموظف المثالي (أسبوعي / شهري)</h5>
    </div>
    <div class="section-body" id="idealBox">
        <div class="text-center py-4"><div class="spinner mx-auto" style="width:34px;height:34px;border-width:3px"></div></div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let idealSelects = {};

function escHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[char]));
}

function bindIdealSelect(inputId, key) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const sel = createSearchableSelect(input, 'employees');
    idealSelects[key] = sel;
    const initialId = input.dataset.empId;
    if (initialId) sel.setValue(initialId, input.value);
}

async function loadIdeal() {
    const box = document.getElementById('idealBox');
    const month = document.getElementById('idealMonth').value;
    const year  = document.getElementById('idealYear').value;

    idealSelects = {};
    box.innerHTML = '<div class="text-center py-4"><div class="spinner mx-auto" style="width:34px;height:34px;border-width:3px"></div></div>';

    try {
        const idealRes = await apiFetch(`/ideal?month=${month}&year=${year}`);

        if (!idealRes.success) {
            box.innerHTML = '<div class="text-danger text-center py-4">تعذر تحميل بيانات الموظف المثالي</div>';
            return;
        }

        const d = idealRes.data;
        if (!d) {
            box.innerHTML = '<div class="text-muted text-center py-4">لا توجد بيانات لهذا الشهر</div>';
            return;
        }

        const months = {
            1: 'يناير', 2: 'فبراير', 3: 'مارس', 4: 'أبريل', 5: 'مايو', 6: 'يونيو',
            7: 'يوليو', 8: 'أغسطس', 9: 'سبتمبر', 10: 'أكتوبر', 11: 'نوفمبر', 12: 'ديسمبر'
        };
        const monthName = d.month.month_name || months[d.month.month_number] || '';
        const monthEmp = d.month.ideal_employee;

        box.innerHTML = `
            <div class="ideal-hero mb-3">
                <div class="crown"><i class="fas fa-crown"></i></div>
                <div class="flex-grow-1">
                    <div class="text-muted" style="font-size:.78rem">موظف الشهر - ${escHtml(monthName)} ${d.month.year}</div>
                    <div class="fw-bold" style="font-size:1.05rem;color:#1a237e">
                        ${monthEmp ? escHtml(monthEmp.name) : 'لم يتم اختيار موظف الشهر بعد'}
                    </div>
                    ${monthEmp ? `<div class="text-muted" style="font-size:.8rem">${escHtml(monthEmp.department || '')}</div>` : ''}
                </div>
            </div>

            <div class="row g-3 mb-3 align-items-end">
                <div class="col-md-7">
                    <label class="form-label mb-1">اختيار موظف الشهر</label>
                    <input type="text" id="selMonthEmp" class="form-control" placeholder="ابحث عن موظف..." autocomplete="off"
                           value="${monthEmp ? escHtml(monthEmp.name) : ''}" ${monthEmp ? `data-emp-id="${monthEmp.id}"` : ''}>
                </div>
                <div class="col-md-5 d-flex gap-2">
                    <button class="btn-primary-custom" onclick="saveIdeal('month')">
                        <i class="fas fa-save me-1"></i> حفظ
                    </button>
                    ${monthEmp ? `<button class="btn btn-outline-danger" onclick="clearIdeal('month')">
                        <i class="fas fa-times me-1"></i> إزالة
                    </button>` : ''}
                </div>
            </div>

            <hr class="my-3">
            <h6 class="fw-bold text-muted mb-3"><i class="fas fa-calendar-week me-1"></i> موظفو الأسابيع</h6>
            <div class="row g-3" id="idealWeeksRows">${(d.weeks || []).map(w => renderIdealWeek(w)).join('')}</div>
        `;

bindIdealSelect('selMonthEmp', 'month');
        document.querySelectorAll('[data-week]').forEach(el => {
            bindIdealSelect(el.id, `week:${el.dataset.week}`);
        });
    } catch (e) {
        box.innerHTML = '<div class="text-danger text-center py-4">حدث خطأ أثناء تحميل البيانات</div>';
    }
}

function renderIdealWeek(w) {
    const emp = w.ideal_employee;
    return `
        <div class="col-md-6 col-xl-4">
            <div class="ideal-card ${emp ? 'done' : ''}">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="ideal-week-badge">الأسبوع ${w.week}</span>
                    <span class="text-muted" style="font-size:.72rem">${escHtml(w.start_date)} ← ${escHtml(w.end_date)}</span>
                </div>
                <div class="mb-2">
                    ${emp
                        ? `<strong>${escHtml(emp.name)}</strong> <span class="text-muted" style="font-size:.8rem">(${escHtml(emp.department || '')})</span>`
                        : '<span class="text-muted" style="font-size:.85rem">لم يتم الاختيار</span>'}
                </div>
                <div class="d-flex gap-2">
                    <div class="flex-grow-1">
                        <input type="text" id="selWeek${w.week}" class="form-control form-control-sm ideal-search" placeholder="ابحث عن موظف..." autocomplete="off"
                               data-week="${w.week}" value="${emp ? escHtml(emp.name) : ''}" ${emp ? `data-emp-id="${emp.id}"` : ''}>
                    </div>
                    <button class="btn btn-sm btn-primary text-nowrap" onclick="saveIdeal('week', ${w.week})" title="حفظ">
                        <i class="fas fa-save"></i>
                    </button>
                    ${emp ? `<button class="btn btn-sm btn-outline-danger text-nowrap" onclick="clearIdeal('week', ${w.week})" title="إزالة">
                        <i class="fas fa-trash"></i>
                    </button>` : ''}
                </div>
            </div>
        </div>
    `;
}

async function saveIdeal(period, week = null) {
    const key = period === 'month' ? 'month' : `week:${week}`;
    const employee_id = idealSelects[key]?.getValue() || '';
    const month = document.getElementById('idealMonth').value;
    const year  = document.getElementById('idealYear').value;

    if (!employee_id) {
        showAlert('اختر الموظف أولاً', 'danger');
        return;
    }

    const payload = { period, month, year, employee_id };
    if (period === 'week') payload.week = week;

    const res = await apiFetch('/ideal', { method: 'POST', body: JSON.stringify(payload) });
    if (!res.success) {
        showAlert(res.message || 'تعذر الحفظ', 'danger');
        return;
    }
    showAlert(res.message || 'تم الحفظ بنجاح');
    loadIdeal();
}

async function clearIdeal(period, week = null) {
    const month = document.getElementById('idealMonth').value;
    const year  = document.getElementById('idealYear').value;

    const payload = { period, month, year, employee_id: null };
    if (period === 'week') payload.week = week;

    const res = await apiFetch('/ideal', { method: 'POST', body: JSON.stringify(payload) });
    if (!res.success) {
        showAlert(res.message || 'تعذر الإزالة', 'danger');
        return;
    }
    showAlert(res.message || 'تمت الإزالة بنجاح');
    loadIdeal();
}

document.addEventListener('DOMContentLoaded', loadIdeal);
</script>
@endpush