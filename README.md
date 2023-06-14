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
# change below to your desired secret for Symfony app to work
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
TRANSIFEX_COMPLETION=40
###< mautic/transifex ###
```

### Building Packages

The simplest manner to build packages is to execute `bin/console mautic:language:packer` from your command line interface.

By default, the command checks for a minimum completion level of a resource before downloading it. This behavior is similar to Mautic's `mautic:transifex:pull` behavior, except that the completion percentage may be customized via the `TRANSIFEX_COMPLETION` .env or bypassed completely via the `--bypass-completion` or `-b` option, e.g. `bin/console mautic:language:packer -b`.

##### Note: Since Transifex API V3, there is no way to know the completion percent stat of a resource.

https://developers.transifex.com/reference/get_resource-language-stats response is below:

```json
{
  ...,
  "data": [
    {
      "id": "o:some-organisation:p:some-project:r:corebundle-flashes:l:af",
      "type": "resource_language_stats",
      "attributes": {
        "untranslated_words": 296,
        "translated_words": 0,
        "reviewed_words": 0,
        "proofread_words": 0,
        "total_words": 296,
        "untranslated_strings": 28,
        "translated_strings": 0,
        "reviewed_strings": 0,
        "proofread_strings": 0,
        "total_strings": 28,
        "last_update": "2015-05-21T08:06:10Z",
        "last_translation_update": null,
        "last_review_update": null,
        "last_proofread_update": null
      },
      ...
    },
    {
      ...
    }
  ]
}
```
Considering above JSON response, we calculate the completion percent using `(translated_words/total_words) * 100` formula. By default the we are expecting the translations to be 40% complete and skip resources which do not meet this criteria. This value is configurable in .env using `TRANSIFEX_COMPLETION`.


You may filter some unwanted languages by passing the `--skip-languages` or `-s` option, e.g. `bin/console mautic:language:packer -s es -s en`.

You may process only some languages by passing the `--languages` or `-l` option, e.g. `bin/console mautic:language:packer -l af -l hi`.

If some file fails sanity validation (checks to make sure ini file is valid) its entire language will be pulled from processing to make sure we do not overwrite older bundles with incomplete translations.

### Testing

To run tests `composer test`

To run unit tests `composer test -- --testsuite=Unit`

To run functional tests `composer test -- --testsuite=Functional`

### Static analysis tools

To run fixes by friendsofphp/php-cs-fixer `composer fixcs`

To run phpstan `composer phpstan`

### GitHub actions

In `https://github.com/<username>/language-packer/settings/secrets/actions/new`, add following action secrets:
1. `TRANSIFEX_API_TOKEN` // Generate a Transifex API token from https://app.transifex.com/user/settings/api/
2. `TRANSIFEX_ORGANISATION`
3. `TRANSIFEX_PROJECT`
4. `NEW_GITHUB_TOKEN` // details below

##### Github Token

1. Create a Github token for pushing packages to language-packs repo
2. Copy this token and create a new secret with `NEW_GITHUB_TOKEN` name in `https://github.com/<username>/language-packer/settings/secrets/actions/new`
