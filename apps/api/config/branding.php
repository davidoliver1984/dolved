<?php

return [
    'workspace_public_id' => env('BRANDING_WORKSPACE_PUBLIC_ID'),
    'asset_disk' => env('BRANDING_ASSET_DISK', env('DOCUMENT_STORAGE_DISK', 'local')),
];
