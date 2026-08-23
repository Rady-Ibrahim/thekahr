@extends('layouts.app')

@section('title', 'المنتجات قاربة الانتهاء')
@section('page-title', 'المنتجات قاربة الانتهاء')

@section('content')

<style>
    .ne-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,.05);
        box-shadow: 0 2px 12px rgba(0,0,0,.06);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: transform .15s ease;
    }
    .ne-card:hover { transform: translateY(-3px); }
    .ne-image {
        height: 150px;
        background: #f4f6fb;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    .ne-image img {
        width: 100%; height: 100%;
        object-fit: cover;
    }
    .ne-expiry-badge {
        position: absolute;
        top: 10px;
        inset-inline-start: 10px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: .72rem;
        font-weight: 700;
        backdrop-filter: blur(4px);
    }
    .ne-stock-badge {
        position: absolute;
        top: 10px;
        inset-inline-end: 10px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: .72rem;
        font-weight: 700;
        background: rgba(255,255,255,.9);
        color: #374151;
    }
    .ne-body { padding: 14px 16px; flex: 1; }
    .ne-name { font-weight: 800; font-size: 1rem; margin-bottom: 6px; }
    .ne-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: .82rem;
        color: #6b7280;
        margin-bottom: 4px;
    }
    .ne-incentive-box {
        background: linear-gradient(135deg,#fff8e1,#fffde7);
        border: 1px dashed #fbc02d;
        border-radius: 10px;
        text-align: center;
        padding: 8px;
        margin-top: 8px;
    }
    .ne-incentive-box .val { font-size: 1.15rem; font-weight: 800; color:#e65100; }
    .ne-footer {
        padding: 10px 16px;
        border-top: 1px solid #f0f2f8;
        display: flex;
        gap: 8px;
    }
    .badge-status { padding: 4px 12px; border-radius: 20px; font-size: .78rem; font-weight: 700; }
    .badge-pending   { background:#fff8e1; color:#f57f17; }
    .badge-approved  { background:#e8f5e9; color:#2e7d32; }
    .badge-rejected  { background:#fce4ec; color:#c62828; }
    .rank-medal { font-size: 1.1rem; width: 34px; display: inline-block; text-align: center; }
    .lb-row-me { background: #e8eaf6 !important; }
</style>

<div class="page-header">
    <div>
        <h1><i class="fas fa-hourglass-half me-2 text-warning"></i> المنتجات قاربة الانتهاء</h1>
        <div class="breadcrumb">كتالوج الأصناف وتسجيل المبيعات وحوافزها الشهرية للموظفين</div>
    </div>
    <button class="btn-primary-custom" onclick="openItemModal()">
        <i class="fas fa-plus me-1"></i> إضافة صنف جديد
    </button>
</div>

<!-- STATS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#e3f2fd;color:#1565c0"><i class="fas fa-boxes-stacked"></i></div>
            <div class="stat-label">أصناف مسجلة</div>
            <div class="stat-value" id="statTotalItems">-</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#fff3e0;color:#e65100"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="stat-label">تنتهي خلال 30 يوم</div>
            <div class="stat-value text-warning" id="statExpiring">-</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#e8f5e9;color:#2e7d32"><i class="fas fa-receipt"></i></div>
            <div class="stat-label">وحدات مباعة (الشهر)</div>
            <div class="stat-value" id="statMonthQty">-</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#fce4ec;color:#c62828"><i class="fas fa-coins"></i></div>
            <div class="stat-label">حوافز الشهر المعتمدة</div>
            <div class="stat-value text-success" id="statMonthIncentives">-</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- LEADERBOARD -->
    <div class="col-lg-5">
        <div class="section-card h-100">
            <div class="section-header d-flex flex-wrap gap-2 align-items-center">
                <i class="fas fa-trophy text-warning"></i>
                <h5 class="section-title mb-0">أبطال المبيعات</h5>
                <div class="d-flex gap-2 ms-auto">
                    <select id="lbMonth" class="form-select form-select-sm" style="width:105px" onchange="loadLeaderboard()">
                        @for($m=1;$m<=12;$m++)
                            <option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>شهر {{ $m }}</option>
                        @endfor
                    </select>
                    <select id="lbYear" class="form-select form-select-sm" style="width:90px" onchange="loadLeaderboard()">
                        @for($y=date('Y')-1;$y<=date('Y')+1;$y++)
                            <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>#</th><th>الموظف</th><th>وحدات</th><th>الحافز</th></tr></thead>
                    <tbody id="leaderboardBody">
                        <tr><td colspan="4" class="text-center py-3 text-muted">جارٍ التحميل...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SALES HISTORY -->
    <div class="col-lg-7">
        <div class="section-card h-100">
            <div class="section-header d-flex flex-wrap gap-2 align-items-center">
                <i class="fas fa-receipt text-primary"></i>
                <h5 class="section-title mb-0">سجل المبيعات</h5>
                <div class="d-flex flex-wrap gap-2 ms-auto">
                    <input type="text" id="salesEmpFilter" class="form-control form-control-sm" placeholder="الموظف..." style="width:150px">
                    <select id="salesStatusFilter" class="form-select form-select-sm" style="width:110px" onchange="loadSales()">
                        <option value="">كل الحالات</option>
                        <option value="pending">معلق</option>
                        <option value="approved">معتمد</option>
                        <option value="rejected">مرفوض</option>
                    </select>
                    <select id="salesMonthFilter" class="form-select form-select-sm" style="width:100px" onchange="loadSales()">
                        <option value="">كل الشهور</option>
                        @for($m=1;$m<=12;$m++)
                            <option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>شهر {{ $m }}</option>
                        @endfor
                    </select>
                    <select id="salesYearFilter" class="form-select form-select-sm" style="width:85px" onchange="loadSales()">
                        @for($y=date('Y')-1;$y<=date('Y')+1;$y++)
                            <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            <div class="table-responsive" style="max-height:420px; overflow-y:auto;">
                <table class="data-table">
                    <thead><tr><th>الصنف</th><th>الموظف</th><th>الكمية</th><th>الحافز</th><th>الفاتورة</th><th>الحالة</th><th class="text-end">إجراءات</th></tr></thead>
                    <tbody id="salesTableBody">
                        <tr><td colspan="7" class="text-center py-3 text-muted">جارٍ التحميل...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="p-2 d-flex justify-content-between align-items-center" id="salesPagination"></div>
        </div>
    </div>
</div>

<!-- CATALOG -->
<div class="section-card mb-2">
    <div class="section-header d-flex flex-wrap gap-2 align-items-center">
        <i class="fas fa-boxes-stacked text-primary"></i>
        <h5 class="section-title mb-0">كتالوج الأصناف</h5>
        <div class="d-flex flex-wrap gap-2 ms-auto">
            <input type="text" id="catalogSearch" class="form-control form-control-sm" placeholder="ابحث باسم الصنف أو الفرع..." style="width:210px" oninput="debouncedCatalog()">
            <select id="catalogExpiryFilter" class="form-select form-select-sm" style="width:150px" onchange="loadCatalog()">
                <option value="">كل حالات الانتهاء</option>
                <option value="critical">ينتهي خلال أسبوع</option>
                <option value="soon">ينتهي خلال 30 يوم</option>
                <option value="expired">منتهي</option>
                <option value="ok">سليم</option>
            </select>
        </div>
    </div>
    <div class="section-body p-3">
        <div class="row g-3" id="catalogGrid"></div>
        <div class="mt-3 d-flex justify-content-between align-items-center" id="catalogPagination"></div>
    </div>
</div>

<!-- ITEM MODAL (ADD/EDIT) -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="itemModalTitle"><i class="fas fa-box text-warning me-2"></i> إضافة صنف جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="itemForm">
                    <input type="hidden" id="if_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">اسم الصنف *</label>
                            <input type="text" id="if_name" class="form-control" required placeholder="مثال: بنادول اكسترا 24 قرص">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">تاريخ الانتهاء *</label>
                            <input type="date" id="if_expiry" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">الفرع</label>
                            <input type="text" id="if_branch" class="form-control" placeholder="مثال: فرع المهندسين">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">الكمية المتاحة بالمخزون *</label>
                            <input type="number" id="if_stock" class="form-control" min="0" step="1" value="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">سعر الوحدة *</label>
                            <div class="input-group">
                                <input type="number" id="if_price" class="form-control" min="0" step="any" required>
                                <span class="input-group-text">ج.م</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">حافز الوحدة الواحدة *</label>
                            <div class="input-group">
                                <input type="number" id="if_incentive" class="form-control" min="0" step="any" required>
                                <span class="input-group-text">ج.م</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">صورة الصنف</label>
                            <input type="file" id="if_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                            <div id="if_currentImageWrap" class="mt-2 d-none align-items-center gap-2">
                                <img id="if_currentImage" src="" alt="" style="height:60px;border-radius:8px">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="if_remove_image">
                                    <label class="form-check-label" for="if_remove_image">إزالة الصورة الحالية</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom px-4" onclick="saveItem()">
                    <i class="fas fa-save me-1"></i> حفظ الصنف
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SELL MODAL -->
<div class="modal fade" id="sellModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="fas fa-cash-register text-success me-2"></i> تسجيل بيع</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="calc-box mb-3 text-start" style="background:#f8f9fe;border:1.5px solid #e8ebf5;border-radius:12px;padding:12px 16px">
                    <div class="fw-bold" id="sf_itemName">-</div>
                    <div class="text-muted" style="font-size:.82rem" id="sf_itemInfo">-</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">الكمية المباعة *</label>
                    <input type="number" id="sf_qty" class="form-control" min="1" step="1" value="1" oninput="recalcSell()">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">رقم الفاتورة</label>
                        <input type="text" id="sf_invoiceNumber" class="form-control" placeholder="اختياري">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">تاريخ الفاتورة *</label>
                        <input type="date" id="sf_invoiceDate" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">الفرع</label>
                    <input type="text" id="sf_branch" class="form-control" placeholder="حسب الصنف تلقائياً">
                </div>
                <div class="calc-box">
                    <div class="text-muted" style="font-size:.8rem">إجمالي الحافز المستحق (يُعتمد بعد مراجعة الإدارة)</div>
                    <div class="calc-value text-success" id="sf_total_display">0.00 ج.م</div>
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn-primary-custom px-4" onclick="submitSale()">
                    <i class="fas fa-check me-1"></i> تسجيل البيع
                </button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE ITEM MODAL -->
<div class="modal fade" id="deleteItemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-body text-center p-4">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <h6 class="fw-bold mb-2">تأكيد حذف الصنف</h6>
                <p class="text-muted" style="font-size:.85rem">لا يمكن حذف صنف عليه عمليات بيع.</p>
                <div class="d-flex gap-2 justify-content-center mt-3">
                    <button class="btn btn-danger btn-sm px-3" onclick="confirmDeleteItem()">نعم، احذف</button>
                    <button class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">إلغاء</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let canManage = false;
let catalogItems = [];
let currentItem = null;
let editingItemId = null;
let deleteItemId = null;
let deleteSaleId = null;
let salesEmpSelect = null;

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('sf_invoiceDate').value = new Date().toISOString().slice(0, 10);
    salesEmpSelect = createSearchableSelect(document.getElementById('salesEmpFilter'), 'employees', {
        onSelect: () => loadSales(),
    });
    loadCatalog();
    loadSales();
    loadLeaderboard();
});

function debouncedCatalog() {
    clearTimeout(window.__catTimer);
    window.__catTimer = setTimeout(() => loadCatalog(), 350);
}

async function loadCatalog(page = 1) {
    const grid = document.getElementById('catalogGrid');
    grid.innerHTML = '<div class="col-12 text-center py-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x d-block mb-2"></i> جارٍ تحميل الأصناف...</div>';

    const search = document.getElementById('catalogSearch').value.trim();
    const status = document.getElementById('catalogExpiryFilter').value;

    let query = `?page=${page}&per_page=12`;
    if (search) query += `&search=${encodeURIComponent(search)}`;
    if (status) query += `&expiry_status=${status}`;

    try {
        const res = await apiFetch(`/near-expiry-items${query}`);
        if (!res.success) { grid.innerHTML = `<div class="col-12 text-center text-danger py-4">${escHtml(res.message || 'خطأ في التحميل')}</div>`; return; }

        canManage = !!res.can_manage;
        catalogItems = res.data.data || [];

        if (res.summary) {
            document.getElementById('statTotalItems').textContent = res.summary.total_items ?? 0;
            document.getElementById('statExpiring').textContent = res.summary.expiring_soon ?? 0;
        }

        if (!catalogItems.length) {
            grid.innerHTML = '<div class="col-12 text-center py-5 text-muted"><i class="fas fa-box-open fa-2x d-block mb-2 opacity-25"></i>لا توجد أصناف مطابقة - أضف صنفاً جديداً للبدء</div>';
            document.getElementById('catalogPagination').innerHTML = '';
            return;
        }

        grid.innerHTML = catalogItems.map(item => renderItemCard(item)).join('');
        renderPagination(res.data, 'catalogPagination', 'loadCatalog');
    } catch (e) {
        grid.innerHTML = '<div class="col-12 text-center text-danger py-4">حدث خطأ أثناء تحميل البيانات</div>';
    }
}

function expiryBadge(item) {
    const days = parseInt(item.days_to_expiry);
    if (item.expiry_status === 'expired')
        return ['rgba(198,40,40,.92)', '#fff', '<i class="fas fa-ban me-1"></i> منتهي'];
    if (days <= 7)
        return ['rgba(230,81,0,.92)', '#fff', `ينتهي خلال ${days} يوم`];
    if (days <= 30)
        return ['rgba(245,127,23,.88)', '#fff', `خلال ${days} يوم`];
    return ['rgba(46,125,50,.88)', '#fff', `سليم (${days} يوم)`];
}

function renderItemCard(item) {
    const [bg, fg, label] = expiryBadge(item);
    const img = item.image_url
        ? `<img src="${escAttr(item.image_url)}" alt="${escAttr(item.name)}" loading="lazy">`
        : '<i class="fas fa-pills fa-3x opacity-25"></i>';
    const outOfStock = item.stock_quantity <= 0;

    return `
    <div class="col-6 col-md-4 col-xl-3">
        <div class="ne-card h-100">
            <div class="ne-image">
                ${img}
                <span class="ne-expiry-badge" style="background:${bg};color:${fg}">${label}</span>
                <span class="ne-stock-badge">${outOfStock ? 'نفد المخزون' : 'متاح: ' + item.stock_quantity}</span>
            </div>
            <div class="ne-body">
                <div class="ne-name">${escHtml(item.name)}</div>
                <div class="ne-meta"><span><i class="fas fa-map-marker-alt me-1"></i>${escHtml(item.branch || 'بدون فرع')}</span>
                    <span><i class="fas fa-tag me-1"></i>${Number(item.unit_price).toLocaleString()} ج.م</span></div>
                <div class="ne-incentive-box">
                    <div style="font-size:.72rem;color:#8d6e63">حافز الوحدة الواحدة</div>
                    <div class="val">${Number(item.incentive_amount).toLocaleString()} ج.م</div>
                </div>
            </div>
            <div class="ne-footer">
                <button class="btn btn-sm btn-success flex-fill" ${outOfStock ? 'disabled' : ''} onclick="openSellModal(${item.id})">
                    <i class="fas fa-cash-register me-1"></i> تسجيل بيع
                </button>
                ${canManage ? `
                <button class="btn btn-sm btn-outline-secondary" onclick="openItemModal(${item.id})" title="تعديل"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="askDeleteItem(${item.id})" title="حذف"><i class="fas fa-trash"></i></button>` : ''}
            </div>
        </div>
    </div>`;
}

async function loadSales(page = 1) {
    const tbody = document.getElementById('salesTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">جارٍ التحميل...</td></tr>';

    const empId = salesEmpSelect?.getValue() || '';
    const status = document.getElementById('salesStatusFilter').value;
    const month = document.getElementById('salesMonthFilter').value;
    const year = document.getElementById('salesYearFilter').value;

    let query = `?page=${page}&per_page=10`;
    if (empId) query += `&employee_id=${empId}`;
    if (status) query += `&status=${status}`;
    if (month) query += `&month=${month}`;
    if (year) query += `&year=${year}`;

    try {
        const res = await apiFetch(`/near-expiry-sales${query}`);
        if (!res.success) return;

        canManage = !!res.can_manage;
        const s = res.summary || {};
        document.getElementById('statMonthQty').textContent = (s.approved_quantity || 0).toLocaleString();
        document.getElementById('statMonthIncentives').textContent = Number(s.approved_incentives || 0).toLocaleString() + ' ج.م';

        const rows = res.data.data || [];
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-receipt fa-2x d-block mb-2 opacity-25"></i>لا توجد عمليات بيع مطابقة</td></tr>';
            document.getElementById('salesPagination').innerHTML = '';
            return;
        }

        tbody.innerHTML = rows.map(sale => {
            const badgeClass = sale.status === 'approved' ? 'badge-approved' : sale.status === 'rejected' ? 'badge-rejected' : 'badge-pending';
            let actions = '';
            if (canManage && sale.status === 'pending') {
                actions += `<button class="btn btn-sm btn-outline-success" onclick="actOnSale(${sale.id},'approve')" title="اعتماد"><i class="fas fa-check"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="actOnSale(${sale.id},'reject')" title="رفض"><i class="fas fa-times"></i></button>`;
            }
            if (sale.status !== 'approved') {
                actions += `<button class="btn btn-sm btn-outline-secondary" onclick="askDeleteSale(${sale.id})" title="حذف"><i class="fas fa-trash"></i></button>`;
            }
            return `
            <tr>
                <td><strong>${escHtml(sale.item?.name || '—')}</strong>
                    <div class="text-muted" style="font-size:.72rem">${escHtml(sale.item?.branch || sale.branch || '')}</div></td>
                <td>${escHtml(sale.employee?.name || '—')}
                    <div class="text-muted" style="font-size:.72rem">${escHtml(sale.employee?.employee_code || '')}</div></td>
                <td><strong>${sale.quantity_sold}</strong></td>
                <td class="fw-bold text-success">${Number(sale.total_incentive).toLocaleString()} ج.م</td>
                <td style="font-size:.78rem">${escHtml(sale.invoice_number || '—')}<br>${new Date(sale.invoice_date).toLocaleDateString('ar-EG')}</td>
                <td><span class="badge-status ${badgeClass}">${escHtml(sale.status_label)}</span></td>
                <td class="text-end text-nowrap">${actions || '<span class="text-muted" style="font-size:.75rem">—</span>'}</td>
            </tr>`;
        }).join('');

        renderPagination(res.data, 'salesPagination', 'loadSales');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-danger">حدث خطأ أثناء التحميل</td></tr>';
    }
}

async function actOnSale(id, action) {
    const verb = action === 'approve'
        ? 'سيتم اعتماد البيع وإضافة الحافز لراتب الموظف تلقائياً.'
        : 'سيتم رفض البيع وإرجاع الكمية للمخزون.';
    if (!confirm(action === 'approve' ? 'تأكيد اعتماد البيع؟\n' + verb : 'تأكيد رفض البيع؟\n' + verb)) return;

    const res = await apiFetch(`/near-expiry-sales/${id}/${action}`, { method: 'POST', body: JSON.stringify({}) });
    showAlert(res.message || (res.success ? 'تم' : 'تعذر تنفيذ العملية'), res.success ? 'success' : 'danger');
    if (res.success) { loadSales(); loadLeaderboard(); }
}

async function loadLeaderboard() {
    const tbody = document.getElementById('leaderboardBody');
    const month = document.getElementById('lbMonth').value;
    const year = document.getElementById('lbYear').value;

    try {
        const res = await apiFetch(`/near-expiry-sales/leaderboard?month=${month}&year=${year}&limit=10`);
        if (!res.success) return;

        const rows = res.data || [];
        const medals = { 1: '🥇', 2: '🥈', 3: '🥉' };

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="fas fa-trophy fa-2x d-block mb-2 opacity-25"></i>لا توجد مبيعات معتمدة هذا الشهر</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(l => `
            <tr class="${l.rank && l.rank <= 3 ? '' : ''}" ${res.my_rank && res.my_rank.rank === l.rank ? 'style="background:#eef2ff"' : ''}>
                <td><span class="rank-medal">${medals[l.rank] || l.rank}</span></td>
                <td><strong>${escHtml(l.employee_name)}</strong>
                    <div class="text-muted" style="font-size:.72rem">${escHtml(l.position || '')}</div></td>
                <td>${l.total_quantity}</td>
                <td class="fw-bold text-success">${Number(l.total_incentive).toLocaleString()} ج.م</td>
            </tr>`).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-3">تعذر تحميل الترتيب</td></tr>';
    }
}

function openSellModal(id) {
    const item = typeof id === 'object' ? id : catalogItems.find(i => i.id === id);
    if (!item) return;
    currentItem = item;

    document.getElementById('sf_itemName').textContent = item.name;
    document.getElementById('sf_itemInfo').textContent =
        `سعر الوحدة: ${Number(item.unit_price).toLocaleString()} ج.م | الحافز/وحدة: ${Number(item.incentive_amount).toLocaleString()} ج.م | المتاح: ${item.stock_quantity}`;
    document.getElementById('sf_qty').value = 1;
    document.getElementById('sf_qty').max = item.stock_quantity;
    document.getElementById('sf_invoiceNumber').value = '';
    document.getElementById('sf_branch').value = item.branch || '';
    recalcSell();

    new bootstrap.Modal(document.getElementById('sellModal')).show();
}

function recalcSell() {
    if (!currentItem) return;
    const qty = parseInt(document.getElementById('sf_qty').value) || 0;
    const total = qty * parseFloat(currentItem.incentive_amount || 0);
    const el = document.getElementById('sf_total_display');
    el.textContent = total.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' ج.م';
}

async function submitSale() {
    if (!currentItem) return;

    const qty = parseInt(document.getElementById('sf_qty').value);
    if (!qty || qty < 1) { showAlert('أدخل الكمية المباعة', 'danger'); return; }
    if (qty > currentItem.stock_quantity) { showAlert(`الكمية المتاحة هي ${currentItem.stock_quantity} فقط`, 'danger'); return; }

    const invoiceDate = document.getElementById('sf_invoiceDate').value;
    if (!invoiceDate) { showAlert('تاريخ الفاتورة مطلوب', 'danger'); return; }

    const payload = {
        near_expiry_item_id: currentItem.id,
        quantity_sold: qty,
        invoice_date: invoiceDate,
        invoice_number: document.getElementById('sf_invoiceNumber').value.trim() || null,
        branch: document.getElementById('sf_branch').value.trim() || null,
    };

    const res = await apiFetch('/near-expiry-sales', { method: 'POST', body: JSON.stringify(payload) });
    if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('sellModal')).hide();
        showAlert(res.message || 'تم تسجيل البيع');
        loadCatalog();
        loadSales();
    } else {
        showAlert(res.message || 'فشل تسجيل البيع', 'danger');
    }
}

function openItemModal(id = null) {
    editingItemId = id;
    const form = document.getElementById('itemForm');
    form.reset();
    document.getElementById('if_id').value = '';

    const wrap = document.getElementById('if_currentImageWrap');
    wrap.classList.add('d-none');
    document.getElementById('if_remove_image').checked = false;

    const title = document.getElementById('itemModalTitle');

    if (id) {
        const item = catalogItems.find(i => i.id === id);
        if (!item) return;

        title.innerHTML = '<i class="fas fa-edit text-primary me-2"></i> تعديل الصنف';
        document.getElementById('if_id').value = item.id;
        document.getElementById('if_name').value = item.name;
        document.getElementById('if_expiry').value = item.expiry_date?.slice(0, 10) || '';
        document.getElementById('if_branch').value = item.branch || '';
        document.getElementById('if_stock').value = item.stock_quantity;
        document.getElementById('if_price').value = item.unit_price;
        document.getElementById('if_incentive').value = item.incentive_amount;

        if (item.image_url) {
            document.getElementById('if_currentImage').src = item.image_url;
            wrap.classList.remove('d-none');
            wrap.classList.add('d-flex');
        }
    } else {
        title.innerHTML = '<i class="fas fa-box text-warning me-2"></i> إضافة صنف جديد';
        document.getElementById('if_expiry').value = new Date().toISOString().slice(0, 10);
    }

    new bootstrap.Modal(document.getElementById('itemModal')).show();
}

async function saveItem() {
    const name = document.getElementById('if_name').value.trim();
    const expiry = document.getElementById('if_expiry').value;
    const price = document.getElementById('if_price').value;
    const incentive = document.getElementById('if_incentive').value;
    const stock = document.getElementById('if_stock').value;

    if (!name || !expiry || price === '' || incentive === '' || stock === '') {
        showAlert('يرجى ملء جميع الحقول المطلوبة', 'danger');
        return;
    }

    const fd = new FormData();
    fd.append('name', name);
    fd.append('expiry_date', expiry);
    fd.append('unit_price', price);
    fd.append('incentive_amount', incentive);
    fd.append('stock_quantity', stock);

    const branch = document.getElementById('if_branch').value.trim();
    if (branch) fd.append('branch', branch);

    const imageFile = document.getElementById('if_image').files[0];
    if (imageFile) fd.append('image', imageFile);

    if (editingItemId) {
        fd.append('_method', 'PUT');
        if (document.getElementById('if_remove_image').checked) fd.append('remove_image', '1');
    }

    const url = editingItemId ? `/near-expiry-items/${editingItemId}` : '/near-expiry-items';
    const res = await apiUpload(url, fd);

    if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
        showAlert(res.message || 'تم الحفظ بنجاح');
        loadCatalog();
    } else {
        showAlert(res.message || 'فشل الحفظ', 'danger');
    }
}

async function apiUpload(url, formData) {
    try {
        const res = await fetch(API_BASE + url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + TOKEN,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: formData,
        });
        const data = await res.json().catch(() => ({}));
        if (data.success === undefined) data.success = res.ok;
        if (!data.success) data.message = formatApiError(data);
        return data;
    } catch (e) {
        return { success: false, message: 'تعذر الاتصال بالخادم' };
    }
}

