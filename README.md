Mautic Language Packager
====================

This is a command line utility to build installable language packages for Mautic.

### General Notes

The default application configuration is stored at `.env` and can be overridden by creating a `.env.local` file.

### Development Notes

Execute a `composer install` to install the app dependencies.

The `packages` and `translations` directories are gitignored since these are directories our remote resources and installable packages are stored in.

### Building Packages

The simplest manner to build packages is to execute `bin/console mautic:language:packer` from your command line interface.

You may filter some unwanted languages by passing the `--filter-languages` argument, e.g. `bin/console mautic:language:packer --filter-languages es hi en`.

A single language may be processed by passing the `--language` option, e.g. `bin/console mautic:language:packer --language es`.

If some file fails sanity validation (checks to make sure ini file is valid) it's entire language will be pulled from processing to make sure we do not overwrite older bundles with incomplete translations.

To upload packages, you must pass the `--upload-package` option when executing the script, e.g. `bin/console mautic:language:packer --upload-package`.  The `amazon` settings must be configured prior to this in .env. The Amazon Web Services account must have permissions to create, edit, and delete objects (and their associated ACL settings) on your S3 instance.

### Testing

To run tests `composer test`

To run unit tests `composer test -- --testsuite=Unit`

To run functional tests `composer test -- --testsuite=Functional`

### Static analysis tools

To run fixes by friendsofphp/php-cs-fixer `composer fixcs`

To run phpstan `composer phpstan`
