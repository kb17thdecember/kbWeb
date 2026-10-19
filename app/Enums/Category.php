<?php

namespace App\Enums;

enum Category: int
{
    case ARISTON_TT = 1;
    case ARISTON_GT = 2;
    case ARISTON_HP = 3;
    case ARISTON_TD = 4;
    case KFLEX_NBR = 5;
    case KFLEX_XLPE = 6;
    case KFLEX_ACCESSOR = 7;

    public static function stringNames(): array
    {
        return [
            self::ARISTON_TT->value => 'Ariston-Bình nước nóng trực tiếp',
            self::ARISTON_GT->value => 'Ariston-Bình nước nóng gián tiếp',
            self::ARISTON_HP->value => 'Ariston-Bơm nhiệt',
            self::ARISTON_TD->value => 'Ariston-Thái dương năng',
            self::KFLEX_NBR->value => 'K-Flex-Cao su lưu hóa NBR',
            self::KFLEX_XLPE->value => 'K-Flex-Polyolefin XLPE',
            self::KFLEX_ACCESSOR->value => 'Phụ kiện bảo ôn K-Flex',
        ];
    }

    public function string(): string
    {
        $stringNames = $this->stringNames();

        return $stringNames[$this->value];
    }
}