<?php

return [
    'label' => 'Role',
    'plural_label' => 'Roles',
    'names' => [
        'sales_agent' => 'Sales agent',
        'sales_manager' => 'Sales manager',
        'project_manager' => 'Project manager',
    ],
    'standard' => [
        'action' => 'Add standard roles',
        'tooltip' => 'Creates ready-made roles so you do not have to tick permissions one by one.',
        'description' => 'Adds the roles your company does not have yet: Sales agent (leads, offers, reservations), Sales manager (everything an agent does, plus approvals and contracts) and Project manager (projects, design, construction and read-only budget). Roles that already exist are never changed, and you can edit any of them afterwards. Accounting staff use the Financial manager role.',
        'submit' => 'Add roles',
        'created' => 'Roles added: :roles',
        'kept' => 'Left unchanged because they already exist: :roles',
        'nothing_new' => 'Nothing to add. All standard roles already exist.',
    ],
];
