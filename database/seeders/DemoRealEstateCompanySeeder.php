<?php

namespace Database\Seeders;

use App\Enums\ContractApprovalStatus;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\ContractVersionStatus;
use App\Enums\CrmInquirySource;
use App\Enums\CrmStageType;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\OpportunityType;
use App\Enums\RentFrequency;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\AcquisitionParty;
use App\Models\AcquisitionProperty;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractApproval;
use App\Models\ContractParty;
use App\Models\ContractSignature;
use App\Models\ContractStatusHistory;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ContractTerm;
use App\Models\ContractVersion;
use App\Models\CrmActivity;
use App\Models\CrmFollowUpTask;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\DueDiligenceCase;
use App\Models\DueDiligenceItem;
use App\Models\Employee;
use App\Models\FinancialCommitment;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\Party;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectBuilding;
use App\Models\ProjectBuildingFloor;
use App\Models\ProjectConstruction;
use App\Models\ProjectDesignPackage;
use App\Models\ProjectMember;
use App\Models\ProjectPlannedUnit;
use App\Models\ProjectProperty;
use App\Models\Property;
use App\Models\PropertyAcquisition;
use App\Models\PropertyOwnership;
use App\Models\Subscription;
use App\Models\Unit;
use App\Models\UnitFeature;
use App\Models\UnitReservation;
use App\Models\User;
use App\Services\CompanyRoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

class DemoRealEstateCompanySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🏗 Seeding Realistic Real Estate Company: شركة دار الأفق للتطوير العقاري...');

        // 1. Company
        $company = Company::updateOrCreate(
            ['email' => 'demo@dar-alofok.com'],
            [
                'name' => 'شركة دار الأفق للتطوير العقاري',
                'phone' => '+966-11-2345678',
                'address' => 'طريق الملك فهد، حي الصحافة، الرياض، المملكة العربية السعودية',
                'is_active' => true,
            ]
        );

        // 2. Subscription (Enterprise - Unlimited power for demo)
        $enterprisePlan = Plan::firstOrCreate(
            ['slug' => 'enterprise'],
            [
                'name' => 'Enterprise',
                'description' => 'Unlimited power for large corporations.',
                'price' => 199.00,
                'billing_cycle' => 'monthly',
                'features' => [
                    'max_properties' => 9999,
                    'max_units' => 9999,
                    'max_employees' => 500,
                    'max_users' => 500,
                    'maintenance_tracking' => true,
                    'rental_requests' => true,
                    'expense_tracking' => true,
                    'document_management' => true,
                    'advanced_reports' => true,
                    'export_data' => true,
                ],
                'is_active' => true,
            ]
        );

        Subscription::updateOrCreate(
            ['company_id' => $company->id],
            [
                'plan_id' => $enterprisePlan->id,
                'status' => 'active',
                'starts_at' => now()->subMonths(6),
                'ends_at' => now()->addYears(5),
            ]
        );

        // 3. Provision Roles & Permissions
        $roleService = app(CompanyRoleService::class);
        $companyRoles = $roleService->provisionDefaults($company);
        $companyAdminRole = $companyRoles->get('company_admin');

        // Grant ALL non-platform permissions to company admin for the demo
        if ($companyAdminRole) {
            $allPerms = Permission::all()->reject(function (Permission $p): bool {
                return in_array($p->name, ['manage_subscriptions', 'view_any_plan', 'create_plan', 'update_plan', 'delete_plan', 'view_any_company', 'create_company', 'delete_company'], true);
            });
            $companyAdminRole->syncPermissions($allPerms);
        }

        // 4. Users
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@dar-alofok.com'],
            [
                'company_id' => $company->id,
                'name' => 'م. سلطان بن عبد العزيز الراجحي',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'phone' => '+966501234567',
                'locale' => 'ar',
            ]
        );
        $roleService->assignNamedRole($adminUser, 'company_admin');

        $salesUser = User::updateOrCreate(
            ['email' => 'sales@dar-alofok.com'],
            [
                'company_id' => $company->id,
                'name' => 'عبدالله بن فهد الشمري',
                'password' => Hash::make('password'),
                'role' => 'property_manager',
                'phone' => '+966502345678',
                'locale' => 'ar',
            ]
        );
        $roleService->assignNamedRole($salesUser, 'property_manager');

        $legalUser = User::updateOrCreate(
            ['email' => 'legal@dar-alofok.com'],
            [
                'company_id' => $company->id,
                'name' => 'أ. فيصل بن خالد الحربي',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'phone' => '+966503456789',
                'locale' => 'ar',
            ]
        );
        $roleService->assignNamedRole($legalUser, 'company_admin');

        // 5. Employees
        Employee::updateOrCreate(
            ['user_id' => $adminUser->id],
            [
                'company_id' => $company->id,
                'employee_id' => 'EMP-001',
                'position' => 'الرئيس التنفيذي (CEO)',
                'department' => 'الإدارة التنفيذية العليا',
                'hire_date' => '2023-01-01',
                'salary' => 65000.00,
            ]
        );
        Employee::updateOrCreate(
            ['user_id' => $salesUser->id],
            [
                'company_id' => $company->id,
                'employee_id' => 'EMP-002',
                'position' => 'مدير إدارة المبيعات والتسويق',
                'department' => 'المبيعات وتطوير الأعمال',
                'hire_date' => '2023-06-01',
                'salary' => 32000.00,
            ]
        );
        Employee::updateOrCreate(
            ['user_id' => $legalUser->id],
            [
                'company_id' => $company->id,
                'employee_id' => 'EMP-003',
                'position' => 'المستشار القانوني ومدير العقود',
                'department' => 'الشؤون القانونية والالتزام',
                'hire_date' => '2023-03-15',
                'salary' => 38000.00,
            ]
        );

        // 6. Locations
        $country = Location::firstOrCreate(
            ['name' => 'المملكة العربية السعودية', 'company_id' => $company->id],
            ['type' => 'country']
        );
        $city = Location::firstOrCreate(
            ['name' => 'الرياض', 'company_id' => $company->id],
            ['type' => 'city', 'parent_id' => $country->id]
        );
        $districtSahafa = Location::firstOrCreate(
            ['name' => 'حي الصحافة', 'company_id' => $company->id],
            ['type' => 'district', 'parent_id' => $city->id]
        );
        $districtNarjis = Location::firstOrCreate(
            ['name' => 'حي النرجس', 'company_id' => $company->id],
            ['type' => 'district', 'parent_id' => $city->id]
        );

        // 7. Parties
        $developerParty = Party::updateOrCreate(
            ['company_id' => $company->id, 'registration_number' => '1010778899'],
            [
                'type' => 'company',
                'name' => 'شركة دار الأفق للتطوير العقاري',
                'legal_name' => 'شركة دار الأفق للتطوير العقاري ذ.م.م',
                'tax_number' => '300998877600003',
                'phone' => '+966-11-2345678',
                'email' => 'contracts@dar-alofok.com',
                'address' => 'طريق الملك فهد، حي الصحافة، الرياض',
                'is_active' => true,
            ]
        );

        $sellerParty = Party::updateOrCreate(
            ['company_id' => $company->id, 'registration_number' => '1010223344'],
            [
                'type' => 'company',
                'name' => 'مجموعة بن لادن للاستثمار وتطوير الأراضي',
                'legal_name' => 'مجموعة بن لادن للاستثمار العقاري ش.م.م',
                'tax_number' => '300112233400003',
                'phone' => '+966-11-4567890',
                'email' => 'lands@binladin-invest.com',
                'address' => 'طريق مكة المكرمة، الرياض',
                'is_active' => true,
            ]
        );

        $buyerTurki = Party::updateOrCreate(
            ['company_id' => $company->id, 'national_id' => '1087654321'],
            [
                'type' => 'person',
                'name' => 'د. تركي بن ناصر السبيعي',
                'legal_name' => 'تركي ناصر سعد السبيعي',
                'phone' => '0551122334',
                'email' => 'turki.subaie@example.com',
                'address' => 'الرياض، حي الملقا، شارع الأمير محمد بن سعد',
                'is_active' => true,
            ]
        );

        $buyerNouf = Party::updateOrCreate(
            ['company_id' => $company->id, 'national_id' => '1098765432'],
            [
                'type' => 'person',
                'name' => 'أ. نوف بنت عبدالله التميمي',
                'legal_name' => 'نوف عبدالله إبراهيم التميمي',
                'phone' => '0504455667',
                'email' => 'nouf.tamimi@example.com',
                'address' => 'الرياض، حي النرجس، شارع عثمان بن عفان',
                'is_active' => true,
            ]
        );

        $tenantMuhaidib = Party::updateOrCreate(
            ['company_id' => $company->id, 'registration_number' => '1010334455'],
            [
                'type' => 'company',
                'name' => 'مجموعة المهيدب للتجزئة والأسواق التجارية',
                'legal_name' => 'شركة المهيدب للتجزئة المساهمة المقفلة',
                'tax_number' => '300554433200003',
                'phone' => '0118899000',
                'email' => 'corporate@almuhaidib.example.com',
                'address' => 'الرياض، طريق خريص',
                'is_active' => true,
            ]
        );

        // 8. Land Property & Land Acquisition (الأراضي والاستحواذ)
        $landProperty = Property::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'أرض مشروع واحة النرجس - الرياض'],
            [
                'location_id' => $districtNarjis->id,
                'address' => 'مخطط رقم 43/أ، حي النرجس، شمال الرياض',
                'type' => 'land',
                'space' => 15000.00,
                'description' => 'أرض خام معتمدة تجاري وسكني بموقع استراتيجي على طريق التخصصي وحي النرجس',
            ]
        );

        $acquisition = PropertyAcquisition::updateOrCreate(
            ['company_id' => $company->id, 'reference_number' => 'ACQ-2025-001'],
            [
                'type' => 'cash_purchase',
                'status' => 'completed',
                'acquisition_date' => '2025-01-10',
                'agreed_value' => 45000000.00,
                'currency' => 'SAR',
                'description' => 'شراء أرض خام بمخطط النرجس لغرض إنشاء وتطوير مشروع برج سكني تجاري متكامل',
                'approved_by' => $adminUser->id,
                'approved_at' => '2025-01-12 10:00:00',
                'completed_at' => '2025-01-15 14:00:00',
            ]
        );

        AcquisitionParty::firstOrCreate(
            ['company_id' => $company->id, 'property_acquisition_id' => $acquisition->id, 'party_id' => $sellerParty->id],
            ['role' => 'seller', 'share_percentage' => 100.00, 'notes' => 'المالك السابق للأرض']
        );
        AcquisitionParty::firstOrCreate(
            ['company_id' => $company->id, 'property_acquisition_id' => $acquisition->id, 'party_id' => $developerParty->id],
            ['role' => 'buyer', 'share_percentage' => 100.00, 'notes' => 'المطور والمشتري الرئيسي']
        );

        AcquisitionProperty::firstOrCreate(
            ['company_id' => $company->id, 'property_acquisition_id' => $acquisition->id, 'property_id' => $landProperty->id],
            ['share_percentage' => 100.00, 'allocated_value' => 45000000.00]
        );

        $ddCase = DueDiligenceCase::firstOrCreate(
            ['company_id' => $company->id, 'property_acquisition_id' => $acquisition->id],
            [
                'property_id' => $landProperty->id,
                'status' => 'cleared',
                'summary' => 'تم استيفاء جميع المتطلبات القانونية والتخطيطية وفحص التربة واعتماد اشتراطات أمانة الرياض',
                'opened_at' => '2025-01-10 11:00:00',
                'completed_at' => '2025-01-14 16:00:00',
                'opened_by' => $adminUser->id,
                'completed_by' => $legalUser->id,
            ]
        );

        DueDiligenceItem::firstOrCreate(
            ['company_id' => $company->id, 'due_diligence_case_id' => $ddCase->id, 'title' => 'فحص سريان الصك العقاري ومطابقة الرفع المساحي'],
            [
                'category' => 'legal',
                'description' => 'التحقق من خلو الصك من أي رهونات أو نزاعات قانونية لدى كتابة العدل الإلكترونية',
                'is_required' => true,
                'status' => 'passed',
                'checked_by' => $legalUser->id,
                'checked_at' => '2025-01-12 12:00:00',
                'notes' => 'الصك إلكتروني ومطابق 100%',
            ]
        );

        DueDiligenceItem::firstOrCreate(
            ['company_id' => $company->id, 'due_diligence_case_id' => $ddCase->id, 'title' => 'تقرير جسات التربة والمناسيب الهيدرولوجية'],
            [
                'category' => 'technical',
                'description' => 'فحص قدرة تحمل التربة للأبراج السكنية ومستوى المياه الجوفية',
                'is_required' => true,
                'status' => 'passed',
                'checked_by' => $adminUser->id,
                'checked_at' => '2025-01-13 14:00:00',
                'notes' => 'التربة صخرية صلبة ومثالية للأبراج العالية',
            ]
        );

        PropertyOwnership::firstOrCreate(
            ['company_id' => $company->id, 'property_id' => $landProperty->id, 'party_id' => $developerParty->id],
            [
                'acquisition_id' => $acquisition->id,
                'ownership_percentage' => 100.00,
                'start_date' => '2025-01-15',
                'notes' => 'ملكية كاملة ومفرغة بموجب الصك الإلكتروني',
            ]
        );

        // 9. Project (المشروع التطويري)
        $project = Project::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'مشروع برج الأفق السكني والتجاري'],
            [
                'project_type' => 'mixed_use',
                'currency' => 'SAR',
                'status' => 'approved',
                'start_date' => '2025-02-01',
                'expected_completion_date' => '2027-04-30',
                'description' => 'مشروع تطوير برج سكني تجاري فاخر من 12 دوراً سكنياً وبوليفارد معارض تجارية ومرافق ذكية',
                'approved_by' => $adminUser->id,
                'approved_at' => '2025-02-05 09:00:00',
            ]
        );

        ProjectProperty::firstOrCreate(
            ['project_id' => $project->id, 'property_id' => $landProperty->id],
            ['company_id' => $company->id, 'attached_at' => now()->subMonths(8)]
        );

        ProjectMember::firstOrCreate(
            ['project_id' => $project->id, 'user_id' => $adminUser->id],
            ['company_id' => $company->id, 'role' => 'project_director', 'started_at' => now()->subMonths(8)]
        );
        ProjectMember::firstOrCreate(
            ['project_id' => $project->id, 'user_id' => $salesUser->id],
            ['company_id' => $company->id, 'role' => 'sales_lead', 'started_at' => now()->subMonths(8)]
        );

        // Project Buildings & Floors
        $buildingA = ProjectBuilding::firstOrCreate(
            ['company_id' => $company->id, 'project_id' => $project->id, 'code' => 'BLD-A'],
            [
                'name' => 'برج النرجس السكني - البرج الشمالي',
                'building_type' => 'residential_tower',
                'description' => 'برج سكني فاخر يضم شققاً عائلية وأجنحة بنتهاوس بإطلالات بانورامية',
                'sort_order' => 1,
                'status' => 'in_progress',
            ]
        );

        $buildingB = ProjectBuilding::firstOrCreate(
            ['company_id' => $company->id, 'project_id' => $project->id, 'code' => 'BLD-B'],
            [
                'name' => 'بوليفارد الأفق التجاري - المبنى الجنوبي',
                'building_type' => 'commercial',
                'description' => 'مجمع تجاري يضم معارض تجارية راقية ومطاعم ومقاهي فاخرة',
                'sort_order' => 2,
                'status' => 'in_progress',
            ]
        );

        $floorG = ProjectBuildingFloor::firstOrCreate(
            ['company_id' => $company->id, 'project_building_id' => $buildingA->id, 'floor_number' => 0],
            ['label' => 'الدور الأرضي - بهو الاستقبال والمرافق', 'sort_order' => 0]
        );
        $floor3 = ProjectBuildingFloor::firstOrCreate(
            ['company_id' => $company->id, 'project_building_id' => $buildingA->id, 'floor_number' => 3],
            ['label' => 'الدور الثالث - أجنحة سكنية ديلوكس', 'sort_order' => 3]
        );
        $floor10 = ProjectBuildingFloor::firstOrCreate(
            ['company_id' => $company->id, 'project_building_id' => $buildingA->id, 'floor_number' => 10],
            ['label' => 'الدور العاشر - أجنحة البنتهاوس الملكية', 'sort_order' => 10]
        );
        $floorCommG = ProjectBuildingFloor::firstOrCreate(
            ['company_id' => $company->id, 'project_building_id' => $buildingB->id, 'floor_number' => 0],
            ['label' => 'الدور الأرضي - واجهة البوليفارد التجاري', 'sort_order' => 0]
        );

        // Planned Units
        $plannedPenthouse = ProjectPlannedUnit::firstOrCreate(
            ['company_id' => $company->id, 'project_building_floor_id' => $floor10->id, 'code' => 'PLN-101'],
            [
                'unit_type' => 'penthouse',
                'planned_area' => 320.00,
                'status' => 'converted',
                'description' => 'بنتهاوس فاخر بتراس بانورامي مفتوح وتصميم معاصر',
                'sort_order' => 1,
            ]
        );

        $plannedApt301 = ProjectPlannedUnit::firstOrCreate(
            ['company_id' => $company->id, 'project_building_floor_id' => $floor3->id, 'code' => 'PLN-301'],
            [
                'unit_type' => 'apartment',
                'planned_area' => 195.00,
                'status' => 'converted',
                'description' => 'شقة 3 غرف وصالة مع غرفة خادمة ومجلس ضيوف مستقل',
                'sort_order' => 2,
            ]
        );

        $plannedApt302 = ProjectPlannedUnit::firstOrCreate(
            ['company_id' => $company->id, 'project_building_floor_id' => $floor3->id, 'code' => 'PLN-302'],
            [
                'unit_type' => 'apartment',
                'planned_area' => 130.00,
                'status' => 'converted',
                'description' => 'شقة غرفتين وصالة مؤثثة بتشطيبات فندقية راقية',
                'sort_order' => 3,
            ]
        );

        $plannedCommercial = ProjectPlannedUnit::firstOrCreate(
            ['company_id' => $company->id, 'project_building_floor_id' => $floorCommG->id, 'code' => 'PLN-G01'],
            [
                'unit_type' => 'retail',
                'planned_area' => 210.00,
                'status' => 'converted',
                'description' => 'معرض تجاري رئيسي بواجهة زجاجية مزدوجة على طريق الملك فهد',
                'sort_order' => 4,
            ]
        );

        // Project Design Package
        ProjectDesignPackage::firstOrCreate(
            ['company_id' => $company->id, 'project_id' => $project->id, 'code' => 'DES-OFK-01'],
            [
                'name' => 'حزمة التصاميم المعمارية والمخططات التنفيذية الكاملة',
                'discipline' => 'architectural',
                'description' => 'المخططات الهندسية المعتمدة لكامل المشروع والواجهات والمرافق المشتركة',
                'status' => 'approved',
                'target_submission_date' => '2025-02-15',
                'approved_at' => '2025-02-20 11:00:00',
            ]
        );

        // Project Budget
        $budget = ProjectBudget::firstOrCreate(
            ['company_id' => $company->id, 'project_id' => $project->id, 'version_number' => 1],
            [
                'name' => 'الميزانية التقديرية العامة لتطوير برج الأفق',
                'notes' => 'الميزانية المعتمدة من مجلس الإدارة لكامل أعمال الإنشاء والتشطيبات والمرافق',
            ]
        );
        $budget->update(['status' => 'approved', 'approved_at' => '2025-02-25 15:00:00']);

        $budgetLine = \App\Models\BudgetLine::firstOrCreate([
            'company_id' => $company->id,
            'project_id' => $project->id,
        ]);

        $catCivil = BudgetCategory::firstOrCreate(
            ['company_id' => $company->id, 'project_budget_id' => $budget->id, 'name' => 'الأعمال المدنية والإنشائية والأساسات'],
            ['code' => 'CAT-01', 'sort_order' => 1]
        );
        $budgetItem = BudgetItem::firstOrCreate(
            ['company_id' => $company->id, 'budget_category_id' => $catCivil->id, 'name' => 'الحفر وتدعيم الجوانب وصب الأساسات العميقة'],
            ['budget_line_id' => $budgetLine->id, 'code' => 'ITEM-01', 'planned_amount' => 15000000.00]
        );

        // Financial Commitment
        FinancialCommitment::firstOrCreate(
            ['company_id' => $company->id, 'project_id' => $project->id, 'reference_number' => 'COM-2025-01'],
            [
                'party_id' => $sellerParty->id,
                'budget_item_id' => $budgetItem->id,
                'budget_line_id' => $budgetLine->id,
                'description' => 'عقد مقاولة تنفيذ الهيكل الخرساني والإنشائي الكامل',
                'amount' => 40000000.00,
                'currency' => 'SAR',
                'budget_amount' => 40000000.00,
                'exchange_rate_to_budget' => 1.00000000,
                'notes' => 'العقد الرئيسي للمقاولة الإنشائية',
                'created_by' => $adminUser->id,
            ]
        );

        // Project Construction
        ProjectConstruction::firstOrCreate(
            ['company_id' => $company->id, 'project_id' => $project->id, 'execution_number' => 1],
            [
                'planned_start_date' => '2025-03-01',
                'expected_completion_date' => '2027-02-28',
                'actual_start_date' => '2025-03-10',
                'manager_party_id' => $developerParty->id,
                'notes' => 'تم إنجاز أعمال الحفر والأساسات والبدء في صب أعمدة الأدوار السكنية بنسبة إنجاز 42%',
            ]
        );

        // 10. Developed Property & Actual Physical Units (الوحدات العقارية الحالية)
        $developedProperty = Property::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'برج الأفق السكني والتجاري'],
            [
                'location_id' => $districtSahafa->id,
                'address' => 'طريق الملك فهد، حي الصحافة، الرياض',
                'type' => 'building',
                'space' => 18500.00,
                'description' => 'برج الأفق الفاخر بموقعه المميز على طريق الملك فهد بالرياض',
            ]
        );

        $unit101 = Unit::updateOrCreate(
            ['company_id' => $company->id, 'property_id' => $developedProperty->id, 'unit_number' => 'APT-101'],
            [
                'project_id' => $project->id,
                'planned_unit_id' => $plannedPenthouse->id,
                'type' => UnitType::Penthouse->value,
                'rent_price' => 2850000.00,
                'status' => UnitStatus::Ready->value,
                'actual_area' => 320.00,
                'area_unit' => 'sqm',
                'bedrooms' => 4,
                'bathrooms' => 5,
                'sqft' => 3444,
                'is_featured' => true,
                'description' => 'بنتهاوس ملكي فاخر بالدور العاشر بإطلالة ساحرة بزاوية 360 درجة وتراس خاص',
            ]
        );

        $unit301 = Unit::updateOrCreate(
            ['company_id' => $company->id, 'property_id' => $developedProperty->id, 'unit_number' => 'APT-301'],
            [
                'project_id' => $project->id,
                'planned_unit_id' => $plannedApt301->id,
                'type' => UnitType::Apartment->value,
                'rent_price' => 1450000.00,
                'status' => UnitStatus::Ready->value,
                'actual_area' => 195.00,
                'area_unit' => 'sqm',
                'bedrooms' => 3,
                'bathrooms' => 4,
                'sqft' => 2098,
                'is_featured' => true,
                'description' => 'شقة عائلية فاخرة بالدور الثالث بتشطيبات سوبر ديلوكس وموقفين سيارة',
            ]
        );

        $unit302 = Unit::updateOrCreate(
            ['company_id' => $company->id, 'property_id' => $developedProperty->id, 'unit_number' => 'APT-302'],
            [
                'project_id' => $project->id,
                'planned_unit_id' => $plannedApt302->id,
                'type' => UnitType::Apartment->value,
                'rent_price' => 950000.00,
                'status' => UnitStatus::Ready->value,
                'actual_area' => 130.00,
                'area_unit' => 'sqm',
                'bedrooms' => 2,
                'bathrooms' => 2,
                'sqft' => 1399,
                'is_featured' => false,
                'description' => 'شقة عصرية حديثة مؤثثة بتشطيبات إيطالية وتكييف مركزي متطور',
            ]
        );

        $unitG01 = Unit::updateOrCreate(
            ['company_id' => $company->id, 'property_id' => $developedProperty->id, 'unit_number' => 'COM-G01'],
            [
                'project_id' => $project->id,
                'planned_unit_id' => $plannedCommercial->id,
                'type' => UnitType::Retail->value,
                'rent_price' => 280000.00,
                'status' => UnitStatus::Ready->value,
                'actual_area' => 210.00,
                'area_unit' => 'sqm',
                'bedrooms' => 0,
                'bathrooms' => 2,
                'sqft' => 2260,
                'is_featured' => true,
                'description' => 'معرض تجاري رئيسي بواجهة مباشرة على طريق الملك فهد مناسب للماركات والأنشطة المميزة',
            ]
        );

        // Unit Features
        $featuresList = [
            'موقف سيارات ذكي مظلل وخاص',
            'إطلالة بانورامية مفتوحة على مدينة الرياض',
            'نظام المنزل الذكي المتكامل (Smart Home)',
            'تكييف مركزي موفر للطاقة (VRF System)',
            'نظام دخول ذكي للأبواب بالبصمة والرمز السري',
        ];
        foreach ($featuresList as $featName) {
            UnitFeature::firstOrCreate(
                ['unit_id' => $unit101->id, 'name' => $featName],
                ['value' => 'متوفر']
            );
            UnitFeature::firstOrCreate(
                ['unit_id' => $unit301->id, 'name' => $featName],
                ['value' => 'متوفر']
            );
        }

        // 11. Listings (العروض العقارية)
        $listing101 = Listing::firstOrCreate(
            ['company_id' => $company->id, 'listable_type' => Unit::class, 'listable_id' => $unit101->id],
            [
                'listing_type' => ListingType::Sale,
                'title' => 'للبيع: بنتهاوس ملكي فاخر بإطلالة بانورامية - برج الأفق الرياض',
                'description' => 'فرصة استثمارية وسكنية نادرة: بنتهاوس 320 متر مربع بالدور العاشر مع تراس مفتوح وتجهيزات ذكية كاملة',
                'asking_price' => 2850000.00,
                'currency' => 'SAR',
                'status' => ListingStatus::Published,
            ]
        );

        $listing301 = Listing::firstOrCreate(
            ['company_id' => $company->id, 'listable_type' => Unit::class, 'listable_id' => $unit301->id],
            [
                'listing_type' => ListingType::Sale,
                'title' => 'للبيع: شقة عائلية 3 غرف بتشطيبات راقية - واحة النرجس',
                'description' => 'شقة 195 متر مربع بتصميم عائلي مدروس وغرفة خادمة ومجلس مستقل',
                'asking_price' => 1450000.00,
                'currency' => 'SAR',
                'status' => ListingStatus::Published,
            ]
        );

        Listing::firstOrCreate(
            ['company_id' => $company->id, 'listable_type' => Unit::class, 'listable_id' => $unitG01->id],
            [
                'listing_type' => ListingType::Rent,
                'title' => 'للإيجار السنوي: معرض تجاري رئيسي على طريق الملك فهد',
                'description' => 'معرض تجاري فاخر 210 متر مربع بموقع حيوي واستثنائي للأعمال والأنشطة الراقية',
                'asking_price' => 280000.00,
                'currency' => 'SAR',
                'rent_frequency' => RentFrequency::Yearly,
                'status' => ListingStatus::Published,
            ]
        );

        // 12. CRM Pipeline & Sales Stages (مسار وقمع المبيعات)
        $pipeline = CrmPipeline::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'مسار مبيعات المشاريع التطويرية والفلل'],
            ['is_default' => true, 'is_active' => true]
        );

        $stageNew = CrmPipelineStage::firstOrCreate(
            ['company_id' => $company->id, 'pipeline_id' => $pipeline->id, 'code' => 'new'],
            ['label' => 'جديد', 'stage_type' => CrmStageType::Open, 'position' => 1, 'is_active' => true]
        );
        $stageQualified = CrmPipelineStage::firstOrCreate(
            ['company_id' => $company->id, 'pipeline_id' => $pipeline->id, 'code' => 'qualified'],
            ['label' => 'مؤهل', 'stage_type' => CrmStageType::Open, 'position' => 2, 'is_active' => true]
        );
        $stageViewing = CrmPipelineStage::firstOrCreate(
            ['company_id' => $company->id, 'pipeline_id' => $pipeline->id, 'code' => 'viewing'],
            ['label' => 'معاينة وزيارة', 'stage_type' => CrmStageType::Open, 'position' => 3, 'is_active' => true]
        );
        $stageNegotiation = CrmPipelineStage::firstOrCreate(
            ['company_id' => $company->id, 'pipeline_id' => $pipeline->id, 'code' => 'negotiation'],
            ['label' => 'تفاوض وعرض مالي', 'stage_type' => CrmStageType::Open, 'position' => 4, 'is_active' => true]
        );
        $stageWon = CrmPipelineStage::firstOrCreate(
            ['company_id' => $company->id, 'pipeline_id' => $pipeline->id, 'code' => 'won'],
            ['label' => 'صفقة ناجحة / تعاقد', 'stage_type' => CrmStageType::Won, 'position' => 5, 'is_active' => true]
        );

        // 13. Leads & Opportunities (العملاء المحتملون والفرص)
        $leadTurki = Lead::updateOrCreate(
            ['company_id' => $company->id, 'phone' => '0551122334'],
            [
                'name' => 'د. تركي بن ناصر السبيعي',
                'email' => 'turki.subaie@example.com',
                'first_source' => CrmInquirySource::Website,
                'assigned_to' => $salesUser->id,
                'created_by' => $salesUser->id,
            ]
        );

        $oppTurki = Opportunity::updateOrCreate(
            ['company_id' => $company->id, 'lead_id' => $leadTurki->id],
            [
                'pipeline_id' => $pipeline->id,
                'pipeline_stage_id' => $stageNegotiation->id,
                'listing_id' => $listing101->id,
                'type' => OpportunityType::Sale,
                'assigned_to' => $salesUser->id,
                'budget_min' => 2700000.00,
                'budget_max' => 2900000.00,
                'currency' => 'SAR',
                'preferred_property_type' => 'penthouse',
                'expected_close_date' => now()->addDays(15)->toDateString(),
                'notes' => 'العميل جاد ومتحمس وطلب حجز البنتهاوس الملكي والدفع عبر تمويل مصرفي فوري',
                'source' => CrmInquirySource::Website,
                'created_by' => $salesUser->id,
            ]
        );

        $leadNouf = Lead::updateOrCreate(
            ['company_id' => $company->id, 'phone' => '0504455667'],
            [
                'name' => 'أ. نوف بنت عبدالله التميمي',
                'email' => 'nouf.tamimi@example.com',
                'first_source' => CrmInquirySource::WalkIn,
                'assigned_to' => $salesUser->id,
                'created_by' => $salesUser->id,
            ]
        );

        $oppNouf = Opportunity::updateOrCreate(
            ['company_id' => $company->id, 'lead_id' => $leadNouf->id],
            [
                'pipeline_id' => $pipeline->id,
                'pipeline_stage_id' => $stageWon->id,
                'listing_id' => $listing301->id,
                'type' => OpportunityType::Sale,
                'assigned_to' => $salesUser->id,
                'budget_min' => 1400000.00,
                'budget_max' => 1500000.00,
                'currency' => 'SAR',
                'preferred_property_type' => 'apartment',
                'expected_close_date' => now()->subDays(5)->toDateString(),
                'closed_at' => now()->subDays(5),
                'notes' => 'تم إتمام التعاقد وسداد الدفعة الأولى وتوقيع العقد الرسمي',
                'source' => CrmInquirySource::WalkIn,
                'created_by' => $salesUser->id,
            ]
        );

        // CRM Activities & Tasks
        CrmActivity::firstOrCreate(
            ['company_id' => $company->id, 'lead_id' => $leadTurki->id, 'type' => \App\Enums\CrmActivityType::Call],
            [
                'opportunity_id' => $oppTurki->id,
                'outcome' => \App\Enums\CrmActivityOutcome::Interested,
                'notes' => 'اتصال هاتفي لمناقشة مواصفات البنتهاوس وجدول الدفعات الإنشائية، وتم الاتفاق على الحجز المبدئي وتحويل العربون',
                'occurred_at' => now()->subDays(2),
                'performed_by' => $salesUser->id,
            ]
        );

        CrmFollowUpTask::firstOrCreate(
            ['company_id' => $company->id, 'lead_id' => $leadTurki->id, 'title' => 'إرسال مسودة عقد البيع النهائي للاعتماد والمراجعة'],
            [
                'opportunity_id' => $oppTurki->id,
                'type' => \App\Enums\CrmActivityType::Call,
                'status' => \App\Enums\CrmTaskStatus::Pending,
                'description' => 'مراجعة الملحقات الفنية مع العميل وإرسال نسخة المسودة للمراجعة القانونية',
                'due_at' => now()->addDays(2),
                'assigned_to' => $salesUser->id,
                'created_by' => $salesUser->id,
            ]
        );

        // 14. Unit Reservations (الحجوزات العقارية)
        $reservationTurki = UnitReservation::updateOrCreate(
            ['company_id' => $company->id, 'reservation_number' => 'RES-2026-0001'],
            [
                'source' => ReservationSource::Direct,
                'transaction_type' => OpportunityType::Sale,
                'unit_id' => $unit101->id,
                'customer_party_id' => $buyerTurki->id,
                'opportunity_id' => $oppTurki->id,
                'agreed_amount' => 2850000.00,
                'currency' => 'SAR',
                'down_payment_amount' => 150000.00,
                'payment_method' => 'bank_transfer',
                'payment_terms' => 'دفعة عربون 150,000 ريال، و3 دفعات ربع سنوية مرتبطة بمراحل الإنجاز الإنشائي',
                'valid_until' => now()->addDays(20),
            ]
        );
        $reservationTurki->forceFill([
            'status' => ReservationStatus::Active,
            'activated_at' => now()->subDays(2),
            'created_by' => $salesUser->id,
        ])->save();

        $reservationNouf = UnitReservation::updateOrCreate(
            ['company_id' => $company->id, 'reservation_number' => 'RES-2026-0002'],
            [
                'source' => ReservationSource::Direct,
                'transaction_type' => OpportunityType::Sale,
                'unit_id' => $unit301->id,
                'customer_party_id' => $buyerNouf->id,
                'opportunity_id' => $oppNouf->id,
                'agreed_amount' => 1450000.00,
                'currency' => 'SAR',
                'down_payment_amount' => 100000.00,
                'payment_method' => 'bank_transfer',
                'payment_terms' => 'دفعة أولى 100,000 ريال ومبلغ العقد المتبقي عبر تمويل بنكي معتمد',
                'valid_until' => now()->addDays(45),
            ]
        );
        $reservationNouf->forceFill([
            'status' => ReservationStatus::Active,
            'activated_at' => now()->subDays(10),
            'created_by' => $salesUser->id,
        ])->save();

        // 15. Contract Template & Versions (نماذج العقود المعتمدة)
        $template = ContractTemplate::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'عقد بيع وحدة عقارية على الخارطة - سكني موحد'],
            [
                'contract_type' => ContractType::Sale,
                'is_active' => true,
            ]
        );

        $termsData = [
            ['term_type' => 'clause', 'title' => 'البند الأول: موضوع العقد وبيانات الوحدة العقارية', 'text' => 'بموجب هذا العقد يبيع الطرف الأول (المطور) للطرف الثاني (المشتري) الوحدة العقارية المحددة تفصيلاً في هذا العقد بكافة مرافقها وملحقاتها المشتركة.', 'position' => 1],
            ['term_type' => 'commercial', 'title' => 'البند الثاني: القيمة الإجمالية وجدول الدفعات الإنشائية', 'text' => 'تم الاتفاق على القيمة الإجمالية للوحدة وتسدد وفق دفعات نقدية أو بنكية ترتبط ارتباطاً وثيقاً بنسب الإنجاز المعتمدة من الاستشاري الهندسي المشرف.', 'position' => 2],
            ['term_type' => 'clause', 'title' => 'البند الثالث: مواصفات التشطيب وتاريخ التسليم المعتمد', 'text' => 'يلتزم الطرف الأول بتسليم الوحدة العقارية مطابقة لجدول المواصفات والتصاميم المعتمدة في موعد أقصاه التاريخ المحدد في ملحق العقد.', 'position' => 3],
            ['term_type' => 'legal', 'title' => 'البند الرابع: الضمانات الإنشائية وأعمال العزل والسباكة', 'text' => 'يضمن المطور سلامة الهيكل الإنشائي لمدة 10 سنوات من تاريخ التسليم، وضمان الأعمال الكهروميكانيكية والعوازل المائية لمدة سنتين.', 'position' => 4],
            ['term_type' => 'legal', 'title' => 'البند الخامس: النزاعات والفسخ والتحكيم النظامي', 'text' => 'تخضع أي خلافات تنشأ عن تطبيق أو تفسير هذا العقد للأنظمة السارية في المملكة العربية السعودية والمركز السعودي للتحكيم التجاري.', 'position' => 5],
        ];

        $templateVersion = ContractTemplateVersion::firstOrCreate(
            ['contract_template_id' => $template->id, 'version_number' => 1],
            [
                'company_id' => $company->id,
                'body' => 'صيغة النموذج الموحد لعقد البيع على الخارطة',
                'clauses' => $termsData,
                'created_by' => $adminUser->id,
            ]
        );

        // 16. Prepare Demo Sample Files in Storage (PDF and Signatures)
        $disk = config('filesystems.default');
        $pdfPath = 'documents/contracts/demo_contract_301.pdf';
        $buyerSigPath = 'signatures/contracts/buyer_nouf_signature.png';
        $devSigPath = 'signatures/contracts/developer_signature.png';

        if (! Storage::disk($disk)->exists($pdfPath)) {
            $samplePdfContent = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000052 00000 n\n0000000101 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n178\n%%EOF";
            Storage::disk($disk)->put($pdfPath, $samplePdfContent);
        }

        // 1x1 transparent PNG bytes for real image preview
        $samplePngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        if (! Storage::disk($disk)->exists($buyerSigPath)) {
            Storage::disk($disk)->put($buyerSigPath, $samplePngBytes);
        }
        if (! Storage::disk($disk)->exists($devSigPath)) {
            Storage::disk($disk)->put($devSigPath, $samplePngBytes);
        }

        // 17. Contract 1: Fully Signed & Active Contract (العقد المكتمل والنشط)
        $contract1 = Contract::updateOrCreate(
            ['company_id' => $company->id, 'contract_number' => 'CNT-2026-OFK-001'],
            [
                'contract_type' => ContractType::Sale,
                'status' => ContractStatus::Active,
                'reservation_id' => $reservationNouf->id,
                'property_id' => $developedProperty->id,
                'project_id' => $project->id,
                'unit_id' => $unit301->id,
                'planned_unit_id' => $plannedApt301->id,
                'created_by' => $adminUser->id,
            ]
        );

        $version1 = ContractVersion::updateOrCreate(
            ['contract_id' => $contract1->id, 'version_number' => 1],
            [
                'company_id' => $company->id,
                'status' => ContractVersionStatus::Signed,
                'template_version_id' => $templateVersion->id,
                'total_amount' => 1450000.00,
                'currency' => 'SAR',
                'start_date' => '2026-03-01',
                'delivery_date' => '2027-04-30',
                'unit_snapshot' => [
                    'unit_number' => 'APT-301',
                    'type' => 'apartment',
                    'actual_area' => 195.00,
                    'bedrooms' => 3,
                    'bathrooms' => 4,
                ],
                'reservation_snapshot' => [
                    'reservation_number' => 'RES-2026-0002',
                    'agreed_amount' => 1450000.00,
                    'down_payment_amount' => 100000.00,
                ],
                'created_by' => $adminUser->id,
                'submitted_at' => now()->subDays(6),
                'approved_at' => now()->subDays(5),
            ]
        );

        $partyDev = ContractParty::firstOrCreate(
            ['contract_version_id' => $version1->id, 'role' => 'seller'],
            [
                'company_id' => $company->id,
                'party_id' => $developerParty->id,
                'share_percentage' => 100.00,
                'snapshot' => ['name' => 'شركة دار الأفق للتطوير العقاري', 'cr' => '1010778899', 'phone' => '+966-11-2345678'],
            ]
        );

        $partyNouf = ContractParty::firstOrCreate(
            ['contract_version_id' => $version1->id, 'role' => 'buyer'],
            [
                'company_id' => $company->id,
                'party_id' => $buyerNouf->id,
                'share_percentage' => 100.00,
                'snapshot' => ['name' => 'أ. نوف بنت عبدالله التميمي', 'national_id' => '1098765432', 'phone' => '0504455667'],
            ]
        );

        // Copy terms to version
        foreach ($termsData as $tData) {
            ContractTerm::firstOrCreate(
                ['contract_version_id' => $version1->id, 'position' => $tData['position']],
                $tData + ['company_id' => $company->id]
            );
        }

        // Approval
        ContractApproval::firstOrCreate(
            ['contract_version_id' => $version1->id, 'reviewed_by' => $legalUser->id],
            [
                'company_id' => $company->id,
                'status' => ContractApprovalStatus::Approved,
                'notes' => 'تمت مراجعة الاشتراطات والمطابقة المالية والتأكد من استيفاء الدفعة الأولى وسريان صك الملكية',
                'reviewed_at' => now()->subDays(5),
            ]
        );

        // Document attached to Version
        $doc = Document::firstOrCreate(
            ['documentable_type' => ContractVersion::class, 'documentable_id' => $version1->id, 'title' => 'عقد بيع نهائي موثق - شقة 301.pdf'],
            ['description' => 'النسخة القانونية الرسمية المعتمدة لعقد البيع النهائي', 'created_by' => $legalUser->id]
        );

        $docVersion = DocumentVersion::firstOrCreate(
            ['document_id' => $doc->id, 'version_number' => 1],
            [
                'company_id' => $company->id,
                'file_name' => 'عقد_بيع_شقة_301_نهائي.pdf',
                'file_path' => $pdfPath,
                'mime_type' => 'application/pdf',
                'file_size' => 245760,
                'checksum' => hash('sha256', Storage::disk($disk)->get($pdfPath)),
                'uploaded_by' => $legalUser->id,
            ]
        );

        // Signatures
        ContractSignature::firstOrCreate(
            ['contract_version_id' => $version1->id, 'contract_party_id' => $partyNouf->id],
            [
                'company_id' => $company->id,
                'document_version_id' => $docVersion->id,
                'signatory_name' => 'نوف بنت عبدالله التميمي',
                'signing_method' => 'manual',
                'signature_image_path' => $buyerSigPath,
                'file_sha256' => $docVersion->checksum,
                'evidence' => ['ip' => '192.168.1.50', 'device' => 'iPad Pro / Apple Pencil', 'notes' => 'تم التوقيع بحضور الطرفين في مقر الشركة'],
                'signed_at' => now()->subDays(3),
                'recorded_by' => $legalUser->id,
            ]
        );

        ContractSignature::firstOrCreate(
            ['contract_version_id' => $version1->id, 'contract_party_id' => $partyDev->id],
            [
                'company_id' => $company->id,
                'document_version_id' => $docVersion->id,
                'signatory_name' => 'سلطان بن عبدالعزيز الراجحي',
                'signing_method' => 'manual',
                'signature_image_path' => $devSigPath,
                'file_sha256' => $docVersion->checksum,
                'evidence' => ['notes' => 'توقيع الرئيس التنفيذي نيابة عن المطور المعتمد'],
                'signed_at' => now()->subDays(3),
                'recorded_by' => $legalUser->id,
            ]
        );

        // Contract Status History
        ContractStatusHistory::firstOrCreate(
            ['contract_id' => $contract1->id, 'action' => 'created'],
            ['company_id' => $company->id, 'contract_version_id' => $version1->id, 'from_status' => null, 'to_status' => 'draft', 'actor_id' => $adminUser->id, 'changed_at' => now()->subDays(7), 'reason' => 'إنشاء مسودة العقد بناءً على الحجز المسبق']
        );
        ContractStatusHistory::firstOrCreate(
            ['contract_id' => $contract1->id, 'action' => 'submitted'],
            ['company_id' => $company->id, 'contract_version_id' => $version1->id, 'from_status' => 'draft', 'to_status' => 'under_review', 'actor_id' => $salesUser->id, 'changed_at' => now()->subDays(6), 'reason' => 'رفع العقد للإدارة القانونية للاعتماد']
        );
        ContractStatusHistory::firstOrCreate(
            ['contract_id' => $contract1->id, 'action' => 'approved'],
            ['company_id' => $company->id, 'contract_version_id' => $version1->id, 'from_status' => 'under_review', 'to_status' => 'approved', 'actor_id' => $legalUser->id, 'changed_at' => now()->subDays(5), 'reason' => 'اعتماد الصيغة القانونية للعقد']
        );
        ContractStatusHistory::firstOrCreate(
            ['contract_id' => $contract1->id, 'action' => 'activated'],
            ['company_id' => $company->id, 'contract_version_id' => $version1->id, 'from_status' => 'approved', 'to_status' => 'active', 'actor_id' => $adminUser->id, 'changed_at' => now()->subDays(2), 'reason' => 'اكتمال التوقيعات وتفعيل العقد رسمياً']
        );

        // 18. Contract 2: Under Review / Submitted (عقد قيد الاعتماد والمراجعة)
        $contract2 = Contract::updateOrCreate(
            ['company_id' => $company->id, 'contract_number' => 'CNT-2026-OFK-002'],
            [
                'contract_type' => ContractType::Sale,
                'status' => ContractStatus::UnderReview,
                'reservation_id' => $reservationTurki->id,
                'property_id' => $developedProperty->id,
                'project_id' => $project->id,
                'unit_id' => $unit101->id,
                'planned_unit_id' => $plannedPenthouse->id,
                'created_by' => $salesUser->id,
            ]
        );

        $version2 = ContractVersion::updateOrCreate(
            ['contract_id' => $contract2->id, 'version_number' => 1],
            [
                'company_id' => $company->id,
                'status' => ContractVersionStatus::Submitted,
                'template_version_id' => $templateVersion->id,
                'total_amount' => 2850000.00,
                'currency' => 'SAR',
                'start_date' => now()->addDays(5)->toDateString(),
                'delivery_date' => '2027-04-30',
                'unit_snapshot' => [
                    'unit_number' => 'APT-101',
                    'type' => 'penthouse',
                    'actual_area' => 320.00,
                    'bedrooms' => 4,
                ],
                'created_by' => $salesUser->id,
                'submitted_at' => now()->subHours(6),
            ]
        );

        ContractParty::firstOrCreate(
            ['contract_version_id' => $version2->id, 'role' => 'seller'],
            [
                'company_id' => $company->id,
                'party_id' => $developerParty->id,
                'share_percentage' => 100.00,
                'snapshot' => ['name' => 'شركة دار الأفق للتطوير العقاري'],
            ]
        );
        ContractParty::firstOrCreate(
            ['contract_version_id' => $version2->id, 'role' => 'buyer'],
            [
                'company_id' => $company->id,
                'party_id' => $buyerTurki->id,
                'share_percentage' => 100.00,
                'snapshot' => ['name' => 'د. تركي بن ناصر السبيعي', 'national_id' => '1087654321'],
            ]
        );

        foreach ($termsData as $tData) {
            ContractTerm::firstOrCreate(
                ['contract_version_id' => $version2->id, 'position' => $tData['position']],
                $tData + ['company_id' => $company->id]
            );
        }

        // 19. Contract 3: Draft Lease Contract for Commercial Retail (عقد إيجار جديد قيد الصياغة)
        $contract3 = Contract::updateOrCreate(
            ['company_id' => $company->id, 'contract_number' => 'CNT-2026-OFK-003'],
            [
                'contract_type' => ContractType::Lease,
                'status' => ContractStatus::Draft,
                'property_id' => $developedProperty->id,
                'project_id' => $project->id,
                'unit_id' => $unitG01->id,
                'planned_unit_id' => $plannedCommercial->id,
                'created_by' => $salesUser->id,
            ]
        );

        $version3 = ContractVersion::updateOrCreate(
            ['contract_id' => $contract3->id, 'version_number' => 1],
            [
                'company_id' => $company->id,
                'status' => ContractVersionStatus::Draft,
                'total_amount' => 280000.00,
                'currency' => 'SAR',
                'start_date' => '2026-06-01',
                'end_date' => '2027-05-31',
                'unit_snapshot' => [
                    'unit_number' => 'COM-G01',
                    'type' => 'retail',
                    'actual_area' => 210.00,
                ],
                'created_by' => $salesUser->id,
            ]
        );

        ContractParty::firstOrCreate(
            ['contract_version_id' => $version3->id, 'role' => 'landlord'],
            [
                'company_id' => $company->id,
                'party_id' => $developerParty->id,
                'share_percentage' => 100.00,
                'snapshot' => ['name' => 'شركة دار الأفق للتطوير العقاري'],
            ]
        );
        ContractParty::firstOrCreate(
            ['contract_version_id' => $version3->id, 'role' => 'tenant'],
            [
                'company_id' => $company->id,
                'party_id' => $tenantMuhaidib->id,
                'share_percentage' => 100.00,
                'snapshot' => ['name' => 'مجموعة المهيدب للتجزئة والأسواق التجارية'],
            ]
        );

        $this->command->info('✅ Successfully seeded comprehensive real estate development company: شركة دار الأفق للتطوير العقاري!');
        $this->command->info('   - Admin Login: admin@dar-alofok.com / password');
        $this->command->info('   - Sales Login: sales@dar-alofok.com / password');
        $this->command->info('   - Legal Login: legal@dar-alofok.com / password');
    }
}
