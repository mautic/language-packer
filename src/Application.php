<?php
/**
 * Mautic Language Packager
 *
 * @copyright  Copyright (C) 2015 Allyde, LLC. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

namespace Mautic;

use Aws\Common\Credentials\Credentials;
use Aws\S3\S3Client;
use BabDev\Transifex\Transifex;
use Joomla\Application\AbstractCliApplication;
use Joomla\Filesystem\Folder;
use Joomla\Http\HttpFactory;
use Joomla\Registry\Registry;

/**
 * CLI application supporting the base application
 *
 * @property-read  \Joomla\Input\Cli  $input  The application input object
 */
class Application extends AbstractCliApplication
{
    /**
     * @var array 
     */
    private $skipLanguages = ["en"];
    
    /**
     * @var bool
     */
    private $errorsEncountered = false;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Set the configuration file path for the application.
		$file = JPATH_ROOT . '/etc/config.json';

		// Verify the configuration exists and is readable.
		if (!is_readable($file))
		{
			throw new \RuntimeException('Configuration file does not exist or is unreadable.');
		}

		// Load the configuration file into an object.
		$configObject = json_decode(file_get_contents($file));

		if ($configObject === null)
		{
			throw new \RuntimeException(sprintf('Unable to parse the configuration file %s.', $file));
		}

		$config = (new Registry)->loadObject($configObject);

