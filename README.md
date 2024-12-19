# Voyage
Seamlessly move data from one source to another.

```php
(new Voyage)
    ->from(new CsvConnection('file.csv'))
    ->to(new WordPressConnection($wpConfig))
    ->addTransformer(new CategoryTransformer)
    ->addTransformer(new BlockTransformer)
    ->start();
```

Voyage is an ETL tool that provides a simple but powerful interface to move massive amounts of data as efficiently as
possible. The core features that allow this include,

- batching data on extraction and load to more efficiently use memory
- multi-phase processing to avoid circular logic and simplify transformers
- checksuming data on extraction to avoid no-op transactions on load
- (coming soon) async transformations allowing for parallel processing of complex data
- a complete auditing interface to track data flow and avoid repeat work

Voyage is implemented as a low-level tool with an emphasis on configuration. Adapters can be written for any framework
allowing Voyage to leverage the power of the framework. A full implemenation may look something like this,

```php
(new Voyage)
    ->from(new CraftConnection($this->elementType, $this->section, $this->type, $this->group, $this->id, $this->status))
    ->to(new WebflowConnection($this->dryRun))
    ->stream(new PhpStreamWrapper($verbosity))
    ->auditor($auditor = new Sqlite(path: CRAFT_BASE_PATH . '/audit.sqlite'))
    ->devMode(\Craft::$app->getConfig()->getGeneral()->devMode)
    ->addTransformer(new MetaTransformer)
    ->addTransformer(new ContentTopicTransformer($auditor))
    ->addTransformer(new ResourceTransformer)
    ->addTransformer(new AuthorTransformer)
    ->addTransformer(new CategoryTransformer)
    ->addTransformer(new AuthorRelationTransformer($auditor))
    ->addTransformer(new PressTransformer)
    ->addTransformer(new PressRelationsTransformer($auditor))
    ->addTransformer(new PublisherTransformer)
    ->addTransformer(new EventTransformer)
    ->start($phase);
```

## Configuration Options

### `->from(SourceConnectionInterface $connection)`

Source connections are responsible for extracting data. They must implement the `SourceConnectionInterface` and yield
batches of data back to Voyage. It is up to the implemetation to determine how data is fetched, but should be eager
loaded where possible across the batch.

### `->to(DestinationConnectionInterface $connection)`

### `->stream(StreamInterface $stream)`
In order for Voyage to output process information it needs a stream to write to. The stream must implement the
`output\StreamInterface` which provides `public function info(string $message, string $verbosity='v'): void;` and 
`public function error(string $message, string $verbosity='v'): void;` methods. The verbosity level is optional but
should be used to communicated critical information (`v`) and debuging information (`vvv`). For example, Voyage outputs
batch information at the `v` level and individual record information at the `vvv` level.

### `->start(DefaultPhase $phase)`

## Frames

The core data object in Voyage is the `Frame`. Connections are responsible for

1. generating immutable source frames
2. generating mutable destination frames
2. transforming source frame data in to destination frame data
