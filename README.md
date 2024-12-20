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

- batching data on extraction and load to allow for eager loading and more efficient memory usage
- multi-phase processing to avoid circular logic and simplify transformers
- checksuming data on extraction to avoid no-op transactions on load
- (coming soon) async transformations allowing for parallel processing of complex data
- a complete auditing interface to track data flow and avoid repeat work

## Getting started

Before writing any code it is important to understand some key concepts of how a voyage works.

### Frames

Frames are the core data object in Voyage. There are "immutable" source frames that come out of your source connection
and mutable destination frames that are crafted via the specified transformers. Note: source frames are not truly
immutable today but may become `readonly` at some point in the future. Do not expect that source frames will remain
mutable.

The lifecycle of data in a Voyage starts with the source connection yielding a `Frame` out of the `->walk()` method.
That frame is then passed to the destination connection which prepares a new frame with `->prepareFrame()` for the
destination repository. With both source and destination frames created the two frames are passed through a series
of transformers which are free to copy whatever data may be necessary from the source to the destination.

Most imporantly, frames carry the `$data` throughout the voyage. But just as important—each frame carries with it a
series of meta data to help track progress and errors as the data moves through the system. This meta data includes,

- `phase`: A voyage can (and probably should) have multiple phases. Each phase is responsible for a different set of
  data.
- `collection`: A collection is a grouping of Frames that make reporting easier. All frames start in the `default`
  collection, but you are free to adjust that as you yield frames out of the source connection. For example, you may
  want to yield frames in a `posts` collection and separately frames in an `authors` collection. This metadata is
  passed to the destination connection as well.
- `sourceKey` and `destinationKey` keep track of the mapping between source data and destination data. This allows you
  to upsert data multiple times.

### Phases

Voyage ships with a `DefaultPhase` and a
`RelationsPhase`. The intent of this separation is to avoid circular logic. For example, if you are importing a blog
you don't want to have to collect a post, with it's authoer, with it's category in one run. That could create a
circular dependency if the post is related to author one and the author is related back to the post via a "featured"
field. Instead, it is recommended that you

### Installation

Install the package via `composer require --dev markhuot/voyage`. Once the library is installed you need to define the
connections to the source and destination data. The source connection is responsible for extracting data from somewhere
and the destination connection is responsible for loading data in to a new repository. There are dedicated interfaces
for each connection type which define what the connection should do.

### Source Connections

A source connection must implement a `->walk()` method that will walk over source data and yield rows back to the
caller. Because the interface does not define a `__constructor` you are free to pass in whatever options you deem
necessary to correctly walk over the source data.

For example, if you were setting up a source connection to read CSV files off the disk you could do something like the
following code. This connection would accept a path during initialization and when started it would would walk over the
lines of that file and yield individual frames back to the caller.

```php
class CsvConnection
{
    public function __construct(
        protected string $path,
    ) {
    }
    
    public function walk(): Generator
    {
        if (($handle = fopen($this->path, "r")) !== FALSE) {
            $row = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                yield new Frame($data, $row++);
            }
            fclose($handle);
        }
    }
}
```

> [!NOTE]
> Because the `->walk()` method is a generator that yields frames there is no hard rule that the "rows" of the source
> connection must map 1:1 with rows in the destination connection. It is perfectly reasonable for a source connection
> to loop over the source data and yield multiple frames out of each source row.
>
> This is incredibly powerful. You could specify a source connection that reads a CSV file and yields a frame for each
> field instead of each row, if necessary.

You could use this connection inside a Laravel console command or other CLI framework passing a CLI path argument
in to the connection each time it is run.

```php
class ImportConsoleCommand
{
    public function handle()
    {
        (new Voyage)
            ->from(new CsvConnection($this->getArgument('path')))
            ->to(...)
            ->start()
    }
}
```

### Destination Connections

A destination connection is responsible for taking destination frames and loading them in to a destination repository.
The connection must adhere to the `DestinationConnectionInterface` and implement the following methods.

First, the destination must "prepare" a frame for the destination repository. It is passed a source frame but is free
to return any sort of frame that is useful to the destination. For example if you were importing in to a 

```php
public function prepareFrame(Frame $frame): Frame
{
    return (clone $frame)->setData([]);
}
```

## Configuration

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

Each configuration option is described in more detail below,

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