		parent::__construct(null, $config);
	}

	/**
	 * Checks a language file. Throws on validation failure.
	 *
	 * @param   string  $filename  Absolute path to the file to check
	 *
	 * @throws  \InvalidArgumentException
	 * @throws  \DomainException
	 */
	public function ensureFileValid($filename)
	{
		// Initialise variables for manually parsing the file for common errors.
		$blacklist = ['YES', 'NO', 'NULL', 'FALSE', 'ON', 'OFF', 'NONE', 'TRUE'];
		$errors = [];

        // Read the whole file at once
        // no sense streaming since we load the whole thing in production
        $file = file_get_contents($filename);
        if($file === FALSE) {
			throw new \InvalidArgumentException(
				sprintf('Unable to read file "%s" for checking', $filename)
			);
        }
        $file = explode("\n", $file);

		foreach ($file as $lineNumber => $line)
		{
            $realNumber = $lineNumber + 1;
			// Ignore comment lines.
			if (!strlen(trim($line)) || $line['0'] == ';')
			{
				continue;
			}

			// Check that the line passes the necessary format.
			if (!preg_match('#^[A-Za-z][A-Za-z0-9_\-\.]*\s*=\s*".*"(\s*;.*)?$#', $line))
			{
				$errors[] = "Line $realNumber does not match format regexp";
				continue;
			}

			// Gets the count of unescaped quotes
			preg_match_all('/(?<!\\\\)\"/', $line, $matches);

			if (count($matches[0]) != 2)
			{
				$errors[] = "Line $realNumber doesn't have exactly 2 unescaped quotes";
				continue;
			}

			// Check that the key is not in the blacklist.
			$key = strtoupper(trim(substr($line, 0, strpos($line, '='))));

			if (in_array($key, $blacklist))
			{
				$errors[] = "Line $realNumber has blacklisted key";
			}
        }
        if(@parse_ini_file($filename, false) === false) {
            $errors[] = "Cannot load file with parse_ini_file";   
        };

		if (count($errors))
		{
            throw new \DomainException("File $filename has following errors:\n".implode(";", $errors));
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws  \InvalidArgumentException
	 */
	protected function doExecute()
	{
		$username       = $this->get('transifex.username');
		$password       = $this->get('transifex.password');
		$completion     = $this->get('transifex.completion', 80);
		$packagesDir    = JPATH_ROOT . '/packages';
		$translationDir = JPATH_ROOT . '/translations';
		$languageFilter = $this->input->get('language', null);
		$debugLanguages = $this->input->getBool('debuglanguages', false);

		if (!$username || !$password)
		{
			throw new \RuntimeException('Must specify user credentials for connecting to Transifex.');
		}

		// Remove any previous pulls and rebuild the translations folder
		if (is_dir($translationDir))
		{
			Folder::delete($translationDir);
		}

		Folder::create($translationDir);

		// Build the Transifex object
		$txOptions = ['api.username' => $username, 'api.password' => $password];
		$transifex = new Transifex($txOptions);

		$project = $transifex->get('projects')->getProject('mautic', true);

		// Build folders for each team's translations
		foreach ($project->teams as $team)
		{
			if (!Folder::create($translationDir . '/' . $team))
			{
				throw new \RuntimeException(
					sprintf(
						'Failed creating translations folder for the "%s" team.  Please verify your filesystem permissions and try again.',
						$team
					)
				);
			}
		}

		// Fetch the project resources now and store them locally
		foreach ($project->resources as $resource)
		{
			// Split the name to create our file name
			list ($bundle, $file) = explode(' ', $resource->name);

			$languageStats = $transifex->get('statistics')->getStatistics('mautic', $resource->slug);

			foreach ($languageStats as $language => $stats)
			{
                $language = $this->fixCode($language);

				// Skip our default language and languages that failed validation
				if (in_array($language, $this->skipLanguages))
				{
					continue;
				}

				// If we are filtering on a specific language, skip anything that doesn't match
				if ($languageFilter && $languageFilter != $language)
				{
					continue;
				}

				$this->out(sprintf('Processing the %1$s "%2$s" resource in "%3$s" language', $bundle, $file, $language));

				$completed = str_replace('%', '', $stats->completed);

				// We only want resources which match our minimum completion level unless told to bypass the completion check
				if ($this->input->getBool('bypasscompletion', false) || $completed >= $completion)
				{
					$translation = $transifex->get('translations')->getTranslation('mautic', $resource->slug, $this->languages[$language]);

					$path = $translationDir . '/' . $language . '/' . $bundle . '/' . $file . '.ini';

					if (!is_dir($translationDir . '/' . $language . '/' . $bundle))
					{
						if (!Folder::create($translationDir . '/' . $language . '/' . $bundle))
						{
							throw new \RuntimeException(
								sprintf(
									'Failed creating bundle folder for the "%1$s" bundle at path "%2$s".  Please verify your filesystem permissions and try again.',
									$bundle,
									$translationDir . '/' . $language . '/' . $bundle
								)
							);
						}
					}

					// Write the file to the system
					if (!file_put_contents($path, $translation->content))
					{
						throw new \RuntimeException(
							sprintf(
								'Failed writing translation file "%s".  Please verify your filesystem permissions and try again.',
								$path
							)
						);
					}

                    try {
                        $this->ensureFileValid($path);
                    }
                    catch (\DomainException $e) {
                        $this->skipLanguages[] = $language;
                        $this->errorsEncountered = true;
                        $this->out("Skipping language $language because of failed validations".$e->getMessage());
                        continue;
                    }
				}
			}
		}

		// Now we start building our ZIP archives
		if (!is_dir($packagesDir))
		{
			if (!Folder::create($packagesDir))
			{
				throw new \RuntimeException(
					'Failed creating root packages folder.  Please verify your filesystem permissions and try again.'
				);
			}
		}

		// Add a folder for our current build
		$timestamp = (new \DateTime())->format('YmdHis');

		if (!Folder::create($packagesDir . '/' . $timestamp))
		{
			throw new \RuntimeException(
				'Failed creating packages folder for this build.  Please verify your filesystem permissions and try again.'
			);
		}

		// Compile our data to forward to mautic.org and build the ZIP packages
		chdir($translationDir);
		$langData = [];

		foreach (Folder::folders($translationDir) as $languageDir)
		{
            // Skip our default language and languages that failed validation
            if (in_array($languageDir, $this->skipLanguages))
            {
                continue;
            }
            // If the directory is empty, there is no point in packaging it
			if (count(scandir($translationDir . '/' . $languageDir)) > 2)
			{
				$this->out(sprintf('Creating package for "%s" language', $languageDir));

				$txLangData = $transifex->get('languageinfo')->getLanguage($this->languages[$languageDir]);
				$langData[] = ['name' => $txLangData->name, 'code' => $languageDir];
				$configData = $this->renderConfig(
					['name' => $txLangData->name, 'locale' => $languageDir, 'author' => 'Mautic Translators']
				);

				if (!file_put_contents($translationDir . '/' . $languageDir . '/config.php', $configData))
				{
					throw new \RuntimeException(
						sprintf(
							'Failed writing translation package configuration file "%s".  Please verify your filesystem permissions and try again.',
							$translationDir . '/' . $languageDir . '/config.php'
						)
					);
				}

				$this->runCommand(
					'zip -r ' . $packagesDir . '/' . $timestamp . '/' . $languageDir . '.zip ' . $languageDir . '/ > /dev/null'
				);
			}
		}

		// Store the lang data as a backup
		file_put_contents($packagesDir . '/' . $timestamp . '.txt', json_encode($langData, JSON_PRETTY_PRINT));

		$connector = HttpFactory::getHttp();

		$connector->post(
			'https://updates.mautic.org/index.php?option=com_mauticdownload&task=addLanguages',
			['languageData' => $langData],
			['Mautic-Token' => $this->get('mautic.token')]
		);

		// If instructed, upload the packages
		if ($this->input->getBool('uploadpackages', false))
		{
			// Build our S3 adapter
			$client = S3Client::factory([
				'credentials' => new Credentials($this->get('amazon.key'), $this->get('amazon.secret')),
				'region' => $this->get('amazon.region')
			]);

			foreach (Folder::files($packagesDir . '/' . $timestamp) as $package)
			{
				if ($languageFilter && $languageFilter != $package)
				{
					continue;
				}

				// Remove our existing objects and upload fresh items
				$client->deleteMatchingObjects($this->get('amazon.bucket'), 'languages/' . $package);

				$client->putObject([
					'Bucket' => $this->get('amazon.bucket'),
					'Key' => 'languages/' . $package,
					'SourceFile' => $packagesDir . '/' . $timestamp . '/' . $package,
					'ACL' => 'public-read'
				]);
			}

			$this->out('Successfully uploaded language packages');
		}
        if($this->errorsEncountered) {
            $this->out('Created language packages for Mautic. But there were errors. Check your logs.');
            exit(1);
        }
        $this->out('Successfully created language packages for Mautic!');
	}

    /**
     * @param $code
     *
     * @return mixed
     */
	private function fixCode($code)
    {
        $converted = str_replace('-', '_', $code);

        $this->languages[$converted] = $code;

        return $converted;
    }

	/**
	 * Renders the translation package configuration
	 *
	 * @param   array  $data  Data array to render
	 *
	 * @return  string
	 */
	private function renderConfig(array $data)
	{
		$string = "<?php\n";
		$string .= "\$config = array(\n";

		foreach ($data as $key => $value)
		{
			$string .= "\t'$key' => '$value',\n";
		}

		$string .= ");\n\nreturn \$config;";

		return $string;
	}

	/**
	 * Execute a command on the server.
	 *
	 * @param   string  $command  The command to execute.
	 *
	 * @return  string  Return data from the command
	 *
	 * @throws  \RuntimeException
	 */
	public function runCommand($command)
	{
		$lastLine = system($command, $status);

		if ($status)
		{
			// Command exited with a status != 0
			if ($lastLine)
			{
				throw new \RuntimeException($lastLine, $status);
			}

			throw new \RuntimeException(sprintf('Unknown error executing "%s" command', $command), $status);
		}

		return $lastLine;
	}
}
