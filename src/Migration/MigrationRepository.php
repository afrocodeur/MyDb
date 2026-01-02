<?php declare(strict_types=1);

namespace MyDB\Migration;

use MyDB\QueryBuilder\ARepository;

class MigrationRepository extends ARepository {

    protected string $table = 'migrations';

    public static function lastVersion(): ?string {
        $migration = (new MigrationRepository())->last();
        if($migration) {
            return $migration['version'];
        }
        return null;
    }

    public static function create(string $version, string $migration): bool {
        $repository = new MigrationRepository();
        $repository->table()->insert([
            'version' => $version,
            'migration' => $migration
        ]);
        return true;
    }

    public static function delete(string $version, string $migration): bool {
        $repository = new MigrationRepository();
        return $repository->table()->where('version', $version)->where('migration', $migration)->delete();
    }

    public static function exists(string $version, string $migration): bool {
        return (new MigrationRepository())->table()
                ->where('version', $version)->where('migration', $migration)->count() === 1;
    }

    public static function stats(): array {
        $stats = [];

        $migrations = (new MigrationRepository())->all();

        foreach ($migrations as $migration) {
            $version = $migration['version'];
            $stats[$version] = $stats[$version] ?? [];
            $stats[$version][] = $migration;
        }

        return $stats;
    }

}