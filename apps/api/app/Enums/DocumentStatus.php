<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentStatus: string
{
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Queued = 'queued';
    case Processing = 'processing';
    case Indexed = 'indexed';
    case Failed = 'failed';
    case Deleting = 'deleting';
    case Deleted = 'deleted';
}
