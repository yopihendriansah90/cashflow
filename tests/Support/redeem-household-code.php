<?php

use App\Http\Controllers\HouseholdController;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('testing') || DB::getDriverName() !== 'mysql' ||
    ! preg_match('/\Acashflow_stage1_test_[a-z0-9_]+\z/', DB::connection()->getDatabaseName())) {
    exit(2);
}

$user = User::findOrFail($argv[1]);
$request = Request::create('/mulai/gabung', 'POST', ['code' => trim(stream_get_contents(STDIN))]);
$request->setUserResolver(fn () => $user);

// Signal after the candidate lookup, immediately before waiting for the household row.
DB::listen(function (QueryExecuted $query): void {
    if (str_contains($query->sql, 'code_hash')) {
        fwrite(STDOUT, "ready\n");
        fflush(STDOUT);
    }
});

try {
    app(HouseholdController::class)->join($request);
    fwrite(STDOUT, "joined\n");
} catch (ValidationException $exception) {
    if (! isset($exception->errors()['code'])) {
        throw $exception;
    }

    fwrite(STDOUT, "rejected\n");
}
