<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator;


use Latte\CompileException;
use Latte\Compiler;
use Latte\MacroNode;
use Latte\Macros\MacroSet;
use Latte\PhpWriter;

final class Macros extends MacroSet
{
	public static function install(Compiler $compiler): self
	{
		$set = new static($compiler);

		$set->addMacro('img', [$set, 'macroImg']);
		$set->addMacro('imageGenerator', [$set, 'macroImageGenerator']);
		$set->addMacro('src', null, null, [$set, 'macroImageGeneratorSrc']);

		return $set;
	}


	/**
	 * @param array{
	 *    w?: int,
	 *    width?: int,
	 *    h?: int,
	 *    height?: int,
	 *    sc?: string,
	 *    cr?: string,
	 *    c?: string
	 * }|null $params
	 */
	public static function renderMacroImg(?string $url = null, ?array $params = null): string
	{
		return ImageGenerator::from($url, $params ?? []);
	}


	/**
	 * @param array{
	 *    w?: int,
	 *    width?: int,
	 *    h?: int,
	 *    height?: int,
	 *    sc?: string,
	 *    cr?: string,
	 *    c?: string,
	 *    alt?: string
	 * }|null $params
	 */
	public static function renderMacroImageGenerator(?string $url = null, ?array $params = null): string
	{
		return sprintf('<img src="%s" alt="%s">',
			ImageGenerator::from($url, $params ?? []),
			$params['alt'] ?? 'Image',
		);
	}


	/**
	 * @param array{
	 *    w?: int,
	 *    width?: int,
	 *    h?: int,
	 *    height?: int,
	 *    sc?: string,
	 *    cr?: string,
	 *    c?: string
	 * }|null $params
	 */
	public static function renderMacroImageGeneratorSrc(?string $url = null, ?array $params = null): string
	{
		return sprintf(' src="%s"', ImageGenerator::from($url, $params ?? []));
	}


	/**
	 * @throws CompileException
	 */
	public function macroImg(MacroNode $node, PhpWriter $writer): string
	{
		return $writer->write(
			'echo \Baraja\ImageGenerator\Macros::renderMacroImg(%node.word, %node.args)',
		);
	}


	/**
	 * @throws CompileException
	 */
	public function macroImageGenerator(MacroNode $node, PhpWriter $writer): string
	{
		return $writer->write(
			'echo \Baraja\ImageGenerator\Macros::renderMacroImageGenerator(%node.word, %node.args)',
		);
	}


	/**
	 * @throws CompileException
	 */
	public function macroImageGeneratorSrc(MacroNode $node, PhpWriter $writer): string
	{
		return $writer->write(
			'echo \Baraja\ImageGenerator\Macros::renderMacroImageGeneratorSrc(%node.word, %node.args)',
		);
	}
}
