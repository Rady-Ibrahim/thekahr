<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Medical / Pharmacy fresh-deployment seeder.
 *
 * Wipes all operational & transactional demo data, preserves the RBAC core
 * (roles, permissions, role_permission) and real work locations, then seeds a
 * clean pharmacy baseline:
 *   - Pharmacy departments & branches
 *   - Morning/evening pharmacy shifts
 *   - Staff: Super Admin, HR Manager, Branch Managers, Senior Pharmacists,
 *     Pharmacists (دكتور صيدلي), Pharmacy Assistants
 *   - Flexible attendance flags for eligible staff
 *   - Near-expiry catalogue across branches
 *
 * Run:  php artisan db:seed --class=MedicalSystemFreshSeeder
 */
class MedicalSystemFreshSeeder extends Seeder
{
    /** Transactional / demo tables wiped on every run. */
    private const WIPE_TABLES = [
        // Attendance & requests
        'attendance_logs', 'attendances', 'attendance_requests', 'approvals',
        'requests', 'request_items',
        // Payroll & finance
        'salaries', 'salary_components_log', 'advances', 'deductions',
        'incentives', 'allowances', 'employee_points', 'commissions',
        // Near-expiry module
        'near_expiry_sales', 'near_expiry_items',
        // Logistics / collections / customers
        'deliveries', 'delivery_checkpoints', 'routes', 'route_stops',
        'collections', 'collection_details', 'customers',
        'customer_daily_expected_amounts', 'customer_employee',
        'car_violations', 'vehicle_tracking', 'vehicle_trackings',
        'items', 'warehouses',
        // Communication & audit
        'chat_groups', 'chat_group_members', 'employee_messages',
        'group_message_reads', 'notifications', 'activity_logs',
        // Framework housekeeping
        'failed_jobs', 'password_reset_tokens', 'personal_access_tokens',
        // Scheduling & misc HR config-demo rows
        'shift_early_exit_rules', 'shift_late_rules', 'shifts',
        'employee_shift', 'ideal_employees', 'employee_tab_permissions',
        // People & access (re-seeded below)
        'employees', 'users', 'user_permission', 'role_user',
        // Org structure refreshed to medical context
        'departments',
    ];

    /** Never touched: roles, permissions, role_permission, migrations, work_locations. */

    private const DEPARTMENTS = [
        ['name' => 'الإدارة العامة', 'description' => 'الإدارة العليا للشركة'],
        ['name' => 'الموارد البشرية', 'description' => 'شئون العاملين والرواتب'],
        ['name' => 'الحسابات والمالية', 'description' => 'الحسابات والخزينة'],
        ['name' => 'الصيدلية الرئيسية', 'description' => 'الفرع الرئيسي'],
        ['name' => 'فرع بني مزار', 'description' => 'صيدلية فرع بني مزار'],
        ['name' => 'المخزن المركزي', 'description' => 'مخزن الأدوية والتوريدات'],
    ];

    private const SHIFTS = [
        ['name' => 'صيدلية صباحية', 'start_time' => '09:00', 'end_time' => '17:00'],
        ['name' => 'صيدلية مسائية', 'start_time' => '17:00', 'end_time' => '01:00'],
        ['name' => 'إداري', 'start_time' => '08:00', 'end_time' => '16:00'],
    ];

    private const BRANCH_MAIN = 'الصيدلية الرئيسية';
    private const BRANCH_BANI_MAZAR = 'بني مزار';

