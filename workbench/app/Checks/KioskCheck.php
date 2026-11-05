<?php

namespace Workbench\App\Checks;

use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

class KioskCheck extends Check
{
    public function run(): Result
    {
        return Result::ok('Kiosk is online');
    }
}
