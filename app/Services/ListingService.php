<?php

namespace App\Services;

use App\Enums\{ListingStatus, ListingType, RentFrequency, UnitStatus};
use App\Models\{Company, CompanySetting, Listing, ListingPriceHistory, ProjectPlannedUnit, Unit, User};
use App\Notifications\ProjectWorkflowNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ListingService
{
    private const TARGETS = [
        'planned_unit' => ProjectPlannedUnit::class,
        'unit' => Unit::class,
    ];

    public function create(User $actor, array $attributes): Listing
    {
        $company = $this->companyFor($actor, $attributes['company_id'] ?? null);
        $this->authorize($actor, 'create', new Listing(['company_id' => $company->id]));
        [$type, $target] = $this->targetFor($attributes, $company->id);
        $data = $this->validatedData($attributes, $type, $target);

        return DB::transaction(function () use ($actor, $company, $type, $target, $data): Listing {
            $listing = Listing::withoutGlobalScopes()->create(array_merge($data, [
                'company_id' => $company->id,
                'listable_type' => $type,
                'listable_id' => $target->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]));
            $this->writeInitialPriceHistory($listing, $actor);
            DB::afterCommit(fn() => $actor->notify(new ProjectWorkflowNotification('Listing created', "{$listing->title} was saved as a draft.")));

            return $listing->refresh();
        });
    }

    public function update(User $actor, Listing $listing, array $attributes): Listing
    {
        $this->authorize($actor, 'update', $listing);
        if ($listing->status === ListingStatus::Archived) {
            $this->invalid('listing', 'Archived Listings must be restored before editing.');
        }

        $company = $this->companyForRecord($actor, $listing);
        if ((isset($attributes['listable_type']) && $attributes['listable_type'] !== $listing->listable_type)
            || (isset($attributes['listable_id']) && (int) $attributes['listable_id'] !== (int) $listing->listable_id)
        ) {
            $this->invalid('listable_id', 'A Listing target is immutable after creation. Create a new Listing for another Unit.');
        }
        $type = $listing->listable_type;
        $target = $this->targetFromStoredListing($listing);
        $data = $this->validatedData($attributes + [
            'listing_type' => $listing->listing_type->value,
            'rent_frequency' => $listing->rent_frequency?->value,
            'asking_price' => $listing->asking_price,
            'currency' => $listing->currency,
        ], $type, $target);

        $pricingChanged = $this->pricingChanged($listing, $data);
        if ($pricingChanged) {
            $this->authorize($actor, 'changePrice', $listing);
        }

        return DB::transaction(function () use ($actor, $listing, $type, $target, $data, $pricingChanged, $attributes): Listing {
            $locked = Listing::withoutGlobalScopes()->lockForUpdate()->findOrFail($listing->id);
            if ($locked->status === ListingStatus::Archived) {
                $this->invalid('listing', 'Archived Listings must be restored before editing.');
            }
            $was = $locked->only(['asking_price', 'currency', 'rent_frequency']);
            $locked->update(array_merge($data, [
                'listable_type' => $type,
                'listable_id' => $target->id,
                'updated_by' => $actor->id,
            ]));
            if ($pricingChanged) {
                $this->writePriceHistory($locked, $was, $actor, $attributes['price_change_reason'] ?? null);
                DB::afterCommit(fn() => $actor->notify(new ProjectWorkflowNotification('Listing price changed', "Pricing was updated for {$locked->title}.")));
            }

            return $locked->refresh();
        });
    }

    public function publish(User $actor, Listing $listing): Listing
    {
        return $this->transition($actor, $listing, ListingStatus::Published, 'publish');
    }

    public function pause(User $actor, Listing $listing): Listing
    {
        return $this->transition($actor, $listing, ListingStatus::Paused, 'pause');
    }

    public function archive(User $actor, Listing $listing): Listing
    {
        return $this->transition($actor, $listing, ListingStatus::Archived, 'archive');
    }

    public function restore(User $actor, Listing $listing): Listing
    {
        return $this->transition($actor, $listing, ListingStatus::Draft, 'restore');
    }

    private function transition(User $actor, Listing $listing, ListingStatus $target, string $ability): Listing
    {
        // Authorize the current database state, not a potentially stale Livewire/model instance.
        $currentRecord = Listing::withoutGlobalScopes()->findOrFail($listing->id);
        $this->authorize($actor, $ability, $currentRecord);

        return DB::transaction(function () use ($actor, $listing, $target): Listing {
            $locked = Listing::withoutGlobalScopes()->lockForUpdate()->findOrFail($listing->id);
            $current = $locked->status;
            if (! $current->canTransitionTo($target)) {
                $this->invalid('status', "Cannot move a Listing from {$current->value} to {$target->value}.");
            }
            $subject = $this->targetFromStoredListing($locked);
            if ($target === ListingStatus::Published) {
                $this->assertMarketable($locked, $subject);
            }
            $locked->forceFill(array_filter([
                'status' => $target,
                'published_at' => $target === ListingStatus::Published ? now() : $locked->published_at,
                'paused_at' => $target === ListingStatus::Paused ? now() : $locked->paused_at,
                'archived_at' => $target === ListingStatus::Archived ? now() : $locked->archived_at,
                'updated_by' => $actor->id,
            ], fn($value) => $value !== null))->save();
            DB::afterCommit(fn() => $actor->notify(new ProjectWorkflowNotification(
                'Listing ' . $target->value,
                "{$locked->title} is now {$target->value}."
            )));

            return $locked->refresh();
        });
    }

    private function validatedData(array $attributes, string $targetType, Model $target): array
    {
        $listingType = ListingType::tryFrom((string) ($attributes['listing_type'] ?? ''));
        if (! $listingType) {
            $this->invalid('listing_type', 'Select sale or rent.');
        }
        $title = trim((string) ($attributes['title'] ?? ''));
        if ($title === '') {
            $this->invalid('title', 'A Listing title is required.');
        }
        $price = $attributes['asking_price'] ?? null;
        if (! is_numeric($price) || (float) $price <= 0) {
            $this->invalid('asking_price', 'Asking price must be greater than zero.');
        }
        $currency = strtoupper(trim((string) ($attributes['currency'] ?? '')));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $this->invalid('currency', 'Currency must be a three-letter ISO code.');
        }

        $rentFrequency = $attributes['rent_frequency'] ?? null;
        if ($listingType === ListingType::Rent) {
            if (! RentFrequency::tryFrom((string) $rentFrequency)) {
                $this->invalid('rent_frequency', 'Rent frequency is required for a rental Listing.');
            }
        } else {
            $rentFrequency = null;
        }

        $minimum = $attributes['minimum_lease_months'] ?? null;
        if ($minimum !== null && (! filter_var($minimum, FILTER_VALIDATE_INT) || (int) $minimum < 1)) {
            $this->invalid('minimum_lease_months', 'Minimum lease months must be at least one.');
        }
        $deposit = $attributes['security_deposit'] ?? null;
        if ($deposit !== null && (! is_numeric($deposit) || (float) $deposit < 0)) {
            $this->invalid('security_deposit', 'Security deposit cannot be negative.');
        }

        return [
            'listing_type' => $listingType->value,
            'title' => $title,
            'description' => $attributes['description'] ?? null,
            'asking_price' => $price,
            'currency' => $currency,
            'rent_frequency' => $rentFrequency,
            'security_deposit' => $listingType === ListingType::Rent ? $deposit : null,
            'minimum_lease_months' => $listingType === ListingType::Rent ? $minimum : null,
            'availability_notes' => $attributes['availability_notes'] ?? null,
        ];
    }

    private function targetFor(array $attributes, int $companyId): array
    {
        $type = $attributes['listable_type'] ?? null;
        if (! isset(self::TARGETS[$type])) {
            $this->invalid('listable_type', 'Select an Actual Unit or Planned Unit.');
        }
        $class = Relation::getMorphedModel($type);
        if ($class !== self::TARGETS[$type]) {
            $this->invalid('listable_type', 'Unsupported Listing target type.');
        }
        $target = $class::withoutGlobalScopes()->findOrFail($attributes['listable_id'] ?? 0);
        if ((int) $target->company_id !== $companyId) {
            $this->invalid('listable_id', 'The selected Listing target belongs to another company.');
        }

        return [$type, $target];
    }

    private function targetFromStoredListing(Listing $listing): Model
    {
        return $this->targetFor([
            'listable_type' => $listing->listable_type,
            'listable_id' => $listing->listable_id,
        ], $listing->company_id)[1];
    }

    private function assertMarketable(Listing $listing, Model $target): void
    {
        if ($target instanceof Unit) {
            $status = UnitStatus::tryFrom($target->status);
            if ($status === UnitStatus::Inactive) {
                $this->invalid('listable_id', 'An inactive Unit cannot be marketed.');
            }
            if ($status === UnitStatus::Maintenance && ! $this->allowsMaintenanceMarketing($listing->company_id)) {
                $this->invalid('listable_id', 'This company does not allow maintenance Units to be marketed.');
            }
            return;
        }
        if ($target instanceof ProjectPlannedUnit && ! in_array($target->status, ['approved', 'converted'], true)) {
            $this->invalid('listable_id', 'Only an approved or converted Planned Unit may be marketed.');
        }
    }

    private function allowsMaintenanceMarketing(int $companyId): bool
    {
        return (bool) CompanySetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->value('allow_maintenance_unit_marketing');
    }

    private function companyFor(User $actor, mixed $requestedId): Company
    {
        if ($actor->isSuperAdmin()) {
            if (! $requestedId) {
                $this->invalid('company_id', 'Select the company that will own this Listing.');
            }
            return Company::findOrFail($requestedId);
        }
        if (! $actor->company_id) {
            $this->invalid('company_id', 'Your user account has no company context.');
        }
        if ($requestedId && (int) $requestedId !== (int) $actor->company_id) {
            $this->invalid('company_id', 'Company is derived from your current company context.');
        }
        return Company::findOrFail($actor->company_id);
    }

    private function companyForRecord(User $actor, Listing $listing): Company
    {
        if (! $actor->isSuperAdmin() && (int) $actor->company_id !== (int) $listing->company_id) {
            throw new AuthorizationException;
        }
        return Company::findOrFail($listing->company_id);
    }

    private function pricingChanged(Listing $listing, array $data): bool
    {
        return number_format((float) $listing->asking_price, 2, '.', '') !== number_format((float) $data['asking_price'], 2, '.', '')
            || $listing->currency !== $data['currency']
            || $listing->rent_frequency?->value !== $data['rent_frequency'];
    }

    private function writeInitialPriceHistory(Listing $listing, User $actor): void
    {
        ListingPriceHistory::create([
            'listing_id' => $listing->id,
            'new_price' => $listing->asking_price,
            'new_currency' => $listing->currency,
            'new_rent_frequency' => $listing->rent_frequency?->value,
            'changed_by' => $actor->id,
            'reason' => 'Initial Listing price',
        ]);
    }

    private function writePriceHistory(Listing $listing, array $was, User $actor, ?string $reason): void
    {
        ListingPriceHistory::create([
            'listing_id' => $listing->id,
            'old_price' => $was['asking_price'],
            'new_price' => $listing->asking_price,
            'old_currency' => $was['currency'],
            'new_currency' => $listing->currency,
            'old_rent_frequency' => $was['rent_frequency'],
            'new_rent_frequency' => $listing->rent_frequency?->value,
            'changed_by' => $actor->id,
            'reason' => blank($reason) ? null : $reason,
        ]);
    }

    private function authorize(User $actor, string $ability, object $record): void
    {
        if (! $actor->can($ability, $record)) {
            throw new AuthorizationException;
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}