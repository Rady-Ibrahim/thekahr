# أمثلة استجابات API - API Response Examples

## 1. لوحة التحكم (Dashboard)

### 1.1 الحصول على مؤشرات لوحة التحكم
**Request:**
```
GET /api/dashboard/metrics
Authorization: Bearer <token>
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "employees": {
      "total": 150,
      "active": 145,
      "present_today": 138,
      "late_today": 5,
      "absent_today": 7,
      "no_checkout": 12
    },
    "operations": {
      "new_requests": 25,
      "prepared_requests": 15,
      "under_review": 8,
      "approved_requests": 50,
      "ready_for_delivery": 30,
      "delivered_requests": 200
    },
    "deliveries": {
      "pending": 10,
      "in_transit": 20,
      "completed": 180,
      "failed": 2
    },
    "collections": {
      "pending": 50000,
      "collected_today": 125000,
      "collected_month": 1500000
    },
    "approvals": {
      "pending_approvals": 15,
      "pending_requests": 8,
      "pending_salaries": 3
    },
    "payroll": {
      "total_salary_amount": 500000,
      "pending_salaries": 12,
      "paid_salaries": 138
    }
  }
}
```

---

## 2. الموظفين (Employees)

### 2.1 قائمة الموظفين
**Request:**
```
GET /api/employees?per_page=15
Authorization: Bearer <token>
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "user_id": 1,
        "employee_code": "EMP001",
        "name": "أحمد محمد",
        "email": "ahmed@company.com",
        "phone": "01012345678",
        "position": "مندوب توصيل",
        "department": "التوصيل",
        "base_salary": 3000,
        "status": "active",
        "joining_date": "2024-01-15",
        "reporting_manager_id": null,
        "created_at": "2024-06-24T10:30:00Z",
        "updated_at": "2024-06-24T10:30:00Z"
      }
    ],
    "per_page": 15,
    "total": 150,
    "last_page": 10
  }
}
```

### 2.2 إنشاء موظف
**Request:**
```
POST /api/employees
Content-Type: application/json
Authorization: Bearer <token>

{
  "name": "محمود علي",
  "email": "mahmoud@company.com",
  "phone": "01098765432",
  "employee_code": "EMP152",
  "position": "مندوب مبيعات",
  "department": "المبيعات",
  "joining_date": "2024-06-25",
  "base_salary": 2500,
  "status": "active"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "تم إنشاء الموظف بنجاح",
  "data": {
    "id": 151,
    "employee_code": "EMP152",
    "name": "محمود علي",
    "email": "mahmoud@company.com",
    "phone": "01098765432",
    "position": "مندوب مبيعات",
    "department": "المبيعات",
    "base_salary": "2500.00",
    "status": "active",
    "joining_date": "2024-06-25",
    "created_at": "2024-06-24T15:45:00Z"
  }
}
```

### 2.3 الحصول على تفاصيل الموظف
**Request:**
```
GET /api/employees/1
Authorization: Bearer <token>
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "employee_code": "EMP001",
    "name": "أحمد محمد",
    "email": "ahmed@company.com",
    "phone": "01012345678",
    "position": "مندوب توصيل",
    "department": "التوصيل",
    "base_salary": "3000.00",
    "status": "active",
    "joining_date": "2024-01-15",
    "manager": {
      "id": 5,
      "name": "علي كريم"
    },
    "subordinates": [],
    "salaries": [
      {
        "id": 1,
        "month": 6,
        "year": 2024,
        "base_salary": "3000.00",
        "gross_salary": "3500.00",
        "net_salary": "3200.00",
        "status": "paid"
      }
    ]
  }
}
```

### 2.4 سجل الرواتب
**Request:**
```
GET /api/employees/1/salary-history
Authorization: Bearer <token>
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "employee_id": 1,
      "month": 6,
      "year": 2024,
      "base_salary": "3000.00",
      "gross_salary": "3500.00",
      "total_incentives": "500.00",
      "total_deductions": "300.00",
      "net_salary": "3200.00",
      "status": "paid",
      "payment_date": "2024-06-30T00:00:00Z"
    },
    {
      "id": 2,
      "employee_id": 1,
      "month": 5,
      "year": 2024,
      "base_salary": "3000.00",
      "gross_salary": "3400.00",
      "total_incentives": "400.00",
      "total_deductions": "250.00",
      "net_salary": "3150.00",
      "status": "paid",
      "payment_date": "2024-05-31T00:00:00Z"
    }
  ]
}
```

### 2.5 تحديث حالة الموظف
**Request:**
```
PUT /api/employees/1/status
Content-Type: application/json
Authorization: Bearer <token>

{
  "status": "on_leave"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "تم تحديث حالة الموظف بنجاح",
  "data": {
    "id": 1,
    "name": "أحمد محمد",
    "status": "on_leave",
    "updated_at": "2024-06-24T15:50:00Z"
  }
}
```

---

## 3. الطلبات (Requests)

### 3.1 قائمة الطلبات
**Request:**
```
GET /api/requests?per_page=15&status=approved
Authorization: Bearer <token>
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "request_number": "REQ-20240624153000",
        "customer_id": 5,
        "customer_name": "محل الجودة",
        "company_name": "الجودة للتجارة",
        "status": "approved",
        "items_count": 3,
        "total_quantity": 25,
        "total_amount": "2500.00",
        "created_by_id": 1,
        "approved_by_id": 2,
        "approved_at": "2024-06-24T14:30:00Z",
        "estimated_delivery_date": "2024-06-25",
        "created_at": "2024-06-24T10:00:00Z"
      }
    ],
    "per_page": 15,
    "total": 50,
    "last_page": 4
  }
}
```

