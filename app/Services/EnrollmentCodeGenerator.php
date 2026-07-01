<?php

namespace App\Services;

class EnrollmentCodeGenerator
{
    /**
     * Generate a 7-digit numeric enrollment code.
     *
     * Example: "3804291", "6150382", "9042716"
     *
     * The code is guaranteed to be exactly 7 digits, never starting with zero.
     */
    public function generate(): string
    {
        return (string) random_int(1000000, 9999999);
    }
}
