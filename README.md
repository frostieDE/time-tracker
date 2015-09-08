# Time Tracker

A simple project-based time tracker written in PHP using Symfony.

*Note:* This is my first Symfony project :)

# Installation

Currently, the software can only installed on servers you have SSH access to.

## Requirements
* PHP 5.4 or higher
* MySQL/MariaDB for database backend
* Composer

## Installation/Deployment

First, checkout the source code:

```
git clone https://github.com/frostie/time-tracker.git
```

First, check if your system matches Symfony's requirements:

```
php app/check.php
```

Then add a environment variable, so all further calls are executed for the `production` environment:

```
export SYMFONY_ENV=prod
```

This ensures that the following call won't fail to execute. Now install all dependencies using composer:

```
composer install --no-dev --optimize-autoloader
```

Now generate assets:

```
php app/console assetic:dump --env=prod --no-debug
```

Finally create the database and create all tables:
```
php app/console doctrine:database:create --env=prod
php app/console doctrine:schema:update --env=prod
```

(Note: only execute the first command if the configured datatabase account has the right to create the database. If not,
create the database yourself in the MySQL prompt. When doing so, ensure you use collation `utf8mb4_general_ci`)

Finally, ensure your app has access to the cache folder:

```
chmod -R 0777 app/cache/
```

All you need to do now is configuring your webserver. Read on [here](http://symfony.com/doc/current/cookbook/configuration/web_server_configuration.html)

# Contribution

Feel free to add issues or create pull requests.

# License

The MIT License (MIT)

Copyright (c) 2015 Marcel Marnitz

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

