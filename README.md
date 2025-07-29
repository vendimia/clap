# vendimia/clap 👏

PHP command-line argument parser.

```php
use Vendimia\Clap\Parser;

function createUser($username, bool $admin = false)
{
    if ($admin) {
        echo "Creating admin user {$username}...";
    } else {
        echo "Creating user {$username}...";
    }
}

$cli = new Parser;
$cli->register(createUser(...));

$cli->process();
```

Calling this script will execute function createUser() with the first CLI argument as $username. If '--admin' argument is passed to the script, it will pass `true` to $admin.

```bash
php createuser.php john
```
