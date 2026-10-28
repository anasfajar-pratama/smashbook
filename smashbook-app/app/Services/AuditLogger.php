<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function log(
        string $action,
        string $entity,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'before_json' => $before,
            'after_json' => $after,
            'reason' => $reason,
            'ip' => request()?->ip(),
        ]);
    }
}
