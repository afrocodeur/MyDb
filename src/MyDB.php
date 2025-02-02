<?php declare(strict_types=1);

namespace MyDB;

use Exceptions\ConfigNotFoundException;
use MyDB\Exceptions\DriverNotFoundException;
use MyDB\Migration\IMigrationBuilder;
use MyDB\QueryBuilder\IQueryBuilder;
use MyDB\QueryBuilder\IUtils;
use MyDB\Migration\MySQL\MigrationBuilder as MySqlMigrationBuilder;
use MyDB\QueryBuilder\MySQL\QueryBuilder as MySQLQueryBuilder;
use MyDB\QueryBuilder\MySQL\Utils as MySqlUtils;
use PDO;
use PDOException;
use Closure;

final class MyDB {

    /** @var array<string, class-string<IConnectionConfig>> */
    private static array $configs = [];
    private static ?MyDB $instance = null;
    private static string $default = 'mysql';

    private IConnectionConfig $config;

    private PDO $pdo;

    /**
     * @throws ConfigNotFoundException
     */
    private function __construct(string $db) {
        $this->init($db);
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function init(?string $db): void {
        if(is_null($db) || !isset(self::$configs[$db])) {
            throw new ConfigNotFoundException($db);
        }
        $config = new self::$configs[$db];
        $this->config = $config;

        try {
            $this->pdo = new PDO($config->getDsn(), $config->getUsername(), $config->getPassword(), $config->getOptions());
        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * @throws DriverNotFoundException
     */
    public static function table(string $table): IQueryBuilder {
        return self::instance()->queryBuilder()->from($table);
    }

    public static function instance(string $db = null): self {
        if($db && $db !== self::$default) {
            return new self($db);
        }
        if (!is_null(self::$instance)) {
            return self::$instance;
        }
        return self::$instance = new self($db ?? self::$default);
    }

    /**
     * @param class-string<IConnectionConfig> $config
     */
    public static function addConfig(string $name, string $config, bool $isDefault = false): void {
        self::$configs[$name] = $config;
        if($isDefault) {
            self::$default = $name;
        }
    }

    /**
     * @param array<string, class-string<IConnectionConfig>> $configs
     */
    public static function addConfigs(array $configs): void {
        self::$configs = array_merge(self::$configs, $configs);
    }
    public static function setDefault(string $name): void {
        self::$default = $name;
    }

    public function get(string $query, array $params = [], ?Closure $wrapper = null): array {
        $query = $this->pdo->prepare($query);
        $query->execute($params);

        if(!is_null($wrapper)) {
            $data = [];

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $data[] = $wrapper($row);
            }
            $query->closeCursor();
            return $data;
        }

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function execute(string $query, array $params = []): bool {
        return $this->pdo->prepare($query)->execute($params);
    }

    public function utils(): IUtils {
        $driver = $this->config->driver();
        if($driver === EDriver::MySQL) {
            return new MySqlUtils();
        }
        throw new DriverNotFoundException($driver->value);
    }

    public function migrationBuilder(): IMigrationBuilder {
        $driver = $this->config->driver();
        if($driver === EDriver::MySQL) {
            return new MySqlMigrationBuilder();
        }
        throw new DriverNotFoundException($driver->value);
    }

    /**
     * @throws DriverNotFoundException
     */
    public function queryBuilder(): IQueryBuilder {
        $driver = $this->config->driver();
        $queryBuilder = null;
        if($driver === EDriver::MySQL) {
            $queryBuilder = new MySQlQueryBuilder();
        }

        if($queryBuilder){
            $queryBuilder->useDb($this);
            return $queryBuilder;
        }

        throw new DriverNotFoundException($driver->value);
    }


}