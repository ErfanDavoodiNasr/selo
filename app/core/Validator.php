<?php
declare(strict_types=1);

namespace App\Core;

class Validator
{
    public static function username(string $username): bool
    {
        if (LaravelValidator::available()) {
            return LaravelValidator::passes(['username' => $username], [
                'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9_]+$/', 'not_regex:/group$/i'],
            ]);
        }

        if (strlen($username) < 3 || strlen($username) > 32) {
            return false;
        }
        if (!preg_match('/^[a-z0-9_]+$/', $username)) {
            return false;
        }
        if (self::usernameEndsWithGroup($username)) {
            return false;
        }
        return true;
    }

    public static function usernameEndsWithGroup(string $username): bool
    {
        return (bool) preg_match('/group$/', strtolower($username));
    }

    public static function groupHandle(string $handle): bool
    {
        $handle = strtolower(trim($handle));
        if (strlen($handle) < 5 || strlen($handle) > 32) {
            return false;
        }
        if (!preg_match('/^[a-z0-9_]+$/', $handle)) {
            return false;
        }
        return (bool) preg_match('/group$/', $handle);
    }

    public static function fullName(string $name): bool
    {
        if (LaravelValidator::available()) {
            return LaravelValidator::passes(['name' => $name], [
                'name' => ['required', 'string', 'min:2', 'max:120'],
            ]);
        }

        $len = mb_strlen($name);
        return $len >= 2 && $len <= 120;
    }

    public static function gmail(string $email): bool
    {
        $email = strtolower(trim($email));
        if (LaravelValidator::available()) {
            return LaravelValidator::passes(['email' => $email], [
                'email' => ['required', 'email:rfc', 'max:190', 'regex:/@(gmail\.com|googlemail\.com)$/i'],
            ]);
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }
        $domain = $parts[1];
        return $domain === 'gmail.com' || $domain === 'googlemail.com';
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
        if (LaravelValidator::available()) {
            return LaravelValidator::passes(['bio' => $bio], [
                'bio' => ['nullable', 'string', 'max:255'],
            ]);
        }

        return mb_strlen($bio) <= 255;
    }

    public static function phone(string $phone): bool
    {
        if ($phone === '') {
            return true;
        }
        if (LaravelValidator::available()) {
            return LaravelValidator::passes(['phone' => $phone], [
                'phone' => ['string', 'min:6', 'max:20', 'regex:/^[+0-9\s\-]{6,20}$/'],
            ]);
        }

        return (bool) preg_match('/^[+0-9\s\-]{6,20}$/', $phone);
    }

    public static function messageBody(string $body): bool
    {
        if (LaravelValidator::available()) {
            return LaravelValidator::passes(['body' => $body], [
                'body' => ['required', 'string', 'min:1', 'max:4000'],
            ]);
        }

        $len = mb_strlen($body);
        return $len > 0 && $len <= 4000;
    }
}
