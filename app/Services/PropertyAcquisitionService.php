<?php
namespace App\Services;

use App\Models\{AcquisitionParty,AcquisitionProperty,Company,DueDiligenceCase,DueDiligenceItem,Party,Property,PropertyAcquisition,PropertyOwnership,User};
use App\Notifications\AcquisitionWorkflowNotification;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyAcquisitionService
{
    public function createDueDiligenceCase(User $actor, PropertyAcquisition $acquisition, array $attributes): DueDiligenceCase
    {
        $this->authorize($actor, 'create', new DueDiligenceCase(['company_id' => $acquisition->company_id]));
        if (!empty($attributes['property_id'])) {
            $property = Property::withoutGlobalScopes()->findOrFail($attributes['property_id']);
            $this->sameCompany($acquisition, $property);
        }

        return DB::transaction(function () use ($actor, $acquisition, $attributes) {
            $items = $attributes['items'] ?? [];
            unset($attributes['items']);
            $case = DueDiligenceCase::create(array_merge($attributes, [
                'company_id' => $acquisition->company_id,
                'property_acquisition_id' => $acquisition->id,
                'opened_at' => now(),
                'opened_by' => $actor->id,
            ]));
            foreach ($items as $item) {
                $case->items()->create(array_merge($item, ['company_id' => $acquisition->company_id]));
            }

            return $case;
        });
    }

    public function createForProperty(User $actor, Property $property, array $attributes): PropertyAcquisition
    {
        $this->authorize($actor, 'create', new PropertyAcquisition(['company_id' => $property->company_id]));

        return DB::transaction(function () use ($property, $attributes) {
            $acquisition = PropertyAcquisition::create(array_merge($attributes, [
                'company_id' => $property->company_id,
                'status' => 'draft',
            ]));
            $property->propertyAcquisitions()->attach($acquisition->id, [
                'company_id' => $property->company_id,
            ]);

            return $acquisition;
        });
    }

    public function attachProperty(User $actor, PropertyAcquisition $acquisition, Property $property, array $attributes=[]): AcquisitionProperty
    {
        $this->authorize($actor, 'update', $acquisition); $this->sameCompany($acquisition, $property);
        if ($acquisition->acquisitionProperties()->where('property_id', $property->id)->exists()) {
            throw ValidationException::withMessages(['property_id' => 'This property is already attached to this acquisition.']);
        }
        return AcquisitionProperty::create(array_merge($attributes, ['company_id'=>$acquisition->company_id, 'property_acquisition_id'=>$acquisition->id, 'property_id'=>$property->id]));
    }
    public function attachParty(User $actor, PropertyAcquisition $acquisition, Party $party, string $role, array $attributes=[]): AcquisitionParty
    {
        $this->authorize($actor, 'update', $acquisition); $this->sameCompany($acquisition, $party);
        if ($acquisition->acquisitionParties()->where('party_id', $party->id)->where('role', $role)->exists()) {
            throw ValidationException::withMessages(['party_id' => "This party is already attached as a {$role} to this acquisition."]);
        }
        $this->validateAcquisitionPartyShare($acquisition, $role, $attributes['share_percentage'] ?? null);
        return AcquisitionParty::create(array_merge($attributes, ['company_id'=>$acquisition->company_id, 'property_acquisition_id'=>$acquisition->id, 'party_id'=>$party->id, 'role'=>$role]));
    }
    public function updateAcquisitionParty(User $actor, AcquisitionParty $acquisitionParty, array $attributes): AcquisitionParty
    {
        $this->authorize($actor, 'update', $acquisitionParty);
        $role = $attributes['role'] ?? $acquisitionParty->role;
        $share = $attributes['share_percentage'] ?? $acquisitionParty->share_percentage;
        $this->validateAcquisitionPartyShare($acquisitionParty->acquisition, $role, $share, $acquisitionParty->id);
        $acquisitionParty->update($attributes);

        return $acquisitionParty->refresh();
    }
    public function transition(User $actor, PropertyAcquisition $acquisition, string $to, ?string $reason=null): PropertyAcquisition
    {
        $abilities=['approved'=>'approve','completed'=>'complete','cancelled'=>'cancel'];
        $this->authorize($actor, $abilities[$to] ?? 'update', $acquisition);
        $from=$acquisition->status;
        $allowed=['draft'=>['under_due_diligence','cancelled'], 'under_due_diligence'=>['approved','cancelled'], 'approved'=>['completed','cancelled']];
        if (!in_array($to, $allowed[$from] ?? [], true)) throw ValidationException::withMessages(['status'=>"Cannot transition an acquisition from {$from} to {$to}."]);
        if ($to==='under_due_diligence' && (!$acquisition->acquisitionProperties()->exists() || !$acquisition->acquisitionParties()->exists())) throw ValidationException::withMessages(['status'=>'At least one property and one involved party are required before due diligence.']);
        if ($to==='approved' && $acquisition->dueDiligenceCases()->where('status','!=','cleared')->exists()) throw ValidationException::withMessages(['status'=>'All due-diligence cases must be cleared before approval.']);
        return DB::transaction(function () use ($actor,$acquisition,$to,$reason) {
            $data=['status'=>$to];
            if ($to==='approved') $data += ['approved_by'=>$actor->id,'approved_at'=>now()];
            if ($to==='completed') $data += ['completed_at'=>now()];
            if ($to==='cancelled') $data += ['cancelled_at'=>now(),'cancellation_reason'=>$reason];
            $acquisition->update($data);
            DB::afterCommit(fn()=>$this->notify($actor, $acquisition, ucfirst(str_replace('_',' ',$to)), "Acquisition {$acquisition->reference_number} is {$to}."));
            return $acquisition->refresh();
        });
    }
    public function waiveItem(User $actor, DueDiligenceItem $item, ?string $notes=null): DueDiligenceItem
    {
        $this->authorize($actor,'waive',$item);
        if (in_array($item->status,['passed','waived'],true)) throw ValidationException::withMessages(['status'=>'Only unresolved items may be waived.']);
        $item->update(['status'=>'waived','checked_by'=>$actor->id,'checked_at'=>now(),'notes'=>$notes]); return $item->refresh();
    }
    public function clearCase(User $actor, DueDiligenceCase $case): DueDiligenceCase
    {
        $this->authorize($actor,'clear',$case);
        $blocking = $case->items()->where('is_required', true)->whereNotIn('status', ['passed', 'waived'])->exists();
        if ($blocking) {
            $case->update(['status' => 'blocked']);
            throw ValidationException::withMessages(['status' => 'Required due-diligence items are pending or failed.']);
        }
        return DB::transaction(function () use ($actor, $case) {
            $case->update(['status'=>'cleared','completed_at'=>now(),'completed_by'=>$actor->id]);
            DB::afterCommit(fn()=>$this->notify($actor,$case->acquisition,'Due diligence cleared',"Due-diligence case #{$case->id} was cleared.")); return $case->refresh();
        });
    }
    /** @param array<int,array{party: Party, percentage: numeric, notes?: string}> $owners */
    public function replaceOwnership(User $actor, Property $property, array $owners, CarbonInterface|string $startDate, ?PropertyAcquisition $acquisition=null): PropertyOwnership
    {
        $this->authorize($actor,'change',new PropertyOwnership(['company_id'=>$property->company_id]));
        $total=collect($owners)->sum(fn($owner)=>(float)$owner['percentage']);
        if (abs($total-100)>0.001 || collect($owners)->contains(fn($owner)=>(float)$owner['percentage']<=0 || (float)$owner['percentage']>100)) throw ValidationException::withMessages(['ownership_percentage'=>'Active ownership must contain valid percentages totaling 100.']);
        if ($acquisition) $this->sameCompany($property,$acquisition);
        return DB::transaction(function() use($actor,$property,$owners,$startDate,$acquisition) {
            foreach($owners as $owner) $this->sameCompany($property,$owner['party']);
            PropertyOwnership::where('property_id',$property->id)->whereNull('end_date')->update(['end_date'=>$startDate]);
            $createdOwnership=null;
            foreach($owners as $owner) $createdOwnership=PropertyOwnership::create(['company_id'=>$property->company_id,'property_id'=>$property->id,'party_id'=>$owner['party']->id,'ownership_percentage'=>$owner['percentage'],'start_date'=>$startDate,'acquisition_id'=>$acquisition?->id,'notes'=>$owner['notes']??null]);
            DB::afterCommit(fn()=>$this->notify($actor, $acquisition, 'Property ownership changed', "Ownership history for {$property->name} was updated.", $property->company_id, true));
            return $createdOwnership;
        });
    }

    public function partyForCompany(Property $property, Company $company): Party
    {
        if ((int) $property->company_id !== (int) $company->id) {
            throw ValidationException::withMessages(['company_id' => 'The ownership company must match the property company.']);
        }

        return Party::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $property->company_id, 'type' => 'company', 'name' => $company->name],
            ['legal_name' => $company->name, 'email' => $company->email, 'phone' => $company->phone, 'address' => $company->address]
        );
    }
    private function validateAcquisitionPartyShare(PropertyAcquisition $acquisition, string $role, mixed $sharePercentage, ?int $exceptId = null): void
    {
        if ($sharePercentage === null || $sharePercentage === '') return;
        $share = (float) $sharePercentage;
        if ($share <= 0 || $share > 100) throw ValidationException::withMessages(['share_percentage' => 'Share percentage must be greater than 0 and no more than 100.']);
        $allocated = (float) $acquisition->acquisitionParties()
            ->where('role', $role)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->sum('share_percentage');
        if ($allocated + $share > 100) throw ValidationException::withMessages(['share_percentage' => "Total \"{$role}\" share would exceed 100%."]);
    }
    private function authorize(User $user,string $ability,object $record):void { if (!$user->can($ability,$record)) throw new AuthorizationException; }
    private function sameCompany(object $left, object $right):void { if ((int)$left->company_id !== (int)$right->company_id) throw ValidationException::withMessages(['company_id'=>'Records from different companies cannot be associated.']); }
    private function notify(User $actor, ?PropertyAcquisition $acquisition, string $title, string $body, ?int $companyId = null, bool $ownershipEvent = false):void
    {
        $companyId ??= $acquisition?->company_id;
        if (!$companyId) return;
        User::withoutGlobalScopes()->where('company_id', $companyId)->get()
            ->filter(fn(User $user) => $user->id === $actor->id
                || $user->can('view_property::acquisition')
                || ($ownershipEvent && $user->can('view_property_ownership')))
            ->each->notify(new AcquisitionWorkflowNotification($title, $body, $acquisition?->id));
    }
}
