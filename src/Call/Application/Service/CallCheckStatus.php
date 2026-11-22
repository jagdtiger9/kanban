<?php

declare(strict_types=1);

namespace App\Call\Application\Service;

enum CallCheckStatus: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Fail = 'fail';
}
