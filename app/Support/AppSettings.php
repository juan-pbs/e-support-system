<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AppSettings
{
    public const EMAIL_SENDING_ENABLED = 'email_sending_enabled';
    public const EMAIL_COTIZACIONES_ENABLED = 'email_cotizaciones_enabled';
    public const EMAIL_ORDENES_ENABLED = 'email_ordenes_enabled';
    public const EMAIL_ACTAS_ENABLED = 'email_actas_enabled';

    public static function emailSendingEnabled(): bool
    {
        return self::boolean(self::EMAIL_SENDING_ENABLED, true);
    }

    public static function emailCotizacionesEnabled(): bool
    {
        return self::boolean(self::EMAIL_COTIZACIONES_ENABLED, self::emailSendingEnabled());
    }

    public static function emailOrdenesEnabled(): bool
    {
        return self::boolean(self::EMAIL_ORDENES_ENABLED, self::emailSendingEnabled());
    }

    public static function emailActasEnabled(): bool
    {
        return self::boolean(self::EMAIL_ACTAS_ENABLED, self::emailSendingEnabled());
    }

    public static function emailEnabledFor(string $documentType): bool
    {
        return match ($documentType) {
            'cotizaciones' => self::emailCotizacionesEnabled(),
            'ordenes' => self::emailOrdenesEnabled(),
            'actas' => self::emailActasEnabled(),
            default => self::emailSendingEnabled(),
        };
    }

    public static function setEmailSendingEnabled(bool $enabled, ?int $userId = null): void
    {
        self::setBoolean(self::EMAIL_SENDING_ENABLED, $enabled, $userId);
    }

    public static function setEmailCotizacionesEnabled(bool $enabled, ?int $userId = null): void
    {
        self::setBoolean(self::EMAIL_COTIZACIONES_ENABLED, $enabled, $userId);
    }

    public static function setEmailOrdenesEnabled(bool $enabled, ?int $userId = null): void
    {
        self::setBoolean(self::EMAIL_ORDENES_ENABLED, $enabled, $userId);
    }

    public static function setEmailActasEnabled(bool $enabled, ?int $userId = null): void
    {
        self::setBoolean(self::EMAIL_ACTAS_ENABLED, $enabled, $userId);
    }

    public static function boolean(string $key, bool $default = false): bool
    {
        if (! Schema::hasTable('app_settings')) {
            return $default;
        }

        return Cache::remember("app_settings.{$key}", 300, function () use ($key, $default) {
            $value = AppSetting::query()->where('key', $key)->value('value');

            if ($value === null) {
                return $default;
            }

            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
        });
    }

    public static function setBoolean(string $key, bool $value, ?int $userId = null): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value ? '1' : '0',
                'updated_by' => $userId,
            ]
        );

        Cache::forget("app_settings.{$key}");
    }
}
