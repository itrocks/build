<?php
namespace ITRocks\Build;

use ITRocks\Build;
use ITRocks\Class_Use\Index;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

class Implement_Test extends TestCase
{

	//-------------------------------------------------------------------------------------- newBuild
	protected function newBuild(
		string $code, array $configuration = [], $home_directory = '/'
	) : Build
	{
		$index  = $this->newIndex($home_directory);
		$tokens = token_get_all($code);
		$index->file_tokens['file.php'] = $tokens;
		$index->scanner->scan($tokens);
		$references = new ReflectionProperty(Index::class, 'references');
		$references->setValue($index, ['file.php' => $index->scanner->references]);
		$index->classify();
		return new Build($configuration, $index);
	}

	//-------------------------------------------------------------------------------------- newIndex
	protected function newIndex(string $home_directory = '/') : Index
	{
		return new class(0, $home_directory) extends Index {
			protected function prepareHome() : void {}
		};
	}

	//---------------------------------------------------------------------------- provideComposeData
	/** @return array{string,string,array<value-of<Implement::SIMPLE_COMPONENTS>,list<string>>|array<Implement::T_TRAIT,list<list<string>>>,string} */
	public static function provideComposeData() : array
	{
		return [
			[
				'one-annotation',
				'class',
				[Build::T_ANNOTATION => ['@display a thing']],
				'<?php namespace N\C; /** * @display a thing */ class B extends \N\C { }'
			],
			[
				'two-annotations',
				'class',
				[Build::T_ANNOTATION => ['@display a thing', '@display some things']],
				'<?php namespace N\C; /** * @display a thing * @display some things */ class B extends \N\C { }'
			],
			[
				'one-attribute',
				'class',
				[T_ATTRIBUTE => ["#[Display('a thing')]"]],
				'<?php namespace N\C; #[Display(\'a thing\')] class B extends \N\C { }'
			],
			[
				'two-attributes',
				'class',
				[T_ATTRIBUTE => ["#[Display('a thing')]", "#[Displays('some things')]"]],
				'<?php namespace N\C; #[Display(\'a thing\')] #[Displays(\'some things\')] class B extends \N\C { }'
			],
			[
				'one-interface',
				'class',
				[T_INTERFACE => ['I1']],
				'<?php namespace N\C; class B extends \N\C implements \I1 { }'
			],
			[
				'two-interfaces',
				'class',
				[T_INTERFACE => ['I1', 'I2']],
				'<?php namespace N\C; class B extends \N\C implements \I1, \I2 { }'
			],
			[
				'trait-interface',
				'trait',
				[T_INTERFACE => ['I1']],
				'<?php namespace N\C; trait B { use \N\C; }'
			],
			[
				'abstract-class',
				'abstract class',
				[T_TRAIT => [['T1']]],
				'<?php namespace N\C; abstract class B extends \N\C { use \T1; }'
			],
			[
				'class',
				'class',
				[T_TRAIT => [['T1']]],
				'<?php namespace N\C; class B extends \N\C { use \T1; }'
			],
			[
				'trait',
				'trait',
				[T_TRAIT => [['T1']]],
				'<?php namespace N\C; trait B { use \N\C; use \T1; }'
			],
			[
				'class-with-two-traits',
				'class',
				[T_TRAIT => [['T1', 'T2']]],
				'<?php namespace N\C; class B extends \N\C { use \T1; use \T2; }'
			],
			[
				'trait-with-two-traits',
				'trait',
				[T_TRAIT => [['T1', 'T2']]],
				'<?php namespace N\C; trait B { use \N\C; use \T1; use \T2; }'
			],
			[
				'class-with-two-level-traits',
				'class',
				[T_TRAIT => [['T1'], ['T2']]],
				'<?php namespace N\C; abstract class B0 extends \N\C { use \T1; } class B extends B0 { use \T2; }'
			],
			[
				'trait-with-two-level-traits',
				'trait',
				[T_TRAIT => [['T1'], ['T2']]],
				'<?php namespace N\C; trait B0 { use \N\C; use \T1; } trait B { use \N\C\B0; use \T2; }'
			],
			[
				'complete-abstract-class',
				'abstract class',
				[
					Build::T_ANNOTATION => ['@display a thing'],
					T_ATTRIBUTE         => ["#[Display('a thing')]"],
					T_INTERFACE         => ['I1'],
					T_TRAIT             => [['T1', 'T2'], ['T3']]
				],
				'<?php namespace N\C; abstract class B0 extends \N\C { use \T1; use \T2; } /** * @display a thing */ #[Display(\'a thing\')] abstract class B extends B0 implements \I1 { use \T3; }'
			],
			[
				'complete-trait',
				'trait',
				[
					Build::T_ANNOTATION => ['@display a thing'],
					T_ATTRIBUTE         => ["#[Display('a thing')]"],
					T_INTERFACE         => ['I1'],
					T_TRAIT             => [['T1'], ['T2', 'T3']]
				],
				'<?php namespace N\C; trait B0 { use \N\C; use \T1; } /** * @display a thing */ #[Display(\'a thing\')] trait B { use \N\C\B0; use \T2; use \T3; }'
			]
		];
	}

