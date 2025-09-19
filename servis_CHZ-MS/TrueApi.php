<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class TrueApi
{
    public static function requestChallenge(?string $inn = null): array
    {
        $query = [];
        if ($inn !== null) {
            $trimmed = trim($inn);
            if ($trimmed !== '') {
                $query['inn'] = $trimmed;
            }
        }

        $response = trueApiRequest('GET', '/auth/key', $query);
        return self::unwrapResult($response);
    }

    public static function exchangeSignature(array $payload, array $options = []): array
    {
        $body = [];
        if (!empty($payload['uuid'])) {
            $body['uuid'] = $payload['uuid'];
        }
        if (!empty($payload['signature'])) {
            $body['data'] = $payload['signature'];
        }
        if (!empty($options['inn'])) {
            $body['inn'] = $options['inn'];
        }
        if (array_key_exists('unitedToken', $options)) {
            $body['unitedToken'] = (bool) $options['unitedToken'];
        }

        $response = trueApiRequest('POST', '/auth/simpleSignIn', [], $body);
        $result = self::unwrapResult($response);
        $normalized = self::normalizeTokenResponse($result);
        $normalized['raw'] = $result;

        return $normalized;
    }

    private static function unwrapResult($response): array
    {
        if (!is_array($response)) {
            return [];
        }
        if (isset($response['result']) && is_array($response['result'])) {
            return $response['result'];
        }
        return $response;
    }

    private static function firstNonEmpty(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            $value = $source[$key];
            if ($value === null) {
                continue;
            }
            if (is_scalar($value)) {
                $trimmed = trim((string) $value);
                if ($trimmed !== '') {
                    return $trimmed;
                }
            }
        }
        return null;
    }

    private static function normalizeDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        try {
            $date = new DateTimeImmutable($trimmed);
            return $date->format(DateTimeInterface::ATOM);
        } catch (Throwable $exception) {
            return null;
        }
    }

    private static function normalizeTokenResponse(array $response): array
    {
        $token = self::firstNonEmpty($response, ['token', 'uuidToken', 'unitedToken']);
        $expiresAt = self::firstNonEmpty(
            $response,
            ['expiresAt', 'expireAt', 'expireDate', 'expireDateTime', 'tokenExpireAt', 'tokenExpireDate']
        );
        $organization = self::normalizeOrganization($response);

        return [
            'token' => $token,
            'expiresAt' => self::normalizeDate($expiresAt),
            'organization' => $organization,
        ];
    }

    private static function normalizeOrganization(array $response): ?array
    {
        $candidates = [];
        foreach (['organization', 'orgInfo', 'participant', 'holder'] as $key) {
            if (!empty($response[$key]) && is_array($response[$key])) {
                $candidates[] = $response[$key];
            }
        }

        $org = $candidates[0] ?? [];
        $inn = self::firstNonEmpty($org, ['inn', 'orgInn', 'participantInn']);
        $kpp = self::firstNonEmpty($org, ['kpp', 'participantKpp']);
        $ogrn = self::firstNonEmpty($org, ['ogrn', 'orgOgrn']);
        $name = self::firstNonEmpty($org, ['name', 'fullName', 'organizationName', 'participantName']);

        if (!$inn && array_key_exists('inn', $response)) {
            $inn = self::firstNonEmpty($response, ['inn']);
        }
        if (!$name && array_key_exists('name', $response)) {
            $name = self::firstNonEmpty($response, ['name']);
        }

        $result = array_filter([
            'name' => $name,
            'inn' => $inn,
            'kpp' => $kpp,
            'ogrn' => $ogrn,
        ], static fn ($value) => $value !== null && $value !== '');

        return $result ?: null;
    }
}
