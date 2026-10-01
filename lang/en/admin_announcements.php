<?php

return [
    'navigation' => [
        'announcements' => 'Announcements',
    ],
    'labels' => [
        'announcement' => 'Announcement',
        'announcements' => 'Announcements',
    ],
    'fields' => [
        'title_id' => 'Title (Indonesian)',
        'title_en' => 'Title (English)',
        'body_id' => 'Body (Indonesian)',
        'body_en' => 'Body (English)',
        'audience' => 'Audience',
        'targets' => 'Specific users',
        'recipient_count' => 'Eligible recipients',
        'status' => 'Status',
        'push_status' => 'Push delivery',
    ],
    'audience' => [
        'all' => 'All eligible users',
        'targeted' => 'Specific users',
    ],
    'status' => ['draft' => 'Draft', 'sent' => 'Sent'],
    'push' => [
        'queued' => 'Queued / processing',
        'sent' => 'Sent',
        'no_subscribers' => 'No subscribers',
        'failed' => 'Failed',
        'unknown' => 'Unknown / ambiguous',
    ],
    'actions' => [
        'create' => 'Create announcement',
        'send' => 'Send announcement',
        'delete' => 'Delete draft',
        'save' => 'Save draft',
    ],
    'confirm' => [
        'heading' => 'Send this announcement?',
        'description' => 'It will be published to :count eligible users and cannot be edited or withdrawn.',
        'body' => 'Confirm the audience and recipient count. Push delivery is queued separately and may fail for some recipients.',
    ],
    'notifications' => [
        'sent' => 'Announcement published; push delivery has been queued.',
        'deleted' => 'Draft deleted.',
    ],
    'validation' => [
        'copy' => 'Provide both Indonesian and English titles and bodies within the allowed limits.',
        'audience' => 'Choose a supported audience.',
        'targets' => 'Every selected target must be eligible and valid.',
        'no_recipients' => 'This announcement has no eligible recipients.',
    ],
];
