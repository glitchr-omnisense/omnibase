---
title: The data structure, described outside Doctrine
order: 29
---

# The data structure, described outside Doctrine

Marco's bridge principle (2026-10-19): the omni bridges are only the adapters of
contracts for one technique, so that several persistence techniques can map the
same tables - Doctrine today, Eloquent or another tomorrow - **without changing
the structure** (the naming, the columns, the relations, the discriminators).
Since step R14 of the audit "lesringostars face au master plan", that structure
is described once, outside any ORM, and Doctrine reads it.

## Where it lives, and why

- **omniscient/omnibridge, `Omnibridge\Schema`** (framework-free, ORM-free): the
  generic part. `Schema\Declaration` reads what the entity classes declare from
  their attributes by reflection, without instantiating them - the attributes
  are `Doctrine\ORM\Mapping`'s, the vocabulary every omnibase entity is written
  in, but their classes need not exist (`Vocabulary` holds their parameters and
  defaults as data). `Schema\Description` is the neutral description: each
  `Entity` (table, kind, parent and root, `Inheritance` with its column, map and
  value, identifier, `Field`s with their portable types, `Relation`s with their
  `JoinColumn`s or `JoinTable`) and each `Table` (`Column`s, primary key,
  `Index`es, `ForeignKey`s), plain data too (`toJson()`). `Schema\Builder`
  builds it from the declarations alone, the way Doctrine completes its
  metadata and the way its SchemaTool makes the tables; `Schema\Naming`
  (`CamelCaseNaming`) names what the declarations leave unnamed, including an
  index or a foreign key as DBAL names it. `Schema\Database\MySqlSchema` reads
  a MySQL `information_schema` and compares it with a description.
- **omnibridge/doctrine**: the Doctrine side. `DeclarationDriver` builds
  Doctrine's metadata from the declarations (wrapping the application's driver,
  which still says which classes exist); `MetadataDescription` writes what an
  entity manager maps in the neutral form; `NamingStrategy` answers Doctrine's
  naming strategy with the family's `Naming`.
- **the core, `Base\Database\Schema`** (Foundation): what is omnibase's own -
  the rules its listeners add to Doctrine's metadata, written once more as
  `Omnibridge\Schema\Builder\Rule`s applied at the same three moments
  (`before` the declaration is read, once `declared`, once `loaded`):
  `DiscriminatorRule` (the maps generated under class names, `#[DiscriminatorEntry]`'s
  values), `VaultRule` (`#[Vault]`'s unique constraints), `OrderColumnRule`
  (`#[OrderColumn]`'s json column), `TranslationRule` (a translation's
  inheritance from its translatable, the `translations` and `translatable`
  relations, `locale`, `<table>_unique_translation`), `TargetRule` (the targets
  DoctrineBundle resolves) and `ExtensionRule` (`extension_entity`); and
  `Describer`, which builds an application's description from its entities'
  folders with omnibase's naming and rules.

The split follows the A1/A2 rule of the plan: generic glue for a host or a
technique goes to omnibridge, what reads omnibase's own models stays in the core.

## How Doctrine uses it

Under Symfony `DoctrineConfigurationPass` wraps every entity manager's driver in
omnibridge/doctrine's `DeclarationDriver`, then in `Base\Bridge\Doctrine\MappingDriver`;
`Base\Bridge\Doctrine\Mapping::register()` does the same on an entity manager
built by hand. Doctrine thus no longer reads the attributes itself: its metadata
are built from the declarations - the same source the ORM-free description is
built from - and they are those its attribute driver built, to the property
(`MetadataSnapshot`, every class, on both hosts).

```php
use Base\Database\Schema\Describer;

$description = Describer::describe([$vendor.'/omniscient/omnibase/src/Entity', $projectDir.'/src/Entity'], ['project_dir' => $projectDir]);
$description->table('thread')->column('slug');                    // string(255), not null
$description->entity(Base\Entity\Thread::class)->relation('tags'); // many_to_many, join table thread_tags
file_put_contents('var/schema.json', $description->toJson());
```

## The proof

`tests/Bridge/Doctrine/SchemaDescriptionTest` (the harness, or an application's
suite run from `vendor/`):

- the entity manager's driver is the `DeclarationDriver`;
- the description `Describer` builds without any ORM is `MetadataDescription`'s
  of the entity manager, entity by entity and table by table (the harness: 202
  classes; lesringostars: 115), and `#[DiscriminatorEntry]`'s values derived
  without the attribute's reader are Doctrine's;
- on MySQL (lesringostars), the description is the database's own schema:
  every table, column (SQL type, nullable, default, auto-increment), primary
  key, index and foreign key read from `information_schema`, nothing of Doctrine
  asked (`doctrine_migration_versions` and `messenger_messages` apart).

## How another persistence would read it

An Eloquent (or any other) bridge needs no attribute of Doctrine's and no class
of the core's mapping: it reads `Omnibridge\Schema\Description` - built by
`Describer::describe()` once, or read back from the JSON file it wrote - and
maps from it:

- **a model per `Entity`**: `$entity->table` is the table, `$entity->identifier`
  the key(s), `$entity->fields` the attributes with the column each one is kept
  in (`Field::$column`), its portable type (`string`, `integer`, `json`,
  `datetime`, `thread_state`...: the casts), `nullable`, `length`, `enum` (the
  PHP enumeration to cast to), `generated` (`identity`: the database gives the
  key);
- **the relations**: `Relation::$kind` says `belongsTo` (`many_to_one`, by its
  `joinColumns`), `hasMany` (`one_to_many`, through the owning side's join
  column named by `mappedBy`), `hasOne`/`belongsTo` for `one_to_one`, and
  `belongsToMany` with `JoinTable::$name` and its `joinColumns`/`inverseJoinColumns`;
- **the hierarchies**: `Entity::$inheritance` gives the discriminator column,
  the map (value => class) and the entity's own value - a single-table hierarchy
  is a global scope on the discriminator, a joined one a model per table keyed
  by the root's identifier (`Entity::$root`, `Entity::$parent`);
- **the tables themselves** (`Description::$tables`) for migrations or a schema
  check, with the same names, types, indexes and foreign keys as Doctrine's.

Nothing of it needs Doctrine installed: omniscient/omnibridge requires PHP and
three PSR interfaces, and its `BareTest` proves the Schema loads with nothing
else. The Eloquent bridge itself is a later step; this one gives it what to read.
