<?php
namespace ITRocks\Build;

use ITRocks\Class_Use\Repository\Type as Index;
use ITRocks\Class_Use\Tokens_Scanner\Type as Token;
use ITRocks\Extend;

trait Implement
{

	//---------------------------------------------------------------------------------- T_ANNOTATION
	const T_ANNOTATION = 0;

	//------------------------------------------------------------------------ $extend_attribute_name
	public string $extend_attribute_name = Extend::class;

	//---------------------------------------------------------------------------------------- $types
	/**
	 * @var int[] (self::T_ANNOTATION|T_ATTRIBUTE|T_CLASS|T_INTERFACE|T_TRAIT)[string $interface_name]
	 */
	protected array $types = [];

	//--------------------------------------------------------------------------------------- compose
	/**
	 * @param $composition string[][][]|string[][] $trait[T_TRAIT][int $level][]
	 *                              $component[self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE][]
	 * @return string Composed class source code
	 */
	protected function compose(string $class, array $composition) : string
	{
		// prepare
		$search     = [Index::TYPE => Token::DECLARE_CLASS, Index::USE => $class];
		$class_use  = $this->class_index->search($search)[0] ?? false;
		$last_level = array_key_last($composition[T_TRAIT]);
		if ($class_use) {
			$extends  = $class;
			$abstract = $this->isAbstract($class_use);
			$type     = 'class';
		}
		else {
			array_unshift($composition[T_TRAIT][$last_level], $class);
			$type = 'trait';
		}
		// is abstract
		$source     = "<?php\nnamespace $class;\n";
		foreach ($composition[T_TRAIT] as $level => $traits) {
			$is_last = ($level === $last_level);
			$built   = 'B';
			if (!$is_last) {
				$built .= $level;
			}
			$source .= "\n";
			if ($is_last) {
				if ($annotations = $composition[self::T_ANNOTATION]) {
					$source .= "/**\n *" . join("\n *", $annotations) . "\n */\n";
				}
				$source .= join("\n", $composition[T_ATTRIBUTE]);
			}
			if ($class_use && ($abstract || !$is_last)) {
				$source .= 'abstract ';
			}
			$source .= $type . ' ' . $built;
			if ($class_use) {
				/** @noinspection PhpUndefinedVariableInspection if ($class_use) */
				$source .= " extends \\$extends";
			}
			if ($is_last && $class_use && ($implements = $composition[T_INTERFACE])) {
				$source .= "\n\timplements \\" . join(', \\', $implements);
			}
			$source .= "\n{\n";
			if ($traits) {
				$source .= "\tuse \\" . join(";\n\tuse \\", $traits) . ";\n";
			}
			$source .= "}\n";
			if ($class_use && !$is_last) {
				$extends = $built;
			}
		}
		return $source;
	}

	//------------------------------------------------------------------------------- compositionTree
	/**
	 * @param $components string[]     Annotations/attributes/interfaces/traits
	 * @return string[][][]|string[][] $trait[T_TRAIT][int $level][]
	 *                                 $component[self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE][]
	 */
	protected function compositionTree(array $components) : array
	{
		$this->identifyComponents($components);
		$composition          = $this->sortComponents($components);
		$composition[T_TRAIT] = $this->traitsByLevel($composition[T_TRAIT]);
		return $composition;
	}

	//---------------------------------------------------------------------------- identifyComponents
	/** @var $components string[] Annotations/attributes/interfaces/traits */
	protected function identifyComponents(array $components) : void
	{
		foreach ($components as $component) {
			if ($component[0] === '#') {
				$this->types[$component] = T_ATTRIBUTE;
				continue;
			}
			elseif ($component[0] === '@') {
				$this->types[$component] = self::T_ANNOTATION;
				continue;
			}
			elseif (isset($this->types[$component])) {
				continue;
			}
			$search = [Index::TYPE => Token::DECLARE_TRAIT, Index::USE => $component];
			$this->types[$component] = $this->class_index->search($search) ? T_TRAIT : T_INTERFACE;
		}
	}

	//------------------------------------------------------------------------------------- implement
	/** Implement replacement classes */
	public function implement() : void
	{
		$cache_configuration_file = $this->class_index->getHome() . static::CACHE_DIRECTORY
			. '/build.php';
		$old_configuration = file_exists($cache_configuration_file)
			? include($cache_configuration_file)
			: [];
		foreach ($this->configuration as $class => $components) {
			$old_components = $old_configuration[$class] ?? [];
			if (
				!is_array($components)
				|| array_diff($components, $old_components)
				|| array_diff($old_components, $components)
			) {
				continue;
			}
			$source   = $this->compose($class, $this->compositionTree($components));
			$filename = $this->getCacheDirectory() . '/build/' . str_replace('\\', '-', $class);
			file_put_contents($filename, $source);
		}
		$this->saveCacheConfigurationFile($cache_configuration_file);
	}

	//------------------------------------------------------------------------------------ isAbstract
	/** @param $class_use (int|string)[] [string[Index::FILE], int[Index::TOKEN_KEY], ...] */
	protected function isAbstract(array $class_use) : bool
	{
		$tokens = $this->class_index->file_tokens[$class_use[Index::FILE]]
			?? token_get_all(file_get_contents($class_use[Index::FILE]), TOKEN_PARSE);
		$token_key = $class_use[Index::TOKEN_KEY] - 1;
		while (!in_array($is = $tokens[$token_key][0], [';', '}', T_OPEN_TAG], true)) {
			if ($is === T_ABSTRACT) {
				return true;
			}
			$token_key --;
		}
		return false;
	}

	//-------------------------------------------------------------------------------- sortComponents
	/**
	 * @param $components string[] Annotations/attributes/interfaces/traits
	 * @return string[][] $component[self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE|T_TRAIT][]
	 */
	protected function sortComponents(array $components) : array
	{
		$build = [self::T_ANNOTATION => [], T_ATTRIBUTE => [], T_INTERFACE => [], T_TRAIT => []];
		foreach ($components as $component) {
			$build[$this->types[$component]][] = $component;
		}
		return $build;
	}

	//--------------------------------------------------------------------------------- traitsByLevel
	/**
	 * @param $traits string[]
	 * @return string[][] $trait[int $level][]
	 */
	protected function traitsByLevel(array $traits) : array
	{
		$extends  = [];
		$search   = [Index::TYPE => Extend::class];
		foreach ($traits as $trait) {
			$search[Index::CLASS] = $trait;
			foreach ($this->class_index->search($search) as $extend) {
				if (in_array($extend, $traits)) {
					$extends[$trait][] = $extend[Index::USE];
				}
			}
		}

		$by_level = [0 => []];
		$level    = 0;
		while ($traits) {
			$next_traits = [];
			foreach ($traits as $trait) {
				// has every extend of this trait been used
				foreach ($extends[$trait] ?? [] as $extend) {
					if (in_array($extend, $traits)) {
						$next_traits[] = $trait;
						continue 2;
					}
				}
				// use this trait
				$by_level[$level][] = $trait;
			}
			$traits = $next_traits;
			$level ++;
		}

		return $by_level;
	}

}
