<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// AUTH
Route::get('/login', fn() => view('auth.login'))->name('login')->middleware('guest');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email'    => 'required|string',
        'password' => 'required|string',
    ]);

    $user = User::where('email', $credentials['email'])
                ->orWhere('phone', $credentials['email'])
                ->first();

    if (!$user || !Hash::check($credentials['password'], $user->password)) {
        return back()->with('error', 'بيانات الدخول غير صحيحة')->withInput();
    }

    Auth::login($user, $request->boolean('remember'));
    $token = $user->createToken('dashboard')->plainTextToken;
    session(['api_token' => $token]);
    return redirect()->intended('/dashboard');
})->name('login.post');

Route::get('/logout', function () {
    if (auth()->check()) auth()->user()->tokens()->delete();
    Auth::logout();
    return redirect('/login');
});

Route::post('/logout', function () {
    if (auth()->check()) auth()->user()->tokens()->delete();
    Auth::logout();
    return redirect('/login');
})->name('logout');

// AUTHENTICATED DASHBOARD ROUTES
Route::middleware('auth')->group(function () {

    Route::get('/', fn() => redirect('/dashboard'));
    Route::get('/dashboard',    fn() => view('dashboard.index'));

    // Employees
    Route::get('/employees',           fn() => view('employees.index'));
    Route::get('/employees/{id}/edit', fn($id) => view('employees.index'));

    // HR
    Route::get('/attendance',   fn() => view('attendance.index'));
    Route::get('/shifts',       fn() => view('shifts.index'));
    Route::get('/salaries',     fn() => view('salaries.index'));
    Route::get('/incentives',   fn() => view('incentives.index'));
    Route::get('/deductions',   fn() => view('deductions.index'));
    Route::get('/advances',     fn() => view('advances.index'));
    Route::get('/allowances',   fn() => view('allowances.index'));
    Route::get('/employee-points', fn() => view('employee-points.index'));
    Route::get('/ideal-employee',  fn() => view('ideal-employee.index'));

    // Operations
    Route::get('/requests',     fn() => view('requests.index'));
    Route::get('/prepaid-requests', fn() => view('prepaid-requests.index'));
    Route::get('/routes',       fn() => view('routes.index'));
    Route::get('/deliveries',   fn() => view('deliveries.index'));
    Route::get('/collections',  fn() => view('collections.index'));
    Route::get('/commissions',  fn() => view('commissions.index'));
    Route::get('/car-violations', fn() => view('car-violations.index'));

    // Management
    Route::get('/customers',    fn() => view('customers.index'));
    Route::get('/warehouses',   fn() => view('warehouses.index'));
    Route::get('/items',        fn() => view('items.index'));
    Route::get('/approvals',    fn() => view('approvals.index'));
    Route::get('/reports',      fn() => view('reports.index'));
    Route::get('/notifications', fn() => view('notifications.index'));
    Route::get('/locations',    fn() => view('locations.index'));
    Route::get('/employee-tab-permissions', fn() => view('employee-tab-permissions.index'));
    Route::get('/users', fn() => view('users.index'));
    Route::get('/financial-statement', fn() => view('financial-statement.index'));
    Route::get('/chat-groups', fn() => view('chat-groups.index'));
});
