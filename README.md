# HR & Operations Management System

A comprehensive Laravel-based system for managing human resources, operations, requests, deliveries, collections, attendance, incentives, deductions, advances, allowances, and salaries within an organization.

## Features

### Dashboard & Monitoring
- Real-time KPI metrics for all departments
- Employee attendance tracking
- Request pipeline visualization
- Collection statistics
- Payroll summary
- Approval queue monitoring

### Employee Management
- Complete employee profiles
- Position and department management
- Organizational hierarchy
- Salary structure management
- Employee status tracking

### Request Management
- Multi-level approval workflow
- Request tracking from creation to delivery
- Item management with pricing
- Status transitions with timestamps
- Request history and audit trail

### Delivery & Logistics
- Daily route planning
- GPS vehicle tracking
- Real-time delivery status
- Proof of delivery (photos, signatures)
- Checkpoint management

### Collections Management
- Cash flow recording
- Multiple payment methods (Cash, Check, Transfer, InstaPay, Fawry)
- Reconciliation process
- Collection approval workflow

### Attendance & HR
- GPS-based check-in/check-out
- Automatic late detection
- Leave request management
- Attendance reports
- Monthly statistics

### Payroll System
- Automated salary calculation
- Incentives (manual and automatic)
- Deductions and taxes
- Advances management
- Allowances (transportation, fuel, missions)
- Commissions
- Vehicle violation fines
- Multi-level approval

### Reports & Analytics
- Employee performance reports
- Delivery and collection reports
- Attendance statistics
- Salary breakdowns
- Custom report builder
- Export to Excel/PDF

## Architecture

### Technology Stack
- **Framework**: Laravel 11
- **Language**: PHP 8.2+
- **Database**: MySQL 8.0+
- **API**: RESTful with Sanctum
- **Authentication**: Token-based (Sanctum)

## Quick Start

### Prerequisites
- PHP 8.2+
- MySQL 8.0+
- Composer
- Node.js (for frontend)

### Installation

1. **Clone and Setup**
bash
git clone <repository-url>
cd mphamedhr
composer install
cp .env.example .env
php artisan key:generate


2. **Database Setup**
bash
php artisan migrate
php artisan db:seed


3. **Start Development Server**
bash
php artisan serve


## API Documentation

Import `HR_System_API.postman_collection.json` into Postman for complete API documentation.

All endpoints require Bearer token authentication:
```
Authorization: Bearer <your_token>
```

## Documentation Files

- **README_AR.md** - Full documentation in Arabic
- **API_RESPONSE_EXAMPLES.md** - API response examples
- **IMPLEMENTATION_PLAN_AR.md** - Development roadmap
- **HR_System_API.postman_collection.json** - Postman collection

## Support

For issues or questions:
- Email: support@mphamedhr.com

## License

All rights reserved © 2024
