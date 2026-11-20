<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Party extends Model
{
    use \App\Traits\HasCompany;
    use \App\Models\Concerns\PreventsDeletionWithHistory;

    protected function deletionChecks(): array
    {
        $has = fn (string $table): \Closure => fn (): bool => \Illuminate\Support\Facades\DB::table($table)->where('party_id', $this->id)->exists();

        return [
            'company_identity' => fn (): bool => \Illuminate\Support\Facades\DB::table('companies')->where('self_party_id', $this->id)->exists(),
            'ownership_history' => fn (): bool => $has('property_ownerships')() || $has('unit_ownerships')(),
            'acquisitions' => $has('acquisition_parties'),
            'financial_records' => fn (): bool => $has('financial_commitments')() || $has('actual_costs')() || $has('payments')(),
            'reservations' => fn (): bool => \Illuminate\Support\Facades\DB::table('unit_reservations')->where('customer_party_id', $this->id)->exists(),
        ];
    }
    protected $fillable = ['company_id', 'type', 'lead_id', 'name', 'legal_name', 'national_id', 'registration_number', 'tax_number', 'phone', 'email', 'address', 'notes', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (Party $party): void {
            if (! $party->exists || $party->isDirty('phone')) {
                $party->normalized_phone = \App\Support\ContactNormalizer::phone($party->phone);
            }
            if (! $party->exists || $party->isDirty('email')) {
                $party->normalized_email = \App\Support\ContactNormalizer::email($party->email);
            }
        });
    }
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
    public function acquisitionParties(): HasMany
    {
        return $this->hasMany(AcquisitionParty::class);
    }
    public function propertyOwnerships(): HasMany
    {
        return $this->hasMany(PropertyOwnership::class);
    }
    public function unitOwnerships(): HasMany
    {
        return $this->hasMany(UnitOwnership::class);
    }
    public function reservations(): HasMany
    {
        return $this->hasMany(UnitReservation::class, 'customer_party_id');
    }
    public function contractParties(): HasMany
    {
        return $this->hasMany(ContractParty::class);
    }
}
