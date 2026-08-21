# Latest versions of Swissup packages

A static list of the **latest stable release** of each Swissup
Magento 2 package. Used by `swissup/module-core` package to notify about outdated
modules.

https://swissup.github.io/packages-latest/

## Generating

```bash
php run.php
```

Requires PHP 8.1+ with `ext-curl` and `ext-json`.

Option         | Effect
-------------- | ---------------
`--force`      | Rebuild even when remote sources are unchanged.
`--output=DIR` | Where to write the repository (default: script directory).
