<?php
namespace ITRocks\Build;

use ITRocks\Extend;

trait Implement
{

	//---------------------------------------------------------------------------------- T_ANNOTATION
	const T_ANNOTATION = 0;

	//---------------------------------------------------------------------------------------- $types
	/**
	 * @var array<string,int>
	 *      array<string $component, self::T_ANNOTATION|T_ATTRIBUTE|T_CLASS|T_INTERFACE|T_TRAIT>
	 */
	protected array $types = [];

	//---------------------------------------------------------------------------- addTraitImplements
	/**
	 * @param array<int,array<string>> $composition
	 *        array<self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE|T_TRAIT, array<string $component>>
	 */
	protected function addTraitImplements(array &$composition) : void
	{
		$interfaces =& $composition[T_INTERFACE];
		$search     =  [T_TYPE => Extend\Implement::class];
		foreach ($composition[T_TRAIT] as $trait) {
			$search[T_CLASS] = $trait;
			foreach ($this->class_index->search($search, true) as $implement) {
				if (!in_array($implement, $interfaces, true)) {
					$interfaces[] = $implement[T_USE];
				}
			}
		}
	}

	//--------------------------------------------------------------------------------------- compose
	/**
	 * @param array<int,array<array<string>|string>> $composition
	 *        array<self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE, array<string $component>>
	 *        array<T_TRAIT, array<int $level, array<string $trait>>>
	 * @return string Composed class source code
	 */
	protected function compose(string $class, array $composition) : string
	{
		// prepare
		$search     = [T_TYPE => T_DECLARE_CLASS, T_USE => $class];
		$class_use  = $this->class_index->search($search, true)[0] ?? false;
		$last_level = array_key_last($composition[T_TRAIT]);
		if ($class_use !== false) {
			$extends  = "\\$class";
			$abstract = $this->isAbstract($class_use);
			$type     = 'class';
		}
		else {
			array_unshift($composition[T_TRAIT][$last_level], $class);
			$abstract = false;
			$extends  = '';
			$type     = 'trait';
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
				$annotations = $composition[self::T_ANNOTATION];
				if ($annotations !== []) {
					$source .= "/**\n *" . join("\n *", $annotations) . "\n */\n";
				}
				$source .= join("\n", $composition[T_ATTRIBUTE]);
			}
			if (($class_use !== false) && ($abstract || !$is_last)) {
				$source .= 'abstract ';
			}
			$source .= $type . ' ' . $built;
			if ($class_use !== false) {
				$source .= " extends $extends";
			}
			if (
				$is_last
				&& ($class_use !== false)
				&& (($implements = $composition[T_INTERFACE]) !== [])
			) {
				$source .= "\n\timplements \\" . join(', \\', $implements);
			}
			$source .= "\n{\n";
			if ($traits !== []) {
				$source .= "\tuse \\" . join(";\n\tuse \\", $traits) . ";\n";
			}
			$source .= "}\n";
			if (($class_use !== false) && !$is_last) {
				$extends = $built;
			}
		}
		return $source;
	}

	//------------------------------------------------------------------------------- compositionTree
	/**
	 * @param  string[] $components Annotations/attributes/interfaces/traits
	 * @return array<int,array<array<string>|string>> composition
	 *         array<self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE, array<string $component>>
	 *         array<T_TRAIT, array<int $level, array<string $trait>>>
	 */
	protected function compositionTree(array $components) : array
	{
		$this->identifyComponents($components);
		$composition = $this->sortComponents($components);
		$this->addTraitImplements($composition);
		$composition[T_TRAIT] = $this->traitsByLevel($composition[T_TRAIT]);
		return $composition;
	}

	//---------------------------------------------------------------------------- identifyComponents
	/** @param string[] $components Annotations/attributes/interfaces/traits */
	protected function identifyComponents(array $components) : void
	{
		$search = [T_TYPE => T_DECLARE_TRAIT];
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
			$search[T_USE] = $component;
			$found_trait   = ($this->class_index->search($search) !== []);
			$this->types[$component] = $found_trait ? T_TRAIT : T_INTERFACE;
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
				|| (
					(count($components) === count($old_components))
					&& (array_diff($components, $old_components) === [])
					&& (array_diff($old_components, $components) === [])
				)
			) {
				continue;
			}
			$source   = $this->compose($class, $this->compositionTree($components));
			$filename = $this->getCacheDirectory() . '/build/' . str_replace('\\', '-', $class) . '-B';
			file_put_contents($filename, $source);
		}
		$this->saveCacheConfigurationFile($cache_configuration_file);
	}

	//------------------------------------------------------------------------------------ isAbstract
	/** @param array<int|string> $class_use <T_FILE,string $filename>|<T_TOKEN_KEY, int $token> */
	protected function isAbstract(array $class_use) : bool
	{
		$tokens = $this->class_index->file_tokens[$class_use[T_FILE]] ?? null;
		if ($tokens === null) {
			$tokens = $this->class_index->file_tokens[$class_use[T_FILE]]
				= token_get_all(file_get_contents($class_use[T_FILE]));
		}
		$token_key = $class_use[T_TOKEN_KEY] - 1;
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
	 * @param string[] $components Annotations/attributes/interfaces/traits
	 * @return array<int,array<string>>
	 * array<self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE|T_TRAIT, string $component>
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
	 * @param string[] $traits
	 * @return array<int,array<string>> array<int $level, array<string $trait>
	 */
	protected function traitsByLevel(array $traits) : array
	{
		$extends = [];
		$search  = [T_TYPE => Extend::class];
		foreach ($traits as $trait) {
			$search[T_CLASS] = $trait;
			foreach ($this->class_index->search($search, true) as $extend) {
				if (in_array($extend[T_USE], $traits, true)) {
					$extends[$trait][] = $extend[T_USE];
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
					if (in_array($extend, $traits, true)) {
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
