<?php
namespace ITRocks\Build;

use ITRocks\Class_Use\Index;
use ITRocks\Extend;

trait Implement
{

	//------------------------------------------------------------------------------------ COMPONENTS
	const COMPONENTS = [self::T_ANNOTATION, T_ATTRIBUTE, T_INTERFACE, T_TRAIT];

	//----------------------------------------------------------------------------- SIMPLE_COMPONENTS
	const SIMPLE_COMPONENTS = [self::T_ANNOTATION, T_ATTRIBUTE, T_INTERFACE];

	//---------------------------------------------------------------------------------- T_ANNOTATION
	const T_ANNOTATION = 0;

	//--------------------------------------------------------------------------------------- T_TRAIT
	const T_TRAIT = T_TRAIT;

	//---------------------------------------------------------------------------------------- $types
	/**
	 * @var array<string,value-of<self::COMPONENTS>>
	 * array<string $component, self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE|T_TRAIT>
	 */
	protected array $types = [];

	//---------------------------------------------------------------------------- addTraitImplements
	/**
	 * @param array<value-of<self::COMPONENTS>,array<string>> $composition
	 *        array<self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE|T_TRAIT, array<string $component>>
	 * @param-out array<value-of<self::COMPONENTS>,array<string>> $composition
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
	 * @param array<value-of<self::SIMPLE_COMPONENTS>,array<string>>|array<self::T_TRAIT,array<int,list<string>>> $composition
	 *        array<self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE, array<string $component>>
	 *        array<T_TRAIT, array<int $level, array<string $trait>>>
	 * @return string Composed class source code
	 */
	protected function compose(string $class, array $composition) : string
	{
		// prepare
		/** @var array<int,list<string>> $composition_traits phpstan fault */
		$composition_traits = $composition[T_TRAIT];
		$search             = [T_TYPE => T_DECLARE_CLASS, T_USE => $class];
		$class_use          = $this->class_index->search($search, true)[0] ?? false;
		$last_level         = array_key_last($composition_traits);
		if ($class_use !== false) {
			$extends  = "\\$class";
			$abstract = $this->isAbstract($class_use);
			$type     = 'class';
		}
		else {
			array_unshift($composition_traits[$last_level], $class);
			$abstract = false;
			$extends  = '';
			$type     = 'trait';
		}
		// is abstract
		$source     = "<?php\nnamespace $class;\n";
		foreach ($composition_traits as $level => $traits) {
			$is_last = ($level === $last_level);
			$built   = 'B';
			if (!$is_last) {
				$built .= $level;
			}
			$source .= "\n";
			if ($is_last) {
				/** @var string[] $annotations phpstan fault */
				$annotations = $composition[self::T_ANNOTATION];
				if ($annotations !== []) {
					$source .= "/**\n *" . join("\n *", $annotations) . "\n */\n";
				}
				/** @var string[] $attributes phpstan fault */
				$attributes = $composition[T_ATTRIBUTE];
				$source .= join("\n", $attributes);
			}
			if (($class_use !== false) && ($abstract || !$is_last)) {
				$source .= 'abstract ';
			}
			$source .= $type . ' ' . $built;
			if ($class_use !== false) {
				$source .= " extends $extends";
			}
			if ($is_last && ($class_use !== false)) {
				/** @var string[] $implements phpstan fault */
				$implements = $composition[T_INTERFACE];
				if ($implements !== []) {
					$source .= "\n\timplements \\" . join(', \\', $implements);
				}
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
	 * @return array<value-of<self::SIMPLE_COMPONENTS>,array<string>>|array<self::T_TRAIT,array<int,list<string>>>
	 *        array<self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE, array<string $component>>
	 *        array<T_TRAIT, array<int $level, array<string $trait>>>
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
		/** @var array<string,array<string>|string> $old_configuration */
		$old_configuration = file_exists($cache_configuration_file)
			? include($cache_configuration_file)
			: [];
		foreach ($this->configuration as $class => $components) {
			$old_components = $old_configuration[$class] ?? [];
			if (
				!is_array($components)
				|| (
					is_array($old_components)
					&& (count($components) === count($old_components))
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
	/**
	 * @param array<value-of<Index::STRING_RESULTS>,string>|array<value-of<Index::INT_RESULTS>,int> $class_use
	 * array<T_FILE, string $filename> | array<T_TOKEN_KEY, int $token>
	 */
	protected function isAbstract(array $class_use) : bool
	{
		/** @var string $filename phpstan fault */
		$filename = $class_use[T_FILE];
		$tokens   = $this->class_index->file_tokens[$filename] ?? null;
		if ($tokens === null) {
			$file_content = file_get_contents($filename);
			if ($file_content === false) $file_content = '';
			$tokens = token_get_all($file_content);
			$this->class_index->file_tokens[$filename] = $tokens;
		}
		/** @var int $token_key phpstan fault */
		$token_key = $class_use[T_TOKEN_KEY];
		$token_key --;
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
	 * @return array<value-of<self::COMPONENTS>,array<string>>
	 * array<self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE|T_TRAIT, string $component>
	 */
	protected function sortComponents(array $components) : array
	{
		/** @var array<value-of<self::COMPONENTS>,array<string>> $build */
		$build = [self::T_ANNOTATION => [], T_ATTRIBUTE => [], T_INTERFACE => [], T_TRAIT => []];
		foreach ($components as $component) {
			$type = $this->types[$component];
			$build[$type][] = $component;
		}
		return $build;
	}

	//--------------------------------------------------------------------------------- traitsByLevel
	/**
	 * @param string[] $traits
	 * @return array<int,list<string>> array<int $level, list<string $trait>
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
