<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Models
use App\Models\Company;
use App\Models\User;
use App\Models\Location;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitFeature;
use App\Models\Tenant;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Expense;
use App\Models\Image;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Starting comprehensive seed for presentation...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $this->truncateAll();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('🛡 Installing Shield & Generating permissions...');
        \Illuminate\Support\Facades\Artisan::call('shield:install', ['panel' => 'admin', '--no-interaction' => true]);
        
        $this->call(RolesAndPermissionsSeeder::class); 
        if (class_exists(PlanAndSubscriptionSeeder::class)) {
            $this->call(PlanAndSubscriptionSeeder::class); 
        }

        $this->seedCompaniesAndUsers();
        $this->seedLocations();
        $this->seedPropertiesAndUnits();
        $this->seedTenantsAndLeases();
        $this->seedMaintenanceRequests();
        $this->seedExpenses();

        $this->command->info('✅ All done! System is fully seeded with realistic demo data.');
        $this->printLoginInfo();
    }

    private function truncateAll(): void
    {
        $tables = [
            'subscriptions', 'plans', 'documents', 'rental_requests', 'expenses',
            'maintenance_requests', 'payments', 'leases',
            'tenants', 'unit_features', 'units', 'properties',
            'locations', 'users', 'companies', 'images'
        ];

        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->truncate();
            }
        }
        $this->command->info('🗑 Tables truncated.');
    }

    private function seedLocations(): void
    {
        $companies = Company::all();
        foreach ($companies as $company) {
            $country = Location::create(['name' => 'United Arab Emirates', 'type' => 'country', 'company_id' => $company->id]);
            Location::create(['name' => 'Dubai', 'type' => 'city', 'parent_id' => $country->id, 'company_id' => $company->id]);
            Location::create(['name' => 'Abu Dhabi', 'type' => 'city', 'parent_id' => $country->id, 'company_id' => $company->id]);
        }
    }

    private function seedCompaniesAndUsers(): void
    {
        // 1. Super Admin
        User::create([
            'name'       => 'Super Admin',
            'email'      => 'super@admin.com',
            'password'   => 'password',
            'role'       => 'super_admin',
            'company_id' => null,
        ]);

        // 2. Companies
        $companies = [
            [
                'name'    => 'Emaar Properties',
                'email'   => 'info@emaar.test',
                'phone'   => '+971-4-1234567',
                'address' => 'Downtown Dubai',
                'is_active' => true,
            ],
            [
                'name'    => 'Damac Properties',
                'email'   => 'info@damac.test',
                'phone'   => '+971-4-7654321',
                'address' => 'Business Bay, Dubai',
                'is_active' => true,
            ],
        ];

        foreach ($companies as $idx => $compData) {
            $company = Company::create($compData);

            // Company Admin
            User::create([
                'name'       => explode(' ', $company->name)[0] . ' Admin',
                'email'      => 'admin' . ($idx + 1) . '@company.com',
                'password'   => 'password',
                'role'       => 'company_admin',
                'company_id' => $company->id,
            ]);
        }
    }

    private function seedPropertiesAndUnits(): void
    {
        $dubai = Location::where('name', 'Dubai')->first();
        $companies = Company::all();

        $propertyImages = [
            'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1000&q=80',
            'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=1000&q=80',
        ];

        $unitImages = [
            'https://images.unsplash.com/photo-1502672260266-1c1de2d93688?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1502005097973-f542523f05fb?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1493809842364-78817add7ffb?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=800&q=80',
        ];

        foreach ($companies as $company) {
            for ($p = 1; $p <= 2; $p++) {
                $prop = Property::create([
                    'company_id'  => $company->id,
                    'location_id' => $dubai->id ?? null,
                    'name'        => $company->name . ' Residence ' . $p,
                    'address'     => 'Premium Location ' . $p . ', Dubai',
                    'description' => 'Luxury residential property featuring modern amenities, stunning views, and prime location for the ultimate urban lifestyle.',
                    'rent_price'  => 5000 + ($p * 1000),
                ]);

                // Property Image
                Image::create([
                    'imageable_type' => Property::class,
                    'imageable_id'   => $prop->id,
                    'path'           => $propertyImages[array_rand($propertyImages)],
                    'disk'           => 'public',
                    'is_primary'     => true,
                    'order'          => 1,
                ]);

                // Units for this property
                for ($u = 1; $u <= 4; $u++) {
                    $unit = Unit::create([
                        'company_id'  => $company->id,
                        'property_id' => $prop->id,
                        'unit_number' => 'A-' . $p . '0' . $u,
                        'rent_price'  => rand(1500, 3500),
                        'status'      => ($u % 2 == 0) ? 'occupied' : 'available',
                        'type'        => 'Apartment',
                        'description' => 'Beautifully designed apartment with spacious living areas and premium finishing.',
                        'bedrooms'    => rand(1, 3),
                        'bathrooms'   => rand(1, 2),
                        'sqft'        => rand(800, 2000),
                        'is_featured' => ($u == 1),
                    ]);

                    // Unit Image
                    Image::create([
                        'imageable_type' => Unit::class,
                        'imageable_id'   => $unit->id,
                        'path'           => $unitImages[array_rand($unitImages)],
                        'disk'           => 'public',
                        'is_primary'     => true,
                        'order'          => 1,
                    ]);

                    UnitFeature::create(['unit_id' => $unit->id, 'name' => 'Balcony', 'value' => 'Yes']);
                    UnitFeature::create(['unit_id' => $unit->id, 'name' => 'Parking', 'value' => '1 Spot']);
                }
            }
        }
    }

    private function seedTenantsAndLeases(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            $occupiedUnits = Unit::where('company_id', $company->id)->where('status', 'occupied')->get();
            
            foreach ($occupiedUnits as $idx => $unit) {
                // Create Tenant User
                $user = User::create([
                    'name'       => 'Tenant ' . $idx . ' ' . $company->name,
                    'email'      => 'tenant' . $idx . '_' . $company->id . '@tenant.com',
                    'password'   => 'password',
                    'role'       => 'tenant',
                    'company_id' => $company->id,
                ]);

                // Create Tenant Profile
                $tenant = Tenant::create([
                    'user_id'                 => $user->id,
                    'company_id'              => $company->id,
                    'employer_name'           => 'Global Corp',
                    'monthly_income'          => $unit->rent_price * 3,
                    'status'                  => 'active',
                    'background_check_status' => 'approved',
                    'move_in_date'            => now()->subMonths(rand(1, 10)),
                ]);

                // Create Lease (without generating payments)
                Lease::create([
                    'company_id'        => $company->id,
                    'unit_id'           => $unit->id,
                    'tenant_id'         => $tenant->id,
                    'start_date'        => now()->subMonths(rand(1, 6))->startOfMonth(),
                    'end_date'          => now()->addMonths(rand(6, 12))->endOfMonth(),
                    'rent_amount'       => $unit->rent_price,
                    'deposit_amount'    => $unit->rent_price * 1.5,
                    'payment_frequency' => 'monthly',
                    'payment_day'       => 1,
                    'status'            => 'active',
                    'notes'             => 'Initial lease contract signed.',
                ]);
            }
        }
    }

    private function seedMaintenanceRequests(): void
    {
        $leases = Lease::with('unit.property')->get();

        foreach ($leases as $lease) {
            if (rand(0, 1)) {
                $status = collect(['new', 'in_progress', 'resolved'])->random();
                MaintenanceRequest::create([
                    'company_id'      => $lease->company_id,
                    'unit_id'         => $lease->unit_id,
                    'reported_by_id'  => User::where('role', 'company_admin')->where('company_id', $lease->company_id)->first()->id,
                    'title'           => collect(['AC not cooling', 'Plumbing issue', 'Electrical fault', 'Broken window'])->random(),
                    'description'     => 'Tenant reported an issue that requires urgent attention from maintenance team.',
                    'priority'        => collect(['low', 'medium', 'high', 'emergency'])->random(),
                    'status'          => $status,
                    'estimated_cost'  => rand(50, 500),
                    'actual_cost'     => $status == 'resolved' ? rand(50, 500) : null,
                    'created_at'      => now()->subDays(rand(1, 30)),
                    'updated_at'      => now(),
                ]);
            }
        }
    }

    private function seedExpenses(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            $admin = User::where('role', 'company_admin')->where('company_id', $company->id)->first();
            
            $expenses = [
                ['title' => 'Building Deep Cleaning', 'category' => 'maintenance', 'amount' => 450],
                ['title' => 'Q3 Property Taxes', 'category' => 'taxes', 'amount' => 1200],
                ['title' => 'Landscaping & Gardening', 'category' => 'maintenance', 'amount' => 200],
                ['title' => 'Electricity - Common Areas', 'category' => 'utilities', 'amount' => 350],
            ];

            foreach ($expenses as $exp) {
                Expense::create([
                    'company_id'     => $company->id,
                    'created_by'     => $admin->id,
                    'title'          => $exp['title'],
                    'category'       => $exp['category'],
                    'amount'         => $exp['amount'],
                    'status'         => collect(['paid', 'pending'])->random(),
                    'expense_date'   => now()->subDays(rand(1, 60)),
                ]);
            }
        }
    }

    private function printLoginInfo(): void
    {
        $this->command->newLine();
        $this->command->table(
            ['Role', 'Email', 'Password', 'Company'],
            [
                ['Super Admin',   'super@admin.com',    'password', '—'],
                ['Company Admin', 'admin1@company.com', 'password', 'Emaar Properties'],
                ['Company Admin', 'admin2@company.com', 'password', 'Damac Properties'],
            ]
        );
        $this->command->newLine();
    }
}