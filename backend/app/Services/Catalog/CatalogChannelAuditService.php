<?php

namespace App\Services\Catalog;

use App\Models\CatalogChannelConnection;
use App\Models\CatalogChannelEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatalogChannelAuditService
{
    private const SENSITIVE_KEY_PATTERN = '/secret|token|authorization|credential|private[_-]?key|service[_-]?account/i';

    public function record(
        CatalogChannelConnection $connection,
        string $event,
        ?User $actor = null,
        array $metadata = [],
        ?Request $request = null,
    ): CatalogChannelEvent {
        return $connection->events()->create([
            'channel' => $connection->channel,
            'event' => $event,
            'actor_id' => $actor?->id,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 1000, '') : null,
            'metadata' => $this->sanitize($metadata),
        ]);
    }

    public function sanitize(array $metadata): array
    {
        $safe = [];
        foreach ($metadata as $key => $value) {
            if (preg_match(self::SENSITIVE_KEY_PATTERN, (string) $key) === 1) {
                continue;
            }
            $safe[$key] = is_array($value)
                ? $this->sanitize($value)
                : (is_scalar($value) || $value === null ? Str::limit((string) $value, 500, '') : null);
        }

        return $safe;
    }
}
