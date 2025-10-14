<?php

final class CurrencyFormatter
{
    private static $instance;

    private static function getInstance()
    {
        // If intl extension is available, lazily create a NumberFormatter instance
        if (self::$instance === null && class_exists('NumberFormatter')) {
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && class_exists('Locale')) {
                self::$instance = new NumberFormatter(Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']), NumberFormatter::CURRENCY);
            } else {
                self::$instance = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
            }
        }

        return self::$instance;
    }

    public static function format($amount, $currency)
    {
        $fmt = self::getInstance();
        if ($fmt instanceof NumberFormatter) {
            return $fmt->formatCurrency($amount, $currency);
        }
        // Fallback when intl extension is missing
        $symbols = [
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'AUD' => 'A$', 'CAD' => 'C$', 'CHF' => 'CHF'
        ];
        $symbol = $symbols[$currency] ?? $currency;
        $formatted = number_format((float)$amount, 2, '.', ',');
        // Place symbol before amount for common currencies
        if ($symbol !== $currency) {
            return $symbol . $formatted;
        }
        return $formatted . ' ' . $currency;
    }
}
