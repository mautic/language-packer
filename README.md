Mautic Language Packager
====================

This is a command line utility to build installable language packages for Mautic.

### Development Notes

Execute a `composer install` to install the app dependencies.

The `packages` and `translations` directories are gitignored since these are directories our remote resources and installable packages are stored in.

### General Notes and Environment variables

The default application configuration is stored at `.env` and can be overridden by creating a `.env.local` file.

```dotenv
###> symfony/framework-bundle ###
APP_ENV=prod
# change below to your desired secret
APP_SECRET=c85b90b0d096eb714652f175409489bb
###< symfony/framework-bundle ###
```

```dotenv
###> mautic/transifex ### 
# Generate a Transifex API token from https://app.transifex.com/user/settings/api/
TRANSIFEX_API_TOKEN=not-a-real-api-token
TRANSIFEX_ORGANISATION=not-a-real-organisation
TRANSIFEX_PROJECT=not-a-real-project
TRANSIFEX_DOWNLOAD_MAX_ATTEMPTS=3
###< mautic/transifex ###
```

```dotenv
###> aws/aws-sdk-php-symfony ###
AWS_KEY=not-a-real-key
AWS_SECRET=not-a-real-secret
AWS_VERSION=latest
AWS_REGION=us-west-2
AWS_S3_BUCKET=not-a-real-bucket
AWS_S3_REGION=us-west-2
AWS_S3_VERSION=2006-03-01
###< aws/aws-sdk-php-symfony ###
```

### Building Packages

The simplest manner to build packages is to execute `bin/console mautic:language:packer` from your command line interface.

You may filter some unwanted languages by passing the `--skip-languages` or `-s` option, e.g. `bin/console mautic:language:packer -s es -s en`.

You may process only some languages by passing the `--languages` or `-l` option, e.g. `bin/console mautic:language:packer -l af -l hi`.

If some file fails sanity validation (checks to make sure ini file is valid) its entire language will be pulled from processing to make sure we do not overwrite older bundles with incomplete translations.

To upload packages to AWS S3, you must pass the `--upload-package` or `-u` option when executing the script, e.g. `bin/console mautic:language:packer -u`.  The `amazon` settings must be configured prior to this in .env. The Amazon Web Services account must have permissions to create, edit, and delete objects (and their associated ACL settings) on your S3 instance.

### Testing

To run tests `composer test`

To run unit tests `composer test -- --testsuite=Unit`

To run functional tests `composer test -- --testsuite=Functional`

### Static analysis tools

To run fixes by friendsofphp/php-cs-fixer `composer fixcs`

To run phpstan `composer phpstan`