	//-------------------------------------------------------------------- provideCompositionTreeData
	/**
	 * @return array list<array{string,list<string>,array<value-of<self::SIMPLE_COMPONENTS>,list<string>>|array<self::T_TRAIT,list<list<string>>>}>
	 */
	public static function provideCompositionTreeData() : array
	{
		return [
			[
				'empty',
				[],
				[
					Build::T_ANNOTATION => [],
					T_ATTRIBUTE         => [],
					T_INTERFACE         => [],
					T_TRAIT             => [[]]
				]
			],
			[
				'annotations',
				['@display name', '@displays names'],
				[
					Build::T_ANNOTATION => ['@display name', '@displays names'],
					T_ATTRIBUTE         => [],
					T_INTERFACE         => [],
					T_TRAIT             => [[]]
				]
			],
			[
				'attributes',
				['#[Display(\'name\')]', '#[Displays(\'names\')]'],
				[
					Build::T_ANNOTATION => [],
					T_ATTRIBUTE         => ['#[Display(\'name\')]', '#[Displays(\'names\')]'],
					T_INTERFACE         => [],
					T_TRAIT             => [[]]
				]
			],
			[
				'interface',
				['I1'],
				[
					Build::T_ANNOTATION => [],
					T_ATTRIBUTE         => [],
					T_INTERFACE         => ['I1'],
					T_TRAIT             => [[]]
				]
			],
			[
				'trait',
				['T1'],
				[
					Build::T_ANNOTATION => [],
					T_ATTRIBUTE         => [],
					T_INTERFACE         => [],
					T_TRAIT             => [['T1']]
				]
			],
			[
				'trait-implements',
				['T4'],
				[
					Build::T_ANNOTATION => [],
					T_ATTRIBUTE         => [],
					T_INTERFACE         => ['I2'],
					T_TRAIT             => [['T4']]
				]
			],
			[
				'mix',
				["#[Display('a thing']", '@display a thing', 'I1', 'I2', 'T1', 'T2'],
				[
					Build::T_ANNOTATION => ['@display a thing'],
					T_ATTRIBUTE         => ["#[Display('a thing']"],
					T_INTERFACE         => ['I1', 'I2'],
					T_TRAIT             => [['T1', 'T2']]
				]
			],
			[
				'trait-hierarchy',
				["#[Display('a thing']", '@display a thing', 'I1', 'I2', 'T1', 'T2', 'T3'],
				[
					Build::T_ANNOTATION => ['@display a thing'],
					T_ATTRIBUTE         => ["#[Display('a thing']"],
					T_INTERFACE         => ['I1', 'I2'],
					T_TRAIT             => [['T1', 'T2'], ['T3']]
				],
			]
		];
	}

	//----------------------------------------------------------------- provideIdentifyComponentsData
	/**
	 * @return list<array{string,list<string>,array<value-of<Build::SIMPLE_COMPONENTS>,list<string>>|array<Build::T_TRAIT,list<list<string>>>}>
	 * list< array{ string $index, list $components, array $identified_components } >
	 */
	public static function provideIdentifyComponentsData() : array
	{
		return [
			['T_ATTRIBUTE',  ["#[Display('a thing']"], [T_ATTRIBUTE]],
			['T_ANNOTATION', ['@display a thing'],     [Build::T_ANNOTATION]],
			['interface',    ['I1'],                   [T_INTERFACE]],
			['trait',        ['T1'],                   [T_TRAIT]],
			['mix',
				["#[Display('a thing']", '@display a thing', 'I1', 'T1'],
				[T_ATTRIBUTE, Build::T_ANNOTATION, T_INTERFACE, T_TRAIT]
			]
		];
	}

