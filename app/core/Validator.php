<?php
namespace App\Core;

class Validator
{
    public static function username(string $username): bool
    {
        if (strlen($username) < 3 || strlen($username) > 32) {
            return false;
        }
        return (bool) preg_match('/^[a-z0-9_]+$/', $username);
    }

    public static function fullName(string $name): bool
    {
        $len = mb_strlen($name);
        return $len >= 2 && $len <= 120;
    }

    public static function gmail(string $email): bool
    {
        return (bool) preg_match('/^[A-Z0-9._%+-]+@gmail\.com$/i', $email);
    }

    public static function password(string $password): array
    {
        $errors = [];
        $len = strlen($password);
        if ($len < 8 || $len > 24) {
            $errors[] = 'رمز عبور باید بین ۸ تا ۲۴ کاراکتر باشد.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'رمز عبور باید حداقل یک حرف بزرگ داشته باشد.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'رمز عبور باید حداقل یک حرف کوچک داشته باشد.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'رمز عبور باید حداقل یک عدد داشته باشد.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'رمز عبور باید حداقل یک نماد داشته باشد.';
        }
        $lower = strtolower($password);
        $weak = ['password', '123456', 'qwerty', '111111', 'abcdef', 'iloveyou', 'admin', 'letmein', 'welcome', 'monkey', 'dragon'];
        foreach ($weak as $pattern) {
            if (strpos($lower, $pattern) !== false) {
                $errors[] = 'رمز عبور بسیار ضعیف است.';
                break;
            }
        }
        return $errors;
    }

    public static function bio(string $bio): bool
    {
        return mb_strlen($bio) <= 255;
    }

    public static function phone(string $phone): bool
    {
        if ($phone === '') {
            return true;
        }
        return (bool) preg_match('/^[+0-9\s\-]{6,20}$/', $phone);
    }

    public static function messageBody(string $body): bool
    {
        $len = mb_strlen($body);
        return $len > 0 && $len <= 4000;
    }
}
