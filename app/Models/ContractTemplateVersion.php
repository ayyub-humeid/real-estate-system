<?php
namespace App\Models;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContractTemplateVersion extends Model
{
    use HasCompany;
    protected $fillable = ['company_id', 'contract_template_id', 'version_number', 'body', 'clauses', 'created_by'];
    protected $casts = ['clauses' => 'array'];
    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->isDirty(['body', 'clauses']) && $version->contractVersions()->withoutGlobalScopes()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'template_version' => __('A template version already used by a contract cannot be changed.'),
                ]);
            }
        });

        static::deleting(function (self $version): void {
            if ($version->contractVersions()->withoutGlobalScopes()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'template_version' => __('A template version used by a contract cannot be deleted.'),
                ]);
            }
        });
    }

    public function contractVersions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ContractVersion::class, 'template_version_id');
    }
}
