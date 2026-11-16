<?php

return [
    'fields' => [
        'total_land_area_m2' => 'Total Land Area (m²)',
        'title_deed_number' => 'Title Deed Number',
        'seller_party' => 'Seller (Party)',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'location_details' => 'Location Details',
        'project_name' => 'Project Name',
        'project_type' => 'Project Type',
        'seed_budget' => 'Create Initial Budget',
        'seed_budget_help' => 'Create a "Land Cost" budget line item with the agreed purchase value',
    ],
    'project_types' => [
        'residential' => 'Residential',
        'commercial' => 'Commercial',
        'mixed_use' => 'Mixed Use',
        'land_development' => 'Land Development',
    ],
    'actions' => [
        'convert_to_project' => 'Convert to Project',
    ],
    'modals' => [
        'convert_heading' => 'Convert Acquisition to Project',
        'convert_description' => 'A new project will be created from this completed acquisition\'s data. Land properties will be linked to the project, and optionally an initial budget with a "Land Cost" line item can be created.',
        'convert_submit' => 'Yes, Convert',
    ],
    'budget' => [
        'initial_budget_name' => 'Initial Budget - ',
        'land_cost_category' => 'Land Cost',
        'land_purchase_item' => 'Land Purchase',
    ],
    'notifications' => [
        'conversion_complete' => 'Conversion Complete',
        'conversion_body' => 'Project ":name" (ID: :project_id) has been created from this acquisition.',
    ],
];