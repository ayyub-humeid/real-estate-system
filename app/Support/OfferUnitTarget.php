<?php

namespace App\Support;

use App\Enums\UnitStatus;
use App\Models\{CrmOffer, Listing, ProjectPlannedUnit, Unit};
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The one answer to "which unit does this offer sell?", shared by offers and reservations so the
 * reservation never discovers a problem the offer could have caught. A listing is authoritative
 * about its own unit; an offer without a listing names its unit directly.
 */
final class OfferUnitTarget
{
    /** `unit`, `planned_unit`, or null. Accepts the short alias and the full class name alike. */
    public static function listingKind(?Listing $listing): ?string
    {
        if (! $listing) {
            return null;
        }

        $type = (string) $listing->listable_type;
        $class = Relation::getMorphedModel($type) ?? $type;

        return match ($class) {
            Unit::class => 'unit',
            ProjectPlannedUnit::class => 'planned_unit',
            default => null,
        };
    }

    /** The actual unit a listing sells: the unit itself, or the one built from its planned unit (null until built). */
    public static function listingUnitId(Listing $listing, int $companyId): ?int
    {
        $id = match (self::listingKind($listing)) {
            'unit' => $listing->listable_id,
            'planned_unit' => Unit::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('planned_unit_id', $listing->listable_id)
                ->value('id'),
            default => null,
        };

        return $id ? (int) $id : null;
    }

    public static function listingOf(CrmOffer $offer): ?Listing
    {
        return $offer->listing_id ? Listing::withoutGlobalScopes()->find($offer->listing_id) : null;
    }

    /** The actual unit the offer sells, or null (no unit chosen, or an off-plan unit not built yet). */
    public static function unitId(CrmOffer $offer): ?int
    {
        $listing = self::listingOf($offer);
        if ($listing) {
            return self::listingUnitId($listing, (int) $offer->company_id);
        }

        return $offer->unit_id ? (int) $offer->unit_id : null;
    }

    /** The unit the offer sells, loaded, or null when there is none yet. */
    public static function unit(CrmOffer $offer): ?Unit
    {
        $id = self::unitId($offer);

        return $id ? Unit::withoutGlobalScopes()->find($id) : null;
    }

    /** A unit can still be sold only while it is ready; any other status means someone else holds it. */
    public static function isAvailable(Unit $unit): bool
    {
        $status = $unit->status instanceof UnitStatus ? $unit->status->value : (string) $unit->status;

        return $status === UnitStatus::Ready->value;
    }

    /** The offer's unit once someone else has taken it, so a screen can say so before more work is wasted. */
    public static function takenUnit(CrmOffer $offer): ?Unit
    {
        $unit = self::unit($offer);

        return $unit && ! self::isAvailable($unit) ? $unit : null;
    }

    /** True for an off-plan offer whose planned unit has not been turned into an actual unit yet. */
    public static function awaitsConstruction(CrmOffer $offer): bool
    {
        $listing = self::listingOf($offer);

        return self::listingKind($listing) === 'planned_unit' && self::listingUnitId($listing, (int) $offer->company_id) === null;
    }
}
