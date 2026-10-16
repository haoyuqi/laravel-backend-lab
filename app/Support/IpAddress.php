<?php

namespace App\Support;

use App\Models\BlackList;
use Illuminate\Database\Eloquent\Builder;

final class IpAddress
{
    /** Return PHP's canonical representation for a valid IPv4 or IPv6 address. */
    public static function normalize(string $ip): string
    {
        $packed = @inet_pton($ip);

        return $packed === false ? $ip : inet_ntop($packed);
    }

    /**
     * Legacy databases may contain expanded IPv6 text or mixed casing.
     * Include common textual forms so callers can find those rows without a
     * bulk data migration.
     *
     * @return list<string>
     */
    public static function equivalentForms(string $ip): array
    {
        $canonical = self::normalize($ip);
        $forms = [$ip, $canonical, strtoupper($ip), strtoupper($canonical)];
        $packed = @inet_pton($canonical);

        if ($packed !== false && strlen($packed) === 16) {
            $groups = str_split(bin2hex($packed), 4);
            $expanded = implode(':', $groups);
            $forms[] = $expanded;
            $forms[] = strtoupper($expanded);
        }

        return array_values(array_unique($forms));
    }

    /** Find an existing row with the same address, including legacy text forms. */
    public static function findBlacklistRecord(Builder $query, string $ip): ?BlackList
    {
        $ip = self::normalize($ip);
        $connection = $query->getModel()->getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $column = $query->getQuery()->getGrammar()->wrap('ip');

            return $query->whereRaw($column.' = ?::inet', [$ip])->first();
        }

        $scanQuery = clone $query;
        $record = $query->whereIn('ip', self::equivalentForms($ip))->first();
        if ($record || ! str_contains($ip, ':')) {
            return $record;
        }

        foreach ($scanQuery->where('ip', 'like', '%:%')->cursor() as $candidate) {
            if (self::normalize($candidate->ip) === $ip) {
                return $candidate;
            }
        }

        return null;
    }
}
