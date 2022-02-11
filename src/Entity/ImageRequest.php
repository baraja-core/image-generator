<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator;


final class ImageRequest
{
	public function __construct(
		private string $dirname,
		private string $basename,
		private string $params,
		private string $hash,
		private string $extension,
	) {
	}


	public function getDirname(): string
	{
		return $this->dirname;
	}


	public function getBasename(): string
	{
		return $this->basename;
	}


	public function getParams(): string
	{
		return $this->params;
	}


	public function getHash(): string
	{
		return $this->hash;
	}


	public function getExtension(): string
	{
		return $this->extension;
	}


	public function getFileName(): string
	{
		return sprintf('%s__%s_%s.%s', $this->basename, $this->params, $this->hash, $this->extension);
	}


	public function getFilePath(): string
	{
		return sprintf('%s/%s.%s', $this->dirname, $this->basename, $this->extension);
	}


	/**
	 * Returns the relative disk path inside the "_cache" directory.
	 */
	public function getFileSuffix(bool $withParams = false): string
	{
		if ($withParams) {
			$basename = $this->getFileName();
		} else {
			$basename = $this->basename;
		}
		if (str_starts_with($this->dirname, '/')) {
			return sprintf('_proxy/%s.%s', $basename, $this->extension);
		}

		return sprintf('%s/%s.%s', $this->dirname, $basename, $this->extension);
	}


	public function getAbsoluteFileDirPath(): string
	{
		$filePath = $this->getFilePath();
		if (str_starts_with($this->dirname, '/')) {
			$absoluteFileDirPath = sprintf('%s/', dirname($filePath));
		} else {
			$absoluteFileDirPath = (string) preg_replace(
				'@/+@',
				'/',
				Helper::getWwwDir() . '/' . str_replace(
					$this->basename . '.' . $this->extension,
					'',
					$filePath,
				),
			);
		}

		return $absoluteFileDirPath;
	}


	/**
	 * Returns the absolute disk path to the source image on which all further operations will be performed.
	 */
	public function getSourceFileAbsolutePath(): ?string
	{
		$absoluteFileDirPath = $this->getAbsoluteFileDirPath();
		$return = null;
		if (is_dir($absoluteFileDirPath) === true) {
			foreach ((new \DirectoryIterator($absoluteFileDirPath)) as $item) {
				assert($item instanceof \DirectoryIterator);
				if (in_array($item->getFilename(), ['.', '..'], true)) {
					continue;
				}
				if (
					($return === null || $item->getExtension() === $this->extension)
					&& pathinfo($item->getRealPath())['filename'] === $this->basename
				) {
					$return = $item->getRealPath();
				}
			}
		}

		return $return;
	}
}
