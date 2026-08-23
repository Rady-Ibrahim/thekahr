# البدء السريع - Quick Start Guide

## 🚀 خطوات البدء في 5 دقائق

### 1️⃣ التثبيت الأساسي

```bash
# الدخول للمشروع
cd C:\wamp6\www\mphamedhr

# تثبيت Composer
composer install

# نسخ ملف البيئة
copy .env.example .env

# توليد مفتاح التطبيق
php artisan key:generate
```

### 2️⃣ إعداد قاعدة البيانات

```bash
# تحديث ملف .env مع بيانات المياه
# DB_DATABASE=mphamedhr
# DB_USERNAME=root
# DB_PASSWORD=

# تشغيل الترحيلات
php artisan migrate

# ملء البيانات الأولية
php artisan db:seed
```

### 3️⃣ تشغيل الخادم

```bash
# بدء خادم التطوير
php artisan serve

# سيكون الـ API متاحاً على:
# http://localhost:8000/api
```

### 4️⃣ اختبار الـ API

#### الخيار الأول: استخدام Postman
1. افتح Postman
2. اختر `Import`
3. اختر الملف `HR_System_API.postman_collection.json`
4. حدّث `base_url` و `token`
5. جرب أي endpoint

#### الخيار الثاني: استخدام cURL
```bash
# الحصول على قائمة الموظفين
curl -X GET http://localhost:8000/api/employees \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <your_token>"
```

#### الخيار الثالث: استخدام PHP Artisan Tinker
```bash
php artisan tinker

# إنشاء موظف تجريبي
>>> use App\Models\Employee;
>>> Employee::create([
  'employee_code' => 'EMP001',
  'name' => 'أحمد محمد',
  'email' => 'ahmed@example.com',
  'phone' => '01012345678',
  'position' => 'مندوب توصيل',
  'department' => 'التوصيل',
  'joining_date' => '2024-06-24',
  'base_salary' => 3000,
  'status' => 'active'
])
```

---

## 📊 اختبار الـ Endpoints الرئيسية

### 1. لوحة التحكم

```bash
GET /api/dashboard/metrics
```

**النتيجة**: 
- عدد الموظفين
- عدد الطلبات
- عدد التسليمات
- التحصيلات
- الرواتب

### 2. الموظفين

```bash
# قائمة الموظفين
GET /api/employees?per_page=15

# إنشاء موظف جديد
POST /api/employees
Content-Type: application/json
{
  "name": "محمود علي",
  "email": "mahmoud@example.com",
  "phone": "01098765432",
  "employee_code": "EMP002",
  "position": "مندوب مبيعات",
  "department": "المبيعات",
  "joining_date": "2024-06-25",
  "base_salary": 2500,
  "status": "active"
}

# الحصول على تفاصيل موظف
GET /api/employees/1

# تحديث موظف
PUT /api/employees/1
{
  "base_salary": 3500
}
```

### 3. الطلبات

```bash
# قائمة الطلبات
GET /api/requests?per_page=15

# إنشاء طلب
POST /api/requests
{
  "customer_id": 1,
  "warehouse": "المخزن الرئيسي",
  "estimated_delivery_date": "2024-06-25",
  "notes": "توصيل مستعجل"
}

# إضافة عناصر للطلب
POST /api/requests/1/items
{
  "items": [
    {
      "item_id": 1,
      "quantity": 10,
      "unit_price": 100
    }
  ]
}

# إرسال للمراجعة
POST /api/requests/1/submit-for-review

# اعتماد الطلب
POST /api/requests/1/approve
{
  "notes": "معتمد"
}
```

---

## 🔑 أساسيات النظام

### الأدوار الرئيسية
1. **Super Admin** - صلاحيات كاملة
2. **HR Manager** - إدارة الموظفين والرواتب
3. **Operations Manager** - إدارة الطلبات
4. **Driver** - تسجيل التسليمات
5. **Employee** - موظف عادي

