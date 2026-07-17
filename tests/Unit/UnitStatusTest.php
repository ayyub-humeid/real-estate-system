<?php

namespace Tests\Unit;

use App\Models\Unit;
use PHPUnit\Framework\TestCase;

class UnitStatusTest extends TestCase
{
    /**
     * Test that isAvailable returns true when the unit status is available.
     */
    public function test_unit_is_available_when_status_is_available(): void
    {
        // Arrange
        $unit = new Unit([
            'status' => 'available',
        ]);

        // Act & Assert
        $this->assertTrue($unit->isAvailable());
    }

    /**
     * Test that isAvailable returns false when the unit status is occupied.
     */
    public function test_unit_is_not_available_when_status_is_occupied(): void
    {
        // Arrange
        $unit = new Unit([
            'status' => 'occupied',
        ]);

        // Act & Assert
        $this->assertFalse($unit->isAvailable());
    }
}