	//-------------------------------------------------------------------------- provideImplementData
	/** @return list<array{string,string,array<string,list<string>|string,array<string,string>>}> */
	public static function provideImplementData() : array
	{
		return [
			['empty', '', [], []],
			[
				'complete',
				'<?php namespace N; use ITRocks\Extend; use ITRocks\Extend\Implement;'
				. ' abstract class A {} abstract class R {}'
				. ' class C {}'
				. ' interface I1 {} interface I2 {} interface I3 {}'
				. ' trait T1 {} #[Implement(I3::class)] trait T2 {} #[Extend(T2::class)] trait T3 {}',
				[
					'N\A' => 'N\R',
					'N\C' => [
						'@display name',
						'@displays names',
						'#[Display(\'name\')]',
						'#[Displays(\'names\')]',
						'N\I1',
						'N\I2',
						'N\T1',
						'N\T2',
						'N\T3'
					],
					'N\T' => [
						'N\T3'
					]
				],
				[
					'N-C-B' => '<?php namespace N\C;'
						. ' abstract class B0 extends \N\C { use \N\T1; use \N\T2; }'
						. ' /** * @display name * @displays names */'
						. ' #[Display(\'name\')] #[Displays(\'names\')]'
						. ' class B extends B0 implements \N\I1, \N\I2, \N\I3 { use \N\T3; }',
					'N-T-B' => '<?php namespace N\T; trait B { use \N\T; use \N\T3; }'
				]
			],
			[
				'change',
				'<?php namespace N; use ITRocks\Extend; use ITRocks\Extend\Implement;'
				. ' abstract class A {} abstract class R {}'
				. ' class C {}'
				. ' interface I1 {} interface I2 {} interface I3 {}'
				. ' trait T1 {} #[Implement(I3::class)] trait T2 {} #[Extend(T2::class)] trait T3 {}',
				[
					'N\A' => 'N\R',
					'N\C' => ['N\I1'],
					'N\T' => ['N\T1', 'N\T3']
				],
				[
					'N-C-B' => '<?php namespace N\C; class B extends \N\C implements \N\I1 { }',
					'N-T-B' => '<?php namespace N\T; trait B { use \N\T; use \N\T1; use \N\T3; }'
				]
			]
		];
	}

	//------------------------------------------------------------------------- provideIsAbstractData
	/**
	 * @return list<array{string,bool,string}>
	 * list< array{ string $index, bool $expected, string $code } >
	 */
	public static function provideIsAbstractData() : array
	{
		return [
			['no',            false, '<?php class C {}'],
			['yes',           true,  '<?php abstract class C {}'],
			['no-after-no',   false, '<?php class A {} class C {}'],
			['no-after-yes',  false, '<?php abstract class A {} class C {}'],
			['yes-after-no',  true,  '<?php class A {} abstract class C {}'],
			['yes-after-yes', true,  '<?php abstract class A {} abstract class C {}']
		];
	}

	//------------------------------------------------------------------------ testAddTraitImplements
	public function testAddTraitImplements() : void
	{
		$build = $this->newBuild(
			'<?php #[ITRocks\Extend\Implement(I::class, I::class)] trait T {}',
			['C' => ['T']]
		);
		$composition = [T_INTERFACE => [], T_TRAIT => ['T']];
		$method = new ReflectionMethod(Build::class, 'addTraitImplements');
		/** @noinspection PhpUnhandledExceptionInspection Should not throw exception */
		$method->invokeArgs($build, [&$composition]);
		static::assertEquals([T_INTERFACE => ['I'], T_TRAIT => ['T']], $composition);
	}

