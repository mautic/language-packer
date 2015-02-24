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
	}
}
