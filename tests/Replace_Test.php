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

	//------------------------------------------------------------------- provideReplaceAttributeData
	/** @return array<array{string,string,string}> */
	public static function provideReplaceAttributeData() : array
	{
		return [
			[
				'T_ARGUMENT-no',
				'<?php function f(Origin $a, Lambda $l) {}',
				'<?php function f(Origin $a, Lambda $l) {}',
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
				'<?php \Replacement::class; Lambda::class;',
			],
			[
				'T_EXTENDS',
				'<?php class C extends Origin, Lambda {}',
				'<?php class C extends \Replacement, Lambda {}'
			],
			[
				'T_NEW',
				'<?php new Origin; new Lambda;',
				'<?php new \Replacement; new Lambda;',
			],
			[
				'T_STATIC',
				'<?php Origin::CONSTANT; Lambda::CONSTANT;',
				'<?php \Replacement::CONSTANT; Lambda::CONSTANT;'
			],
			[
				'T_USE',
				'<?php class C { use Origin, Lambda; }',
				'<?php class C { use \Replacement, Lambda; }',
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

	//-------------------------------------------------------------------------- testReplaceAttribute
	#[DataProvider('provideReplaceAttributeData')]
	public function testReplaceAttribute(string $index, string $code, string $expected) : void
	{
		$tokens = token_get_all($code);
		$build = new Build(['Origin' => 'Replacement'], new class(0, __DIR__) extends Index {
			protected function prepareHome(): void {}
		});
		$build->class_index->file_tokens['file.php'] =& $tokens;
		$build->class_index->scanFile(__DIR__ . '/file.php');
		$build->class_index->classify();
		$build->replace();
		$buffer = '';
		foreach ($tokens as $token) {
			$buffer .= is_string($token) ? $token : $token[1];
		}
		static::assertEquals($expected, $buffer, $index);
	}

}
