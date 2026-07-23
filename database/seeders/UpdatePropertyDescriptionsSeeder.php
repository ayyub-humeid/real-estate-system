<?php

namespace Database\Seeders;

use App\Models\Property;
use Illuminate\Database\Seeder;

class UpdatePropertyDescriptionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $descriptions = [
            "Modern luxury property located in a prime city location featuring state-of-the-art amenities, high-end interior finishes, 24/7 security, and breathtaking views.",
            "Spacious residential complex designed for comfortable family living with private gardens, playground areas, dedicated parking, and easy access to top schools.",
            "Exclusive waterfront property offering panoramic skyline views, private beach access, premium gym facilities, and elegant architectural design throughout.",
            "Contemporary urban residence surrounded by vibrant shopping districts, fine dining, and public transport hubs. Ideal for modern professionals seeking convenience.",
            "High-rise luxury tower with smart home integration, temperature-controlled swimming pools, concierge service, and floor-to-ceiling panoramic glass windows.",
            "Charming community property with lush green landscapes, jogging tracks, clubhouse facilities, and serene environment perfect for peaceful residential living.",
            "Premium commercial and residential mixed-use development featuring modern office spaces, retail outlets on the ground floor, and luxury apartments above.",
            "Boutique architectural gem featuring energy-efficient design, solar integration, underground secure parking, and private rooftop terraces for residents.",
            "Elegant traditional villa property combining classic Mediterranean aesthetic with modern luxury amenities, private pool, and expansive courtyard garden.",
            "State-of-the-art smart building located in the business center featuring flexible floor plans, high-speed fiber internet, and comprehensive facilities management.",
            "Serene suburban sanctuary featuring eco-friendly architecture, quiet neighborhood vibes, community park views, and spacious outdoor entertainment areas.",
            "Ultra-modern tower overlooking the marina with yacht dock access, infinity pool, private cinema room, and luxury spa facilities for exclusive residents.",
        ];

        $properties = Property::withoutGlobalScopes()->get();

        foreach ($properties as $index => $property) {
            $baseDescription = $descriptions[$index % count($descriptions)];
            $uniqueDescription = "{$property->name} - {$baseDescription}";

            $property->update([
                'description' => $uniqueDescription,
            ]);
        }
    }
}
