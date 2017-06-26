Mautic Language Packager
====================

This is a command line utility to build installable language packages for Mautic.

### General Notes

The application configuration is stored at `etc/config.json` and should be filled prior to executing this script.  You can copy the `etc/config.dist.json` file to start with.

### Development Notes

Execute a `composer install` to install the app dependencies.

The `packages` and `translations` directories are gitignored since these are directories our remote resources and installable packages are stored in.

### Building Packages

The simplest manner to build packages is to execute `bin/execute` from your command line interface.

By default, this script checks for a minimum completion level of a resource before downloading it.  This behavior is similar to Mautic's `mautic:transifex:pull` behavior, except that the completion percentage may be customized via the configuration or bypassed completely via the `--bypasscompletion` option, e.g. `bin/execute --bypasscompletion`.

A single language may be processed by passing the `--language` option, e.g. `bin/execute --language=es`.

If some file fails sanity validation (checks to make sure ini file is valid) it's entire language will be pulled from processing to make sure we do not overwrite older bundles with incomplete translations.

To upload packages, you must pass the `--uploadpackages` option when executing the script.  The `amazon` settings must be configured prior to this.  The Amazon Web Services account must have permissions to create, edit, and delete objects (and their associated ACL settings) on your S3 instance.

### Testing

`vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/`

