<?php

namespace Workbench\App\Checks;

use Illuminate\Support\Facades\DB;
use NetOs\Vitals\Check;
use NetOs\Vitals\Result;

class DeskCountCheck extends Check
{
    public function run(): Result
    {
        $desks = DB::table('desks')->count();

        return $desks === 0
            ? Result::warning('This tenant has no desks')
            : Result::ok("{$desks} desks")->meta(['desks' => $desks]);
    }
}
