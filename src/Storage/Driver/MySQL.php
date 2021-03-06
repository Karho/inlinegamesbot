<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2019 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\inlinegamesbot\Storage\Driver;

use AD7six\Dsn\Dsn;
use jacklul\inlinegamesbot\Entity\TempFile;
use jacklul\inlinegamesbot\Exception\StorageException;
use PDO;
use PDOException;

/**
 * Class MySQL
 */
class MySQL
{
    /**
     * PDO object
     *
     * @var PDO
     */
    private static $pdo;

    /**
     * Lock file object
     *
     * @var TempFile
     */
    private static $lock;

    /**
     * SQL to create database structure
     *
     * @var string
     */
    private static $structure = 'CREATE TABLE IF NOT EXISTS `game` (
        `id` CHAR(255) COMMENT "Unique identifier for this entry",
        `data` TEXT NOT NULL COMMENT "Stored data",
        `created_at` timestamp NULL DEFAULT NULL COMMENT "Entry creation date",
        `updated_at` timestamp NULL DEFAULT NULL COMMENT "Entry update date",

        PRIMARY KEY (`id`)
    );';

    /**
     * Create table structure
     *
     * @return bool
     * @throws StorageException
     */
    public static function createStructure(): bool
    {
        if (!self::isDbConnected()) {
            self::initializeStorage();
        }

        if (!self::$pdo->query(self::$structure)) {
            throw new StorageException('Failed to create DB structure!');
        }

        return true;
    }

    /**
     * Check if database connection has been created
     *
     * @return bool
     */
    public static function isDbConnected(): bool
    {
        return self::$pdo !== null;
    }

    /**
     * Initialize PDO connection
     *
     * @param $pdo
     *
     * @return bool
     *
     * @throws StorageException
     */
    public static function initializeStorage($pdo = null): bool
    {
        if (self::isDbConnected()) {
            return true;
        }

        if (!defined('TB_GAME')) {
            define('TB_GAME', 'game');
        }

        if ($pdo === null) {
            try {
                $dsn = Dsn::parse(getenv('DATABASE_URL'));
                $dsn = $dsn->toArray();
            } catch (\Exception $e) {
                throw new StorageException($e);
            }

            try {
                self::$pdo = new PDO('mysql:' . 'host=' . $dsn['host'] . ';port=' . $dsn['port'] . ';dbname=' . $dsn['database'], $dsn['user'], $dsn['pass']);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
            } catch (PDOException $e) {
                throw new StorageException('Connection to the database failed: ' . $e->getMessage());
            }
        } else {
            self::$pdo = $pdo;
        }

        return true;
    }

    /**
     * Select data from database
     *
     * @param string $id
     *
     * @return array|bool
     * @throws StorageException
     */
    public static function selectFromGame(string $id)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        try {
            $sth = self::$pdo->prepare(
                '
                SELECT * FROM `' . TB_GAME . '`
                WHERE `id` = :id
            '
            );

            $sth->bindParam(':id', $id, PDO::PARAM_STR);

            if ($result = $sth->execute()) {
                $result = $sth->fetchAll(PDO::FETCH_ASSOC);

                return isset($result[0]) ? json_decode($result[0]['data'], true) : [];
            }

            return false;
        } catch (PDOException $e) {
            throw new StorageException($e->getMessage());
        }
    }

    /**
     * Insert data to database
     *
     * @param string $id
     * @param array  $data
     *
     * @return bool
     * @throws StorageException
     */
    public static function insertToGame(string $id, array $data): bool
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        if (empty($data)) {
            throw new StorageException('Data is empty!');
        }

        try {
            $sth = self::$pdo->prepare(
                '
                INSERT INTO `' . TB_GAME . '`
                (`id`, `data`, `created_at`, `updated_at`)
                VALUES
                (:id, :data, :date, :date)
                ON DUPLICATE KEY UPDATE
                    `data`       = VALUES(`data`),
                    `updated_at` = VALUES(`updated_at`)
            '
            );

            $data = json_encode($data);
            $date = date('Y-m-d H:i:s');

            $sth->bindParam(':id', $id, PDO::PARAM_STR);
            $sth->bindParam(':data', $data, PDO::PARAM_STR);
            $sth->bindParam(':date', $date, PDO::PARAM_STR);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new StorageException($e->getMessage());
        }
    }

    /**
     * Delete data from storage
     *
     * @param string $id
     *
     * @return bool
     * @throws StorageException
     */
    public static function deleteFromGame(string $id): bool
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        try {
            $sth = self::$pdo->prepare(
                '
                DELETE FROM `' . TB_GAME . '`
                WHERE `id` = :id
            '
            );

            $sth->bindParam(':id', $id, PDO::PARAM_STR);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new StorageException($e->getMessage());
        }
    }

    /**
     * Basic file-powered lock to prevent other process accessing same game
     *
     * @param string $id
     *
     * @return bool
     *
     * @throws StorageException
     * @throws \jacklul\inlinegamesbot\Exception\BotException
     */
    public static function lockGame(string $id): bool
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        self::$lock = new TempFile($id);

        return flock(fopen(self::$lock->getFile()->getPathname(), "a+"), LOCK_EX);
    }

    /**
     * Unlock the game to allow access from other processes
     *
     * @param string $id
     *
     * @return bool
     *
     * @throws StorageException
     */
    public static function unlockGame(string $id): bool
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        if (self::$lock === null) {
            throw new StorageException('No lock file object!');
        }

        return flock(fopen(self::$lock->getFile()->getPathname(), "a+"), LOCK_UN);
    }

    /**
     * Select multiple data from the database
     *
     * @param int $time
     *
     * @return array|bool
     * @throws StorageException
     */
    public static function listFromGame(int $time = 0)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (!is_numeric($time)) {
            throw new StorageException('Time must be a number!');
        }

        if ($time >= 0) {
            $compare_sign = '<=';
        } else {
            $compare_sign = '>';
        }

        try {
            $sth = self::$pdo->prepare(
                '
                SELECT * FROM `' . TB_GAME . '`
                WHERE `updated_at` ' . $compare_sign . ' :date
                ORDER BY `updated_at` ASC
            '
            );

            $date = date('Y-m-d H:i:s', strtotime('-' . abs($time) . ' seconds'));
            $sth->bindParam(':date', $date, PDO::PARAM_STR);

            if ($result = $sth->execute()) {
                return $sth->fetchAll(PDO::FETCH_ASSOC);
            }

            return false;
        } catch (PDOException $e) {
            throw new StorageException($e->getMessage());
        }
    }
}
