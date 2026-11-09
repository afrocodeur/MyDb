<?php declare(strict_types=1);

namespace MyDB;

use MyDB\Exceptions\ConfigNotFoundException;
use MyDB\Exceptions\DriverNotFoundException;
use MyDB\Migration\IMigrationBuilder;
use MyDB\QueryBuilder\Abstract\Plugin\IPlugin;
use MyDB\QueryBuilder\Abstract\Plugin\QueryContext;
use MyDB\QueryBuilder\IQueryBuilder;
use MyDB\QueryBuilder\IUtils;
use MyDB\Migration\MySQL\MigrationBuilder as MySqlMigrationBuilder;
use MyDB\QueryBuilder\MySQL\QueryBuilder as MySQLQueryBuilder;
use MyDB\QueryBuilder\MySQL\Utils as MySqlUtils;
use PDO;
use PDOException;
use Throwable;
use Closure;

final class MyDB {

    /** @var array<string, class-string<IConnectionConfig>> */
    private static array $configs = [];
    private static ?MyDB $instance = null;
    private static string $default = 'mysql';

    private IConnectionConfig $config;

    private PDO $pdo;

    /**
     * @var array<IPlugin>
     */
    private array $plugins = [];

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

    public function registerPlugin(IPlugin $plugin): void {
        $this->plugins[] = $plugin;
    }

    /**
     * @param array<IPlugin> $plugins
     */
    public function registerPlugins(array $plugins): void {
        $this->plugins = array_merge($this->plugins, $plugins);
    }

    /**
     * @throws DriverNotFoundException
     */
    public static function table(string $table): IQueryBuilder {
        return self::instance()->queryBuilder()->from($table);
    }

    public static function instance(?string $db = null): self {
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

    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool {
        return $this->pdo->commit();
    }

    public function rollBack(): bool {
        return $this->pdo->rollBack();
    }

    /**
     * @param array<string, class-string<IConnectionConfig>> $configs
     */
    public static function addConfigs(array $configs): void {
        self::$configs = array_merge(self::$configs, $configs);
    }
    public static function setDefault(string $name): void {
        if(self::$default !== $name) {
            self::$instance = null;
        }
        self::$default = $name;
    }

    /**
     * @param string $query
     * @param array $params
     * @return array
     */
    public function get(string $query, array $params = [], ?IQueryBuilder $queryBuilder  = null): array {
        if($queryBuilder === null) {
            $query = $this->pdo->prepare($query);
            $query->execute($params);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        }

        return $this->runWithPlugins($query, $params, $queryBuilder, function() use ($query, $params) {
            $query = $this->pdo->prepare($query);
            $query->execute($params);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    public static function transaction(callable $callback): mixed {
        $db = self::instance();
        $db->beginTransaction();
        try {
            $result = $callback($db);
            $db->commit();

            return $result;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function lastInsertedId(): ?int {
        $id = self::instance()->pdo->lastInsertId();
        return $id ? (int)$id : null;
    }


    /**
     * @param string $query
     * @param array $params
     * @param IQueryBuilder|null $queryBuilder
     * @return bool
     * @throws Throwable
     */
    public function execute(string $query, array $params = [], ?IQueryBuilder $queryBuilder  = null): bool {
        if ($queryBuilder === null) {
            return $this->pdo->prepare($query)->execute($params);
        }
        return $this->runWithPlugins($query, $params, $queryBuilder, fn() => $this->pdo->prepare($query)->execute($params));
    }

    private function runWithPlugins(string $sql, array $params, IQueryBuilder $queryBuilder, Closure $callback): mixed {
        $context = new QueryContext(
            type: $queryBuilder->getSqlType(),
            builder: $queryBuilder,
            sql: $sql,
            params: $params
        );

        $plugins = $queryBuilder->getPlugins();
        try {
            foreach ($plugins as $plugin) {
                $plugin->before($context);
                if ($context->isShortCircuited()) {
                    return $context->result;
                }
            }

            $context->result = $callback();

            foreach ($plugins as $plugin) {
                $plugin->after($context);
                if ($context->isShortCircuited()) {
                    break;
                }
            }

            return $context->result;

        } catch (Throwable $e) {
            foreach ($plugins as $plugin) {
                $plugin->onError($context, $e);
            }
            throw $e;
        }
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
            $queryBuilder->registerPlugins($this->plugins);
            $queryBuilder->useDb($this);
            return $queryBuilder;
        }

        throw new DriverNotFoundException($driver->value);
    }


}