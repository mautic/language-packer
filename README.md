Mautic Language Packager
====================

This is a command line utility to build installable language packages for Mautic.

### General Notes

The application configuration is stored at `etc/config.json` and should be filled with your Transifex credentials.  You can copy the `etc/config.dist.json` file to start with.

### Development Notes

Execute a `composer install` to install the app dependencies.

The `packages` and `translations` directories are gitignored since these are directories our remote resources and installable packages are stored in.

### Building Packages

The simplest manner to build packages is to execute `bin/execute` from your command line interface.

By default, this script checks for a minimum completion level of a resource before downloading it.  This behavior is similar to Mautic's `mautic:transifex:pull` behavior, except that the completion percentage may be customized via the configuration or bypassed completely via the `--bypasscompletion` option, e.g. `bin/execute --bypasscompletion`.
