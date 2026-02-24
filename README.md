# jdwx/volume-php

A simple PHP module for managed directories.

## Installation

You can require it directly with Composer:

```bash
composer require jdwx/volume
```

Or download the source from GitHub: https://github.com/jdwx/volume-php.git

## Requirements

This module requires PHP 8.3 or later.

## Usage

Provides a simplified interface for creating managed directories and contained files. Volumes can be temporary or persistent.

```php

```

This module is typically most useful in situations where a directory and its contents are only needed temporarily. For example, setting up Docker build instructions programmatically for a number of images that differ only in small, easily enumerable ways. (To use the example above, maybe Docker images using several different combinations of TypeScript and Node versions.) It can also be useful for providing context to sandboxed microservices.

## Security

This module is not intended for use in high-security contexts. It does not support access control or file permissions, does not exhaustively check for symlinks leading outside the volume, and does not do anything to address TOCTOU concerns. It's designed to protect from bugs, not malice.

## Stability

This is a new module that has not been widely used yet.

## History

This module was newly developed in February 2026. 
