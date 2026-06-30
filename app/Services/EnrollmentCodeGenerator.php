<?php

namespace App\Services;

class EnrollmentCodeGenerator
{
    /**
     * List of short Persian names (3–5 characters) used for generating
     * human-friendly enrollment codes.
     */
    private const PERSIAN_NAMES = [
        'آوا', 'باران', 'بهار', 'پدرام', 'پریا', 'تارا', 'ثمین',
        'جاوید', 'چکاو', 'حافظ', 'خورشید', 'دانیال', 'رادمان',
        'زهرا', 'سارا', 'شایان', 'طاها', 'ظریف', 'عرفان',
        'غزل', 'فرهاد', 'کامیار', 'گلناز', 'مهرداد', 'نرگس',
        'ویدا', 'هادی', 'یاسمن', 'آرش', 'بیتا', 'پگاه',
        'ژاله', 'سامان', 'شبنم', 'مهرسا', 'نیکا', 'هدیه',
    ];

    /**
     * Generate a 7-character enrollment code consisting of a random
     * Persian name combined with random digits.
     *
     * Example: "سارا512", "مهرداد", "باران73"
     *
     * The total length will be exactly 7 characters (Persian name + digits).
     */
    public function generate(): string
    {
        $name = self::PERSIAN_NAMES[array_rand(self::PERSIAN_NAMES)];
        $nameLen = mb_strlen($name);
        $digitsNeeded = 7 - $nameLen;

        $digits = '';
        for ($i = 0; $i < $digitsNeeded; $i++) {
            $digits .= random_int(0, 9);
        }

        return $name . $digits;
    }
}
