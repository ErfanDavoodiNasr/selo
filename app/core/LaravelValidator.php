<?php
declare(strict_types=1);

namespace App\Core;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

class LaravelValidator
{
    private static ?Factory $factory = null;

    public static function available(): bool
    {
        return class_exists(Factory::class)
            && class_exists(Translator::class)
            && class_exists(ArrayLoader::class)
            && class_exists(Filesystem::class);
    }

    public static function passes(array $data, array $rules): bool
    {
        if (!self::available()) {
            return false;
        }

        return self::factory()->make($data, $rules)->passes();
    }

    private static function factory(): Factory
    {
        if (self::$factory instanceof Factory) {
            return self::$factory;
        }

        $loader = new ArrayLoader();
        $translator = new Translator($loader, 'fa');
        self::$factory = new Factory($translator);

        return self::$factory;
    }
}
