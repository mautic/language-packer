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
	 * List of language files that are in error state
	 *
	 * @var  array
	 */
	private $errorFiles = [];

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
	 * Debugs a language file
	 *
	 * @param   string  $filename  Absolute path to the file to debug
	 *
	 * @return  integer  A count of the number of parsing errors
	 *
	 * @throws  \InvalidArgumentException
	 */
	private function debugFile($filename)
	{
		// Make sure our file actually exists
		if (!file_exists($filename))
		{
			throw new \InvalidArgumentException(
				sprintf('Unable to locate file "%s" for debugging', $filename)
			);
		}

		// Initialise variables for manually parsing the file for common errors.
		$blacklist = array('YES', 'NO', 'NULL', 'FALSE', 'ON', 'OFF', 'NONE', 'TRUE');
		$errors = array();
		$php_errormsg = null;

		// Open the file as a stream.
		$file = new \SplFileObject($filename);

		foreach ($file as $lineNumber => $line)
		{
			// Avoid BOM error as BOM is OK when using parse_ini.
			if ($lineNumber == 0)
			{
				$line = str_replace("\xEF\xBB\xBF", '', $line);
			}

			$line = trim($line);

			// Ignore comment lines.
			if (!strlen($line) || $line['0'] == ';')
			{
				continue;
			}

			// Ignore grouping tag lines, like: [group]
			if (preg_match('#^\[[^\]]*\](\s*;.*)?$#', $line))
			{
				continue;
			}

			$realNumber = $lineNumber + 1;

			// Check for odd number of double quotes.
			if (substr_count($line, '"') % 2 != 0)
			{
				$errors[] = $realNumber;
				continue;
			}

			// Check that the line passes the necessary format.
			if (!preg_match('#^[A-Za-z][A-Za-z0-9_\-\.]*\s*=\s*".*"(\s*;.*)?$#', $line))
			{
				$errors[] = $realNumber;
				continue;
			}

			// Check for unescaped quotes
			preg_match_all('/\"*[^\\"]*[\"\n](?=(?:[^\\"]*[^\"]*")*[^"]*$)/', $line, $matches);

			if (count($matches[0]) > 2)
			{
				$errors[] = $realNumber;
				continue;
			}

			// Check that the key is not in the blacklist.
			$key = strtoupper(trim(substr($line, 0, strpos($line, '='))));

			if (in_array($key, $blacklist))
			{
				$errors[] = $realNumber;
			}
		}

		// Check if we encountered any errors.
		if (count($errors))
		{
			$this->errorFiles[$filename] = $filename . ' - error(s) in line(s) ' . implode(', ', $errors);
		}
		elseif ($php_errormsg)
		{
			// We didn't find any errors but there's probably a parse notice.
			$this->errorFiles['PHP' . $filename] = 'PHP parser errors -' . $php_errormsg;
		}

		return count($errors);
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

		$project = $transifex->projects->getProject('mautic', true);

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

			$languageStats = $transifex->statistics->getStatistics('mautic', $resource->slug);

			foreach ($languageStats as $language => $stats)
			{
				// Skip our default language
				if ($language == 'en')
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
					$translation = $transifex->translations->getTranslation('mautic', $resource->slug, $language);

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

					if ($debugLanguages)
					{
						$this->debugFile($path);
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
			// If the directory is empty, there is no point in packaging it
			if (count(scandir($translationDir . '/' . $languageDir)) > 2)
			{
				$this->out(sprintf('Creating package for "%s" language', $languageDir));

				$txLangData = $transifex->languageinfo->getLanguage($languageDir);
				$langData[] = ['name' => $txLangData->name, 'code' => $txLangData->code];
				$configData = $this->renderConfig(
					['name' => $txLangData->name, 'locale' => $txLangData->code, 'author' => 'Mautic Translators']
				);

				file_put_contents($translationDir . '/' . $languageDir . '/config.php', $configData);

				$this->runCommand(
					'zip -r ' . $packagesDir . '/' . $timestamp . '/' . $languageDir . '.zip ' . $languageDir . '/ > /dev/null'
				);
			}
		}

		// Store the lang data as a backup
		file_put_contents($packagesDir . '/' . $timestamp . '.txt', json_encode($langData));

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

			$this->out('<info>Successfully uploaded language packages</info>');
		}

		if ($debugLanguages && !empty($this->errorFiles))
		{
			if (!file_put_contents($translationDir . '/errors.txt', json_encode($this->errorFiles, JSON_PRETTY_PRINT)))
			{
				throw new \RuntimeException(
					sprintf(
						'Failed writing translation file "%s".  Please verify your filesystem permissions and try again.',
						$translationDir . '/errors.txt'
					)
				);
			}
		}
		$this->out('<info>Successfully created language packages for Mautic!</info>');
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
			if ($value !== '')
			{
				if (is_string($value))
				{
					$value = "'$value'";
				}
				elseif (is_bool($value))
				{
					$value = ($value) ? 'true' : 'false';
				}
				elseif (is_null($value))
				{
					$value = 'null';
				}
				elseif (is_array($value))
				{
					$value = $this->renderArray($value);
				}

				$string .= "\t'$key' => $value,\n";
			}
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
				throw new \RuntimeException($lastLine);
			}

			throw new \RuntimeException(sprintf('Unknown error executing "%s" command', $command));
		}

		return $lastLine;
	}
}
