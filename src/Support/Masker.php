<?php

declare(strict_types=1);

namespace Redakte\Support;

use Redakte\MaskStrategy;

/**
 * Verilen entity türü ve maskeleme stratejisine göre maske üreten yardımcı sınıf.
 */
final class Masker
{
    /**
     * Değeri seçilen stratejiye göre maskeler
     */
    public static function mask(
        string $value,
        string $entityType,
        MaskStrategy $strategy,
        int $index = 1,
        ?string $token = null,
    ): string {
        return match ($strategy) {
            MaskStrategy::TAG => $token ?? '[' . $entityType . '_' . $index . ']',
            MaskStrategy::LABEL => '[' . $entityType . ']',
            MaskStrategy::ASTERISK => self::maskAsterisk($value),
            MaskStrategy::PARTIAL => self::maskPartial($value, $entityType),
        };
    }

    /**
     * Boşluk ve bazı özel karakterleri koruyarak karakter sayısınca '*' basar
     */
    public static function maskAsterisk(string $value): string
    {
        $len = mb_strlen($value, 'UTF-8');
        if ($len === 0) {
            return '';
        }

        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return str_repeat('*', strlen($value));
        }

        $out = '';
        foreach ($chars as $char) {
            $out .= ctype_space($char) ? $char : '*';
        }

        return $out;
    }

    /**
     * Entity türüne özel kısmi (okunabilir) maskeleme
     */
    public static function maskPartial(string $value, string $entityType): string
    {
        return match ($entityType) {
            'TCKN' => self::partialTckn($value),
            'VKN' => self::partialVkn($value),
            'IBAN' => self::partialIban($value),
            'TELEFON' => self::partialPhone($value),
            'EPOSTA' => self::partialEmail($value),
            'KISI' => self::partialName($value),
            'KREDI_KARTI' => self::partialCreditCard($value),
            'PLAKA' => self::partialPlate($value),
            'MERSIS' => self::partialMersis($value),
            'IP_ADRESI' => self::partialIp($value),
            'ESAS_NO', 'KARAR_NO', 'DOSYA_NO' => self::partialLegalNumber($value),
            'ADRES' => self::partialAddress($value),
            default => self::partialGeneric($value),
        };
    }

    private static function partialTckn(string $tckn): string
    {
        $clean = preg_replace('/\D/', '', $tckn) ?? $tckn;
        if (strlen($clean) === 11) {
            return substr($clean, 0, 3) . '*****' . substr($clean, -3);
        }
        return self::partialGeneric($tckn);
    }

    private static function partialVkn(string $vkn): string
    {
        $clean = preg_replace('/\D/', '', $vkn) ?? $vkn;
        if (strlen($clean) === 10) {
            return substr($clean, 0, 3) . '****' . substr($clean, -3);
        }
        return self::partialGeneric($vkn);
    }

    private static function partialIban(string $iban): string
    {
        $clean = preg_replace('/\s+/', '', strtoupper($iban)) ?? $iban;
        if (str_starts_with($clean, 'TR') && strlen($clean) === 26) {
            return substr($clean, 0, 4) . ' **** **** **** **** **** ' . substr($clean, -2);
        }
        return self::partialGeneric($iban);
    }

    private static function partialPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? $phone;
        // Son 10 hanesini al (örn. 5321234567)
        if (strlen($digits) >= 10) {
            $core = substr($digits, -10);
            $prefix = strlen($digits) > 10 ? substr($digits, 0, strlen($digits) - 10) : '';
            $prefixFormatted = $prefix !== '' ? ($prefix === '90' ? '+90 ' : '0') : '';
            return $prefixFormatted . substr($core, 0, 3) . ' *** ** ' . substr($core, -2);
        }
        return self::partialGeneric($phone);
    }

    private static function partialEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return self::partialGeneric($email);
        }

        $user = $parts[0];
        $domain = $parts[1];

        $userLen = mb_strlen($user, 'UTF-8');
        if ($userLen <= 1) {
            $maskedUser = '*';
        } elseif ($userLen === 2) {
            $maskedUser = mb_substr($user, 0, 1, 'UTF-8') . '*';
        } else {
            $maskedUser = mb_substr($user, 0, 1, 'UTF-8') . '***' . mb_substr($user, -1, 1, 'UTF-8');
        }

        return $maskedUser . '@' . $domain;
    }

    private static function partialName(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) {
            return '***';
        }

        $maskedWords = [];
        foreach ($words as $word) {
            $len = mb_strlen($word, 'UTF-8');
            if ($len <= 1) {
                $maskedWords[] = $word;
            } else {
                $firstChar = mb_substr($word, 0, 1, 'UTF-8');
                $maskedWords[] = $firstChar . str_repeat('*', $len - 1);
            }
        }

        return implode(' ', $maskedWords);
    }

    private static function partialCreditCard(string $cc): string
    {
        $clean = preg_replace('/\D/', '', $cc) ?? $cc;
        if (strlen($clean) >= 12) {
            return '**** **** **** ' . substr($clean, -4);
        }
        return self::partialGeneric($cc);
    }

    private static function partialPlate(string $plate): string
    {
        $parts = preg_split('/\s+/u', trim($plate), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts && count($parts) >= 2) {
            return $parts[0] . ' *** ' . end($parts);
        }
        return self::partialGeneric($plate);
    }

    private static function partialMersis(string $mersis): string
    {
        $clean = preg_replace('/\D/', '', $mersis) ?? $mersis;
        if (strlen($clean) === 16) {
            return substr($clean, 0, 4) . '********' . substr($clean, -4);
        }
        return self::partialGeneric($mersis);
    }

    private static function partialIp(string $ip): string
    {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.*.*';
        }
        return self::partialGeneric($ip);
    }

    private static function partialLegalNumber(string $val): string
    {
        if (preg_match('/(\d{4})[\/-](\d+)/', $val, $m)) {
            return $m[1] . '/****';
        }
        return self::partialGeneric($val);
    }

    private static function partialAddress(string $addr): string
    {
        $len = mb_strlen($addr, 'UTF-8');
        if ($len <= 10) {
            return '***';
        }
        return mb_substr($addr, 0, 8, 'UTF-8') . '... [Adres Gizlendi]';
    }

    private static function partialGeneric(string $value): string
    {
        $len = mb_strlen($value, 'UTF-8');
        if ($len <= 3) {
            return str_repeat('*', $len);
        }
        return mb_substr($value, 0, 1, 'UTF-8') . '***' . mb_substr($value, -1, 1, 'UTF-8');
    }
}
