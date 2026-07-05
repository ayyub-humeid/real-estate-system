<?php

namespace Database\Seeders;

use App\Models\Agency;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    public function run(): void
    {
        $agencies = [
            [
                'name'                 => 'Skyline Realty',
                'logo'                 => null, // سيُستخدم الرابط الخارجي في الواجهة حالياً
                'verified'             => true,
                'relation'             => 'Subsidiary of Apex Group',
                'badge'                => 'Elite Partner',
                'badge_type'           => 'elite',
                'hq'                   => 'Downtown',
                'branches'             => 'Arts District',
                'rating'               => 4.9,
                'years_active'         => 12,
                'partner_developers'   => 'Emaar, Damac',
                'phone'                => '+971 4 123 4567',
                'email'                => 'info@skylinerealty.ae',
                'about_title'          => 'Curating Exceptional Living Experiences',
                'about_description'    => 'Skyline Realty was founded on the principle that property management should be as seamless as it is sophisticated. We specialize in luxury high-rise developments and exclusive urban estates, providing a boutique service tailored to the modern tenant and discerning owner.',
                'about_sub_description'=> 'Our team combines deep market intelligence with a passion for architectural excellence. We don\'t just manage buildings; we foster communities and ensure every resident feels the Skyline difference from the moment they walk through the lobby.',
            ],
            [
                'name'                 => 'Urban Living',
                'logo'                 => null,
                'verified'             => true,
                'relation'             => 'Exclusive Partner of Nakheel',
                'badge'                => 'Exclusive',
                'badge_type'           => 'exclusive',
                'hq'                   => 'West End',
                'branches'             => 'Coastal',
                'rating'               => 4.8,
                'years_active'         => 8,
                'partner_developers'   => 'Nakheel, Sobha',
                'phone'                => '+971 4 987 6543',
                'email'                => 'contact@urbanliving.ae',
                'about_title'          => 'Modern Urban Living Solutions',
                'about_description'    => 'Urban Living is dedicated to bringing you the best urban housing options in prime locations. We specialize in modern apartments and family townhouses with smart city integrations.',
                'about_sub_description'=> 'Since 2018, we have connected thousands of young professionals and families to active urban spaces. Our philosophy focuses on efficiency, sustainability, and community-centric development.',
            ],
            [
                'name'                 => 'Prestige Estates',
                'logo'                 => null,
                'verified'             => true,
                'relation'             => 'Independent Boutique Agency',
                'badge'                => 'High Growth',
                'badge_type'           => 'high_growth',
                'hq'                   => 'Palm North',
                'branches'             => 'Marina',
                'rating'               => 5.0,
                'years_active'         => 15,
                'partner_developers'   => 'Dubai Properties, Meydan',
                'phone'                => '+971 4 456 7890',
                'email'                => 'concierge@prestigeestates.ae',
                'about_title'          => 'Luxury Real Estate Uncompromised',
                'about_description'    => 'Prestige Estates has been a leading force in luxury real estate for over a decade. We pride ourselves on representing the most exclusive properties in the market, providing world-class discretion and advisory services.',
                'about_sub_description'=> 'Representing properties of timeless architecture, our agents are equipped with top-tier analytical models and highly refined local expertise. We deliver concierge brokerage, tenant placement, and asset advisory.',
            ],
        ];

        foreach ($agencies as $agencyData) {
            Agency::firstOrCreate(['name' => $agencyData['name']], $agencyData);
        }

        $this->command->info('✅ Agency Seeder: ' . Agency::count() . ' agencies seeded successfully.');
    }
}