    /**
     * [code, name, email, phone, position, department, employee_type,
     *  base_salary, role_key, branch, is_custom_attendance, daily_required_hours]
     */
    private const STAFF = [
        ['HR-001', 'مروة عبد الرحمن', 'hr@medpharm.local', '01011100001', 'مدير الموارد البشرية', 'الموارد البشرية', 'manager', 12000, 'hr_manager', null, false, null],
        ['BR-001', 'د. أحمد السيد', 'branch.main@medpharm.local', '01011100002', 'مدير فرع', self::BRANCH_MAIN, 'manager', 15000, 'manager', self::BRANCH_MAIN, false, null],
        ['BR-002', 'د. منى شاكر', 'branch.bm@medpharm.local', '01011100003', 'مدير فرع', 'فرع بني مزار', 'manager', 14000, 'manager', self::BRANCH_BANI_MAZAR, false, null],
        ['PH-001', 'د. كريم فؤاد', 'ph1@medpharm.local', '01011100004', 'صيدلي أول', self::BRANCH_MAIN, 'employee', 10000, 'employee', self::BRANCH_MAIN, false, null],
        ['PH-002', 'د. سلمى عادل', 'ph2@medpharm.local', '01011100005', 'دكتور صيدلي', self::BRANCH_MAIN, 'employee', 8000, 'employee', self::BRANCH_MAIN, true, 6],
        ['PH-003', 'د. يوسف حسن', 'ph3@medpharm.local', '01011100006', 'دكتور صيدلي', 'فرع بني مزار', 'employee', 8000, 'employee', self::BRANCH_BANI_MAZAR, true, 6],
        ['PA-001', 'هدى مصطفى', 'pa1@medpharm.local', '01011100007', 'مساعد صيدلي', self::BRANCH_MAIN, 'employee', 5500, 'employee', self::BRANCH_MAIN, false, null],
    ];

    private const STAFF_PASSWORD = 'Pharm@123456';

    /** [name, expiry_offset_days, unit_price, incentive, stock, branch] */
    private const NEAR_EXPIRY_ITEMS = [
        ['بانادول اكسترا 24 قرص', 21, 48.00, 5.00, 40, self::BRANCH_MAIN],
        ['أوجمنتين 1g 14 قرص', 35, 120.50, 10.00, 25, self::BRANCH_MAIN],
        ['كونجستال 20 قرص', 14, 36.75, 4.00, 60, self::BRANCH_MAIN],
        ['فيتامين سي 1000mg 20 فوار', 45, 55.00, 6.00, 80, self::BRANCH_BANI_MAZAR],
        ['بروفين 400mg 30 قرص', 28, 42.25, 4.50, 50, self::BRANCH_BANI_MAZAR],
        ['كتافلام جل 50 جم', 18, 63.00, 7.00, 30, self::BRANCH_MAIN],
        ['سيتال شراب أطفال 125مل', 40, 22.00, 2.50, 90, self::BRANCH_BANI_MAZAR],
        ['أوميز 20mg 14 كبسولة', 25, 58.50, 5.50, 45, self::BRANCH_MAIN],
    ];

    public function run(): void
    {
        $this->wipe();
        $this->seedDepartments();
        $this->seedShifts();
        $this->alignRolePermissions();
        $this->seedStaff();
        $this->seedNearExpiryItems();

        $this->command->info('✓ Medical/pharmacy baseline seeded successfully.');
        $this->command->info('  Super Admin : admin@mphamedhr.com / Admin@123456');
        $this->command->info('  Staff login : <email> / ' . self::STAFF_PASSWORD);
    }

