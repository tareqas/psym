# pSym
A REPL for Symfony and PHP

**pSym** works both inside and outside Symfony project. When used within a Symfony project, it provides additional
features such as access to variables like `$kernel`, `$container`, `$doctrine`, and `$em`.
Additionally, all registered **project commands** become accessible as well.

The `lse` command and `table()`, `sql()` functions are available when **Doctrine** is installed.

Function `html()` and features like `auto-completion`, `auto-suggestion`, and `doc-and-signature` work **universally**.

## Installation
You must install pSym **globally**. If you have multiple PHP versions installed on your machine, install it on the
**lowest** version. It supports PHP `>=7.2`.
```shell
composer global require tareqas/psym
```

### Commands
```shell
# list all the commands, including your project commands.
list
# or
?
```

### Auto-complete and Auto-suggestion
To get suggestions, press the `TAB` key.

> **Note:** Sometimes you may need to press `SPACE` first and then `TAB`.

```shell
# press TAB for suggestion
$kernel->
# it also works with method chaining
$kernel->getBundle()-> 
# press TAB for completion
$kernel->getBund
```

### Documentation and Signature
You can view PHPDoc documentation and signature for `function`, `property`, and `method`.
```shell
# press TAB to display the phpDoc and signature for getBundle
$kernel->getBundle 
```

### lse
The `lse` command lists all entities managed by Doctrine.
```shell
# list of of all matching tables
lse ca
# list all properties, columns, types, and default values of an entity
lse cart
# list of all matching properties for the 'cart' entity
lse cart tot
```

### html()
```php
function html(...$vars): void
```
The `html()` function dumps variables and renders them as a browsable HTML page. If any of your variables contain
Doctrine objects, it will automatically instantiate all proxy objects.

You can fine-tune the dump by providing additional options in the last parameter as an associative array:
```php
html($var, [
    'nestedLevel' => -1, # or 'level' - how deep it should go to instantiate doctrine proxy object
    'collectionSize' => 1, # or 'size' - cut the Doctrine association collection to this specific size
    'maxString' => -1 # # cut the overlong string to this specific size
])
```
** -1 means no limit

### table()
```php
function table(string $table, ?string $alias = null): EntityRepository|QueryBuilder
```
The `table()` function retrieves a repository for a given entity. It returns a `Doctrine\ORM\EntityRepository`
if no alias is provided, or a `Doctrine\ORM\QueryBuilder` if an alias is specified.

### sql()
```php
function sql(string $sql): array
```
The `sql()` function executes raw SQL queries and returns results as an associative array.
Doctrine is required to use this feature.
```shell
# press TAB to display all available tables
sql('select * from '
# press TAB to display all available columns in the 'cart' table
sql('select c. from cart c'
```

## And more
To unlock the full potential, explore the [PsySH documentation](https://psysh.org/#docs). pSym is built on top of PsySH.