	//----------------------------------------------------------------------------------- testCompose
	/**
	 * @noinspection PhpDocMissingThrowsInspection
	 * @param array<value-of<Implement::SIMPLE_COMPONENTS>,array<string>>|array<Implement::T_TRAIT,array<int,list<string>>> $composition
	 */
	#[DataProvider('provideComposeData')]
	public function testCompose(string $index, string $declare, array $composition, string $expected)
		: void
	{
		$build = $this->newBuild("<?php namespace N; $declare C {}");
		foreach ([Build::T_ANNOTATION, T_ATTRIBUTE, T_INTERFACE, T_TRAIT] as $key) {
			if (!isset($composition[$key])) {
				$composition[$key] = [];
			}
		}
		if ($composition[T_TRAIT] === []) {
			$composition[T_TRAIT] = [[]];
		}
		$method = new ReflectionMethod(Build::class, 'compose');
		/** @noinspection PhpUnhandledExceptionInspection Should not throw exception */
		$actual = $method->invokeArgs($build, ['N\C', $composition]);
		$actual = trim(str_replace(["\n", "\t"], ' ', $actual));
		while (str_contains($actual, '  ')) {
			$actual = str_replace('  ', ' ', $actual);
		}
		static::assertEquals($expected, $actual, $index);
	}

	//--------------------------------------------------------------------------- testCompositionTree
	#[DataProvider('provideCompositionTreeData')]
	public function testCompositionTree(string $index, array $components, array $expected) : void
	{
		$code   = '<?php interface I1 {} interface I2 {}'
			. ' trait T1 {} trait T2 {} #[ITRocks\Extend(T2::class) trait T3 {}]'
			. ' #[ITRocks\Extend\Implement(I2::class)] trait T4';
		$build  = $this->newBuild($code);
		$method = new ReflectionMethod(Build::class, 'compositionTree');
		/** @noinspection PhpUnhandledExceptionInspection Should not throw exception */
		$actual = $method->invoke($build, $components);
		static::assertEquals($expected, $actual, $index);
	}

	//------------------------------------------------------------------------ testIdentifyComponents
	/**
	 * @noinspection PhpDocMissingThrowsInspection
	 * @param list<string> $components
	 * @param array<value-of<Build::SIMPLE_COMPONENTS>,list<string>>|array<Build::T_TRAIT,list<list<string>>> $expected
	 */
	#[DataProvider('provideIdentifyComponentsData')]
	public function testIdentifyComponents(string $index, array $components, array $expected) : void
	{
		$build  = $this->newBuild('<?php interface I1 {} interface I2 {} trait T1 {} trait T2 {}');
		$method = new ReflectionMethod(Build::class, 'identifyComponents');
		/** @noinspection PhpUnhandledExceptionInspection Should not throw exception */
		$method->invoke($build, $components);
		$types    = new ReflectionProperty(Build::class, 'types');
		$actual   = $types->getValue($build);
		$expected = array_combine($components, $expected);
		static::assertEquals($expected, $actual, $index);
	}

	//------------------------------------------------------------------- testIdentifyComponentsTwice
	public function testIdentifyComponentsTwice() : void
	{
		$components = [
			"#[Display('a thing']", "#[Display('a thing']", "#[Displays('some things']",
			'@display a thing', '@display a thing', '@displays some things',
			'I1', 'I1', 'I2', 'T1', 'T2', 'T2'
		];
		$expected = [
			"#[Display('a thing']"      => T_ATTRIBUTE,
			"#[Displays('some things']" => T_ATTRIBUTE,
			'@display a thing'          => Build::T_ANNOTATION,
			'@displays some things'     => Build::T_ANNOTATION,
			'I1'                        => T_INTERFACE,
			'I2'                        => T_INTERFACE,
			'T1'                        => T_TRAIT,
			'T2'                        => T_TRAIT
		];
		$build = $this->newBuild('<?php interface I1; interface I2; trait T1 {} trait T2 {}');
		$method = new ReflectionMethod(Build::class, 'identifyComponents');
		/** @noinspection PhpUnhandledExceptionInspection Should not throw exception */
		$method->invoke($build, $components);
		$types    = new ReflectionProperty(Build::class, 'types');
		$actual   = $types->getValue($build);
		static::assertEquals($expected, $actual);
	}

