@extends('layouts.app')

@section('title', 'نقاط الموظفين')
@section('page-title', 'نقاط الموظفين')

@section('content')

<style>
    .points-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,.06);
        border: 1px solid rgba(0,0,0,.04);
        overflow: hidden;
    }
    .points-header {
        padding: 18px 22px;
        border-bottom: 1px solid #f0f2f8;
        display: flex;
        align-items: center;
        gap: 12px;
        background: linear-gradient(135deg, #f8f9fe, #fff);
    }
    .badge-credit {
        background: #e8f5e9;
        color: #2e7d32;
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: .8rem;
    }
    .badge-debit {
        background: #fce4ec;
        color: #c62828;
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: .8rem;
    }
    .calc-box {
        background: #f8f9fe;
        border: 1.5px dashed #c5cae9;
        border-radius: 12px;
        padding: 12px 16px;
        text-align: center;
    }
    .calc-value {
        font-size: 1.3rem;
        font-weight: 800;
        color: #1a237e;
    }
</style>

<div class="page-header">
    <div>
        <h1><i class="fas fa-star me-2 text-warning"></i> نقاط الموظفين</h1>
        <div class="breadcrumb">إدارة إضافة وخصم النقاط وتأثيرها على الراتب الشهري</div>
    </div>
    <button class="btn-primary-custom" onclick="openAddModal()">
        <i class="fas fa-plus me-1"></i> إضافة نقاط لموظف
    </button>
</div>

<!-- STATS CARDS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#e8f5e9;color:#2e7d32">
                <i class="fas fa-plus-circle"></i>
            </div>
            <div class="stat-label">نقاط (له +)</div>
            <div class="stat-value text-success" id="statCreditPts">0</div>
            <div class="stat-change up" id="statCreditAmt">0 ج.م</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#fce4ec;color:#c62828">
                <i class="fas fa-minus-circle"></i>
            </div>
            <div class="stat-label">نقاط (عليه -)</div>
            <div class="stat-value text-danger" id="statDebitPts">0</div>
            <div class="stat-change down" id="statDebitAmt">0 ج.م</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#e8eaf6;color:#3949ab">
                <i class="fas fa-calculator"></i>
            </div>
            <div class="stat-label">صافي القيمة الكلية</div>
            <div class="stat-value" id="statNetAmt" style="font-size:1.5rem">0 ج.م</div>
            <div class="stat-label" style="font-size:.7rem">تُضاف/تُخصم تلقائياً للراتب</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#fff3e0;color:#e65100">
                <i class="fas fa-list-ol"></i>
            </div>
            <div class="stat-label">إجمالي العمليات</div>
            <div class="stat-value" id="statTotalCount">0</div>
            <div class="stat-label" style="font-size:.7rem">سجلات النقاط المسجلة</div>
        </div>
    </div>
</div>

<!-- FILTERS & TABLE CARD -->
<div class="points-card mb-4">
    <div class="points-header d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-filter text-primary"></i>
            <span class="fw-bold text-dark">فلترة وقائمة سجلات النقاط</span>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center ms-auto">
            <select id="filterEmp" class="form-select form-select-sm" style="width:160px" onchange="loadPoints()">
                <option value="">كل الموظفين</option>
            </select>

            <select id="filterType" class="form-select form-select-sm" style="width:130px" onchange="loadPoints()">
                <option value="">كل الأنواع</option>
                <option value="credit">له (+)</option>
                <option value="debit">عليه (-)</option>
            </select>

            <select id="filterMonth" class="form-select form-select-sm" style="width:110px" onchange="loadPoints()">
                <option value="">كل الشهور</option>
                @for($m=1;$m<=12;$m++)
                    <option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>شهر {{ $m }}</option>
                @endfor
            </select>

            <select id="filterYear" class="form-select form-select-sm" style="width:100px" onchange="loadPoints()">
                @for($y=date('Y')-1;$y<=date('Y')+1;$y++)
                    <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>

            <button class="btn btn-sm btn-outline-secondary" onclick="loadPoints()">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الموظف</th>
                    <th>نوع العملية</th>
                    <th>عدد النقاط</th>
                    <th>سعر النقطة</th>
                    <th>المبلغ الإجمالي</th>
                    <th>السبب</th>
                    <th>الشهر / السنة</th>
                    <th>التاريخ</th>
                    <th class="text-end">إجراءات</th>
                </tr>
            </thead>
            <tbody id="pointsTableBody">
                <tr><td colspan="10" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr>
            </tbody>
        </table>
    </div>

    <div class="p-3 d-flex justify-content-between align-items-center" id="paginationContainer"></div>
</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addPointModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="fas fa-star text-warning me-2"></i> إضافة نقاط لموظف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="addPointForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">اختر الموظف *</label>
                        <select id="pf_employee_id" class="form-select" required>
                            <option value="">-- اختر الموظف --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">نوع النقاط *</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="pf_type" id="type_credit" value="credit" checked onchange="recalcTotal()">
                                <label class="btn btn-outline-success w-100 fw-bold py-2" for="type_credit">
                                    <i class="fas fa-plus-circle me-1"></i> له (+) مكافأة
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="pf_type" id="type_debit" value="debit" onchange="recalcTotal()">
                                <label class="btn btn-outline-danger w-100 fw-bold py-2" for="type_debit">
                                    <i class="fas fa-minus-circle me-1"></i> عليه (-) خصم
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">عدد النقاط *</label>
                            <input type="number" id="pf_points" class="form-control" step="any" min="0.1" required placeholder="مثال: 5" oninput="recalcTotal()">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">سعر النقطة *</label>
                            <div class="input-group">
                                <input type="number" id="pf_price" class="form-control" step="any" min="0" required placeholder="مثال: 50" oninput="recalcTotal()">
                                <span class="input-group-text">ج.م</span>
                            </div>
                        </div>
                    </div>

                    <div class="calc-box mb-3">
                        <div class="text-muted" style="font-size:.8rem">المبلغ الإجمالي المستحق</div>
                        <div class="calc-value" id="pf_total_display">0.00 ج.م</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">الشهر</label>
                            <select id="pf_month" class="form-select">
                                @for($m=1;$m<=12;$m++)
                                    <option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>شهر {{ $m }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">السنة</label>
                            <select id="pf_year" class="form-select">
                                @for($y=date('Y')-1;$y<=date('Y')+1;$y++)
                                    <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">السبب / البيان *</label>
                        <textarea id="pf_reason" class="form-control" rows="3" required placeholder="اكتب سبب إعطاء أو خصم النقاط..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom px-4" onclick="savePointRecord()">
                    <i class="fas fa-save me-1"></i> حفظ السجل
                </button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="deletePointModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-body text-center p-4">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <h6 class="fw-bold mb-2">تأكيد حذف سجل النقاط</h6>
                <p class="text-muted" style="font-size:.85rem">هل تريد حذف هذا السجل؟ لن يؤثر على الراتب في حالة اعتماده مسبقاً.</p>
                <div class="d-flex gap-2 justify-content-center mt-3">
                    <button class="btn btn-danger btn-sm px-3" onclick="confirmDeletePoint()">نعم، احذف</button>
                    <button class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">إلغاء</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let deleteTargetPointId = null;

document.addEventListener('DOMContentLoaded', () => {
    loadEmployeesDropdown();
    loadPoints();
});

async function loadEmployeesDropdown() {
    try {
        const res = await apiFetch('/employees?per_page=1000&status=active');
        const list = res.data?.data || res.data || [];
        
        const filterSelect = document.getElementById('filterEmp');
        const formSelect = document.getElementById('pf_employee_id');

        const options = list.map(emp => 
            `<option value="${emp.id}">${escHtml(emp.name)} (${escHtml(emp.employee_code || '')})</option>`
        ).join('');

        filterSelect.innerHTML = '<option value="">كل الموظفين</option>' + options;
        formSelect.innerHTML   = '<option value="">-- اختر الموظف --</option>' + options;
    } catch (e) {
        console.error('Failed loading employees', e);
    }
}

async function loadPoints(page = 1) {
    const tableBody = document.getElementById('pointsTableBody');
    tableBody.innerHTML = '<tr><td colspan="10" class="text-center py-4"><div class="spinner mx-auto" style="width:30px;height:30px;border-width:3px"></div></td></tr>';

    const empId = document.getElementById('filterEmp').value;
    const type  = document.getElementById('filterType').value;
    const month = document.getElementById('filterMonth').value;
    const year  = document.getElementById('filterYear').value;

    let query = `?page=${page}&per_page=15`;
    if (empId) query += `&employee_id=${empId}`;
    if (type)  query += `&type=${type}`;
    if (month) query += `&month=${month}`;
    if (year)  query += `&year=${year}`;

    try {
        const res = await apiFetch(`/employee-points${query}`);
        if (!res.success) return;

        const data = res.data.data || [];
        const summary = res.summary || {};

        // Update stats
        document.getElementById('statCreditPts').textContent = summary.total_credit_points || 0;
        document.getElementById('statCreditAmt').textContent = (summary.total_credit_amount || 0).toLocaleString() + ' ج.م';
        document.getElementById('statDebitPts').textContent  = summary.total_debit_points || 0;
        document.getElementById('statDebitAmt').textContent  = (summary.total_debit_amount || 0).toLocaleString() + ' ج.م';
        
        const netAmt = summary.net_amount || 0;
        const netEl = document.getElementById('statNetAmt');
        netEl.textContent = (netAmt >= 0 ? '+' : '') + netAmt.toLocaleString() + ' ج.م';
        netEl.style.color = netAmt >= 0 ? '#2e7d32' : '#c62828';

        document.getElementById('statTotalCount').textContent = res.data.total || data.length;

        if (data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted"><i class="fas fa-star fa-2x d-block mb-2 opacity-25"></i>لا توجد سجلات نقاط مطابقة للفلتر الحالي</td></tr>';
            document.getElementById('paginationContainer').innerHTML = '';
            return;
        }

        tableBody.innerHTML = data.map((item, index) => {
            const isCredit = item.type === 'credit';
            const badgeClass = isCredit ? 'badge-credit' : 'badge-debit';
            const typeLabel  = isCredit ? 'له (+)' : 'عليه (-)';
            const amountPrefix = isCredit ? '+' : '-';

            return `
                <tr>
                    <td>${item.id}</td>
                    <td>
                        <strong>${escHtml(item.employee?.name || '—')}</strong>
                        <div class="text-muted" style="font-size:.75rem">${escHtml(item.employee?.employee_code || '')}</div>
                    </td>
                    <td><span class="${badgeClass}">${typeLabel}</span></td>
                    <td><strong>${item.points}</strong> نقطة</td>
                    <td>${item.point_price} ج.م</td>
                    <td><strong style="color:${isCredit ? '#2e7d32' : '#c62828'}">${amountPrefix}${item.total_amount} ج.م</strong></td>
                    <td style="max-width:220px;white-space:normal">${escHtml(item.reason)}</td>
                    <td>شهر ${item.month} / ${item.year}</td>
                    <td style="font-size:.8rem">${new Date(item.created_at).toLocaleDateString('ar-EG')}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-danger" onclick="openDeleteModal(${item.id})" title="حذف">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        renderPagination(res.data);
    } catch (e) {
        tableBody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-danger">حدث خطأ أثناء تحميل البيانات</td></tr>';
    }
}

function recalcTotal() {
    const pts   = parseFloat(document.getElementById('pf_points').value) || 0;
    const price = parseFloat(document.getElementById('pf_price').value) || 0;
    const total = pts * price;

    const isCredit = document.getElementById('type_credit').checked;
    const prefix   = isCredit ? '+' : '-';
    const color    = isCredit ? '#2e7d32' : '#c62828';

    const display = document.getElementById('pf_total_display');
    display.textContent = `${prefix}${total.toFixed(2)} ج.م`;
    display.style.color = color;
}

function openAddModal() {
    document.getElementById('addPointForm').reset();
    document.getElementById('type_credit').checked = true;
    recalcTotal();
    new bootstrap.Modal(document.getElementById('addPointModal')).show();
}

async function savePointRecord() {
    const empId  = document.getElementById('pf_employee_id').value;
    const pts    = document.getElementById('pf_points').value;
    const price  = document.getElementById('pf_price').value;
    const reason = document.getElementById('pf_reason').value;

    if (!empId || !pts || !price || !reason.trim()) {
        showAlert('يرجى ملء جميع الحقول المطلوبة', 'danger');
        return;
    }

    const isCredit = document.getElementById('type_credit').checked;
    const payload = {
        employee_id: parseInt(empId),
        type:        isCredit ? 'credit' : 'debit',
        points:      parseFloat(pts),
        point_price: parseFloat(price),
        reason:      reason.trim(),
        month:       parseInt(document.getElementById('pf_month').value),
        year:        parseInt(document.getElementById('pf_year').value),
    };

    try {
        const res = await apiFetch('/employee-points', {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('addPointModal')).hide();
            showAlert('تم إضافة نقاط الموظف بنجاح');
            loadPoints();
        } else {
            showAlert(res.message || 'فشل إضافة السجل', 'danger');
        }
    } catch (e) {
        showAlert('حدث خطأ أثناء الحفظ', 'danger');
    }
}

function openDeleteModal(id) {
    deleteTargetPointId = id;
    new bootstrap.Modal(document.getElementById('deletePointModal')).show();
}

async function confirmDeletePoint() {
    if (!deleteTargetPointId) return;
    try {
        const res = await apiFetch(`/employee-points/${deleteTargetPointId}`, { method: 'DELETE' });
        bootstrap.Modal.getInstance(document.getElementById('deletePointModal')).hide();
        if (res.success) {
            showAlert('تم حذف السجل بنجاح');
            loadPoints();
        } else {
            showAlert(res.message || 'فشل الحذف', 'danger');
        }
    } catch (e) {
        showAlert('حدث خطأ أثناء الحذف', 'danger');
    } finally {
        deleteTargetPointId = null;
    }
}

function renderPagination(data) {
    const container = document.getElementById('paginationContainer');
    if (!data.last_page || data.last_page <= 1) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = `
        <span class="text-muted" style="font-size:.8rem">عرض ${data.from || 0} - ${data.to || 0} من أصل ${data.total}</span>
        <div class="btn-group">
            <button class="btn btn-sm btn-outline-secondary" ${data.current_page === 1 ? 'disabled' : ''} onclick="loadPoints(${data.current_page - 1})">السابق</button>
            <button class="btn btn-sm btn-outline-secondary" ${data.current_page === data.last_page ? 'disabled' : ''} onclick="loadPoints(${data.current_page + 1})">التالي</button>
        </div>
    `;
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

@endsection
