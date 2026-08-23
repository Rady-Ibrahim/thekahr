@extends('layouts.app')

@section('title', 'صلاحيات التابات للموظفين')
@section('page-title', 'صلاحيات التابات')

@section('content')

<style>
    .tab-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,.06);
        border: 1px solid rgba(0,0,0,.04);
        overflow: hidden;
    }
    .tab-card-header {
        padding: 18px 22px;
        border-bottom: 1px solid #f0f2f8;
        display: flex;
        align-items: center;
        gap: 12px;
        background: linear-gradient(135deg, #f8f9fe, #fff);
    }
    .tab-card-title {
        font-weight: 700;
        font-size: 1rem;
        color: #1a237e;
        margin: 0;
    }
    .tab-card-body { padding: 22px; }

    /* Employee Search & Dropdown */
    .emp-select-group {
        display: flex;
        gap: 10px;
        flex-direction: column;
    }

    .form-control-custom, .form-select-custom {
        padding: 10px 14px;
        border-radius: 10px;
        border: 1.5px solid #e2e8f4;
        font-family: 'Cairo', sans-serif;
        font-size: .9rem;
        color: #2d3748;
        background-color: #fff;
        transition: border-color .2s, box-shadow .2s;
        width: 100%;
    }
    .form-control-custom:focus, .form-select-custom:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(44,62,140,.1);
    }

    /* Tabs Builder */
    .available-tabs-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }

    .btn-tab-chip {
        background: #f0f3fa;
        border: 1.5px solid #dcdfe8;
        color: #334155;
        border-radius: 20px;
        padding: 6px 14px;
        font-size: .82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all .2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-tab-chip:hover {
        background: #e2e8f0;
        border-color: #cbd5e1;
    }
    .btn-tab-chip.selected {
        background: #e0e7ff;
        border-color: #6366f1;
        color: #4338ca;
    }
    .btn-tab-chip .key-tag {
        background: rgba(0,0,0,.08);
        border-radius: 10px;
        padding: 1px 6px;
        font-size: .7rem;
        font-family: monospace;
    }

    .tab-item-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        background: #f8f9fe;
        border-radius: 10px;
        border: 1px solid #e8ebf5;
        margin-bottom: 8px;
        transition: all .2s;
        animation: fadeInUp .25s ease;
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .tab-item-row:hover { border-color: #c5cae9; background: #eef0fb; }
    .tab-drag-handle {
        cursor: grab;
        color: #c5cae9;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .tab-drag-handle:active { cursor: grabbing; }
    .tab-name-badge {
        flex: 1;
        font-weight: 600;
        font-size: .875rem;
        color: #2d3748;
    }
    .tab-key-badge {
        background: #e8eaf6;
        color: #3949ab;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 600;
        font-family: monospace;
    }
    .tab-delete-btn {
        background: none;
        border: none;
        color: #e74c3c;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 6px;
        transition: background .15s;
        flex-shrink: 0;
    }
    .tab-delete-btn:hover { background: #fce4ec; }

    /* Input row for adding new tab */
    .add-tab-row {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .add-tab-row input {
        flex: 1;
        min-width: 120px;
        border-radius: 10px;
        border: 1.5px solid #e2e8f4;
        padding: 9px 14px;
        font-size: .875rem;
        font-family: 'Cairo', sans-serif;
        color: #2d3748;
        transition: border-color .2s, box-shadow .2s;
        background: #fff;
    }
    .add-tab-row input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(44,62,140,.1);
    }
    .btn-add-tab {
        background: linear-gradient(135deg, #1abc9c, #16a085);
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 9px 18px;
        font-weight: 600;
        font-size: .875rem;
        font-family: 'Cairo', sans-serif;
        cursor: pointer;
        transition: all .2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }
    .btn-add-tab:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(26,188,156,.3); }

    /* Selected employee display */
    #selectedEmployeeCard {
        display: none;
        background: linear-gradient(135deg, #e8eaf6, #f3e5f5);
        border-radius: 12px;
        padding: 14px 18px;
        margin-bottom: 18px;
        border: 1.5px solid #c5cae9;
    }
    #selectedEmployeeCard .emp-full-name {
        font-weight: 700;
        font-size: 1rem;
        color: #1a237e;
    }
    #selectedEmployeeCard .emp-full-meta {
        font-size: .8rem;
        color: #5c6bc0;
    }

    /* Employee list table */
    .emp-tabs-table tbody tr td { vertical-align: middle; }
    .tab-badge-list { display: flex; flex-wrap: wrap; gap: 5px; }
    .mini-tab-badge {
        background: #e8eaf6;
        color: #3949ab;
        border-radius: 20px;
        padding: 3px 10px;
        font-size: .72rem;
        font-weight: 600;
    }
    .btn-edit-emp {
        background: linear-gradient(135deg, #1a237e, #3949ab);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 6px 14px;
        font-size: .8rem;
        cursor: pointer;
        transition: all .2s;
        font-family: 'Cairo', sans-serif;
    }
    .btn-edit-emp:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(26,35,126,.25); }
    .btn-delete-emp {
        background: none;
        border: 1px solid #e74c3c;
        color: #e74c3c;
        border-radius: 8px;
        padding: 6px 12px;
        font-size: .8rem;
        cursor: pointer;
        transition: all .2s;
        font-family: 'Cairo', sans-serif;
    }
    .btn-delete-emp:hover { background: #fce4ec; }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 48px 24px;
        color: #8892a4;
    }
    .empty-state i { font-size: 3rem; margin-bottom: 12px; opacity: .3; }
    .empty-state p { font-size: .9rem; margin: 0; }

    /* Save button */
    .btn-save-tabs {
        background: linear-gradient(135deg, #2c3e8c, #3949ab);
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 11px 28px;
        font-weight: 700;
        font-size: .9rem;
        font-family: 'Cairo', sans-serif;
        cursor: pointer;
        transition: all .2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-save-tabs:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(44,62,140,.3); }
    .btn-save-tabs:disabled { opacity: .6; cursor: not-allowed; transform: none; }

    /* Loader */
    .spinner-sm {
        width: 16px; height: 16px;
        border: 2px solid rgba(255,255,255,.3);
        border-top-color: #fff;
        border-radius: 50%;
        animation: spin .6s linear infinite;
        display: none;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .field-error { font-size: .75rem; color: #e74c3c; margin-top: 3px; display: none; }
    .input-error { border-color: #e74c3c !important; }
</style>

<div class="page-header">
    <div>
        <h1><i class="fas fa-layer-group me-2 text-primary"></i> صلاحيات التابات للموظفين</h1>
        <div class="breadcrumb">تحديد التابات المسموح للموظفين بفتحها وتصفحها على الموبايل</div>
    </div>
</div>

<div class="row g-4">

    <!-- ===== LEFT: FORM ===== -->
    <div class="col-lg-5">
        <div class="tab-card">
            <div class="tab-card-header">
                <i class="fas fa-user-cog" style="color:#3949ab;font-size:1.1rem"></i>
                <span class="tab-card-title">إدارة صلاحيات موظف</span>
            </div>
            <div class="tab-card-body">

                <!-- Employee Selection: Dropdown & Search -->
                <div class="mb-4">
                    <label class="form-label fw-semibold text-muted" style="font-size:.85rem">
                        <i class="fas fa-user me-1"></i> اختر الموظف
                    </label>
                    <div class="emp-select-group">
                        <select id="empSelectDropdown" class="form-select-custom" onchange="onSelectDropdownChange(this.value)">
                            <option value="">-- اختر موظفاً من القائمة --</option>
                        </select>
                        <div class="text-center text-muted my-1" style="font-size:.78rem">- أو ابحث بالاسم / الكود -</div>
                        <input type="text" id="empSearchInput" class="form-control-custom" placeholder="اكتب اسم أو كود الموظف..."
                               autocomplete="off" oninput="searchEmployees(this.value)">
                        <div id="empDropdown" style="display:none; background:#fff; border:1px solid #e2e8f4; border-radius:10px; max-height:200px; overflow-y:auto; box-shadow:0 4px 12px rgba(0,0,0,.08); margin-top:4px;"></div>
                    </div>
                </div>

                <!-- Selected Employee Display -->
                <div id="selectedEmployeeCard">
                    <div class="d-flex align-items-center gap-3">
                        <div class="emp-avatar" id="selAvatar" style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#1a237e,#3949ab);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem">
                        </div>
                        <div>
                            <div class="emp-full-name" id="selName"></div>
                            <div class="emp-full-meta" id="selMeta"></div>
                        </div>
                        <button onclick="clearSelectedEmployee()" class="btn btn-sm btn-outline-secondary ms-auto" style="border-radius:8px">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Tabs Section -->
                <div id="tabsSection" style="display:none">
                    
                    <!-- Predefined / Available Tabs List -->
                    <div class="mb-3">
                        <label class="fw-semibold text-muted d-block mb-2" style="font-size:.85rem">
                            <i class="fas fa-hand-pointer me-1"></i> اختر من التابات المتاحة (انقر للإضافة/الإزالة)
                        </label>
                        <div class="available-tabs-grid" id="availableTabsGrid">
                            <!-- Populated dynamically -->
                        </div>
                    </div>

                    <!-- Selected Tabs List -->
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="fw-semibold text-muted" style="font-size:.85rem">
                            <i class="fas fa-th-list me-1"></i> التابات المحددة للموظف
                        </label>
                        <span class="mini-tab-badge" id="tabsCount">0 تاب</span>
                    </div>

                    <div id="tabsList" class="mb-3">
                        <!-- tabs rendered here -->
                    </div>

                    <!-- Add Custom Tab -->
                    <div class="border rounded-3 p-3 mb-4" style="background:#f8f9fe;border-color:#e8ebf5!important">
                        <div class="fw-semibold text-muted mb-2" style="font-size:.82rem">
                            <i class="fas fa-plus-circle me-1"></i> إضافة تاب مخصص جديد
                        </div>
                        <div class="add-tab-row">
                            <div style="flex:1;min-width:130px">
                                <input type="text" id="newTabName" placeholder="اسم التاب (مثال: المكافآت)"
                                       onkeydown="if(event.key==='Enter')addCustomTab()">
                                <div class="field-error" id="errTabName">أدخل اسم التاب</div>
                            </div>
                            <div style="flex:1;min-width:110px">
                                <input type="text" id="newTabKey" placeholder="المفتاح/الكود (مثال: 11)"
                                       onkeydown="if(event.key==='Enter')addCustomTab()">
                                <div class="field-error" id="errTabKey">أدخل كود/مفتاح التاب</div>
                            </div>
                            <button class="btn-add-tab" onclick="addCustomTab()">
                                <i class="fas fa-plus"></i> إضافة
                            </button>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <button class="btn-save-tabs w-100" onclick="saveTabs()" id="saveBtnMain">
                        <div class="spinner-sm" id="saveSpinner"></div>
                        <i class="fas fa-save" id="saveIcon"></i>
                        <span id="saveBtnText">حفظ الصلاحيات</span>
                    </button>
                </div>

                <!-- Placeholder when no employee selected -->
                <div id="noEmployeePlaceholder">
                    <div class="empty-state">
                        <i class="fas fa-user-shield d-block"></i>
                        <p>اختر موظفاً من القائمة أو البحث<br>لإدارة صلاحيات التابات</p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ===== RIGHT: LIST ===== -->
    <div class="col-lg-7">
        <div class="tab-card">
            <div class="tab-card-header">
                <i class="fas fa-list-alt" style="color:#3949ab;font-size:1.1rem"></i>
                <span class="tab-card-title">الموظفون الذين لديهم صلاحيات مُعدَّة</span>
                <button class="btn-primary-custom ms-auto" style="padding:6px 14px;font-size:.8rem" onclick="loadEmployeeList()">
                    <i class="fas fa-sync-alt me-1"></i> تحديث
                </button>
            </div>
            <div class="tab-card-body p-0">
                <div id="empListContainer">
                    <div class="empty-state">
                        <i class="fas fa-spinner fa-spin d-block" style="opacity:.3"></i>
                        <p>جاري التحميل...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Delete Confirm Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none">
            <div class="modal-body p-4 text-center">
                <div style="width:64px;height:64px;background:#fce4ec;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                    <i class="fas fa-trash-alt" style="color:#e74c3c;font-size:1.5rem"></i>
                </div>
                <h5 class="fw-bold mb-2">حذف صلاحيات الموظف</h5>
                <p class="text-muted" id="deleteModalMsg">هل تريد حذف جميع التابات المضافة لهذا الموظف؟</p>
                <div class="d-flex gap-2 justify-content-center mt-3">
                    <button class="btn btn-danger px-4" onclick="confirmDelete()" style="border-radius:10px;font-family:Cairo,sans-serif">
                        <i class="fas fa-trash me-1"></i> نعم، احذف
                    </button>
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" style="border-radius:10px;font-family:Cairo,sans-serif">إلغاء</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/* ============================================================
   STATE & PREDEFINED TABS
   ============================================================ */
let availableTabs = [
    { tab_name: 'الرواتب',           tab_key: '1' },
    { tab_name: 'الحوافز',            tab_key: '2' },
    { tab_name: 'الخصومات',           tab_key: '3' },
    { tab_name: 'السلف',              tab_key: '4' },
    { tab_name: 'الطلبات',            tab_key: '5' },
    { tab_name: ' تحضير الطلبيه',       tab_key: '6' },
    { tab_name: 'خطوط السير',         tab_key: '7' },
    { tab_name: 'التسليمات',          tab_key: '8' },
    { tab_name: 'التحصيلات',          tab_key: '9' },
    { tab_name: 'الحضور والانصراف',   tab_key: '10' }
];

let selectedEmployee = null;
let selectedTabs = [];
let allEmployeesList = [];
let deleteTargetId = null;
let searchTimeout = null;

/* ============================================================
   INITIALIZATION
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    loadAllEmployeesForDropdown();
    loadAvailableTabs();
    loadEmployeeList();
});

async function loadAvailableTabs() {
    try {
        const res = await apiGet('/employee-tabs/available');
        if (res.data && res.data.length > 0) {
            availableTabs = res.data;
        }
    } catch (e) {
        console.log('Using default predefined tabs');
    }
    renderAvailableTabsGrid();
}

async function loadAllEmployeesForDropdown() {
    try {
        const res = await apiGet('/employees?per_page=1000&status=active');
        const data = res.data?.data || res.data || [];
        allEmployeesList = data;

        const select = document.getElementById('empSelectDropdown');
        select.innerHTML = '<option value="">-- اختر موظفاً من القائمة --</option>' +
            data.map(emp => `<option value="${emp.id}">${escHtml(emp.name)} (${escHtml(emp.employee_code || '')})</option>`).join('');
    } catch (e) {
        console.error('Failed to load employees for dropdown', e);
    }
}

/* ============================================================
   EMPLOYEE SELECTION
   ============================================================ */
function onSelectDropdownChange(empId) {
    if (!empId) {
        clearSelectedEmployee();
        return;
    }
    selectEmployee(parseInt(empId));
}

async function searchEmployees(query) {
    const dropdown = document.getElementById('empDropdown');
    if (!query || query.trim().length < 1) {
        dropdown.style.display = 'none';
        return;
    }

    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        try {
            const res = await apiGet(`/employees?search=${encodeURIComponent(query)}&per_page=15&status=active`);
            const data = res.data?.data || res.data || [];
            renderSearchDropdown(data);
        } catch (e) {
            dropdown.style.display = 'none';
        }
    }, 300);
}

function renderSearchDropdown(employees) {
    const dropdown = document.getElementById('empDropdown');
    if (!employees || employees.length === 0) {
        dropdown.innerHTML = `<div class="p-2 text-muted text-center" style="font-size:.8rem">لا توجد نتائج</div>`;
        dropdown.style.display = 'block';
        return;
    }

    dropdown.innerHTML = employees.map(emp => `
        <div class="d-flex align-items-center gap-2 p-2 border-bottom cursor-pointer hover-bg"
             onclick="selectEmployee(${emp.id})" style="cursor:pointer">
            <div style="width:30px;height:30px;border-radius:50%;background:#1a237e;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem">
                ${emp.name.charAt(0)}
            </div>
            <div>
                <div style="font-weight:600;font-size:.85rem;color:#2d3748">${escHtml(emp.name)}</div>
                <div style="font-size:.72rem;color:#8892a4">${escHtml(emp.employee_code || '')} ${emp.department ? '• ' + escHtml(emp.department) : ''}</div>
            </div>
        </div>
    `).join('');

    dropdown.style.display = 'block';
}

async function selectEmployee(id) {
    let emp = allEmployeesList.find(e => e.id === id);
    if (!emp) {
        try {
            const res = await apiGet(`/employees/${id}`);
            emp = res.data;
        } catch { return; }
    }

    selectedEmployee = emp;
    document.getElementById('empDropdown').style.display = 'none';
    document.getElementById('empSearchInput').value = emp.name;
    document.getElementById('empSelectDropdown').value = emp.id;

    // Show selected card
    document.getElementById('selAvatar').textContent = emp.name.charAt(0);
    document.getElementById('selName').textContent = emp.name;
    document.getElementById('selMeta').textContent =
        [emp.employee_code, emp.department, emp.position].filter(Boolean).join(' • ');
    document.getElementById('selectedEmployeeCard').style.display = 'block';
    document.getElementById('noEmployeePlaceholder').style.display = 'none';
    document.getElementById('tabsSection').style.display = 'block';

    // Load existing tabs for this employee
    await loadEmployeeTabs(id);
}

function clearSelectedEmployee() {
    selectedEmployee = null;
    selectedTabs = [];
    document.getElementById('empSearchInput').value = '';
    document.getElementById('empSelectDropdown').value = '';
    document.getElementById('selectedEmployeeCard').style.display = 'none';
    document.getElementById('noEmployeePlaceholder').style.display = 'block';
    document.getElementById('tabsSection').style.display = 'none';
    document.getElementById('empDropdown').style.display = 'none';
    renderSelectedTabsList();
    renderAvailableTabsGrid();
}

/* ============================================================
   LOAD EMPLOYEE TABS
   ============================================================ */
async function loadEmployeeTabs(empId) {
    try {
        const res = await apiGet(`/employee-tabs/${empId}`);
        selectedTabs = (res.data?.tabs || []).map(t => ({
            tab_name:   t.tab_name,
            tab_key:    String(t.tab_key),
            sort_order: t.sort_order,
        }));
    } catch (e) {
        selectedTabs = [];
    }
    renderSelectedTabsList();
    renderAvailableTabsGrid();
}

/* ============================================================
   AVAILABLE TABS GRID & CHIPS
   ============================================================ */
function renderAvailableTabsGrid() {
    const container = document.getElementById('availableTabsGrid');
    if (!container) return;

    container.innerHTML = availableTabs.map(tab => {
        const isSelected = selectedTabs.some(t => String(t.tab_key) === String(tab.tab_key));
        return `
            <button type="button"
                    class="btn-tab-chip ${isSelected ? 'selected' : ''}"
                    onclick="toggleTabFromGrid('${escHtml(tab.tab_name)}', '${escHtml(tab.tab_key)}')">
                <i class="fas ${isSelected ? 'fa-check-circle text-primary' : 'fa-plus'}"></i>
                <span>${escHtml(tab.tab_name)}</span>
                <span class="key-tag">#${escHtml(tab.tab_key)}</span>
            </button>
        `;
    }).join('');
}

function toggleTabFromGrid(name, key) {
    const index = selectedTabs.findIndex(t => String(t.tab_key) === String(key));
    if (index > -1) {
        selectedTabs.splice(index, 1);
    } else {
        selectedTabs.push({
            tab_name: name,
            tab_key: String(key),
            sort_order: selectedTabs.length
        });
    }
    selectedTabs.forEach((t, i) => t.sort_order = i);
    renderSelectedTabsList();
    renderAvailableTabsGrid();
}

/* ============================================================
   CUSTOM TAB ADDITION
   ============================================================ */
function addCustomTab() {
    const nameInput = document.getElementById('newTabName');
    const keyInput  = document.getElementById('newTabKey');
    const errName   = document.getElementById('errTabName');
    const errKey    = document.getElementById('errTabKey');

    let valid = true;
    nameInput.classList.remove('input-error');
    keyInput.classList.remove('input-error');
    errName.style.display = 'none';
    errKey.style.display  = 'none';

    const tabName = nameInput.value.trim();
    const tabKey  = keyInput.value.trim();

    if (!tabName) {
        nameInput.classList.add('input-error');
        errName.style.display = 'block';
        valid = false;
    }
    if (!tabKey) {
        keyInput.classList.add('input-error');
        errKey.style.display = 'block';
        valid = false;
    }
    if (!valid) return;

    // Add to available tabs list if not present
    if (!availableTabs.some(t => String(t.tab_key) === String(tabKey))) {
        availableTabs.push({ tab_name: tabName, tab_key: String(tabKey) });
    }

    // Add to employee's selected tabs if not present
    if (!selectedTabs.some(t => String(t.tab_key) === String(tabKey))) {
        selectedTabs.push({ tab_name: tabName, tab_key: String(tabKey), sort_order: selectedTabs.length });
    }

    nameInput.value = '';
    keyInput.value  = '';
    renderSelectedTabsList();
    renderAvailableTabsGrid();
}

function removeSelectedTab(index) {
    selectedTabs.splice(index, 1);
    selectedTabs.forEach((t, i) => t.sort_order = i);
    renderSelectedTabsList();
    renderAvailableTabsGrid();
}

function renderSelectedTabsList() {
    const container = document.getElementById('tabsList');
    const countEl   = document.getElementById('tabsCount');

    if (countEl) countEl.textContent = selectedTabs.length + ' تاب';

    if (selectedTabs.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="padding:20px 12px">
                <i class="fas fa-layer-group d-block" style="font-size:1.5rem"></i>
                <p style="font-size:.82rem">لم تعين أي تابات لهذا الموظف بعد</p>
            </div>`;
        return;
    }

    container.innerHTML = selectedTabs.map((tab, i) => `
        <div class="tab-item-row" data-index="${i}">
            <span class="tab-drag-handle"><i class="fas fa-grip-vertical"></i></span>
            <span class="tab-name-badge">${escHtml(tab.tab_name)}</span>
            <span class="tab-key-badge">كود: ${escHtml(tab.tab_key)}</span>
            <button class="tab-delete-btn" onclick="removeSelectedTab(${i})" title="إزالة">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');
}

/* ============================================================
   SAVE TABS
   ============================================================ */
async function saveTabs() {
    if (!selectedEmployee) return;

    const btn     = document.getElementById('saveBtnMain');
    const spinner = document.getElementById('saveSpinner');
    const icon    = document.getElementById('saveIcon');
    const btnText = document.getElementById('saveBtnText');

    btn.disabled = true;
    spinner.style.display = 'inline-block';
    icon.style.display    = 'none';
    btnText.textContent   = 'جاري الحفظ...';

    try {
        await apiPost(`/employee-tabs/${selectedEmployee.id}`, { tabs: selectedTabs });
        showAlert('تم حفظ صلاحيات التابات بنجاح ✓', 'success');
        loadEmployeeList();
        loadAvailableTabs(); // refresh available custom tabs
    } catch (e) {
        showAlert(e.message || 'حدث خطأ أثناء الحفظ', 'danger');
    } finally {
        btn.disabled = false;
        spinner.style.display = 'none';
        icon.style.display    = 'inline-block';
        btnText.textContent   = 'حفظ الصلاحيات';
    }
}

/* ============================================================
   EMPLOYEE LIST (RIGHT PANEL)
   ============================================================ */
async function loadEmployeeList() {
    const container = document.getElementById('empListContainer');
    container.innerHTML = `
        <div class="empty-state">
            <i class="fas fa-spinner fa-spin d-block" style="opacity:.3"></i>
            <p>جاري التحميل...</p>
        </div>`;

    try {
        const res = await apiGet('/employee-tabs');
        const employees = res.data || [];
        renderEmployeeList(employees);
    } catch (e) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-exclamation-circle d-block" style="color:#e74c3c;opacity:.5"></i>
                <p>تعذر تحميل البيانات</p>
            </div>`;
    }
}

function renderEmployeeList(employees) {
    const container = document.getElementById('empListContainer');

    if (!employees || employees.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-layer-group d-block"></i>
                <p>لا يوجد موظفون مُعدَّة لهم صلاحيات بعد.<br>
                   اختر موظفاً وعيّن تاباته من القسم المجاور.</p>
            </div>`;
        return;
    }

    const rows = employees.map(emp => {
        const tabBadges = (emp.tab_permissions || []).map(t =>
            `<span class="mini-tab-badge" title="كود: ${escHtml(t.tab_key)}">${escHtml(t.tab_name)} (${escHtml(t.tab_key)})</span>`
        ).join('');

        return `
        <tr>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#1a237e,#3949ab);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0">
                        ${emp.name.charAt(0)}
                    </div>
                    <div>
                        <div class="fw-semibold" style="font-size:.875rem;color:#2d3748">${escHtml(emp.name)}</div>
                        <div style="font-size:.75rem;color:#8892a4">${escHtml(emp.employee_code || '')}${emp.department ? ' • ' + escHtml(emp.department) : ''}</div>
                    </div>
                </div>
            </td>
            <td>
                <div class="tab-badge-list">
                    ${tabBadges || '<span class="text-muted" style="font-size:.8rem">—</span>'}
                </div>
            </td>
            <td class="text-center" style="font-size:.8rem;color:#5c6bc0;font-weight:600">
                ${(emp.tab_permissions || []).length}
            </td>
            <td>
                <div class="d-flex gap-2 justify-content-end">
                    <button class="btn-edit-emp" onclick="selectEmployee(${emp.id})">
                        <i class="fas fa-edit me-1"></i> تعديل
                    </button>
                    <button class="btn-delete-emp" onclick="openDeleteModal(${emp.id}, '${escHtml(emp.name)}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');

    container.innerHTML = `
        <div class="table-responsive">
            <table class="data-table emp-tabs-table">
                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>التابات المسموحة (الكود)</th>
                        <th class="text-center">العدد</th>
                        <th class="text-end">إجراءات</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
}

/* ============================================================
   DELETE
   ============================================================ */
function openDeleteModal(empId, empName) {
    deleteTargetId = empId;
    document.getElementById('deleteModalMsg').textContent =
        `هل تريد حذف جميع التابات المضافة للموظف "${empName}"؟`;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

async function confirmDelete() {
    if (!deleteTargetId) return;
    try {
        await apiDelete(`/employee-tabs/${deleteTargetId}`);
        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        showAlert('تم حذف الصلاحيات بنجاح', 'success');
        if (selectedEmployee && selectedEmployee.id === deleteTargetId) {
            clearSelectedEmployee();
        }
        loadEmployeeList();
    } catch (e) {
        showAlert('حدث خطأ أثناء الحذف', 'danger');
    } finally {
        deleteTargetId = null;
    }
}

/* ============================================================
   API HELPERS
   ============================================================ */
async function apiGet(path) {
    const res = await fetch(API_BASE + path, {
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' }
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.message || 'Server error');
    return json;
}

async function apiPost(path, body) {
    const res = await fetch(API_BASE + path, {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + TOKEN,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(body),
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.message || 'Server error');
    return json;
}

async function apiDelete(path) {
    const res = await fetch(API_BASE + path, {
        method: 'DELETE',
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' }
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.message || 'Server error');
    return json;
}

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>

@endsection
