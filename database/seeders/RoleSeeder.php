<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;


class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       $roles = [
            ['name' => 'Administrator'],
            ['name' => 'Branch Manager'],
            ['name' => 'Store Manager'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate($role);
        }
        
        $this->command->info('Roles seeded successfully!');
    }
}
