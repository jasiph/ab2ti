<?php

namespace App\Support\Lapak;

final class LapakPublicContact
{
    /**
     * Build safe seller contact channels from explicit Lapak fields only.
     *
     * @return list<array{type: string, label: string, href: string, display: string}>
     */
    public static function channels(
        ?string $phone,
        ?string $whatsapp,
        ?string $email,
        ?string $prefillMessage = null,
    ): array {
        $channels = [];

        $whatsappHref = self::whatsappHref($whatsapp, $prefillMessage);
        if ($whatsappHref !== null) {
            $channels[] = [
                'type' => 'whatsapp',
                'label' => 'WhatsApp',
                'href' => $whatsappHref,
                'display' => self::displayPhone($whatsapp) ?? 'WhatsApp',
            ];
        }

        $telHref = self::telHref($phone);
        if ($telHref !== null) {
            $channels[] = [
                'type' => 'phone',
                'label' => 'Telepon',
                'href' => $telHref,
                'display' => self::displayPhone($phone) ?? 'Telepon',
            ];
        }

        $mailHref = self::mailtoHref($email);
        if ($mailHref !== null) {
            $channels[] = [
                'type' => 'email',
                'label' => 'Email',
                'href' => $mailHref,
                'display' => trim((string) $email),
            ];
        }

        return $channels;
    }

    public static function whatsappHref(?string $value, ?string $prefillMessage = null): ?string
    {
        $digits = self::internationalDigits($value);

        if ($digits === null) {
            return null;
        }

        $url = 'https://wa.me/'.$digits;

        $message = is_string($prefillMessage) ? trim($prefillMessage) : '';
        if ($message !== '') {
            $url .= '?text='.rawurlencode($message);
        }

        return $url;
    }

    public static function telHref(?string $value): ?string
    {
        $digits = self::localOrInternationalDigits($value);

        if ($digits === null) {
            return null;
        }

        return 'tel:+'.$digits;
    }

    public static function mailtoHref(?string $value): ?string
    {
        $email = trim((string) $value);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        if (preg_match('/[\s<>"\']/', $email) === 1) {
            return null;
        }

        return 'mailto:'.$email;
    }

    private static function internationalDigits(?string $value): ?string
    {
        $digits = self::digitsOnly($value);

        if ($digits === null) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        if (! str_starts_with($digits, '62') || strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    private static function localOrInternationalDigits(?string $value): ?string
    {
        $digits = self::digitsOnly($value);

        if ($digits === null) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    private static function digitsOnly(?string $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '' || preg_match('/^(javascript|data|vbscript):/i', $raw) === 1) {
            return null;
        }

        if (preg_match('#https?://#i', $raw) === 1) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        return $digits;
    }

    private static function displayPhone(?string $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $safe = preg_replace('/[^\d+\s\-()]/', '', $raw) ?? '';
        $safe = trim($safe);

        return $safe !== '' ? $safe : null;
    }
}