### 3.2 إنشاء طلب
**Request:**
```
POST /api/requests
Content-Type: application/json
Authorization: Bearer <token>

{
  "customer_id": 5,
  "warehouse": "المخزن الرئيسي",
  "estimated_delivery_date": "2024-06-25",
  "notes": "توصيل مستعجل"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "تم إنشاء الطلب بنجاح",
  "data": {
    "id": 152,
    "request_number": "REQ-20240624155000",
    "customer_id": 5,
    "status": "draft",
    "items_count": 0,
    "total_amount": "0.00",
    "created_by_id": 1,
    "created_at": "2024-06-24T15:50:00Z"
  }
}
```

### 3.3 إضافة عناصر للطلب
**Request:**
```
POST /api/requests/1/items
Content-Type: application/json
Authorization: Bearer <token>

{
  "items": [
    {
      "item_id": 1,
      "quantity": 10,
      "unit_price": 100
    },
    {
      "item_id": 2,
      "quantity": 5,
      "unit_price": 250
    }
  ]
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "تم إضافة العناصر بنجاح",
  "data": {
    "id": 1,
    "request_number": "REQ-20240624153000",
    "items_count": 2,
    "total_quantity": 15,
    "total_amount": "2250.00"
  }
}
```

### 3.4 إرسال الطلب للمراجعة
**Request:**
```
POST /api/requests/1/submit-for-review
Authorization: Bearer <token>
```

**Response (200):**
```json
{
  "success": true,
  "message": "تم إرسال الطلب للمراجعة",
  "data": {
    "id": 1,
    "status": "under_review",
    "prepared_at": "2024-06-24T16:00:00Z"
  }
}
```

### 3.5 اعتماد الطلب
**Request:**
```
POST /api/requests/1/approve
Content-Type: application/json
Authorization: Bearer <token>

{
  "notes": "تمت المراجعة والاعتماد"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "تم اعتماد الطلب بنجاح",
  "data": {
    "id": 1,
    "status": "approved",
    "approved_at": "2024-06-24T16:05:00Z",
    "approved_by_id": 2
  }
}
```

### 3.6 رفض الطلب
**Request:**
```
POST /api/requests/1/reject
Content-Type: application/json
Authorization: Bearer <token>

{
  "rejection_reason": "البيانات غير دقيقة - يرجى التحديث"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "تم رفض الطلب",
  "data": {
    "id": 1,
    "status": "rejected",
    "rejection_reason": "البيانات غير دقيقة - يرجى التحديث"
  }
}
```

---

## 4. رسائل الخطأ

### 4.1 خطأ المصادقة
**Response (401):**
```json
{
  "success": false,
  "message": "غير مصرح - يرجى تسجيل الدخول",
  "error": "Unauthenticated"
}
```

### 4.2 خطأ الصلاحيات
**Response (403):**
```json
{
  "success": false,
  "message": "ليس لديك صلاحية للقيام بهذا الإجراء",
  "error": "Forbidden"
}
```

### 4.3 خطأ التحقق
**Response (422):**
```json
{
  "success": false,
  "message": "فشل التحقق من البيانات",
  "errors": {
    "email": ["البريد الإلكتروني مستخدم من قبل"],
    "phone": ["رقم الهاتف غير صحيح"]
  }
}
```

### 4.4 خطأ موارد غير موجودة
**Response (404):**
```json
{
  "success": false,
  "message": "الموارد المطلوبة غير موجودة",
  "error": "Not Found"
}
```

### 4.5 خطأ خادم
**Response (500):**
```json
{
  "success": false,
  "message": "حدث خطأ في الخادم",
  "error": "Internal Server Error"
}
```

---

## 5. رسائل الحالة الشاملة

### جميع رسائل رسائل Status Codes

| Code | Message | الوصف |
|------|---------|-------|
| 200 | OK | نجاح العملية |
| 201 | Created | تم إنشاء مورد جديد |
| 204 | No Content | نجح لكن بدون محتوى |
| 400 | Bad Request | طلب غير صحيح |
| 401 | Unauthorized | غير مصرح |
| 403 | Forbidden | محظور |
| 404 | Not Found | غير موجود |
| 422 | Unprocessable Entity | فشل التحقق |
| 429 | Too Many Requests | عدد كبير من الطلبات |
| 500 | Internal Server Error | خطأ في الخادم |
| 503 | Service Unavailable | الخدمة غير متاحة |

---

## 6. المتغيرات في Postman

```
base_url: http://localhost:8000 (أثناء التطوير)
         https://api.mphamedhr.com (الإنتاج)

token: <your_jwt_token_here>

employee_id: 1
customer_id: 1
request_id: 1
```

---

## 7. رؤوس الطلب (Headers)

```
Content-Type: application/json
Accept: application/json
Authorization: Bearer <token>
X-Requested-With: XMLHttpRequest
Accept-Language: ar
```

---

## 8. ملاحظات مهمة

1. **جميع التواريخ بصيغة ISO 8601**: `2024-06-24T15:50:00Z`
2. **جميع الأموال بـ decimal(14,2)**: `"2500.00"`
3. **جميع الصفحات تدعم pagination**
4. **جميع الـ timestamps تشمل timezone**
5. **جميع رسائل النجاح تشمل `success: true`**
6. **جميع رسائل الأخطاء تشمل `success: false`**

---

آخر تحديث: 24 يونيو 2024
