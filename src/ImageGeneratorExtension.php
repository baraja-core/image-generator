<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator;


use Baraja\Url\Url;
use Nette\Application\Application;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

/**
 * @method array{debugMode: bool, defaultBackgroundColor: array<int, int>, cropPoints: array<int, array<int, int>>} getConfig()
 */
final class ImageGeneratorExtension extends CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure(
			[
				'debugMode' => Expect::bool(false),
				'defaultBackgroundColor' => Expect::arrayOf(Expect::int())->max(3),
				'cropPoints' => Expect::arrayOf(Expect::arrayOf(Expect::int())),
			],
		)->castTo('array');
	}


	public function beforeCompile(): void
	{
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('image'))
			->setFactory(Image::class)
			->addSetup('?->setDebugMode(?)', ['@self', $config['debugMode'] ?? false]);

		$builder->addDefinition($this->prefix('config'))
			->setFactory(Config::class)
			->setArguments(
				[
					'defaultBackgroundColor' => $config['defaultBackgroundColor'] ?? [255, 255, 255],
					'cropPoints' => $config['cropPoints'] ?? [
						480 => [910, 30, 1845, 1150],
						600 => [875, 95, 1710, 910],
						768 => [975, 130, 1743, 660],
						1024 => [805, 110, 1829, 850],
						1280 => [615, 63, 1895, 800],
						1440 => [535, 63, 1975, 800],
						1680 => [410, 63, 2090, 800],
						1920 => [320, 63, 2240, 800],
						2560 => [0, 63, 2560, 800],
					],
				],
			);


		$builder->addDefinition($this->prefix('imageGenerator'))
			->setFactory(ImageGenerator::class);

		$latte = $builder->getDefinitionByType(LatteFactory::class);
		assert($latte instanceof FactoryDefinition);
		$latte->getResultDefinition()
			->addSetup(
				'?->onCompile[] = function ($engine) { ' . Macros::class . '::install($engine->getCompiler()); }',
				[
					'@self',
				],
			);
	}


	public function afterCompile(ClassType $class): void
	{
		if (PHP_SAPI === 'cli') {
			return;
		}

		$application = $this->getContainerBuilder()->getDefinitionByType(Application::class);
		assert($application instanceof ServiceDefinition);

		$image = $this->getContainerBuilder()->getDefinitionByType(Image::class);
		assert($image instanceof ServiceDefinition);

		$class->getMethod('initialize')->addBody(
			'// image generator.' . "\n"
			. '(function (): void {' . "\n"
			. "\t" . 'if (preg_match(?, \\' . Url::class . '::get()->getRelativeUrl(false), $parser) === 1 ' . "\n"
			. "\t\t" . '&& (isset($parser[\'dirname\']) === false || $parser[\'dirname\'] !== \'gallery\')) {' . "\n"
			. "\t\t" . '$this->getService(?)->onStartup[] = function(' . Application::class . ' $a): void {' . "\n"
			. "\t\t\t" . '(new ' . ImageGeneratorRoute::class . ')->run($this->getService(?));' . "\n"
			. "\t\t" . '};' . "\n"
			. "\t" . '}' . "\n"
			. '})();',
			[
				ImageGeneratorRoute::PATTERN,
				$application->getName(),
				$image->getName(),
			],
		);
	}
}