function askDeleteItem(id) {
    deleteItemId = id;
    new bootstrap.Modal(document.getElementById('deleteItemModal')).show();
}

async function confirmDeleteItem() {
    if (!deleteItemId) return;
    const res = await apiFetch(`/near-expiry-items/${deleteItemId}`, { method: 'DELETE' });
    bootstrap.Modal.getInstance(document.getElementById('deleteItemModal')).hide();
    showAlert(res.message || (res.success ? 'تم الحذف' : 'فشل الحذف'), res.success ? 'success' : 'danger');
    if (res.success) loadCatalog();
    deleteItemId = null;
}

function askDeleteSale(id) {
    if (!confirm('تأكيد حذف سجل البيع؟ سيتم إرجاع الكمية للمخزون.')) return;
    deleteSaleId = id;
    confirmDeleteSale();
}

async function confirmDeleteSale() {
    if (!deleteSaleId) return;
    const res = await apiFetch(`/near-expiry-sales/${deleteSaleId}`, { method: 'DELETE' });
    showAlert(res.message || (res.success ? 'تم الحذف' : 'فشل الحذف'), res.success ? 'success' : 'danger');
    if (res.success) { loadSales(); loadCatalog(); }
    deleteSaleId = null;
}

function renderPagination(data, containerId, loaderName) {
    const container = document.getElementById(containerId);
    if (!data.last_page || data.last_page <= 1) { container.innerHTML = ''; return; }

    container.innerHTML = `
        <span class="text-muted" style="font-size:.8rem">عرض ${data.from || 0} - ${data.to || 0} من أصل ${data.total}</span>
        <div class="btn-group">
            <button class="btn btn-sm btn-outline-secondary" ${data.current_page === 1 ? 'disabled' : ''} onclick="${loaderName}(${data.current_page - 1})">السابق</button>
            <button class="btn btn-sm btn-outline-secondary" ${data.current_page === data.last_page ? 'disabled' : ''} onclick="${loaderName}(${data.current_page + 1})">التالي</button>
        </div>`;
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function escAttr(str) {
    return escHtml(str).replace(/'/g, '&#39;');
}
</script>

@endsection
