<?php
declare(strict_types=1);

/** Record who viewed or changed what. Never put patient details in $details. */
function audit(string $action, ?string $entity = null, ?int $entityId = null, string $details = ''): void
{
    $user = function_exists('current_user') ? current_user() : null;
    db()->prepare('INSERT INTO audit_log (created_at, user_id, user_name, action, entity, entity_id, ip, details) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([
            now_utc(),
            $user['id'] ?? null,
            $user['name'] ?? (PHP_SAPI === 'cli' ? 'system (CLI)' : 'public'),
            $action,
            $entity,
            $entityId,
            client_ip(),
            mb_substr($details, 0, 500),
        ]);
}
