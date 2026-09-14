<?php

// Two independent PHP processes pause immediately before the actual UPDATE.
// The parent releases both barriers only once both SQL statements are ready.
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use AnyMedia\Interpresso\Services\ProcessLock;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Config\Repository as Config;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;

$app = new Container();
Container::setInstance($app);
$app->instance('config', new Config(['interpresso' => [
    'db_connection' => 'default', 'table_settings' => 'lock_settings', 'cache_key' => 'lock-test',
]]));
Facade::setFacadeApplication($app);
Cache::swap(new Repository(new ArrayStore()));
$database = new Manager($app);
$database->addConnection(['driver' => 'sqlite', 'database' => $argv[1], 'busy_timeout' => 5000]);
$database->bootEloquent();
$connection = $database->getConnection();
$connection->enableQueryLog();
$connection->beforeExecuting(static function (string $sql): void {
    if (str_starts_with(strtolower($sql), 'update ')) {
        fwrite(STDOUT, "ready\n");
        fflush(STDOUT);
        if (trim(fgets(STDIN)) !== 'go') {
            throw new RuntimeException('Missing SQL barrier signal.');
        }
    }
});
$won = (new ProcessLock())->acquire($argv[2], 900);
echo json_encode(['won' => $won, 'queries' => $connection->getQueryLog()], JSON_THROW_ON_ERROR), "\n";
