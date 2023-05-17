<?php
namespace ITRocks\Build;

use ITRocks\Class_Use\Index;
use ITRocks\Extend;

trait Implement
{

	//------------------------------------------------------------------------------------ COMPONENTS
	public const COMPONENTS = [self::T_ANNOTATION, T_ATTRIBUTE, T_INTERFACE, T_TRAIT];

	//----------------------------------------------------------------------------- SIMPLE_COMPONENTS
	public const SIMPLE_COMPONENTS = [self::T_ANNOTATION, T_ATTRIBUTE, T_INTERFACE];

	//---------------------------------------------------------------------------------- T_ANNOTATION
	public const T_ANNOTATION = 0;

	//--------------------------------------------------------------------------------------- T_TRAIT
	public const T_TRAIT = T_TRAIT;

	//---------------------------------------------------------------------------------------- $types
	/**
	 * @var array<string,value-of<self::COMPONENTS>>
	 * array<string $component, self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE|T_TRAIT>
	 */
	protected array $types = [];

	//---------------------------------------------------------------------------- addTraitImplements
	/**
	 * @param array<value-of<self::COMPONENTS>,list<string>> $composition
	 *        array<self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE|T_TRAIT, array<string $component>>
	 * @param-out array<value-of<self::COMPONENTS>,list<string>> $composition
	 */
	protected function addTraitImplements(array &$composition) : void
	{
		$interfaces =& $composition[T_INTERFACE];
		$search     =  [T_TYPE => Extend\Implement::class];
		foreach ($composition[T_TRAIT] as $trait) {
			$search[T_CLASS] = $trait;
			foreach ($this->class_index->search($search, true) as $implement) {
				$implements = $implement[T_USE];
				if (!in_array($implements, $interfaces, true)) {
					$interfaces[] = $implements;
				}
			}
		}
	}

	//--------------------------------------------------------------------------------------- compose
	/**
	 * @param array<value-of<self::SIMPLE_COMPONENTS>,list<string>>|array<self::T_TRAIT,list<list<string>>> $composition
	 *        array<self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE, list<string $component>>
	 *        array<T_TRAIT, list<$level, list<string $trait>>>
	 * @return string Composed class source code
	 */
	protected function compose(string $class, array $composition) : string
	{
		// prepare
		/** @var array<int,list<string>> $composition_traits phpstan fault */
		$composition_traits = $composition[T_TRAIT];
		$search             = [T_TYPE => T_DECLARE_CLASS, T_USE => $class];
		$class_use          = $this->class_index->search($search, true)[0] ?? null;
		$last_level         = array_key_last($composition_traits);
		if (isset($class_use)) {
			$abstract = $this->isAbstract($class_use);
			$is_class = true;
			$extends  = "\\$class";
			$type     = 'class';
		}
		else {
			array_unshift($composition_traits[0], $class);
			$abstract = false;
			$extends  = '';
			$is_class = false;
			$type     = 'trait';
		}
		$source = "<?php\nnamespace $class;\n";
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
					$source .= "/**\n * " . join("\n * ", $annotations) . "\n */\n";
				}
				/** @var string[] $attributes phpstan fault */
				$attributes = $composition[T_ATTRIBUTE];
				if ($attributes !== []) {
					$source .= join("\n", $attributes) . "\n";
				}
			}
			if ($is_class && ($abstract || !$is_last)) {
				$source .= 'abstract ';
			}
			$source .= $type . ' ' . $built;
			if ($is_class) {
				$source .= " extends $extends";
			}
			if ($is_last && $is_class) {
				/** @var string[] $implements phpstan fault */
				$implements = $composition[T_INTERFACE];
				if ($implements !== []) {
					$source .= "\n\timplements \\" . join(', \\', $implements);
				}
			}
			$source .= "\n{\n";
			if (($level > 0) && !$is_class) {
				array_unshift($traits, $class . '\\B' . ($level - 1));
			}
			if ($traits !== []) {
				$source .= "\tuse \\" . join(";\n\tuse \\", $traits) . ";\n";
			}
			$source .= "}\n";
			if ($is_class && !$is_last) {
				$extends = $built;
			}
		}
		return $source;
	}

	//------------------------------------------------------------------------------- compositionTree
	/**
	 * @param  list<string> $components Annotations/attributes/interfaces/traits
	 * @return array<value-of<self::SIMPLE_COMPONENTS>,list<string>>|array<self::T_TRAIT,list<list<string>>>
	 *        array<self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE, list<string $component>>
	 *        array<T_TRAIT, list<$level, list<string $trait>>>
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
	/** @param list<string> $components Annotations/attributes/interfaces/traits */
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
		$cache_configuration_file = $this->getCacheDirectory() . '/configuration.json';
		$content = file_exists($cache_configuration_file)
			? file_get_contents($cache_configuration_file)
			: false;
		if ($content === false) {
			$content = '[]';
		}
		/** @var array<string,array<string>|string> $old_configuration */
		$old_configuration = json_decode($content, true);
		$changes = false;
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
			$filename = $this->getCacheDirectory() . '/' . str_replace('\\', '-', $class) . '-B';
			file_put_contents($filename, $source);
			$changes = true;
		}
		if ($changes) {
			file_put_contents($cache_configuration_file, json_encode($this->configuration));
		}
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
			$file_content = file_get_contents($this->class_index->getHome() . '/' . $filename);
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
	 * @param list<string> $components Annotations/attributes/interfaces/traits
	 * @return array<value-of<self::COMPONENTS>,list<string>>
	 * array<self::T_ANNOTATION|T_ATTRIBUTE|T_INTERFACE|T_TRAIT, list<string $component>>
	 */
	protected function sortComponents(array $components) : array
	{
		/** @var array<value-of<self::COMPONENTS>,list<string>> $build */
		$build = [self::T_ANNOTATION => [], T_ATTRIBUTE => [], T_INTERFACE => [], T_TRAIT => []];
		foreach ($components as $component) {
			$type = $this->types[$component];
			$build[$type][] = $component;
		}
		return $build;
	}

	//--------------------------------------------------------------------------------- traitsByLevel
	/**
	 * @param list<string> $traits
	 * @return list<list<string>> list<$level, list<string $trait>
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

		/** @var list<list<string>> $by_level phpstan force list */
		return $by_level;
	}

}
