<?php

namespace App\Http\Enums;

enum FinanceEnum: string
{
    case PHD = 'مصاحبه دکتری';
    case TUTS = 'دوره های آموزشی مهارتی';
    case EVENTS = 'رویدادهای دانشجویی فرهنگی';
    case DORM = ' خوابگاه دائمی';
    case DORM_NIGHT = 'شب خواب (خوابگاه های دانشجویی)';
    case HEALTH = 'مرکز سنجش سلامت روان';
    case MOSHAVERA = 'مرکز مشاوره';
    case ADVISOR = 'مشاوره انتخاب رشته';

    public static function getType($type)
    {
        return (new \ReflectionEnum("FinanceEnum"))->getCase(strtoupper($type))->getValue();
    }
}