    private function wipe(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (self::WIPE_TABLES as $table) {
            if (DB::select("SHOW TABLES LIKE '{$table}'")) {
                DB::statement("TRUNCATE TABLE `{$table}`");
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('✓ Operational/transactional tables truncated (' . count(self::WIPE_TABLES) . ').');
    }

    private function seedDepartments(): void
    {
        foreach (self::DEPARTMENTS as $dept) {
            Department::firstOrCreate(
                ['name' => $dept['name']],
                ['description' => $dept['description']]
            );
        }

        $this->command->info('✓ Pharmacy departments seeded.');
    }

    private function seedShifts(): void
    {
        foreach (self::SHIFTS as $shift) {
            $shift = Shift::firstOrCreate(['name' => $shift['name']], [
                'start_time'            => $shift['start_time'],
                'end_time'              => $shift['end_time'],
                'grace_period_minutes'  => 15,
                'is_active'             => true,
            ]);

            if ($shift->lateRules()->count() === 0) {
                $shift->lateRules()->createMany([
                    ['min_delay_minutes' => 1, 'max_delay_minutes' => 119, 'deduction_type' => 'minutes', 'deduction_value' => null],
                    ['min_delay_minutes' => 120, 'max_delay_minutes' => null, 'deduction_type' => 'half_day', 'deduction_value' => null],
                ]);
            }

            if ($shift->earlyExitRules()->count() === 0) {
                $shift->earlyExitRules()->createMany([
                    ['min_early_minutes' => 1, 'max_early_minutes' => 59, 'deduction_type' => 'minutes', 'deduction_value' => null],
                    ['min_early_minutes' => 60, 'max_early_minutes' => null, 'deduction_type' => 'half_day', 'deduction_value' => null],
                ]);
            }
        }

        $this->command->info('✓ Pharmacy shifts seeded (with late/early penalty rules).');
    }

    /**
     * Ensure domain permissions are mapped to the right roles:
     *  - super_admin keeps every permission
     *  - manager (مدير فرع): view + manage near-expiry
     *  - employee (صيادلة/مساعدين): view_near_expiry to record sales & track incentives
     *  - hr_manager: attendance management incl. flexible attendance screens
     */
    private function alignRolePermissions(): void
    {
        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->sync(Permission::all()->pluck('id'));
        }

        $map = [
            'manager'    => ['view_near_expiry', 'manage_near_expiry', 'view_attendance', 'manage_attendance'],
            'employee'   => ['view_near_expiry', 'view_attendance', 'view_dashboard'],
            'hr_manager' => ['view_attendance', 'manage_attendance', 'view_employees', 'create_employees',
                'edit_employees', 'view_salaries', 'create_salaries', 'approve_salaries', 'view_reports'],
        ];

        foreach ($map as $roleKey => $permissionNames) {
            $role = Role::where('name', $roleKey)->first();
            if (! $role) {
                continue;
            }

            $ids = Permission::whereIn('name', $permissionNames)->pluck('id');
            $role->permissions()->syncWithoutDetaching($ids);
        }

        $this->command->info('✓ Near-expiry & flexible-attendance permissions mapped to medical roles.');
    }

    private function seedStaff(): void
    {
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@mphamedhr.com'],
            [
                'name'      => 'مدير النظام',
                'password'  => Hash::make('Admin@123456'),
                'phone'     => '01000000000',
                'is_active' => true,
            ]
        );

        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin && ! $adminUser->hasRole('super_admin')) {
            $adminUser->roles()->attach($superAdmin);
        }

        Employee::firstOrCreate(
            ['employee_code' => 'ADM-001'],
            [
                'user_id'       => $adminUser->id,
                'name'          => 'مدير النظام',
                'email'         => 'admin@mphamedhr.com',
                'phone'         => '01000000000',
                'joining_date'  => now()->toDateString(),
                'position'      => 'Super Admin',
                'department'    => 'الإدارة العامة',
                'employee_type' => 'manager',
                'base_salary'   => 0,
                'status'        => 'active',
            ]
        );

        $passwordHash = Hash::make(self::STAFF_PASSWORD);

        foreach (self::STAFF as [$code, $name, $email, $phone, $position, $department, $type, $salary, $roleKey, $branch, $flexible, $requiredHours]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'      => $name,
                    'password'  => $passwordHash,
                    'phone'     => $phone,
                    'is_active' => true,
                ]
            );

            $role = Role::where('name', $roleKey)->first();
            if ($role && ! $user->hasRole($roleKey)) {
                $user->roles()->attach($role);
            }

            Employee::firstOrCreate(
                ['employee_code' => $code],
                [
                    'user_id'              => $user->id,
                    'name'                 => $name,
                    'email'                => $email,
                    'phone'                => $phone,
                    'joining_date'         => now()->toDateString(),
                    'position'             => $position,
                    'department'           => $department,
                    'employee_type'        => $type,
                    'base_salary'          => $salary,
                    'is_custom_attendance' => $flexible,
                    'daily_required_hours' => $requiredHours,
                    'status'               => 'active',
                ]
            );
        }

        $this->command->info('✓ Medical staff seeded (admin + ' . count(self::STAFF) . ' employees).');
    }

    private function seedNearExpiryItems(): void
    {
        $creator = User::where('email', 'admin@mphamedhr.com')->first();

        foreach (self::NEAR_EXPIRY_ITEMS as [$name, $daysToExpiry, $price, $incentive, $stock, $branch]) {
            \App\Models\NearExpiryItem::firstOrCreate(
                ['name' => $name, 'expiry_date' => now()->addDays($daysToExpiry)->toDateString()],
                [
                    'branch'           => $branch,
                    'unit_price'       => $price,
                    'incentive_amount' => $incentive,
                    'stock_quantity'   => $stock,
                    'created_by'       => $creator?->id,
                ]
            );
        }

        $this->command->info('✓ Baseline near-expiry items seeded across branches.');
    }
}
