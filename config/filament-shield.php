<?php

return [
    'entities' => [
        // Relation-manager and workflow permissions are not top-level Filament
        // resources. Show them in Shield's Roles editor so they can be assigned.
        'custom_permissions' => true,
    ],
];