	//--------------------------------------------------------------------------------- testImplement
	/**
	 * @noinspection PhpDocMissingThrowsInspection
	 * @param array<string,list<string>|string> $configuration <$class, <$component> | $replacement>
	 * @param array<string,string> $expected_files <$expected_file_name, $expected_content>
	 */
	#[DataProvider('provideImplementData')]
	public function testImplement(
		string $index, string $code, array $configuration, array $expected_files,
		bool $purge_configuration = true
	) : void
	{
		/** @noinspection PhpUnhandledExceptionInspection Must work */
		$build = $this->newBuild($code, $configuration, __DIR__);

		$cache_directory    = $build->getCacheDirectory();
		$configuration_file = $cache_directory . '/configuration.json';
		if (!file_exists($cache_directory)) {
			mkdir($cache_directory, 0700, true);
		}
		if (file_exists($configuration_file) && $purge_configuration) {
			unlink($configuration_file);
		}

		$build->implement();

		foreach ($expected_files as $file_name => $expected_content) {
			static::assertFileExists($cache_directory . '/' . $file_name, $index . ':file-exists');
			if (!file_exists($cache_directory . '/' . $file_name)) {
				continue;
			}
			$content = file_get_contents($cache_directory . '/' . $file_name);
			$content = str_replace(["\n", "\t"], ' ', trim(($content === false) ? '' : $content));
			while (str_contains($content, '  ')) {
				$content = str_replace('  ', ' ', $content);
			}
			static::assertEquals($expected_content, $content, $index . ':' . $file_name);
		}

		if ($configuration === []) {
			if ($purge_configuration) {
				static::assertFileDoesNotExist($configuration_file, 'cache-configuration-file');
			}
		}
		else {
			static::assertFileExists($configuration_file, 'cache-configuration-file');
		}
		if (file_exists($configuration_file)) {
			$content = file_get_contents($configuration_file);
			$content = ($content === false) ? [] : json_decode($content, true);
			static::assertEquals($configuration, $content, 'cache-configuration-content');
			if ($purge_configuration) {
				unlink($configuration_file);
			}
		}

		foreach (scandir($cache_directory) as $file_name) {
			if (in_array($file_name, ['.', '..'], true) || ($file_name === 'configuration.json')) {
				continue;
			}
			static::assertArrayHasKey($file_name, $expected_files, 'not-too-many-files');
			if ($purge_configuration) {
				unlink($cache_directory . '/' . $file_name);
			}
		}

		if ($purge_configuration) {
			rmdir($cache_directory);
			$home_directory = $build->class_index->getHome();
			rmdir($home_directory . '/cache');
			static::assertDirectoryDoesNotExist($home_directory . '/cache', 'trailing-files');
		}
	}

	//-------------------------------------------------------------------------- testImplementChanges
	public function testImplementChanges() : void
	{
		foreach (static::provideImplementData() as $arguments) {
			$arguments[] = false;
			$this->testImplement(...$arguments);
		}

		/** @noinspection PhpUnhandledExceptionInspection Must work */
		$build = $this->newBuild('', [], __DIR__);
		$cache_directory = $build->getCacheDirectory();
		foreach (scandir($cache_directory) as $file_name) {
			if (!in_array($file_name, ['.', '..'], true)) {
				unlink($cache_directory . '/' . $file_name);
			}
		}
		rmdir($cache_directory);
		rmdir($build->class_index->getHome() . '/cache');
	}

	//-------------------------------------------------------------------------------- testIsAbstract
	#[DataProvider('provideIsAbstractData')]
	public function testIsAbstract(string $index, bool $expected, string $code) : void
	{
		$build     = $this->newBuild($code);
		$token_key = array_search(
			[T_STRING, 'C', 1],
			$build->class_index->file_tokens['file.php'],
			true
		);
		static::assertNotFalse($token_key, $index . ':found-token');
		if ($token_key === false) {
			return;
		}
		$class_use = [
			T_CLASS     => 'C',
			T_TYPE      => T_DECLARE_CLASS,
			T_USE       => 'C',
			T_FILE      => 'file.php',
			T_LINE      => 1,
			T_TOKEN_KEY => $token_key
		];
		$method = new ReflectionMethod(Build::class, 'isAbstract');
		/** @noinspection PhpUnhandledExceptionInspection Should not throw exception */
		$actual = $method->invoke($build, $class_use);
		static::assertEquals($expected, $actual, $index);
	}

