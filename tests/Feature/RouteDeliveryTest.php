<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RouteDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private array $driverCredentials = [
        'email' => 'driver@example.test',
        'password' => 'password123',
    ];

    private function seedRoleDriver(): Role
    {
        return Role::firstOrCreate(['name' => 'driver'], ['description' => 'سائق / مندوب']);
    }

    private function createDriverUser(): User
    {
        $user = User::create([
            'name' => 'Driver Test',
            'email' => $this->driverCredentials['email'],
            'password' => Hash::make($this->driverCredentials['password']),
            'is_active' => true,
        ]);

        $user->giveRole('driver');

        Employee::create([
            'user_id' => $user->id,
            'employee_code' => 'EMP-DRV-1',
            'name' => 'Driver Test',
            'email' => 'driver@example.test',
            'phone' => '01000000001',
            'joining_date' => now()->toDateString(),
            'position' => 'سائق',
            'department' => 'التوصيل',
            'salary_type' => 'monthly',
            'base_salary' => 5000,
            'status' => 'active',
            'employee_type' => 'driver_representative',
            'sub_role' => 'driver',
        ]);

        return $user;
    }

    private function createCustomer(): Customer
    {
        return Customer::create([
            'customer_code' => 'CUS-001',
            'name' => 'عميل اختبار',
            'company_name' => 'شركة الاختبار',
            'phone' => '01000000002',
            'status' => 'active',
        ]);
    }

    /**
     * Full E2E: login -> create request -> create route with stops -> execute delivery -> verify.
     */
    public function test_full_delivery_workflow_flow(): void
    {
        $this->seedRoleDriver();
        $this->createDriverUser();
        $customer = $this->createCustomer();

        // Step 1: Driver Authentication
        $loginResponse = $this->postJson('/api/auth/login', $this->driverCredentials);
        $loginResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'employee' => ['id']]]);

        $token = $loginResponse->json('data.token');
        $employeeId = $loginResponse->json('data.employee.id');
        $this->assertNotNull($token);
        $this->assertNotNull($employeeId);

        $headers = ['Authorization' => "Bearer {$token}"];

        // Step 2: Request Creation
        $requestResponse = $this->postJson('/api/requests/prepaid', [
            'customer_id' => $customer->id,
            'items_count' => 2,
            'orders_count' => 5,
            'notes' => 'طلب اختبار E2E',
        ], $headers);

        $requestResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $requestId = $requestResponse->json('data.id');
        $this->assertNotNull($requestId);

        // Simulate the approval workflow so the request is ready for delivery.
        RequestModel::whereKey($requestId)->update([
            'status' => 'approved',
            'approved_by_id' => $employeeId,
            'approved_at' => now(),
        ]);

        // Step 3: Route & Stops Creation
        $routeResponse = $this->postJson('/api/routes/with-stops', [
            'route_name' => 'خط اختبار E2E',
            'driver_id' => $employeeId,
            'vehicle_number' => 'ABC-123',
            'start_point' => 'المخزن',
            'end_point' => 'العميل',
            'stops' => [
                [
                    'customer_id' => $customer->id,
                    'request_ids' => [$requestId],
                    'boxes_count' => 2,
                    'cartons_count' => 1,
                    'bundles_count' => 0,
                    'packages_count' => 3,
                    'expected_amount' => 150.00,
                ],
            ],
        ], $headers);

        $routeResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $routeId = $routeResponse->json('data.id');
        $stop = $routeResponse->json('data.stops.0');
        $this->assertNotNull($routeId);
        $this->assertNotNull($stop['id']);

        $this->assertDatabaseHas('routes', [
            'id' => $routeId,
            'driver_id' => $employeeId,
        ]);

        // Step 4: Execute Delivery
        $deliveryResponse = $this->postJson("/api/routes/{$routeId}/deliveries", [
            'route_stop_id' => $stop['id'],
            'request_id' => $requestId,
            'customer_id' => $customer->id,
            'packages_count' => 3,
            'expected_collection_amount' => 150.00,
            'goods_notes' => 'تسليم ناجح',
        ], $headers);

        $deliveryResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.driver_id', $employeeId);

        $deliveryId = $deliveryResponse->json('data.id');
        $this->assertNotNull($deliveryId);

        // Step 5: Status & Integrity Verification
        $showResponse = $this->getJson("/api/routes/{$routeId}", $headers);
        $showResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $stops = $showResponse->json('data.stops');
        $targetStop = collect($stops)->firstWhere('id', $stop['id']);
        $this->assertNotNull($targetStop, 'Target route stop should be returned by GET /routes/{id}');
        $this->assertEquals('delivered', $targetStop['delivery_status']);

        $this->assertDatabaseHas('deliveries', [
            'id' => $deliveryId,
            'route_id' => $routeId,
            'route_stop_id' => $stop['id'],
            'driver_id' => $employeeId,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('route_stops', [
            'id' => $stop['id'],
            'delivery_status' => 'delivered',
        ]);

        $this->assertDatabaseHas('requests', [
            'id' => $requestId,
            'status' => 'delivered',
        ]);
    }

    /**
     * A driver must not be able to execute delivery on a route assigned to someone else.
     */
    public function test_route_not_assigned_to_driver_is_rejected(): void
    {
        $this->seedRoleDriver();
        $this->createDriverUser();
        $customer = $this->createCustomer();

        $loginResponse = $this->postJson('/api/auth/login', $this->driverCredentials);
        $token = $loginResponse->json('data.token');
        $headers = ['Authorization' => "Bearer {$token}"];

        // Another employee owns the route.
        $otherEmployee = Employee::create([
            'employee_code' => 'EMP-DRV-2',
            'name' => 'سائق آخر',
            'email' => 'driver2@example.test',
            'phone' => '01000000003',
            'joining_date' => now()->toDateString(),
            'position' => 'سائق',
            'department' => 'التوصيل',
            'salary_type' => 'monthly',
            'base_salary' => 5000,
            'status' => 'active',
            'employee_type' => 'driver_representative',
            'sub_role' => 'driver',
        ]);

        $route = Route::create([
            'route_code' => 'RT-E2E-2',
            'route_name' => 'خط سائق آخر',
            'driver_id' => $otherEmployee->id,
            'status' => 'active',
        ]);

        $stop = RouteStop::create([
            'route_id' => $route->id,
            'customer_id' => $customer->id,
            'stop_order' => 1,
            'request_ids' => [],
            'delivery_status' => 'pending',
        ]);

        $request = RequestModel::create([
            'request_number' => 'REQ-E2E-2',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'items_count' => 1,
            'total_quantity' => 1,
            'status' => 'approved',
            'created_by_id' => $otherEmployee->id,
        ]);

        $response = $this->postJson("/api/routes/{$route->id}/deliveries", [
            'route_stop_id' => $stop->id,
            'request_id' => $request->id,
            'customer_id' => $customer->id,
        ], $headers);

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    /**
     * The delivered stop must be rejected from being delivered twice.
     */
    public function test_stop_cannot_be_delivered_twice(): void
    {
        $this->seedRoleDriver();
        $this->createDriverUser();
        $customer = $this->createCustomer();

        $loginResponse = $this->postJson('/api/auth/login', $this->driverCredentials);
        $token = $loginResponse->json('data.token');
        $headers = ['Authorization' => "Bearer {$token}"];
        $employeeId = $loginResponse->json('data.employee.id');

        $route = Route::create([
            'route_code' => 'RT-E2E-3',
            'route_name' => 'خط تسليم مكرر',
            'driver_id' => $employeeId,
            'status' => 'active',
        ]);

        $stop = RouteStop::create([
            'route_id' => $route->id,
            'customer_id' => $customer->id,
            'stop_order' => 1,
            'request_ids' => [],
            'delivery_status' => 'pending',
        ]);

        $request = RequestModel::create([
            'request_number' => 'REQ-E2E-3',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'items_count' => 1,
            'total_quantity' => 1,
            'status' => 'approved',
            'created_by_id' => $employeeId,
        ]);

        $payload = [
            'route_stop_id' => $stop->id,
            'request_id' => $request->id,
            'customer_id' => $customer->id,
        ];

        $first = $this->postJson("/api/routes/{$route->id}/deliveries", $payload, $headers);
        $first->assertStatus(201)->assertJsonPath('success', true);

        $second = $this->postJson("/api/routes/{$route->id}/deliveries", $payload, $headers);
        $second->assertStatus(422);
    }
}
