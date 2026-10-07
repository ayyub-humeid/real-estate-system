<?php

namespace Tests\Unit;

use App\Enums\UnitStatus;
use App\Models\Unit;
use PHPUnit\Framework\TestCase;

class UnitStatusTest extends TestCase
{
    /**
     * Test that a physically ready Unit is operationally ready.
     */
    public function test_unit_is_operationally_ready_when_status_is_ready(): void
    {
        // Arrange
        $unit = new Unit([
            'status' => 'ready',
        ]);

        // Act & Assert
        $this->assertTrue($unit->isOperationallyReady());
    }

    /**
     * Commercial tenancy does not replace the physical Unit status.
     */
    public function test_unit_is_not_operationally_ready_when_in_maintenance(): void
    {
        // Arrange
        $unit = new Unit([
            'status' => 'maintenance',
        ]);

        // Act & Assert
        $this->assertFalse($unit->isOperationallyReady());
    }

    public function test_unit_status_enum_rejects_legacy_commercial_values_and_enforces_transitions(): void
    {
        $this->assertNull(UnitStatus::tryFrom('available'));
        $this->assertNull(UnitStatus::tryFrom('occupied'));
        $this->assertTrue(UnitStatus::Draft->canTransitionTo(UnitStatus::Ready));
        $this->assertFalse(UnitStatus::Ready->canTransitionTo(UnitStatus::Draft));
        $this->assertTrue(UnitStatus::Inactive->canTransitionTo(UnitStatus::Draft));
    }
}
