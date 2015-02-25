<?php
/**
 * Mautic Language Packager
 *
 * @copyright  Copyright (C) 2015 Allyde, LLC. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

namespace Mautic;

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
	 * {@inheritdoc}
	 *
	 * @throws  \InvalidArgumentException
	 */
	protected function doExecute()
	{
		// If a --version option wasn't given, prompt the user
		if (!($version = $this->input->getString('version')))
		{
			$this->out('Please specify the Mautic version these packages are for.');
			$version = trim($this->in());
		}

		// Don't bother if we don't have a version string (really, even just 'M' will do!)
		if (!$version)
		{
			throw new \InvalidArgumentException('Must specify version number.');
		}

		$username       = $this->get('transifex.username');
		$password       = $this->get('transifex.password');
		$completion     = $this->get('transifex.completion', 80);
		$packagesDir    = JPATH_ROOT . '/packages';
		$translationDir = JPATH_ROOT . '/translations';

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

				$this->out(sprintf('Processing the %1$s "%2$s" resource in "%3$s" language', $bundle, $file, $language));

				$completed = str_replace('%', '', $stats->completed);

				// We only want resources which are 80% completed unless told to bypass the completion check
				if ($this->input->getBool('bypasscompletion', false) || $completed >= 80)
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

		if (!is_dir($packagesDir . '/' . $version))
		{
			if (!Folder::create($packagesDir . '/' . $version))
			{
				throw new \RuntimeException(
					sprintf(
						'Failed creating packages folder for version "%s".  Please verify your filesystem permissions and try again.',
						$version
					)
				);
			}
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
				$langData[] = ['name' => $txLangData->name, 'code' => $txLangData->code, 'version' => $version];
				$configData = $this->renderConfig(
					['name' => $txLangData->name, 'locale' => $txLangData->code, 'author' => 'Mautic Translators']
				);

				file_put_contents($translationDir . '/' . $languageDir . '/config.php', $configData);

				$this->runCommand(
					'zip -r ' . $packagesDir . '/' . $version . '/' . $languageDir . '-' . $version . '.zip ' . $languageDir . '/ > /dev/null'
				);
			}
		}

		// Store the lang data as a backup
		file_put_contents($packagesDir . '/' . $version . '.txt', json_encode($langData));

		$connector = HttpFactory::getHttp();

		$connector->post(
			'https://www.mautic.org/index.php?option=com_mauticdownload&task=addLanguages',
			['languageData' => $langData],
			['Mautic-Token' => $this->get('mautic.token')]
		);

		$this->out(sprintf('<info>Successfully created language packages for Mautic version %s!</info>', $version));
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
