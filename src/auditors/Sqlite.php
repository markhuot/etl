<?php

namespace markhuot\etl\auditors;

use DateTime;
use markhuot\etl\base\AuditorInterface;
use markhuot\etl\base\Frame;
use PDO;
use stdClass;
use Throwable;

class Sqlite implements AuditorInterface
{
    protected ?PDO $db = null;

    public function __construct(
        protected ?string $path = null,
    ){
        $this->path ??= __DIR__ . '/../../audit.sqlite';
    }

    public function getSourceFrame(string $collection, string $sourceKey)
    {
        $statement = $this->db()->prepare('SELECT * from keys WHERE collection=? AND sourceKey=?');
        $statement->execute([$collection, $sourceKey]);
        $result = $statement->fetch(PDO::FETCH_OBJ);

        if (! $result) {
            return null;
        }

        return new Frame(null, $collection, $sourceKey, $result->destinationKey);
    }

    public function trackFrame(Frame $frame): Frame
    {
        $this->db()->prepare('INSERT OR IGNORE INTO keys (collection, sourceKey)VALUES(?,?)')->execute([
            $frame->collection,
            $frame->sourceKey
        ]);

        $statement = $this->db()->prepare('SELECT * from keys WHERE sourceKey=?');
        $statement->execute([$frame->sourceKey]);
        $result = $statement->fetch(PDO::FETCH_OBJ);

        if ($result) {
            $frame->destinationKey = $result->destinationKey ?? null;
            $frame->lastError = $result->lastError ? new DateTime($result->lastError) : null;
            $frame->lastImport = $result->lastImport ? new DateTime($result->lastImport) : null;
        }

        return $frame;
    }

    public function trackSourceKey(string $key): void
    {
        $this->db()->prepare('INSERT OR IGNORE INTO keys (sourceKey)VALUES(?)')->execute([$key]);
    }

    public function getTotals(): array
    {
        $statement = $this->db()->prepare('SELECT count(*) AS count from keys WHERE lastImport IS NOT NULL');
        $statement->execute();
        $completed = $statement->fetch(PDO::FETCH_OBJ);

        $statement = $this->db()->prepare('SELECT count(*) AS count from keys');
        $statement->execute();
        $total = $statement->fetch(PDO::FETCH_OBJ);

        return [$completed->count, $total->count];
    }

    public function getStatusForKey(string $key): ?stdClass
    {
        $statement = $this->db()->prepare('SELECT * from keys WHERE sourceKey=?');
        $statement->execute([$key]);

        $result = $statement->fetch(PDO::FETCH_OBJ);

        return $result ?: null;
    }

    public function fetchFrames(array $frames): void
    {
        $this->db()->prepare('INSERT OR IGNORE INTO keys (sourceKey)VALUES'.implode(',', array_fill(0, count($frames), '(?)')))
            ->execute(array_map(fn ($frame) => $frame->sourceKey, $frames));

        $keyedFrames = [];
        foreach ($frames as $frame) {
            $keyedFrames[$frame->sourceKey] = $frame;
        }

        $statement = $this->db()->prepare('SELECT * FROM keys WHERE sourceKey IN (' . implode(',', array_fill(0, count($frames), '?')) . ')');
        $statement->execute(array_map(fn ($frame) => $frame->sourceKey, $frames));

        $result = $statement->fetchAll(PDO::FETCH_OBJ);

        foreach ($result as $row) {
            $keyedFrames[$row->sourceKey]->checksum = $row->checksum ?? null;
            $keyedFrames[$row->sourceKey]->destinationKey = $row->destinationKey ?? null;
            $keyedFrames[$row->sourceKey]->lastError = $row->lastError ? new DateTime($row->lastError) : null;
            $keyedFrames[$row->sourceKey]->lastImport = $row->lastImport ? new DateTime($row->lastImport) : null;
        }
    }

    /**
     * @param array<Frame> $frames
     * @return void
     */
    public function trackFrames(array $frames): void
    {
        $params = [];

        foreach ($frames as $frame) {
            $params[] = $frame->collection;
            $params[] = $frame->sourceKey;
            $params[] = $frame->destinationKey;
            $params[] = $frame->getDerivedChecksum();
            $params[] = $frame->lastError?->format('Y-m-d H:i:s');
            $params[] = $frame->lastImport?->format('Y-m-d H:i:s');
        }

        $this->db()->prepare('INSERT OR REPLACE INTO keys (collection, sourceKey, destinationKey, checksum, lastError, lastImport)VALUES'.implode(',', array_fill(0, count($frames), '(?,?,?,?,?,?)')))
            ->execute($params);
    }

    public function trackErrorForFrame(Frame $frame, Throwable $throwable): void
    {
        $this->db()->prepare('INSERT OR IGNORE INTO keys (collection,sourceKey)VALUES(?,?)')
            ->execute([$frame->collection, $frame->sourceKey]);

        $this->db()->prepare('UPDATE keys set lastError=? and lastImport=? WHERE collection=? and sourceKey=?')->execute([
            (new DateTime())->format('Y-m-d H:i:s'),
            null,
            $frame->collection,
            $frame->sourceKey,
        ]);
    }

    public function trackErrorForFrames(array $frames, Throwable $throwable): void
    {
        $params = [];

        foreach ($frames as $frame) {
            $frame->lastError = new DateTime();
            $frame->lastImport = null;

            $params[] = $frame->collection;
            $params[] = $frame->sourceKey;
            $params[] = $frame->destinationKey;
            $params[] = $frame->lastError?->format('Y-m-d H:i:s');
            $params[] = $frame->lastImport?->format('Y-m-d H:i:s');
        }

        $this->db()->prepare('INSERT OR IGNORE INTO keys (collection,sourceKey)VALUES'.implode(',', array_fill(0, count($frames), '(?,?)')))
            ->execute(array_merge(...array_map(fn ($frame) => [$frame->collection, $frame->sourceKey], $frames)));

        $this->db()->prepare('INSERT OR REPLACE INTO keys (collection, sourceKey, destinationKey, lastError, lastImport)VALUES'.implode(',', array_fill(0, count($frames), '(?,?,?,?,?)')))
            ->execute($params);
    }

    public function getImportStats(): array
    {
        $stats = [];

        $statement = $this->db()->prepare('SELECT collection, count(*) AS count FROM keys GROUP BY collection');
        $statement->execute();
        $totals = $statement->fetchAll(PDO::FETCH_OBJ);
        foreach ($totals as $row) {
            $stats[$row->collection] = [0, $row->count];
        }

        $statement = $this->db()->prepare('SELECT collection, count(*) AS count FROM keys WHERE lastError IS NULL GROUP BY collection');
        $statement->execute();
        $complete = $statement->fetchAll(PDO::FETCH_OBJ);
        foreach ($complete as $row) {
            $stats[$row->collection][0] = $row->count;
        }

        return $stats;
    }

    protected function db()
    {
        if ($this->db) {
            return $this->db;
        }

        $conn = new PDO('sqlite:' . $this->path);
        $conn->prepare('CREATE TABLE IF NOT EXISTS keys (
            `collection` varchar(512) NOT NULL DEFAULT \'default\',
            `sourceKey` varchar(512) NOT NULL,
            `destinationKey` varchar(512),
            `checksum` varchar(512),
            `lastError` DATETIME,
            `lastImport` DATETIME,
            primary key (`sourceKey`)
        )')->execute();

        return $this->db = $conn;
    }
}
