<?php
namespace ITRocks\Build;

use ITRocks\Build;
use ITRocks\Class_Use\Index;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class Replace_Test extends TestCase
{

	//----------------------------------------------------------------------------------- DEFINITIONS
	const DEFINITIONS = '<?php class Origin{} class Replacement{}';

	//--------------------------------------------------------------------------------- buildToBuffer
	/**
	 * @param string        $code If empty: __DIR__ . '/file.php' will be read
	 * @param callable|null $init If set: call the callback Build init function with parameter Build
	 */
	protected function buildToBuffer(
		string $code, array $configuration, bool $replaced, callable $init = null
	) : string
	{
		$tokens = token_get_all($code);
		$build  = new Build($configuration, new class(0, __DIR__) extends Index {
			protected function prepareHome(): void { }
		});
		if ($tokens !== []) {
			$build->class_index->file_tokens['file.php'] = $tokens;
		}
		if ($init !== null) {
			call_user_func($init, $build);
		}
		$build->class_index->scanFile(__DIR__ . '/file.php');
		$build->class_index->classify();
		if ($tokens === []) {
			$build->class_index->file_tokens = [];
		}
		$build->replace();
		if ($replaced) {
			static::assertArrayHasKey('file.php', $build->write_files);
		}
		else {
			static::assertArrayNotHasKey('file.php', $build->write_files);
		}
		$buffer = '';
		$tokens = $build->write_files['file.php'] ?? $build->class_index->file_tokens['file.php'];
		foreach ($tokens as $token) {
			$buffer .= is_string($token) ? $token : $token[1];
		}
		return $buffer;
	}

	//----------------------------------------------------------------------- provideReplaceCasesData
	/** @return array<array{string,string,string}> */
	public static function provideReplaceCasesData() : array
	{
		return [
			[
				'T_ARGUMENT-no',
				'<?php function f(Origin $a, Lambda $l) {}',
				'<?php function f(Origin $a, Lambda $l) {}'
			],
			[
				'T_ATTRIBUTE',
				'<?php #[Origin, Lambda] class C() {}',
				'<?php #[\Replacement, Lambda] class C() {}'
			],
			[
				'T_ATTRIBUTE_CLASS',
				'<?php #[ITRocks\Extend(Origin::class), Lambda(Origin::class)] class C() {}',
				'<?php #[ITRocks\Extend(Origin::class), Lambda(\Replacement::class)] class C() {}'
			],
			[
				'T_CLASS',
				'<?php Origin::class; Lambda::class;',
				'<?php \Replacement::class; Lambda::class;'
			],
			[
				'T_EXTENDS',
				'<?php class C extends Origin, Lambda {}',
				'<?php class C extends \Replacement, Lambda {}'
			],
			[
				'T_EXTENDS_REPLACEMENT',
				'<?php class Replacement extends Origin, Lambda {}',
				'<?php class Replacement extends Origin, Lambda {}'
			],
			[
				'T_NEW',
				'<?php new Origin; new Lambda;',
				'<?php new \Replacement; new Lambda;'
			],
			[
				'T_NEW_PARENTHESIS',
				'<?php new Origin(15); new Lambda(18);',
				'<?php new \Replacement(15); new Lambda(18);'
			],
			[
				'T_STATIC',
				'<?php Origin::CONSTANT; Lambda::CONSTANT;',
				'<?php \Replacement::CONSTANT; Lambda::CONSTANT;'
			],
			[
				'T_USE',
				'<?php class C { use Origin, Lambda; }',
				'<?php class C { use \Replacement, Lambda; }'
			],
			[
				'T_DECLARE_CLASS-no',
				'<?php class Origin {} class Lambda {}',
				'<?php class Origin {} class Lambda {}'
			],
			[
				'T_DECLARE_TRAIT-no',
				'<?php interface Origin {} interface Lambda {}',
				'<?php interface Origin {} interface Lambda {}'
			],
			[
				'T_DECLARE_TRAIT-no',
				'<?php trait Origin {} trait Lambda {}',
				'<?php trait Origin {} trait Lambda {}'
			],
		];
	}

	//------------------------------------------------------------------------------- testExcludeFile
	public function testExcludeFile() : void
	{
		$code     = '<?php new Origin';
		$replaced = '<?php new \Replacement';
		$cases    = [
			$code     => function(Build $build) { $build->exclude_files['file.php'] = 0; },
			$replaced => null
		];
		foreach ($cases as $expected => $init) {
			$actual = $this->buildToBuffer($code, ['Origin' => 'Replacement'], !isset($init), $init);
			static::assertEquals($expected, $actual, isset($init) ? 'exclusion' : 'no-exclusion');
		}
	}

	//---------------------------------------------------------------------------------- testFromFile
	public function testFromFile() : void
	{
		$code     = '<?php echo Origin::CONSTANT; echo Lambda::CONSTANT';
		$expected = '<?php echo \Replacement::CONSTANT; echo Lambda::CONSTANT';
		file_put_contents(__DIR__ . '/file.php', $code);
		$actual = $this->buildToBuffer('', ['Origin' => 'Replacement'], true);
		unlink(__DIR__ . '/file.php');
		static::assertEquals($expected, $actual);
	}

	//------------------------------------------------------------------------------------ testNoSave
	public function testNoSave() : void
	{
		/** @var Build $build */
		$this->buildToBuffer(
			'<?php $some_code;',
			['Origin' => 'Replacement'],
			false,
			function(Build $source_build) use(&$build) { $build = $source_build; }
		);
		mkdir(__DIR__ . '/cache');
		mkdir(__DIR__ . '/cache/build');
		$build->save();
		static::assertFalse(is_file(__DIR__ . '/cache/build/file.php'));
		rmdir(__DIR__ . '/cache/build');
		rmdir(__DIR__ . '/cache');
	}

	//------------------------------------------------------------------------------------- testNoUse
	#[DataProvider('provideReplaceCasesData')]
	public function testNoUse(string $index, string $code) : void
	{
		$actual = $this->buildToBuffer($code, ['Maybe' => 'Not'], false);
		static::assertEquals($code, $actual, $index);
	}

	//------------------------------------------------------------------------------ testReplaceCases
	#[DataProvider('provideReplaceCasesData')]
	public function testReplaceCases(string $index, string $code, string $expected) : void
	{
		$actual = $this->buildToBuffer($code, ['Origin' => 'Replacement'], $expected !== $code);
		static::assertEquals($expected, $actual, $index);
	}

	//-------------------------------------------------------------------------- testReplacementArray
	public function testReplacementArray() : void
	{
		$actual = $this->buildToBuffer(
			'<?php namespace N; new Origin;', ['N\Origin' => ['Trait']], true
		);
		$expected = '<?php namespace N; new \N\Origin\B;';
		static::assertEquals($expected, $actual);
	}

	//-------------------------------------------------------------------------------------- testSave
	public function testSave() : void
	{
		/** @var Build $build */
		$actual = $this->buildToBuffer(
			'<?php new Origin();',
			['Origin' => 'Replacement'],
			true,
			function(Build $source_build) use(&$build) { $build = $source_build; }
		);
		mkdir(__DIR__ . '/cache');
		mkdir(__DIR__ . '/cache/build');
		$build->save();
		static::assertTrue(is_file(__DIR__ . '/cache/build/file'));
		unlink(__DIR__ . '/cache/build/file');
		rmdir(__DIR__ . '/cache/build');
		rmdir(__DIR__ . '/cache');
	}

}
