<?php

return [
    'nav_group' => 'Platform',
    'title' => 'Database backups',
    'create_heading' => 'Create a backup now',
    'create_description' => 'Saves a copy of the whole database, every company included, to your backup storage. The automatic backup also runs every night at 02:00.',
    'create_button' => 'Back up the database now',
    'running' => 'A backup is being created. This page updates by itself, and you will get a notification when it is ready.',
    'already_running' => 'A backup is already being created. Wait for it to finish.',
    'started_title' => 'Backup started',
    'started_body' => 'It will appear in the list below in a moment, and you will be notified when it is ready.',
    'list_heading' => 'Saved backups',
    'list_description' => 'Newest first. Download links work for 5 minutes. Keep downloaded files private: they contain all companies\' data.',
    'list_failed' => 'The backup list could not be loaded from storage: :detail',
    'empty' => 'No backups yet. Create the first one above.',
    'download' => 'Download',
    'download_missing' => 'That backup no longer exists. Refresh the list.',
    'download_failed' => 'The download link could not be prepared.',
    'notify' => [
        'done_title' => 'Backup ready',
        'done_body' => 'The database backup was saved and can be downloaded from Database backups.',
        'failed_title' => 'Backup failed',
        'failed_body' => 'The backup was not saved. Details: :detail',
    ],
];
