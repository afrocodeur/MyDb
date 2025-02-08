<?php declare(strict_types=1);

namespace MyDB\Migration;

use MyDB\Exceptions\MigrationVersionNotFoundException;
use MyDB\Exceptions\DriverNotFoundException;
use MyDB\ILogger;
use MyDB\MyDB;

class DbMigrator {

    /** @var null|class-string<IMigrationConfig> */
    private static ?string $logClassName = null;
    /** @var class-string<IMigrationConfig> */
    private static string $configClassName;
    private IMigrationConfig $config;
    private ?ILogger $logger = null;
    private int $nbMigrationsExecuted = 0;

    public function __construct(?IMigrationConfig $config = null, ?ILogger $logger = null) {

        if($config) {
            $this->config = $config;
        } else {
            $this->config = new self::$configClassName;
        }

        if($logger) {
            $this->logger = $logger;
        } else if(self::$logClassName) {
            $this->logger = new self::$logClassName;
        }
    }

    /**
     * @param class-string<IMigrationConfig> $configClassName
     */
    public static function setConfig(string $configClassName): void {
        self::$configClassName = $configClassName;
    }
    public static function setLogger(string $logClassName): void {
        self::$logClassName = $logClassName;
    }

    public function executedMigrations(): int {
        return $this->nbMigrationsExecuted;
    }

    /**
     * @throws MigrationVersionNotFoundException
     */
    private function assertVersion(?string $version): void {
        if(is_null($version)) {
            return;
        }
        if(in_array($version, $this->config->versions())) {
            return;
        }
        throw new MigrationVersionNotFoundException($version);
    }

    /**
     * @throws DriverNotFoundException
     */
    private function setup(): void {
        if(MyDb::instance()->utils()->tableExists('migrations')) {
            return;
        }
        $this->logger?->info('Setup of the migrations table.');

        $migrationBuilder = MyDb::instance()->migrationBuilder();
        $migrationBuilder->createTableIfNotExists('migrations', function(ITableBuilder $table) {
            $table->id('id');
            $table->string('version');
            $table->string('migration');
            $table->timestamp('created_at')->useCurrent();
        });
        if(MyDb::instance()->utils()->tableExists('migrations')) {
            $this->logger?->success('Migrations table created with success.');
            return;
        }
        $this->logger?->critical('Error while creating migrations table.');
        exit;
    }

    private function getCurrentVersion(): ?string {
        return MigrationRepository::lastVersion();
    }

    /**
     * @param array<string> $versions
     * @throws DriverNotFoundException
     */
    private function runVersions(?string $toVersion, array $versions, bool $shouldRollback = false): void {
        $currentVersion = $this->getCurrentVersion();
        if(!$currentVersion && $shouldRollback) {
            $this->logger?->warning('No current version find. Make sure that your migrations table is update.');
            return;
        }
        $this->logger?->info("Current version : $currentVersion");
        $startFromIndex = array_search($currentVersion, $versions);
        $startFromIndex = ($startFromIndex === false ? 0 : ($shouldRollback ? $startFromIndex : $startFromIndex + 1));

        $versionsToRun = array_slice($versions, $startFromIndex);
        $this->nbMigrationsExecuted = 0;
        foreach ($versionsToRun as $versionToRun) {
            $this->logger?->info("Running version : $versionToRun ");
            $nbMigrations = $this->runVersionMigrations($versionToRun, $shouldRollback);
            $this->nbMigrationsExecuted += $nbMigrations;
            $this->logger?->success("$nbMigrations migrations executed");
            if($versionToRun === $toVersion) {
                break;
            }
        }
    }

    /**
     * @throws DriverNotFoundException
     */
    private function runVersionMigrations(string $versionName, bool $shouldRollback = false): int {
        $migrationClassNames = $this->config->migrations();
        $nbMigrations = 0;

        $migrationClassNames = $shouldRollback ? array_reverse($migrationClassNames) : $migrationClassNames;
        $methodName = $shouldRollback ? $this->config->rollbackName($versionName) : $versionName;

        foreach ($migrationClassNames as $migrationClassName) {
            if(!method_exists($migrationClassName, $versionName)) {
                continue;
            }

            if($shouldRollback && !MigrationRepository::exists($versionName, $migrationClassName)) {
                $this->logger?->note("SKIP: $migrationClassName::$methodName no migration found for $versionName.");
                continue;
            }
            $this->runVersionMigration($migrationClassName, $methodName);
            if(!$shouldRollback) {
                MigrationRepository::create(version: $versionName, migration: $migrationClassName);
            } else {
                MigrationRepository::delete(version: $versionName, migration: $migrationClassName);
            }
            $nbMigrations++;
        }
        return $nbMigrations;
    }

    /**
     * @throws DriverNotFoundException
     */
    private function runVersionMigration(string $migrationClassName, string $methodName): void {
        $this->logger?->note("Run $migrationClassName::$methodName");

        $migration = new $migrationClassName();
        $migrationBuilder = MyDB::instance()->migrationBuilder();
        $migration->{$methodName}($migrationBuilder);
    }

    /**
     * @throws MigrationVersionNotFoundException
     * @throws DriverNotFoundException
     */
    public function migrate(?string $toVersion = null): void {
        $this->assertVersion($toVersion);
        $this->setup();

        $this->runVersions($toVersion, $this->config->versions());
    }

    /**
     * @throws MigrationVersionNotFoundException
     * @throws DriverNotFoundException
     */
    public function rollback(?string $toVersion = null): void {
        $this->assertVersion($toVersion);
        $this->setup();

        $versions = array_reverse($this->config->versions());
        $this->runVersions($toVersion, $versions, shouldRollback: true);
    }

    /**
     * @throws MigrationVersionNotFoundException
     * @throws DriverNotFoundException
     */
    public function run(string $version): void {
        $this->assertVersion($version);
        $this->runVersionMigrations($version);
    }

    /**
     * @throws MigrationVersionNotFoundException
     * @throws DriverNotFoundException
     */
    public function reset(?string $fromVersion = null): void {
        $currentVersion = $this->getCurrentVersion();
        $this->rollback($fromVersion);
        $this->migrate($currentVersion);
    }

    public function stats(): array {
        return MigrationRepository::stats();
    }

}