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
}
