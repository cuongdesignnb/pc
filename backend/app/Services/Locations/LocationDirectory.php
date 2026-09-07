<?php

namespace App\Services\Locations;

use Illuminate\Support\Facades\Cache;

class LocationDirectory
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function provinces(): array
    {
        return Cache::rememberForever('locations_provinces', function (): array {
            return collect($this->payload()['provinces'] ?? [])
                ->map(fn (array $province): array => $this->publicLocation($province))
                ->values()
                ->all();
        });
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function wards(string $provinceCode): ?array
    {
        $province = $this->province($provinceCode);
        if ($province === null) {
            return null;
        }

        return collect($province['wards'] ?? [])
            ->map(fn (array $ward): array => $this->publicLocation($ward))
            ->values()
            ->all();
    }

    /**
     * Resolve both codes from the same source used by the public location API.
     * Returning the source snapshots prevents clients from submitting an
     * arbitrary name/code pair to an order.
     *
     * @return array{province: array<string, mixed>, ward: array<string, mixed>}|null
     */
    public function resolve(string $provinceCode, string $wardCode): ?array
    {
        $province = $this->province($provinceCode);
        if ($province === null) {
            return null;
        }

        $ward = collect($province['wards'] ?? [])
            ->first(fn (array $candidate): bool => $this->sameCode($candidate['code'] ?? null, $wardCode));
        if (! is_array($ward)) {
            return null;
        }

        return [
            'province' => $this->publicLocation($province),
            'ward' => $this->publicLocation($ward),
        ];
    }

    /** @return array<string, mixed>|null */
    private function province(string $code): ?array
    {
        return collect($this->payload()['provinces'] ?? [])
            ->first(fn (array $province): bool => $this->sameCode($province['code'] ?? null, $code));
    }

    /** @return array<string, mixed> */
    private function publicLocation(array $location): array
    {
        return [
            'name' => (string) ($location['name'] ?? ''),
            'code' => (string) ($location['code'] ?? ''),
            'type' => (string) ($location['type'] ?? ''),
            'typename' => (string) ($location['typename'] ?? ''),
            'fullname' => (string) ($location['fullname'] ?? $location['name'] ?? ''),
        ];
    }

    private function sameCode(mixed $left, mixed $right): bool
    {
        return (string) $left !== '' && (string) $left === trim((string) $right);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return Cache::rememberForever('locations_payload', function (): array {
            $contents = file_get_contents(base_path('locations.json'));
            $decoded = json_decode(ltrim((string) $contents, "\xEF\xBB\xBF"), true);

            return is_array($decoded) ? $decoded : ['provinces' => []];
        });
    }
}
