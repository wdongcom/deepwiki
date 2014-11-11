
# HiWiki

A pure documents showcase, bases on Markdown, coded in PHP.

## Installation

1. Download the latest release of HiWiki.
2. Unarchive package, go to shell and run `composer update`.
3. Done.

## Quick Start

1. Make `hiwiki-config/config.json` from the sample.
2. Write down some Markdown files into `hiwiki-docs/`.
3. Run it in PHP. 

## URL Rewrite in Apache httpd server

Place the code in the `.htaccess` file

```
# prevent directory listing
Options -Indexes

<IfModule mod_rewrite.c>
RewriteEngine on

# change / to your HiWiki relative directory path, eg. /path/to/wiki/
RewriteBase /

# prevent illegal request
RewriteRule ^hiwiki-config/(.*)$ index.php\?p=_403 [L]
RewriteRule ^hiwiki-docs/(.*)$ index.php\?p=_403 [L]

# rewrite non-exist path to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php\?p=$1 [QSA,L]

</IfModule>
```

## Directory Structure

```
hiwiki-docs/                Markdown files
hiwiki-config/config.json   Main configuration file in JSON
hiwiki-themes/xxx/          A theme named xxx
hiwiki-vendor/              The PHP Composer components
```

