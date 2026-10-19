<?php

namespace App\Enums;

enum SubCategory: int
{
    case ARISTON = 1;
    case KFLEX = 2;

    public static function stringNames(): array
    {
        return [
            self::ARISTON->value => 'Ariston',
            self::KFLEX->value => 'K-Flex',
        ];
    }

    public function string(): string
    {
        $stringNames = $this->stringNames();

        return $stringNames[$this->value];
    }
}