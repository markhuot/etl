<?php

namespace markhuot\etl\auditors;

use DateTime;
use markhuot\etl\base\AuditorInterface;
use markhuot\etl\base\Frame;
use markhuot\etl\phases\DefaultPhase;
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

    public function getStatusForKey(string $phase, string $collection, string|int $sourceKey): ?stdClass
    {
        $statement = $this->db()->prepare('SELECT * from keys WHERE phase=? AND collection=? AND sourceKey=?');
        $statement->execute([$phase, $collection, $sourceKey]);

        /** @var stdClass|false $result */
        $result = $statement->fetch(PDO::FETCH_OBJ);

        return $result ?: null;
    }

    public function fetchFrames(array $frames): void
    {
        $this->db()->prepare('INSERT OR IGNORE INTO keys (phase, collection, sourceKey)VALUES'.implode(',', array_fill(0, count($frames), '(?,?,?)')))
            ->execute(array_merge(...array_map(fn ($frame) => [$frame->phase, $frame->collection, $frame->sourceKey], $frames)));

        // Copy over destination keys from the default phase to any higher phases
        $this->db()->prepare('UPDATE keys SET destinationKey=(SELECT innerKeys."destinationKey" FROM keys AS innerKeys WHERE innerKeys.phase=? AND innerKeys.collection=keys.collection AND innerKeys.sourceKey=keys.sourceKey) WHERE destinationKey is null AND phase != ?')
            ->execute([DefaultPhase::class, DefaultPhase::class]);

        $keyedFrames = [];
        foreach ($frames as $frame) {
            $keyedFrames[$frame->phase.$frame->collection.$frame->sourceKey] = $frame;
        }

        $statement = $this->db()->prepare('SELECT * FROM keys WHERE (phase, collection, sourceKey) IN (' . implode(',', array_fill(0, count($frames), '(?,?,?)')) . ')');
        $statement->execute(array_merge(...array_map(fn ($frame) => [$frame->phase, $frame->collection, $frame->sourceKey], $frames)));

        /** @var array<object{phase: string, collection: string, sourceKey: string|int, checksum: ?string, destinationKey: ?string, lastError: ?string, lastImport: ?string}> $result */
        $result = $statement->fetchAll(PDO::FETCH_OBJ);

        foreach ($result as $row) {
            $keyedFrames[$row->phase.$row->collection.$row->sourceKey]->checksum = $row->checksum ?? null;
            $keyedFrames[$row->phase.$row->collection.$row->sourceKey]->destinationKey = $row->destinationKey ?? null;
            $keyedFrames[$row->phase.$row->collection.$row->sourceKey]->lastError = $row->lastError ? new DateTime($row->lastError) : null;
            $keyedFrames[$row->phase.$row->collection.$row->sourceKey]->lastImport = $row->lastImport ? new DateTime($row->lastImport) : null;
        }
    }

    /**
     * @param array<Frame<mixed>> $frames
     * @return void
     */
    public function trackFrames(array $frames): void
    {
        $params = [];

        foreach ($frames as $frame) {
            $params[] = $frame->phase;
            $params[] = $frame->collection;
            $params[] = $frame->sourceKey;
            $params[] = $frame->destinationKey;
            $params[] = $frame->getDerivedChecksum();
            $params[] = $frame->lastError?->format('Y-m-d H:i:s');
            $params[] = $frame->lastImport?->format('Y-m-d H:i:s');
        }

        $this->db()->prepare('INSERT OR REPLACE INTO keys (phase, collection, sourceKey, destinationKey, checksum, lastError, lastImport)VALUES'.implode(',', array_fill(0, count($frames), '(?,?,?,?,?,?,?)')))
            ->execute($params);
    }

    public function trackErrorForFrames(array $frames, Throwable $throwable): void
    {
        $params = [];

        foreach ($frames as $frame) {
            $frame->lastError = new DateTime();
            $frame->lastImport = null;

            $params[] = $frame->phase;
            $params[] = $frame->collection;
            $params[] = $frame->sourceKey;
            $params[] = $frame->destinationKey;
            $params[] = $frame->lastError->format('Y-m-d H:i:s');
            $params[] = null;
        }

        $this->db()->prepare('INSERT OR IGNORE INTO keys (phase, collection, sourceKey)VALUES'.implode(',', array_fill(0, count($frames), '(?,?,?)')))
            ->execute(array_merge(...array_map(fn ($frame) => [$frame->phase, $frame->collection, $frame->sourceKey], $frames)));

        $this->db()->prepare('INSERT OR REPLACE INTO keys (phase, collection, sourceKey, destinationKey, lastError, lastImport)VALUES'.implode(',', array_fill(0, count($frames), '(?,?,?,?,?,?)')))
            ->execute($params);
    }

    public function getImportStats(): array
    {
        $stats = [];

        $statement = $this->db()->prepare('SELECT phase, collection, count(*) AS count FROM keys GROUP BY phase, collection');
        $statement->execute();

        /** @var array<object{phase: string, collection: string, count: int}> $totals */
        $totals = $statement->fetchAll(PDO::FETCH_OBJ);

        foreach ($totals as $row) {
            $stats[$row->phase][$row->collection] = [0, $row->count];
        }

        $statement = $this->db()->prepare('SELECT phase, collection, count(*) AS count FROM keys WHERE lastImport IS NOT NULL GROUP BY phase, collection');
        $statement->execute();

        /** @var array<object{phase: string, collection: string, count: int}> $complete */
        $complete = $statement->fetchAll(PDO::FETCH_OBJ);

        foreach ($complete as $row) {
            $stats[$row->phase][$row->collection][0] = $row->count;
        }

        return $stats;
    }

    protected function db(): PDO
    {
        if ($this->db) {
            return $this->db;
        }

        $conn = new PDO('sqlite:' . $this->path);
        $conn->prepare('CREATE TABLE IF NOT EXISTS keys (
            `phase` varchar(512) NOT NULL default \'default\',
            `collection` varchar(512) NOT NULL DEFAULT \'default\',
            `sourceKey` varchar(512) NOT NULL,
            `destinationKey` varchar(512),
            `checksum` varchar(512),
            `lastError` DATETIME,
            `lastImport` DATETIME,
            primary key (`phase`,`collection`,`sourceKey`)
        )')->execute();

        return $this->db = $conn;
    }
}
