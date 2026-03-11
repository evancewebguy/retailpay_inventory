# KK Wholesalers Inventory Management System

## Introduction

KK Wholesalers Inventory Management System is a comprehensive, production-grade inventory management solution built for multi-branch wholesale operations. The system handles complex inventory movements including sales, inter-store transfers, inter-branch transfers, and stock adjustments with complete audit trail and race-condition prevention.

This application is built using **Laravel** for the backend and **Vue 3** for the frontend, connected seamlessly with **[Inertia.js](https://inertiajs.com)**. Inertia allows you to build modern, single-page applications using classic server-side routing and controllers, combining the frontend power of Vue with the incredible backend productivity of Laravel.

The frontend utilizes **Vue 3 Composition API**, **TypeScript** for type safety, **Tailwind CSS** for styling, and **[Heroicons](https://heroicons.com)** for beautiful UI components.

## Features

- **Multi-branch Architecture**: Manage multiple branches with multiple stores per branch
- **Real-time Inventory Tracking**: Live stock updates across all locations
- **Sales Processing**: Point-of-sale functionality with automatic inventory deduction
- **Stock Transfers**: Inter-store and inter-branch transfers with approval workflow
- **Stock Adjustments**: Manual adjustments with reason tracking and audit trail
- **Complete Traceability**: Every stock movement is logged with user accountability
- **Role-Based Access Control**: Administrator, Branch Manager, and Store Manager roles
- **Comprehensive Reporting**: Stock valuation, movement history, sales analysis, product performance, and low stock alerts
- **Concurrency Control**: Pessimistic locking to prevent race conditions during peak sales
- **Audit Trail**: Complete history of all inventory movements with before/after states

## Technology Stack

| Component | Technology |
|-----------|------------|
| **Backend** | Laravel 12.x |
| **Frontend** | Vue 3 (Composition API) |
| **Bridge** | Inertia.js |
| **Database** | MySQL  |
| **Authentication** | Laravel Sanctum |
| **Authorization** | Spatie Laravel-Permission |
| **Styling** | Tailwind CSS |
| **Icons** | Heroicons |
| **Notifications** | Vue Toastification |
| **Type Safety** | TypeScript |
| **Build Tool** | Vite |

## Prerequisites

Before you begin, ensure you have the following installed:

- **PHP** >= 8.2
- **Composer** (PHP dependency manager)
- **Node.js** >= 18.x (includes npm)
- **MySQL** >= 8.0 or **MariaDB** >= 10.3
- **Git** (version control)

## Step-by-Step Installation Guide

### 1. Clone the Repository

```bash
git clone https://github.com/evancewebguy/retailpay_inventory.git
cd retailpay_inventory
```
### 2. Install PHP Dependencies

composer install

# This will install Laravel and all backend dependencies including:


### 3. Install JavaScript Dependencies

npm install

# This will install frontend dependencies including:


### 4. Environment Configuration
Copy the example environment file to create your own configuration:

# bash
cp .env.example .env
Open the .env file and configure your database connection:

# env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kk_wholesalers
DB_USERNAME=root
DB_PASSWORD=yourpassword
Also configure your application URL:

# env
APP_NAME="KK Wholesalers"
APP_URL=http://localhost:8000

### 5. Generate Application Key
# bash
php artisan key:generate
This generates a unique application key for encryption services.

### 6. Create Database

# Create a new MySQL database for the application:

Using command line:

# bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS kk_wholesalers CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
Or using MySQL client:

# sql
CREATE DATABASE kk_wholesalers CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

### 7. Run Database Migrations
bash
php artisan migrate
This creates all the necessary tables:

Table	Purpose
**users**	System users
**roles**	User roles (Administrator, Branch Manager, Store Manager)
**permissions**	Granular permissions
**branches**	Business branches
**stores**	Stores within branches
**products**	Product catalog with SKUs
**inventories**	Current stock levels per store
**sales**	Sales transactions
**sale_items**	Individual items in each sale
**transfers**	Stock transfers between stores
**transfer_items**	Items in each transfer
**stock_adjustments**	Manual inventory adjustments
**adjustment_items**	Items in each adjustment
**inventory_movements**	Complete audit trail of all stock movements


### 8. Seed the Database with Sample Data
# bash
php artisan db:seed

## This populates your database with:

# Branches and Stores
Branch	Stores
Branch A	Store A1 - Downtown
Branch B	Store B1 - North, Store B2 - South

# User Accounts with Different Roles
Role	Email	Password	Access Level
Administrator	admin@kk.com	password	Full system access

Branch Manager A	manager.a@kk.com	password	Manage Branch A stores
Branch Manager B	manager.b@kk.com	password	Manage Branch B stores

Store Manager A1	store.a1@kk.com	password	Manage Store A1 only
Store Manager B1	store.b1@kk.com	password	Manage Store B1 only
Store Manager B2	store.b2@kk.com	password	Manage Store B2 only

# Sample Products
SKU	Name	Selling Price
SKU0001	Product 1	$39.00 - $150.00
SKU0002	Product 2	$39.00 - $150.00
SKU0003	Product 3	$39.00 - $150.00
...	...	...
*10 products with randomized prices between $20-150*

# Permissions Created
view sales, create sales, edit sales, delete sales, void sales

view transfers, create transfers, edit transfers, delete transfers, approve transfers, receive transfers

view inventory, adjust inventory, transfer inventory, view inventory movements

view reports, export reports

view products, create products, edit products, delete products

view stores, manage stores

manage users, view users, edit users

### 9. Create Storage Link
# bash
php artisan storage:link
Creates a symbolic link from public/storage to storage/app/public for file access.

### 10. Build Frontend Assets
For development with hot-reload (recommended while coding):

# bash
npm run dev
For production build (optimized assets):

# bash
npm run build

### 11. Start the Development Server
# bash
php artisan serve
Your application will be available at: http://localhost:8000

Accessing the Application
Login Page
Navigate to http://localhost:8000/login and use the credentials above.


### 12. Project Structure

kk-wholesalers-inventory/
├── app/
│   ├── Http/
│   │   ├── Controllers/              # Web controllers (Inertia)
│   │   │   ├── Auth/                  # Authentication controllers
│   │   │   ├── SalesController.php    # Sales management
│   │   │   ├── TransferController.php # Transfer management
│   │   │   ├── InventoryController.php # Inventory management
│   │   │   └── ReportController.php    # Reporting
│   │   ├── Controllers/Api/           # API controllers
│   │   │   ├── SalesController.php    # Sales API
│   │   │   ├── TransferController.php # Transfer API
│   │   │   ├── InventoryController.php # Inventory API
│   │   │   ├── ReportController.php    # Reporting API
│   │   │   ├── StoreController.php     # Store management API
│   │   │   ├── ProductController.php   # Product management API
│   │   │   └── CustomerController.php  # Customer management API
│   │   ├── Middleware/                 # Custom middleware
│   │   │   └── HandleInertiaRequests.php # Inertia middleware
│   │   └── Requests/                    # Form requests with validation
│   │       ├── SaleRequest.php
│   │       ├── TransferRequest.php
│   │       └── InventoryAdjustmentRequest.php
│   ├── Models/                          # Eloquent models
│   │   ├── User.php
│   │   ├── Branch.php
│   │   ├── Store.php
│   │   ├── Product.php
│   │   ├── Inventory.php
│   │   ├── Sale.php
│   │   ├── SaleItem.php
│   │   ├── Transfer.php
│   │   ├── TransferItem.php
│   │   ├── StockAdjustment.php
│   │   ├── AdjustmentItem.php
│   │   ├── InventoryMovement.php
│   │   └── Customer.php
│   ├── Services/                         # Business logic layer
│   │   └── InventoryService.php           # Core inventory operations
│   ├── Exceptions/                        # Custom exceptions
│   │   ├── InsufficientStockException.php
│   │   └── ProductNotInStoreException.php
│   └── Policies/                           # Authorization policies
├── bootstrap/                              # Framework bootstrapping
├── config/                                  # Configuration files
│   ├── app.php
│   ├── auth.php
│   ├── database.php
│   └── permission.php                       # Spatie permission config
├── database/
│   ├── factories/                           # Model factories
│   ├── migrations/                          # Database migrations
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 2026_03_05_113607_1_create_branches_table.php
│   │   ├── 2026_03_05_113607_2_create_stores_table.php
│   │   ├── 2026_03_05_113607_3_create_products_table.php
│   │   ├── 2026_03_05_113607_4_create_inventories_table.php
│   │   ├── 2026_03_05_113607_5_create_inventory_movements_table.php
│   │   ├── 2026_03_05_113607_6_create_transfers_table.php
│   │   ├── 2026_03_05_113607_7_create_transfer_items_table.php
│   │   ├── 2026_03_05_113607_8_create_customers_table.php
│   │   ├── 2026_03_05_113607_9_create_sales_table.php
│   │   ├── 2026_03_05_113607_10_create_sale_items_table.php
│   │   ├── 2026_03_05_113607_11_create_stock_adjustments_table.php
│   │   └── 2026_03_05_113607_12_create_adjustment_items_table.php
│   └── seeders/                            # Database seeders
│       └── DatabaseSeeder.php               # Main seeder with all test data
├── public/                                   # Publicly accessible files
│   └── index.php                             # Front controller
├── resources/
│   ├── js/
│   │   ├── Components/                       # Reusable Vue components
│   │   │   └── InputError.vue
│   │   ├── Composables/                       # Vue composables
│   │   ├── Layouts/                           # Layout components
│   │   │   ├── AuthenticatedLayout.vue
│   │   │   └── AuthLayout.vue
│   │   ├── Pages/                              # Inertia page components
│   │   │   ├── Auth/                           # Authentication pages
│   │   │   │   └── Login.vue
│   │   │   ├── Dashboard.vue                    # Main dashboard
│   │   │   ├── Sales/                           # Sales management pages
│   │   │   │   ├── Index.vue
│   │   │   │   └── Create.vue
│   │   │   ├── Transfers/                       # Transfer management pages
│   │   │   │   ├── Index.vue
│   │   │   │   ├── Create.vue
│   │   │   │   └── Show.vue
│   │   │   ├── Inventory/                        # Inventory management pages
│   │   │   │   ├── Index.vue
│   │   │   │   ├── Show.vue
│   │   │   │   └── Adjustment.vue
│   │   │   └── Reports/                          # Reporting pages
│   │   │       ├── Index.vue
│   │   │       ├── StockValuation.vue
│   │   │       ├── MovementHistory.vue
│   │   │       ├── SalesReport.vue
│   │   │       ├── ProductPerformance.vue
│   │   │       └── LowStock.vue
│   │   ├── Types/                               # TypeScript type definitions
│   │   │   ├── index.ts
│   │   │   └── global.d.ts
│   │   └── app.ts                                # Application entry point
│   ├── css/                                      # CSS/Tailwind styles
│   │   └── app.css
│   └── views/                                     # Blade templates
│       └── app.blade.php                          # Root template
├── routes/
│   ├── web.php                                   # Web routes (Inertia)
│   ├── api.php                                   # API routes
│   └── console.php                                # Console commands
├── storage/                                       # Application storage
│   ├── logs/                                      # Application logs
│   └── framework/
├── tests/                                         # PHPUnit tests
│   ├── Feature/                                   # Feature tests
│   │   └── InventoryTest.php
│   └── Unit/                                      # Unit tests
├── .env.example                                   # Environment example
├── artisan                                        # Laravel CLI
├── composer.json                                  # PHP dependencies
├── package.json                                   # Node dependencies
├── tailwind.config.js                             # Tailwind configuration
├── tsconfig.json                                  # TypeScript configuration
└── vite.config.js                                 # Vite configuration

### 13. Some of the Endpoints
Endpoint	Method	Description
/api/login	POST	Authenticate user
/api/sales	GET/POST	List/create sales
/api/transfers	GET/POST	List/create transfers
/api/transfers/{id}/approve	POST	Approve transfer
/api/transfers/{id}/receive	POST	Receive transfer
/api/inventory	GET	List inventory
/api/inventory/check-availability	POST	Check stock availability
/api/inventory/adjustments	POST	Create stock adjustment
/api/reports/*	GET	Access various reports
All API endpoints require authentication via Bearer token.


### 14. Support
For support, email support@kkwholesalers.com or create an issue in the repository.

### 15. Acknowledgments
Laravel Team for the amazing framework

Vue.js Team for the reactive frontend library

Inertia.js Team for bridging the gap

All contributors who have helped shape this project