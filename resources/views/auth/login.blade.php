<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>تسجيل الدخول - نظام HR</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #1a237e 0%, #283593 40%, #1abc9c 100%);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
        }
        .login-wrapper {
            display: flex;
            width: 900px; max-width: 95%;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(0,0,0,.4);
        }
        .login-left {
            flex: 1;
            background: linear-gradient(180deg, rgba(255,255,255,.08) 0%, rgba(255,255,255,.03) 100%);
            padding: 60px 40px;
            color: #fff;
            display: flex; flex-direction: column; justify-content: center;
        }
        .login-left .brand-icon {
            width: 70px; height: 70px;
            background: rgba(255,255,255,.15);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem;
            margin-bottom: 28px;
        }
        .login-left h1 { font-weight: 800; font-size: 2rem; margin-bottom: 8px; }
        .login-left p  { color: rgba(255,255,255,.7); font-size: .95rem; line-height: 1.6; }
        .login-left .features { list-style: none; padding: 0; margin-top: 32px; }
        .login-left .features li { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; color: rgba(255,255,255,.85); font-size: .9rem; }
        .login-left .features li .f-icon { width: 36px; height: 36px; background: rgba(255,255,255,.12); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

        .login-right {
            width: 420px;
            background: #fff;
            padding: 60px 50px;
        }
        .login-right h2 { font-weight: 800; font-size: 1.6rem; color: #1a237e; margin-bottom: 4px; }
        .login-right .sub  { color: #8892a4; font-size: .875rem; margin-bottom: 36px; }
        .form-group { margin-bottom: 20px; }
        .form-label { font-weight: 600; font-size: .83rem; color: #4a5568; margin-bottom: 6px; }
        .input-wrap { position: relative; }
        .input-wrap .form-control {
            padding: 12px 16px 12px 42px;
            border-radius: 12px;
            border: 2px solid #e8ebf5;
            font-size: .875rem;
            transition: all .2s;
        }
        .input-wrap .form-control:focus { border-color: #1a237e; box-shadow: 0 0 0 4px rgba(26,35,126,.08); }
        .input-wrap .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #a0aec0; }
        .login-btn {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #1a237e, #3949ab);
            color: #fff; border: none; border-radius: 12px;
            font-weight: 700; font-size: 1rem;
            cursor: pointer;
            transition: all .3s;
            margin-top: 8px;
        }
        .login-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(26,35,126,.35); }
        .login-btn:active { transform: translateY(0); }
        .error-msg { color: #e53e3e; font-size: .8rem; margin-top: 6px; display: none; }
        .alert-login { border-radius: 12px; padding: 12px 16px; font-size: .85rem; margin-bottom: 20px; }
        @media (max-width: 768px) {
            .login-left { display: none; }
            .login-right { width: 100%; }
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <!-- LEFT -->
    <div class="login-left">
        <div class="brand-icon"><i class="fas fa-users-cog"></i></div>
        <h1>HR & Operations</h1>
        <p>نظام متكامل لإدارة الموارد البشرية والعمليات التشغيلية</p>
        <ul class="features">
            <li><div class="f-icon"><i class="fas fa-users"></i></div> إدارة شاملة للموظفين</li>
            <li><div class="f-icon"><i class="fas fa-truck"></i></div> تتبع التسليمات والتحصيلات</li>
            <li><div class="f-icon"><i class="fas fa-money-bill-wave"></i></div> حساب وإدارة الرواتب</li>
            <li><div class="f-icon"><i class="fas fa-chart-bar"></i></div> تقارير وتحليلات شاملة</li>
            <li><div class="f-icon"><i class="fas fa-mobile-alt"></i></div> واجهة API للتطبيق المحمول</li>
        </ul>
    </div>
    <!-- RIGHT -->
    <div class="login-right">
        <h2>أهلاً بك</h2>
        <div class="sub">سجّل دخولك للوصول إلى لوحة التحكم</div>

        @if(session('error'))
            <div class="alert alert-danger alert-login"><i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}</div>
        @endif

        <form id="loginForm" action="/login" method="POST">
            @csrf
            <div class="form-group">
                <label class="form-label">البريد الإلكتروني أو اسم المستخدم</label>
                <div class="input-wrap">
                    <input type="text" name="email" class="form-control" placeholder="admin@example.com" required autofocus value="{{ old('email') }}">
                    <i class="fas fa-envelope input-icon"></i>
                </div>
                @error('email')<div class="error-msg" style="display:block">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">كلمة المرور</label>
                <div class="input-wrap">
                    <input type="password" name="password" id="pwdInput" class="form-control" placeholder="••••••••" required>
                    <i class="fas fa-eye input-icon" style="cursor:pointer" onclick="togglePwd()"></i>
                </div>
                @error('password')<div class="error-msg" style="display:block">{{ $message }}</div>@enderror
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember" style="font-size:.875rem">تذكرني</label>
            </div>
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt me-2"></i> تسجيل الدخول
            </button>
        </form>
        <div class="text-center mt-4" style="color:#a0aec0; font-size:.75rem">
            &copy; {{ date('Y') }} نظام إدارة الموارد البشرية
        </div>
    </div>
</div>
<script>
function togglePwd() {
    const inp = document.getElementById('pwdInput');
    const icon = inp.nextElementSibling;
    if (inp.type === 'password') { inp.type = 'text'; icon.classList.replace('fa-eye','fa-eye-slash'); }
    else { inp.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
}
</script>
</body>
</html>
