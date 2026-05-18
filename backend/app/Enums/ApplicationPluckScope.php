<?php

declare(strict_types=1);

namespace App\Enums;

enum ApplicationPluckScope: string
{
    /** Applications with at least one log (logs filters). */
    case WithLogs = 'with_logs';

    /** Applications with at least one archived log (archived logs filters). */
    case WithArchivedLogs = 'with_archived_logs';

    /** All applications (error codes UI). */
    case All = 'all';
}
