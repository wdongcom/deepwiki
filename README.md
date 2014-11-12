
# DeepWiki

A pure documents showcase, bases on Markdown, coded in PHP.

## Installation

1. Download the latest release of DeepWiki.
2. Unarchive package, go to shell and run `composer update`.
3. Done.

## Quick Start

1. Make `deepwiki-config/config.json` from the sample.
2. Write down some Markdown files into `deepwiki-docs/`.
3. Run it in PHP. 

## URL Rewrite

### For Apache HTTP Server

Place the code in the `/.htaccess` file

```
# prevent directory listing
Options -Indexes

# custom error documents
ErrorDocument 404 index.php\?p=_404
ErrorDocument 403 index.php\?p=_403

<IfModule mod_rewrite.c>
RewriteEngine on

# change / to your DeepWiki relative directory path, eg. /path/to/wiki/
RewriteBase /

# prevent illegal request
RewriteRule ^deepwiki-config/(.*)$ index.php\?p=_403 [L]
RewriteRule ^deepwiki-docs/(.*)$ index.php\?p=_403 [L]

# rewrite non-exist path to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php\?p=$1 [QSA,L]

</IfModule>
```

### For Nginx

TBD

## Directory Structure

```
deepwiki-docs/                Markdown files
deepwiki-config/config.json   Main configuration file in JSON
deepwiki-themes/xxx/          A theme named xxx
deepwiki-vendor/              The PHP Composer components
```

