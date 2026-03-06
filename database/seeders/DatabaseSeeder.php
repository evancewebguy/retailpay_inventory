<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Branch;
use App\Models\Store;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create roles
        $roles = ['Administrator', 'Branch Manager', 'Store Manager'];
        foreach ($roles as $role) {
            Role::create(['name' => $role]);
        }

        // Create permissions
        $permissions = [
            'view sales', 'create sales', 'view transfers', 'create transfers',
            'approve transfers', 'view inventory', 'adjust inventory',
            'view reports', 'manage users'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Assign permissions to roles
        Role::findByName('Administrator')->givePermissionTo(Permission::all());
        
        Role::findByName('Branch Manager')->givePermissionTo([
            'view sales', 'view transfers', 'create transfers',
            'view inventory', 'view reports'
        ]);
        
        Role::findByName('Store Manager')->givePermissionTo([
            'view sales', 'create sales', 'view transfers',
            'view inventory'
        ]);

        // Create branches
        $branchA = Branch::create([
            'name' => 'Branch A',
            'code' => 'BR-A',
            'address' => '123 Main St',
            'phone' => '555-0101'
        ]);

        $branchB = Branch::create([
            'name' => 'Branch B',
            'code' => 'BR-B',
            'address' => '456 Oak Ave',
            'phone' => '555-0102'
        ]);

        // Create stores
        $storeA1 = Store::create([
            'branch_id' => $branchA->id,
            'name' => 'Store A1 - Downtown',
            'code' => 'ST-A1',
            'location' => 'Downtown'
        ]);

        $storeB1 = Store::create([
            'branch_id' => $branchB->id,
            'name' => 'Store B1 - North',
            'code' => 'ST-B1',
            'location' => 'North Side'
        ]);

        $storeB2 = Store::create([
            'branch_id' => $branchB->id,
            'name' => 'Store B2 - South',
            'code' => 'ST-B2',
            'location' => 'South Side'
        ]);

        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@kk.com',
            'password' => bcrypt('password'),
            'branch_id' => $branchA->id
        ]);
        $admin->assignRole('Administrator');

        // Create branch managers
        $branchManagerA = User::create([
            'name' => 'Branch Manager A',
            'email' => 'manager.a@kk.com',
            'password' => bcrypt('password'),
            'branch_id' => $branchA->id
        ]);
        $branchManagerA->assignRole('Branch Manager');

        $branchManagerB = User::create([
            'name' => 'Branch Manager B',
            'email' => 'manager.b@kk.com',
            'password' => bcrypt('password'),
            'branch_id' => $branchB->id
        ]);
        $branchManagerB->assignRole('Branch Manager');

        // Create store managers
        $storeManagerA1 = User::create([
            'name' => 'Store Manager A1',
            'email' => 'store.a1@kk.com',
            'password' => bcrypt('password'),
            'branch_id' => $branchA->id,
            'store_id' => $storeA1->id
        ]);
        $storeManagerA1->assignRole('Store Manager');

        $storeManagerB1 = User::create([
            'name' => 'Store Manager B1',
            'email' => 'store.b1@kk.com',
            'password' => bcrypt('password'),
            'branch_id' => $branchB->id,
            'store_id' => $storeB1->id
        ]);
        $storeManagerB1->assignRole('Store Manager');

        $storeManagerB2 = User::create([
            'name' => 'Store Manager B2',
            'email' => 'store.b2@kk.com',
            'password' => bcrypt('password'),
            'branch_id' => $branchB->id,
            'store_id' => $storeB2->id
        ]);
        $storeManagerB2->assignRole('Store Manager');

        // Create sample products
        $products = [];
        for ($i = 1; $i <= 10; $i++) {
            $products[] = Product::create([
                'sku' => 'SKU' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'name' => 'Product ' . $i,
                'barcode' => 'BAR' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'description' => 'Description for product ' . $i,
                'cost_price' => rand(10, 100),
                'selling_price' => rand(20, 150),
                'unit' => 'piece',
                'reorder_level' => 10
            ]);
        }
    }
}