<?php

namespace App\Services\Integrations\Kiot;

use App\Models\IntegrationConnection;
use App\Models\IntegrationConnectionEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KiotConfigurationAuditService
{
    private const SENSITIVE_KEY_PATTERN = '/secret|token|signature|authorization|pairing[_-]?code|credential/i';

    public function record(
        IntegrationConnection $connection,
        string $event,
        ?User $actor = null,
        array $metadata = [],
        ?Request $request = null,
    ): IntegrationConnectionEvent {
        return $connection->events()->create([
            'provider' => $connection->provider,
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

            if (is_array($value)) {
                $safe[$key] = $this->sanitize($value);
            } elseif (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $safe[$key] = $value;
            } else {
                $safe[$key] = Str::limit((string) $value, 500, '');
            }
        }

        return $safe;
    }
}
