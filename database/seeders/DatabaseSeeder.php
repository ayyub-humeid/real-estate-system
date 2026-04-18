<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
use App\Models\Payment;
use App\Models\Document;
use App\Models\MaintenanceRequest;
use App\Models\Expense;
use App\Models\RentalRequest;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Starting comprehensive seed...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $this->truncateAll();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ── Seed order matters ──────────────────────────────────────
        // Generate Shield Permissions and setup UI
        $this->command->info('🛡  Installing Shield & Generating permissions...');
        \Illuminate\Support\Facades\Artisan::call('shield:install', ['panel' => 'admin', '--no-interaction' => true]);
        
        $this->call(RolesAndPermissionsSeeder::class); 
        $this->call(PlanAndSubscriptionSeeder::class); 

        $this->seedLocations();
        $this->seedCompanies();
        $this->seedUsers();
        $this->seedProperties();
        $this->seedUnits();
        $this->seedUnitFeatures();
        $this->seedTenants();
        $this->seedLeases();
        $this->seedPayments();
        $this->seedMaintenanceRequests();
        $this->seedExpenses();
        $this->seedRentalRequests();
        $this->seedDocuments();

        $this->command->info('✅ All done! System is fully seeded.');
        $this->printLoginInfo();
    }

    // ──────────────────────────────────────────────────────────────
    // TRUNCATE
    // ──────────────────────────────────────────────────────────────
    private function truncateAll(): void
    {
        $tables = [
            'subscriptions', 'plans', 'documents', 'rental_requests', 'expenses',
            'maintenance_requests', 'payments', 'leases',
            'tenants', 'unit_features', 'units', 'properties',
            'locations', 'users', 'companies',
        ];

        // Also truncate images if the table exists
        if (DB::getSchemaBuilder()->hasTable('images')) {
            DB::table('images')->truncate();
        }

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        $this->command->info('🗑  Tables truncated.');
    }

    // ──────────────────────────────────────────────────────────────
    // 1. LOCATIONS
    // ──────────────────────────────────────────────────────────────
    private function seedLocations(): void
    {
        // Countries
        $palestine = Location::create(['name' => 'Palestine',   'type' => 'country']);
        $jordan    = Location::create(['name' => 'Jordan',      'type' => 'country']);
        $uae       = Location::create(['name' => 'UAE',         'type' => 'country']);

        // Cities
        $nablus    = Location::create(['name' => 'Nablus',      'type' => 'city',  'parent_id' => $palestine->id, 'latitude' => 32.2211, 'longitude' => 35.2544]);
        $ramallah  = Location::create(['name' => 'Ramallah',    'type' => 'city',  'parent_id' => $palestine->id, 'latitude' => 31.9022, 'longitude' => 35.2035]);
        $jerusalem = Location::create(['name' => 'Jerusalem',   'type' => 'city',  'parent_id' => $palestine->id, 'latitude' => 31.7683, 'longitude' => 35.2137]);
        $amman     = Location::create(['name' => 'Amman',       'type' => 'city',  'parent_id' => $jordan->id,    'latitude' => 31.9539, 'longitude' => 35.9106]);
        $dubai     = Location::create(['name' => 'Dubai',       'type' => 'city',  'parent_id' => $uae->id,       'latitude' => 25.2048, 'longitude' => 55.2708]);

        // Districts
        Location::create(['name' => 'Rafidia',       'type' => 'district', 'parent_id' => $nablus->id]);
        Location::create(['name' => 'Old City',      'type' => 'district', 'parent_id' => $nablus->id]);
        Location::create(['name' => 'Al-Masyoun',    'type' => 'district', 'parent_id' => $ramallah->id]);
        Location::create(['name' => 'Downtown',      'type' => 'district', 'parent_id' => $amman->id]);
        Location::create(['name' => 'Business Bay',  'type' => 'district', 'parent_id' => $dubai->id]);

        $this->command->info('📍 Locations seeded.');
    }

    // ──────────────────────────────────────────────────────────────
    // 2. COMPANIES
    // ──────────────────────────────────────────────────────────────
    private function seedCompanies(): void
    {
        Company::insert([
            [
                'name'      => 'Al-Nour Real Estate',
                'email'     => 'info@alnour.ps',
                'phone'     => '+970-9-2345678',
                'address'   => 'Rafidia Street, Nablus',
                'is_active' => true,
                'created_at'=> now(),
                'updated_at'=> now(),
            ],
            [
                'name'      => 'Horizon Properties',
                'email'     => 'contact@horizonprop.com',
                'phone'     => '+962-6-5678901',
                'address'   => 'King Abdullah Street, Amman',
                'is_active' => true,
                'created_at'=> now(),
                'updated_at'=> now(),
            ],
            [
                'name'      => 'GulfNest Realty',
                'email'     => 'hello@gulfnest.ae',
                'phone'     => '+971-4-3456789',
                'address'   => 'Business Bay, Dubai',
                'is_active' => true,
                'created_at'=> now(),
                'updated_at'=> now(),
            ],
            [
                'name'      => 'Inactive Corp',
                'email'     => 'inactive@test.com',
                'phone'     => '+970-1-0000000',
                'address'   => 'Nowhere',
                'is_active' => false,
                'created_at'=> now(),
                'updated_at'=> now(),
            ],
        ]);

        $this->command->info('🏢 Companies seeded.');
    }

    // ──────────────────────────────────────────────────────────────
    // 3. USERS
    // ──────────────────────────────────────────────────────────────
    private function seedUsers(): void
    {
        $company1 = Company::where('name', 'Al-Nour Real Estate')->first();
        $company2 = Company::where('name', 'Horizon Properties')->first();
        $company3 = Company::where('name', 'GulfNest Realty')->first();

        $users = [
            // Super Admin (no company)
            ['name' => 'Super Admin',        'email' => 'super@admin.com',       'role' => 'super_admin',      'company_id' => null,          'phone' => '+970-599-000001'],

            // Company 1 - Al-Nour
            ['name' => 'Ahmad Al-Nour',      'email' => 'admin@alnour.ps',        'role' => 'company_admin',    'company_id' => $company1->id, 'phone' => '+970-599-100001'],
            ['name' => 'Sara Manager',       'email' => 'sara@alnour.ps',         'role' => 'property_manager', 'company_id' => $company1->id, 'phone' => '+970-599-100002'],
            ['name' => 'Mohammed Manager',   'email' => 'mohammed@alnour.ps',     'role' => 'property_manager', 'company_id' => $company1->id, 'phone' => '+970-599-100003'],

            // Company 1 - Tenants
            ['name' => 'Khalid Tenant',      'email' => 'khalid@tenant.com',      'role' => 'tenant',           'company_id' => $company1->id, 'phone' => '+970-599-200001'],
            ['name' => 'Lina Tenant',        'email' => 'lina@tenant.com',        'role' => 'tenant',           'company_id' => $company1->id, 'phone' => '+970-599-200002'],
            ['name' => 'Omar Tenant',        'email' => 'omar@tenant.com',        'role' => 'tenant',           'company_id' => $company1->id, 'phone' => '+970-599-200003'],
            ['name' => 'Rania Tenant',       'email' => 'rania@tenant.com',       'role' => 'tenant',           'company_id' => $company1->id, 'phone' => '+970-599-200004'],
            ['name' => 'Yusuf Tenant',       'email' => 'yusuf@tenant.com',       'role' => 'tenant',           'company_id' => $company1->id, 'phone' => '+970-599-200005'],
            ['name' => 'Nour Tenant',        'email' => 'nour@tenant.com',        'role' => 'tenant',           'company_id' => $company1->id, 'phone' => '+970-599-200006'],

            // Company 2 - Horizon
            ['name' => 'Tariq Horizon',      'email' => 'admin@horizonprop.com',  'role' => 'company_admin',    'company_id' => $company2->id, 'phone' => '+962-79-100001'],
            ['name' => 'Dina Manager',       'email' => 'dina@horizonprop.com',   'role' => 'property_manager', 'company_id' => $company2->id, 'phone' => '+962-79-100002'],
            ['name' => 'Sami Tenant',        'email' => 'sami@tenant.com',        'role' => 'tenant',           'company_id' => $company2->id, 'phone' => '+962-79-200001'],
            ['name' => 'Hana Tenant',        'email' => 'hana@tenant.com',        'role' => 'tenant',           'company_id' => $company2->id, 'phone' => '+962-79-200002'],
            ['name' => 'Faris Tenant',       'email' => 'faris@tenant.com',       'role' => 'tenant',           'company_id' => $company2->id, 'phone' => '+962-79-200003'],

            // Company 3 - GulfNest
            ['name' => 'Zayed Gulf',         'email' => 'admin@gulfnest.ae',      'role' => 'company_admin',    'company_id' => $company3->id, 'phone' => '+971-50-100001'],
            ['name' => 'Maya Tenant',        'email' => 'maya@tenant.com',        'role' => 'tenant',           'company_id' => $company3->id, 'phone' => '+971-50-200001'],
            ['name' => 'Karim Tenant',       'email' => 'karim@tenant.com',       'role' => 'tenant',           'company_id' => $company3->id, 'phone' => '+971-50-200002'],
        ];

        foreach ($users as $user) {
            $password = ($user['email'] === 'super@admin.com') ? '123456' : 'password';
            
            User::create(array_merge($user, [
                'password'   => $password,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->command->info('👤 Users seeded.');
    }

    // ──────────────────────────────────────────────────────────────
    // 4. PROPERTIES
    // ──────────────────────────────────────────────────────────────
    private function seedProperties(): void
    {
        $company1 = Company::where('name', 'Al-Nour Real Estate')->first();
        $company2 = Company::where('name', 'Horizon Properties')->first();
        $company3 = Company::where('name', 'GulfNest Realty')->first();

        $nablus   = Location::where('name', 'Nablus')->first();
        $ramallah = Location::where('name', 'Ramallah')->first();
        $amman    = Location::where('name', 'Amman')->first();
        $dubai    = Location::where('name', 'Dubai')->first();

        $properties = [
            // Al-Nour — Nablus
            ['company_id' => $company1->id, 'location_id' => $nablus->id,   'name' => 'Al-Nour Tower',       'address' => 'Rafidia Street, Nablus',        'description' => 'Modern residential tower in the heart of Nablus with stunning city views.'],
            ['company_id' => $company1->id, 'location_id' => $nablus->id,   'name' => 'Green Valley Villas',  'address' => 'Old Nablus Road, Nablus',       'description' => 'Premium villa compound with private gardens and parking.'],
            ['company_id' => $company1->id, 'location_id' => $ramallah->id, 'name' => 'Ramallah Heights',    'address' => 'Al-Masyoun, Ramallah',          'description' => 'Luxury apartments with panoramic views over Ramallah.'],

            // Horizon — Amman
            ['company_id' => $company2->id, 'location_id' => $amman->id,    'name' => 'Horizon Residence',   'address' => 'Abdoun Circle, Amman',          'description' => 'High-end serviced apartments in Amman\'s most prestigious district.'],
            ['company_id' => $company2->id, 'location_id' => $amman->id,    'name' => 'Downtown Flats',      'address' => 'King Hussein Street, Amman',    'description' => 'Affordable flats in the commercial center of Amman.'],

            // GulfNest — Dubai
            ['company_id' => $company3->id, 'location_id' => $dubai->id,    'name' => 'GulfNest Marina',     'address' => 'Marina Walk, Dubai',            'description' => 'Waterfront luxury apartments in Dubai Marina.'],
            ['company_id' => $company3->id, 'location_id' => $dubai->id,    'name' => 'Bay View Tower',      'address' => 'Business Bay, Dubai',           'description' => 'Premium commercial and residential tower in Business Bay.'],
        ];

        foreach ($properties as $p) {
            Property::create(array_merge($p, ['created_at' => now(), 'updated_at' => now()]));
        }

        $this->command->info('🏠 Properties seeded.');
    }

    // ──────────────────────────────────────────────────────────────
    // 5. UNITS
    // ──────────────────────────────────────────────────────────────
    private function seedUnits(): void
    {
        $unitData = [];

        $propertyUnits = [
            'Al-Nour Tower' => [
                ['unit_number' => '101', 'type' => 'Apartment', 'rent_price' => 450,  'status' => 'occupied'],
                ['unit_number' => '102', 'type' => 'Apartment', 'rent_price' => 450,  'status' => 'occupied'],
                ['unit_number' => '103', 'type' => 'Apartment', 'rent_price' => 480,  'status' => 'available'],
                ['unit_number' => '201', 'type' => 'Apartment', 'rent_price' => 500,  'status' => 'occupied'],
                ['unit_number' => '202', 'type' => 'Studio',    'rent_price' => 300,  'status' => 'available'],
                ['unit_number' => '301', 'type' => 'Apartment', 'rent_price' => 550,  'status' => 'occupied'],
                ['unit_number' => '302', 'type' => 'Apartment', 'rent_price' => 550,  'status' => 'maintenance'],
            ],
            'Green Valley Villas' => [
                ['unit_number' => 'V-01', 'type' => 'Villa',    'rent_price' => 1200, 'status' => 'occupied'],
                ['unit_number' => 'V-02', 'type' => 'Villa',    'rent_price' => 1200, 'status' => 'available'],
                ['unit_number' => 'V-03', 'type' => 'Villa',    'rent_price' => 1350, 'status' => 'reserved'],
            ],
            'Ramallah Heights' => [
                ['unit_number' => 'A1',  'type' => 'Apartment', 'rent_price' => 600,  'status' => 'occupied'],
                ['unit_number' => 'A2',  'type' => 'Apartment', 'rent_price' => 620,  'status' => 'available'],
            ],
            'Horizon Residence' => [
                ['unit_number' => '1A',  'type' => 'Apartment', 'rent_price' => 800,  'status' => 'occupied'],
                ['unit_number' => '1B',  'type' => 'Apartment', 'rent_price' => 800,  'status' => 'occupied'],
                ['unit_number' => '2A',  'type' => 'Studio',    'rent_price' => 500,  'status' => 'available'],
                ['unit_number' => '2B',  'type' => 'Apartment', 'rent_price' => 850,  'status' => 'occupied'],
            ],
            'Downtown Flats' => [
                ['unit_number' => 'F1',  'type' => 'Apartment', 'rent_price' => 350,  'status' => 'available'],
                ['unit_number' => 'F2',  'type' => 'Apartment', 'rent_price' => 350,  'status' => 'occupied'],
            ],
            'GulfNest Marina' => [
                ['unit_number' => 'M101','type' => 'Apartment', 'rent_price' => 2500, 'status' => 'occupied'],
                ['unit_number' => 'M102','type' => 'Apartment', 'rent_price' => 2500, 'status' => 'available'],
                ['unit_number' => 'M201','type' => 'Apartment', 'rent_price' => 3000, 'status' => 'occupied'],
            ],
            'Bay View Tower' => [
                ['unit_number' => 'B01', 'type' => 'Office',    'rent_price' => 4000, 'status' => 'occupied'],
                ['unit_number' => 'B02', 'type' => 'Office',    'rent_price' => 3500, 'status' => 'available'],
                ['unit_number' => 'B03', 'type' => 'Apartment', 'rent_price' => 3200, 'status' => 'occupied'],
            ],
        ];

        foreach ($propertyUnits as $propertyName => $units) {
            $property = Property::where('name', $propertyName)->first();
            if (!$property) continue;
            foreach ($units as $unit) {
                Unit::create(array_merge($unit, [
                    'property_id' => $property->id,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]));
            }
        }

        $this->command->info('🏡 Units seeded.');
    }

    // ──────────────────────────────────────────────────────────────
    // 6. UNIT FEATURES
    // ──────────────────────────────────────────────────────────────
    private function seedUnitFeatures(): void
    {
        $featureMap = [
            '101' => [['name' => 'Balcony', 'value' => 'City View'], ['name' => 'AC', 'value' => '2 Units']],
            '201' => [['name' => 'Sea View', 'value' => 'Panoramic'], ['name' => 'Parking', 'value' => '1 Spot']],
            'V-01'=> [['name' => 'Garden', 'value' => '200 sqm'], ['name' => 'Pool', 'value' => 'Private'], ['name' => 'Parking', 'value' => '2 Cars']],
            'M101'=> [['name' => 'Marina View', 'value' => 'Full'], ['name' => 'Gym Access', 'value' => 'Included'], ['name' => 'Concierge', 'value' => '24/7']],
            'B01' => [['name' => 'Meeting Room', 'value' => '1 Room'], ['name' => 'High Speed Internet', 'value' => '1 Gbps']],
        ];

        foreach ($featureMap as $unitNumber => $features) {
            $unit = Unit::where('unit_number', $unitNumber)->first();
            if (!$unit) continue;
            foreach ($features as $f) {
                UnitFeature::create(array_merge($f, [
                    'unit_id'    => $unit->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        $this->command->info('✨ Unit features seeded.');
    }

    // ──────────────────────────────────────────────────────────────
    // 7. TENANTS
    // ──────────────────────────────────────────────────────────────
    private function seedTenants(): void
    {
        $tenantUsers = User::where('role', 'tenant')->get();

        $extras = [
            'khalid@tenant.com' => [
                'employer_name'  => 'Nablus Municipality',
                'job_title'      => 'Engineer',
                'monthly_income' => 2500,
                'id_type'        => 'national_id',
                'id_number'      => 'PS-123456',
                'id_expiry_date' => now()->addYears(3),
                'background_check_status' => 'approved',
                'background_check_date'   => now()->subMonths(2),
                'number_of_occupants'     => 3,
                'has_pets'                => false,
                'status'                  => 'active',
                'move_in_date'            => now()->subYear(),
            ],
            'lina@tenant.com' => [
                'employer_name'  => 'An-Najah University',
                'job_title'      => 'Lecturer',
                'monthly_income' => 3000,
                'id_type'        => 'passport',
                'id_number'      => 'A12345678',
                'id_expiry_date' => now()->addYears(5),
                'background_check_status' => 'approved',
                'background_check_date'   => now()->subMonth(),
                'number_of_occupants'     => 1,
                'has_pets'                => true,
                'pet_details'             => 'One small cat',
                'status'                  => 'active',
                'move_in_date'            => now()->subMonths(8),
            ],
            'omar@tenant.com' => [
                'employer_name'  => 'Self Employed',
                'job_title'      => 'Business Owner',
                'monthly_income' => 5000,
                'id_type'        => 'national_id',
                'id_number'      => 'PS-654321',
                'id_expiry_date' => now()->addYears(2),
                'background_check_status' => 'approved',
                'number_of_occupants'     => 4,
                'has_pets'                => false,
                'status'                  => 'active',
                'move_in_date'            => now()->subYears(2),
            ],
            'rania@tenant.com' => [
                'employer_name'  => 'Ministry of Health',
                'job_title'      => 'Doctor',
                'monthly_income' => 4000,
                'background_check_status' => 'pending',
                'number_of_occupants'     => 2,
                'has_pets'                => false,
                'status'                  => 'active',
                'move_in_date'            => now()->subMonths(3),
            ],
            'yusuf@tenant.com' => [
                'employer_name'  => 'Tech Startup',
                'job_title'      => 'Software Developer',
                'monthly_income' => 3500,
                'background_check_status' => 'approved',
                'number_of_occupants'     => 1,
                'has_pets'                => false,
                'status'                  => 'active',
                'move_in_date'            => now()->subMonths(5),
            ],
            'nour@tenant.com' => [
                'employer_name'  => 'Freelance',
                'job_title'      => 'Designer',
                'monthly_income' => 1800,
                'background_check_status' => 'not_required',
                'number_of_occupants'     => 2,
                'has_pets'                => false,
                'status'                  => 'active',
                'move_in_date'            => now()->subMonths(6),
            ],
            'sami@tenant.com' => [
                'employer_name'  => 'Arab Bank',
                'job_title'      => 'Branch Manager',
                'monthly_income' => 6000,
                'background_check_status' => 'approved',
                'number_of_occupants'     => 4,
                'has_pets'                => false,
                'status'                  => 'active',
                'move_in_date'            => now()->subYear(),
            ],
            'hana@tenant.com' => [
                'employer_name'  => 'USAID',
                'job_title'      => 'Project Manager',
                'monthly_income' => 7000,
                'background_check_status' => 'approved',
                'number_of_occupants'     => 2,
                'has_pets'                => true,
                'pet_details'             => 'Two dogs',
                'status'                  => 'active',
                'move_in_date'            => now()->subMonths(14),
            ],
            'faris@tenant.com' => [
                'employer_name'  => 'Jordan Telecom',
                'job_title'      => 'IT Specialist',
                'monthly_income' => 3200,
                'background_check_status' => 'rejected',
                'number_of_occupants'     => 3,
                'has_pets'                => false,
                'status'                  => 'blacklisted',
                'move_in_date'            => null,
            ],
            'maya@tenant.com' => [
                'employer_name'  => 'Emirates Airlines',
                'job_title'      => 'Cabin Crew',
                'monthly_income' => 8000,
                'background_check_status' => 'approved',
                'number_of_occupants'     => 1,
                'has_pets'                => false,
                'status'                  => 'active',
                'move_in_date'            => now()->subMonths(10),
            ],
            'karim@tenant.com' => [
                'employer_name'  => 'DAMAC Properties',
                'job_title'      => 'Sales Manager',
                'monthly_income' => 12000,
                'background_check_status' => 'approved',
                'number_of_occupants'     => 3,
                'has_pets'                => false,
                'status'                  => 'active',
                'move_in_date'            => now()->subMonths(7),
            ],
        ];

        foreach ($tenantUsers as $user) {
            $extra = $extras[$user->email] ?? [];
            Tenant::create(array_merge([
                'user_id'    => $user->id,
                'company_id' => $user->company_id,
                'status'     => 'active',
                'background_check_status' => 'pending',
                'number_of_occupants'     => 1,
                'has_pets'                => false,
                'references'              => [
                    ['name' => 'Ali Hassan', 'relationship' => 'Friend', 'phone' => '+970-599-111111', 'email' => 'ali@ref.com'],
                ],
                'emergency_contact_name'         => 'Family Member',
                'emergency_contact_phone'        => '+970-599-999999',
                'emergency_contact_relationship' => 'Father',
                'created_at' => now(),
                'updated_at' => now(),
            ], $extra));
        }

        $this->command->info('👥 Tenants seeded.');
    }

    // ──────────────────────────────────────────────────────────────
    // 8. LEASES
    // ──────────────────────────────────────────────────────────────
    private function seedLeases(): void
    {
        // Map: [unit_number => tenant_email, start_date_months_ago, end_date_months_from_now, status, rent]
        $leaseMap = [
            ['101',  'khalid@tenant.com',  12, 12, 'active',      450],
            ['102',  'lina@tenant.com',    8,  16, 'active',      450],
            ['201',  'omar@tenant.com',    24, 0,  'expired',     500],
            ['301',  'rania@tenant.com',   3,  9,  'active',      550],
            ['V-01', 'yusuf@tenant.com',   5,  7,  'active',      1200],
            ['A1',   'nour@tenant.com',    6,  6,  'active',      600],
            ['1A',   'sami@tenant.com',    10, 14, 'active',      800],
            ['1B',   'hana@tenant.com',    14, 10, 'active',      800],
            ['2B',   'faris@tenant.com',   2,  0,  'terminated',  850],
            ['M101', 'maya@tenant.com',    10, 2,  'active',      2500],
            ['M201', 'karim@tenant.com',   7,  5,  'active',      3000],
            ['B01',  'karim@tenant.com',   7,  5,  'active',      4000],
        ];

        foreach ($leaseMap as [$unitNum, $tenantEmail, $startAgo, $endIn, $status, $rent]) {
            $unit   = Unit::where('unit_number', $unitNum)->first();
            $tenant = Tenant::whereHas('user', fn($q) => $q->where('email', $tenantEmail))->first();

            if (!$unit || !$tenant) continue;

            $startDate = now()->subMonths($startAgo)->startOfMonth();
            $endDate   = $endIn > 0 ? now()->addMonths($endIn)->endOfMonth() : now()->subDay();

            $lease = Lease::create([
                'company_id'       => $unit->property->company_id ?? $tenant->company_id,
                'unit_id'          => $unit->id,
                'tenant_id'        => $tenant->id,
                'start_date'       => $startDate,
                'end_date'         => $endDate,
                'rent_amount'      => $rent,
                'deposit_amount'   => $rent * 2,
                'payment_frequency'=> 'monthly',
                'payment_day'      => 1,
                'status'           => $status,
                'termination_date' => $status === 'terminated' ? now()->subMonth() : null,
                'termination_reason'=> $status === 'terminated' ? 'Tenant violated contract terms' : null,
                'notes'            => "Lease for unit {$unitNum}",
                'created_at'       => $startDate,
                'updated_at'       => now(),
            ]);

            // Generate payment schedule for active leases
            if ($status === 'active') {
                $lease->generatePaymentSchedule();
            }
        }

        // ── One draft lease ────────────────────────────────────────
        $draftUnit = Unit::where('unit_number', '103')->first();
        if ($draftUnit) {
            $draftTenant = Tenant::whereHas('user', fn($q) => $q->where('email', 'khalid@tenant.com'))->first();
            if ($draftTenant) {
                Lease::create([
                    'company_id'        => $draftUnit->property->company_id,
                    'unit_id'           => $draftUnit->id,
                    'tenant_id'         => $draftTenant->id,
                    'start_date'        => now()->addMonth(),
                    'end_date'          => now()->addMonths(13),
                    'rent_amount'       => 480,
                    'deposit_amount'    => 960,
                    'payment_frequency' => 'monthly',
                    'payment_day'       => 1,
                    'status'            => 'draft',
                    'notes'             => 'Pending signature',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        }

        $this->command->info('📄 Leases seeded.');
    }

    // ──────────────────────────────────────────────────────────────
    // 9. PAYMENTS  (mark some as paid, some overdue, some partial)
    // ──────────────────────────────────────────────────────────────
    private function seedPayments(): void
    {
        $leases = Lease::where('status', 'active')->with('payments')->get();

        foreach ($leases as $lease) {
            foreach ($lease->payments as $payment) {
                if ($payment->due_date->isPast()) {
                    // Randomly: 80% paid, 10% partial, 10% overdue
                    $rand = rand(1, 10);
                    if ($rand <= 8) {
                        // Paid
                        $payment->update([
                            'status'         => 'paid',
                            'paid_amount'    => $payment->amount,
                            'remaining_amount'=> 0,
                            'payment_date'   => $payment->due_date->addDays(rand(0, 5)),
                            'payment_method' => collect(['cash', 'bank_transfer', 'check', 'online'])->random(),
                            'reference_number'=> 'REF-' . strtoupper(substr(md5(rand()), 0, 8)),
                        ]);
                    } elseif ($rand === 9) {
                        // Partial
                        $paidAmount = round($payment->amount * 0.6, 2);
                        $payment->update([
                            'status'          => 'partial',
                            'paid_amount'     => $paidAmount,
                            'remaining_amount'=> $payment->amount - $paidAmount,
                            'payment_date'    => $payment->due_date->addDays(rand(1, 10)),
                            'payment_method'  => 'cash',
                        ]);
                    } else {
                        // Overdue
                        $payment->update(['status' => 'overdue']);
                    }
                }
                // Future payments stay as 'pending'
            }
        }

        // Add a few standalone manual payments for variety
        $leases->each(function ($lease) {
            if (rand(0, 1)) {
                Payment::create([
                    'lease_id'         => $lease->id,
                    'amount'           => $lease->rent_amount,
                    'paid_amount'      => $lease->rent_amount,
                    'remaining_amount' => 0,
                    'due_date'         => now()->subMonths(rand(2, 4))->startOfMonth(),
                    'payment_date'     => now()->subMonths(rand(1, 3)),
                    'status'           => 'paid',
                    'payment_method'   => 'bank_transfer',
                    'reference_number' => 'MANUAL-' . strtoupper(substr(md5(rand()), 0, 6)),
                    'recorded_by'      => User::where('role', '!=', 'tenant')->inRandomOrder()->value('id'),
                    'created_at'       => now()->subMonths(2),
                    'updated_at'       => now(),
                ]);
            }
        });

        $this->command->info('💰 Payments seeded.');
    }

    // ──────────────────────────────────────────────────────────────
    // 10. MAINTENANCE REQUESTS
    // ──────────────────────────────────────────────────────────────
    private function seedMaintenanceRequests(): void
    {
        $units    = Unit::with('property')->get();
        $admins   = User::whereIn('role', ['company_admin', 'property_manager'])->get();

        $requests = [
            ['title' => 'Broken AC unit',              'description' => 'Air conditioning stopped working in the bedroom.',       'status' => 'resolved',     'priority' => 'high',      'estimated_cost' => 200,  'actual_cost' => 180],
            ['title' => 'Water leak in bathroom',      'description' => 'There is a water leak under the sink causing damage.',   'status' => 'in_progress',  'priority' => 'emergency', 'estimated_cost' => 350,  'actual_cost' => null],
            ['title' => 'Broken window latch',         'description' => 'The window latch on the balcony is broken.',             'status' => 'new',          'priority' => 'low',       'estimated_cost' => 50,   'actual_cost' => null],
            ['title' => 'Elevator malfunction',        'description' => 'Main elevator stopped on floor 2.',                     'status' => 'in_progress',  'priority' => 'emergency', 'estimated_cost' => 1500, 'actual_cost' => null],
            ['title' => 'Paint peeling in living room','description' => 'Wall paint is peeling near the window.',                'status' => 'pending',      'priority' => 'low',       'estimated_cost' => 120,  'actual_cost' => null],
            ['title' => 'Electrical socket sparking',  'description' => 'Kitchen socket sparks when appliances are plugged in.', 'status' => 'resolved',     'priority' => 'high',      'estimated_cost' => 80,   'actual_cost' => 95],
            ['title' => 'Door lock stuck',             'description' => 'Front door lock is jammed and hard to open.',           'status' => 'resolved',     'priority' => 'medium',    'estimated_cost' => 60,   'actual_cost' => 55],
            ['title' => 'Blocked drainage',            'description' => 'Shower drain is blocked and flooding.',                 'status' => 'new',          'priority' => 'high',      'estimated_cost' => 90,   'actual_cost' => null],
            ['title' => 'Heating system failure',      'description' => 'Central heating not working in winter.',                'status' => 'in_progress',  'priority' => 'high',      'estimated_cost' => 400,  'actual_cost' => null],
            ['title' => 'Pest control needed',         'description' => 'Spotted cockroaches in kitchen area.',                  'status' => 'resolved',     'priority' => 'medium',    'estimated_cost' => 150,  'actual_cost' => 150],
        ];

        $unitsList = $units->shuffle();

        foreach ($requests as $i => $req) {
            $unit  = $unitsList[$i % $unitsList->count()];
            $admin = $admins->where('company_id', $unit->property->company_id ?? null)->first() ?? $admins->first();

            MaintenanceRequest::create(array_merge($req, [
                'unit_id'         => $unit->id,
                'company_id'      => $unit->property->company_id ?? $admin->company_id,
                'reported_by_id'  => $admin->id,
                'assigned_to_id'  => $admin->id,
                'scheduled_at'    => now()->addDays(rand(1, 7)),
                'completed_at'    => $req['status'] === 'resolved' ? now()->subDays(rand(1, 10)) : null,
                'internal_notes'  => 'Inspected and work scheduled.',
                'created_at'      => now()->subDays(rand(5, 60)),
                'updated_at'      => now(),
            ]));
        }

        $this->command->info('🔧 Maintenance requests seeded.');
    }

    // ──────────────────────────────────────────────────────────────
    // 11. EXPENSES
    // ──────────────────────────────────────────────────────────────
    private function seedExpenses(): void
    {
        $companies  = Company::where('is_active', true)->get();
        $properties = Property::all();

        $expenseTemplates = [
            ['title' => 'Monthly Cleaning Service',   'category' => 'maintenance', 'amount' => 300,  'status' => 'paid',    'payment_method' => 'bank_transfer'],
            ['title' => 'Electricity Bill - March',   'category' => 'utilities',   'amount' => 450,  'status' => 'paid',    'payment_method' => 'bank_transfer'],
            ['title' => 'Water Bill - March',         'category' => 'utilities',   'amount' => 120,  'status' => 'paid',    'payment_method' => 'cash'],
            ['title' => 'Security Guard Salary',      'category' => 'salaries',    'amount' => 800,  'status' => 'paid',    'payment_method' => 'bank_transfer'],
            ['title' => 'Building Insurance Premium', 'category' => 'insurance',   'amount' => 1200, 'status' => 'paid',    'payment_method' => 'cheque'],
            ['title' => 'Property Tax Q1',            'category' => 'taxes',       'amount' => 950,  'status' => 'pending', 'payment_method' => null],
            ['title' => 'HVAC Annual Maintenance',    'category' => 'maintenance', 'amount' => 600,  'status' => 'paid',    'payment_method' => 'bank_transfer'],
            ['title' => 'Marketing - Social Media',   'category' => 'marketing',   'amount' => 250,  'status' => 'paid',    'payment_method' => 'card'],
            ['title' => 'Office Supplies',            'category' => 'other',       'amount' => 75,   'status' => 'paid',    'payment_method' => 'cash'],
            ['title' => 'Landscaping Service',        'category' => 'maintenance', 'amount' => 200,  'status' => 'pending', 'payment_method' => null],
            ['title' => 'Electricity Bill - April',   'category' => 'utilities',   'amount' => 480,  'status' => 'pending', 'payment_method' => null],
            ['title' => 'Internet & Telecom',         'category' => 'utilities',   'amount' => 150,  'status' => 'paid',    'payment_method' => 'bank_transfer'],
        ];

        $creator = User::whereIn('role', ['company_admin', 'property_manager'])->first();

        foreach ($companies as $company) {
            $companyProperties = $properties->where('company_id', $company->id);
            $property = $companyProperties->first();

            foreach ($expenseTemplates as $template) {
                Expense::create(array_merge($template, [
                    'company_id'       => $company->id,
                    'property_id'      => $property?->id,
                    'created_by'       => $creator->id,
                    'expense_date'     => now()->subDays(rand(1, 90)),
                    'paid_at'          => $template['status'] === 'paid' ? now()->subDays(rand(1, 30)) : null,
                    'reference_number' => 'EXP-' . strtoupper(substr(md5(rand()), 0, 6)),
                    'created_at'       => now()->subDays(rand(1, 90)),
                    'updated_at'       => now(),
                ]));
            }
        }

        $this->command->info('💸 Expenses seeded.');
    }

    // ──────────────────────────────────────────────────────────────
    // 12. RENTAL REQUESTS
    // ──────────────────────────────────────────────────────────────
    private function seedRentalRequests(): void
    {
        $tenants   = Tenant::with(['user', 'company'])->get();
        $companies = Company::where('is_active', true)->get();

        $requestTemplates = [
            ['title' => 'Looking for 2BR Apartment',      'status' => 'pending',   'priority' => 'high',   'preferred_type' => 'Apartment', 'max_budget' => 600,  'duration_months' => 12],
            ['title' => 'Need villa for family',           'status' => 'approved',  'priority' => 'high',   'preferred_type' => 'Villa',     'max_budget' => 1500, 'duration_months' => 24],
            ['title' => 'Studio for single professional',  'status' => 'pending',   'priority' => 'medium', 'preferred_type' => 'Studio',    'max_budget' => 400,  'duration_months' => 6],
            ['title' => 'Office space needed ASAP',        'status' => 'approved',  'priority' => 'high',   'preferred_type' => 'Office',    'max_budget' => 5000, 'duration_months' => 36],
            ['title' => 'Affordable apartment in city',    'status' => 'rejected',  'priority' => 'low',    'preferred_type' => 'Apartment', 'max_budget' => 250,  'duration_months' => 12],
            ['title' => '3BR flat for relocation',         'status' => 'pending',   'priority' => 'medium', 'preferred_type' => 'Apartment', 'max_budget' => 900,  'duration_months' => 12],
            ['title' => 'Short term rental 3 months',      'status' => 'cancelled', 'priority' => 'low',    'preferred_type' => 'Studio',    'max_budget' => 500,  'duration_months' => 3],
        ];

        foreach ($requestTemplates as $i => $template) {
            $tenant  = $tenants->where('status', 'active')->values()->get($i % $tenants->count());
            if (!$tenant) continue;

            RentalRequest::create(array_merge($template, [
                'tenant_id'        => $tenant->id,
                'company_id'       => $tenant->company_id,
                'description'      => "Detailed request: " . $template['title'],
                'desired_move_in'  => now()->addDays(rand(14, 60)),
                'admin_notes'      => $template['status'] !== 'pending' ? 'Reviewed by admin.' : null,
                'reviewed_at'      => in_array($template['status'], ['approved', 'rejected']) ? now()->subDays(rand(1, 10)) : null,
                'reviewed_by'      => in_array($template['status'], ['approved', 'rejected'])
                    ? User::where('role', 'company_admin')->where('company_id', $tenant->company_id)->value('id')
                    : null,
                'created_at'       => now()->subDays(rand(1, 30)),
                'updated_at'       => now(),
            ]));
        }

        $this->command->info('📋 Rental requests seeded.');
    }

    // ──────────────────────────────────────────────────────────────
    // 13. DOCUMENTS
    // ──────────────────────────────────────────────────────────────
    private function seedDocuments(): void
    {
        $leases   = Lease::all();
        $payments = Payment::where('status', 'paid')->take(10)->get();
        $uploader = User::whereIn('role', ['company_admin', 'property_manager'])->first();

        $docTypes = ['contract', 'receipt', 'invoice', 'id_document', 'proof_of_income', 'other'];

        // Lease documents
        foreach ($leases->take(8) as $lease) {
            Document::create([
                'documentable_id'   => $lease->id,
                'documentable_type' => Lease::class,
                'title'             => 'Lease Agreement - Unit ' . $lease->unit->unit_number ?? 'N/A',
                'file_name'         => 'lease_' . $lease->id . '.pdf',
                'file_path'         => 'documents/lease_' . $lease->id . '.pdf',
                'file_type'         => 'application/pdf',
                'file_size'         => rand(50000, 500000),
                'extension'         => 'pdf',
                'document_type'     => 'contract',
                'description'       => 'Signed lease agreement document.',
                'document_date'     => $lease->start_date,
                'uploaded_by'       => $uploader->id,
                'created_at'        => $lease->start_date,
                'updated_at'        => now(),
            ]);
        }

        // Payment receipts
        foreach ($payments->take(6) as $payment) {
            Document::create([
                'documentable_id'   => $payment->id,
                'documentable_type' => Payment::class,
                'title'             => 'Payment Receipt #' . $payment->id,
                'file_name'         => 'receipt_' . $payment->id . '.pdf',
                'file_path'         => 'documents/receipt_' . $payment->id . '.pdf',
                'file_type'         => 'application/pdf',
                'file_size'         => rand(20000, 100000),
                'extension'         => 'pdf',
                'document_type'     => 'receipt',
                'description'       => 'Official payment receipt.',
                'document_date'     => $payment->payment_date ?? now(),
                'uploaded_by'       => $uploader->id,
                'created_at'        => $payment->payment_date ?? now(),
                'updated_at'        => now(),
            ]);
        }

        // ID documents for tenants
        $tenants = Tenant::take(5)->get();
        foreach ($tenants as $tenant) {
            Document::create([
                'documentable_id'   => $tenant->leases()->first()?->id ?? $leases->first()->id,
                'documentable_type' => Lease::class,
                'title'             => 'ID Document - ' . ($tenant->user->name ?? 'Tenant'),
                'file_name'         => 'id_' . $tenant->id . '.jpg',
                'file_path'         => 'documents/id_' . $tenant->id . '.jpg',
                'file_type'         => 'image/jpeg',
                'file_size'         => rand(100000, 800000),
                'extension'         => 'jpg',
                'document_type'     => 'id_document',
                'description'       => 'National ID or passport copy.',
                'document_date'     => now()->subMonths(rand(1, 6)),
                'uploaded_by'       => $uploader->id,
                'created_at'        => now()->subMonths(rand(1, 6)),
                'updated_at'        => now(),
            ]);
        }

        $this->command->info('📁 Documents seeded.');
    }

    // ──────────────────────────────────────────────────────────────
    // PRINT LOGIN INFO
    // ──────────────────────────────────────────────────────────────
    private function printLoginInfo(): void
    {
        $this->command->newLine();
        $this->command->table(
            ['Role', 'Email', 'Password', 'Company'],
            [
                ['Super Admin',       'super@admin.com',       '123456', '—'],
                ['Company Admin',     'admin@alnour.ps',        'password', 'Al-Nour Real Estate'],
                ['Company Admin',     'admin@horizonprop.com',  'password', 'Horizon Properties'],
                ['Company Admin',     'admin@gulfnest.ae',      'password', 'GulfNest Realty'],
                ['Property Manager',  'sara@alnour.ps',         'password', 'Al-Nour Real Estate'],
                ['Tenant (Active)',   'khalid@tenant.com',      'password', 'Al-Nour Real Estate'],
                ['Tenant (Active)',   'maya@tenant.com',        'password', 'GulfNest Realty'],
                ['Tenant (Blacklisted)', 'faris@tenant.com',   'password', 'Horizon Properties'],
            ]
        );

        $this->command->newLine();
        $this->command->line('📊 <info>Seeded Summary:</info>');
        $this->command->line('   Locations:            ' . Location::count());
        $this->command->line('   Companies:            ' . Company::count());
        $this->command->line('   Users:                ' . User::count());
        $this->command->line('   Properties:           ' . Property::count());
        $this->command->line('   Units:                ' . Unit::count());
        $this->command->line('   Tenants:              ' . Tenant::count());
        $this->command->line('   Leases:               ' . Lease::count());
        $this->command->line('   Payments:             ' . Payment::count());
        $this->command->line('   Maintenance Requests: ' . MaintenanceRequest::count());
        $this->command->line('   Expenses:             ' . Expense::count());
        $this->command->line('   Rental Requests:      ' . RentalRequest::count());
        $this->command->line('   Documents:            ' . Document::count());
        $this->command->newLine();
        $this->command->line('🚀 Run: <comment>php artisan db:seed</comment> or <comment>php artisan migrate:fresh --seed</comment>');
    }
}