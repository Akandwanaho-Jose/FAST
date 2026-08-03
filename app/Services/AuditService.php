<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\Database;
use JsonException;

final class AuditService
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @param array<string, mixed> $metadata
     * @throws JsonException
     */
    public function record(
        ?int $userId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        string $ipAddress,
        string $userAgent,
        array $metadata = []
    ): void {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO audit_logs
                (user_id, action, entity_type, entity_id, ip_address,
                 user_agent, metadata)
             VALUES
                (:user_id, :action, :entity_type, :entity_id, :ip_address,
                 :user_agent, :metadata)'
        );
        $statement->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $ipAddress !== '' ? $ipAddress : null,
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'metadata' => $metadata === []
                ? null
                : json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }
}