	//------------------------------------------------------------------------ testIsAbstractFromFile
	public function testIsAbstractFromFile() : void
	{
		$build     = new Build([], $this->newIndex());
		$line      = 0;
		$token_key = false;
		$content   = file_get_contents(__FILE__);
		$tokens    = token_get_all(($content === false) ? '' : $content);
		for ($key = count($tokens) - 1; $key > 0; $key --) {
			$token = $tokens[$key];
			if (is_array($token) && ($token[0] === T_STRING) && ($token[1] === 'Abstract_One')) {
				$line      = $token[2];
				$token_key = $key;
				break;
			}
		}
		static::assertNotEquals(0, $line, 'found-line');
		static::assertNotFalse($token_key, 'found-token');
		if ($token_key === false) {
			return;
		}
		$class_use = [
			T_CLASS     => Abstract_One::class,
			T_TYPE      => T_DECLARE_CLASS,
			T_USE       => Abstract_One::class,
			T_FILE      => substr(__FILE__, strpos(__FILE__, '/') + 1),
			T_LINE      => $line,
			T_TOKEN_KEY => $token_key
		];
		$method = new ReflectionMethod(Build::class, 'isAbstract');
		/** @noinspection PhpUnhandledExceptionInspection Should not throw exception */
		$actual = $method->invoke($build, $class_use);
		static::assertTrue($actual, 'is-abstract');
	}

	//---------------------------------------------------------------------------- testSortComponents
	public function testSortComponents() : void
	{
		$build = new Build([], $this->newIndex());
		$types = [
			"#[Display('a thing']"      => T_ATTRIBUTE,
			"#[Displays('some things']" => T_ATTRIBUTE,
			'@display a thing'          => Build::T_ANNOTATION,
			'@displays some things'     => Build::T_ANNOTATION,
			'I1'                        => T_INTERFACE,
			'I2'                        => T_INTERFACE,
			'T1'                        => T_TRAIT,
			'T2'                        => T_TRAIT
		];
		$types_property = new ReflectionProperty(Build::class, 'types');
		$types_property->setValue($build, $types);
		$method = new ReflectionMethod(Build::class, 'sortComponents');
		/** @noinspection PhpUnhandledExceptionInspection Should not throw exception */
		$actual   = $method->invoke($build, array_keys($types));
		$expected = [
			Build::T_ANNOTATION => ['@display a thing', '@displays some things'],
			T_ATTRIBUTE         => ["#[Display('a thing']", "#[Displays('some things']"],
			T_INTERFACE         => ['I1', 'I2'],
			T_TRAIT             => ['T1', 'T2']
		];
		static::assertEquals($expected, $actual);
	}

	//----------------------------------------------------------------------------- testTraitsByLevel
	#[TestWith(['simple',      ['T1', 'T2'], [['T1', 'T2']]])]
	#[TestWith(['ignore',      ['T1', 'T3'], [['T1', 'T3']]])]
	#[TestWith(['two',         ['T2', 'T3'], [['T2'], ['T3']]])]
	#[TestWith(['two-reverse', ['T3', 'T2'], [['T2'], ['T3']]])]
	#[TestWith(['multiple',    ['T1', 'T2', 'T3'], [['T1', 'T2'], ['T3']]])]
	#[TestWith(['several',     ['T1', 'T2', 'T3', 'T4'], [['T1', 'T2'], ['T3'], ['T4']]])]
	#[TestWith(['a-lot',       ['T1', 'T2', 'T3', 'T4', 'T5'], [['T1', 'T2'], ['T3'], ['T4', 'T5']]])]
	public function testTraitsByLevel(string $index, array $traits, array $expected) : void
	{
		$code   = '<?php trait T1 {} trait T2 {}'
			. ' #[ITRocks\Extend(T2::class)] trait T3 {}'
			. ' #[ITRocks\Extend(T3::class)] trait T4 {}'
			. ' #[ITRocks\Extend(T3::class)] trait T5 {}';
		$build  = $this->newBuild($code);
		$method = new ReflectionMethod(Build::class, 'traitsByLevel');
		/** @noinspection PhpUnhandledExceptionInspection Should not throw exception */
		$actual = $method->invoke($build, $traits);
		static::assertEquals($expected, $actual, $index);
	}

}

// phpcs:disable
abstract class Abstract_One { }
// phpcs:enable