### حالات الطلبات
- **draft** - مسودة
- **prepared** - تم التحضير
- **under_review** - تحت المراجعة
- **approved** - معتمد
- **rejected** - مرفوض
- **ready_for_delivery** - جاهز للتسليم
- **in_delivery** - في الطريق
- **delivered** - تم التسليم
- **collected** - تم التحصيل

---

## 🗂️ هيكل المشروع

```
mphamedhr/
├── app/
│   ├── Models/               # 25+ نموذج
│   ├── Http/Controllers/Api/ # Controllers
│   ├── Enums/                # 8 Enums
│   └── Repositories/         # Base Repository
├── database/
│   ├── migrations/           # 13 جدول
│   └── seeders/              # بيانات أولية
├── config/
│   └── hr.php                # إعدادات النظام
├── routes/
│   └── api.php               # routes API
├── HR_System_API.postman_collection.json
├── README_AR.md              # توثيق كامل بالعربية
├── API_RESPONSE_EXAMPLES.md  # أمثلة الـ API
└── IMPLEMENTATION_PLAN_AR.md # خطة التطوير
```

---

## 🔍 استكشاف الأخطاء

### خطأ: SQLSTATE[HY000] [2002] No connection possible

**الحل:**
```bash
# تأكد من تشغيل MySQL
# تحديث بيانات الاتصال في .env
DB_HOST=127.0.0.1
DB_DATABASE=mphamedhr
DB_USERNAME=root
DB_PASSWORD=
```

### خطأ: Class does not exist

**الحل:**
```bash
# تحديث Composer
composer dump-autoload

# تنظيف الـ cache
php artisan cache:clear
```

### خطأ: CORS

**الحل:**
- إضافة رؤوس CORS المناسبة
- تحديث SANCTUM_STATEFUL_DOMAINS في .env

---

## 📚 الملفات المهمة

| الملف | الوصف |
|------|-------|
| `.env` | إعدادات البيئة |
| `config/hr.php` | إعدادات النظام |
| `routes/api.php` | جميع API endpoints |
| `database/migrations/` | تعريفات الجداول |
| `app/Models/` | نماذج البيانات |
| `HR_System_API.postman_collection.json` | مجموعة Postman |

---

## ⏰ الخطوات التالية

### الأسبوع الأول
- [ ] اختبار جميع endpoints
- [ ] إنشاء موظفين تجريبيين
- [ ] تجربة عمليات الطلبات
- [ ] فهم دورة سير العمل

### الأسبوع الثاني
- [ ] تطوير SalaryCalculationService
- [ ] إنشاء AttendanceController
- [ ] تطوير DeliveryController
- [ ] اختبار الـ Business Logic

### الأسبوع الثالث
- [ ] بناء Dashboard الأمامي
- [ ] تطوير واجهات React/Vue
- [ ] تكامل Postman مع التطوير
- [ ] اختبار الأداء

---

## 💡 نصائح مهمة

1. **استخدم Postman Collection** لاختبار الـ API بسرعة
2. **استخدم Tinker** للتجريب السريع
3. **استخدم Database Seeder** لملء بيانات تجريبية
4. **استخدم Logs** لتتبع الأخطاء
5. **استخدم Migrations** للتغييرات في قاعدة البيانات

---

## 🆘 الحصول على المساعدة

### المشاكل الشائعة وحلولها

```bash
# مشكلة: أخطاء في الترحيلات
php artisan migrate:refresh
php artisan migrate
php artisan db:seed

# مشكلة: بطء النظام
php artisan cache:clear
php artisan config:cache
php artisan route:cache

# مشكلة: لا يعمل Sanctuary
php artisan migrate
php artisan tinker
>>> use App\Models\User;
>>> User::first()->createToken('token')->plainTextToken
```

---

## 📞 التواصل

للمزيد من المساعدة:
- البريد: support@mphamedhr.com
- الهاتف: +20 xxx xxx xxxx
- التوثيق الكاملة: انظر `README_AR.md`

---

**نصيحة ذهبية**: قراءة `README_AR.md` تعطيك فهماً شاملاً للنظام!

آخر تحديث: 24 يونيو 2024
