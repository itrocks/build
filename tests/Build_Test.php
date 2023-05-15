<?php
namespace ITRocks\Build;

use ITRocks\Build;
use ITRocks\Class_Use\Index;
use PHPUnit\Framework\TestCase;

class Build_Test extends TestCase
{

	//-------------------------------------------------------------------------------------- newIndex
	protected function newIndex() : Index
	{
		return new class extends Index {
			protected function prepareHome() : void {}
		};
	}

	//----------------------------------------------------------------------------- testConfiguration
	public function testConfiguration() : void
	{
		$build = new Build(['Origin' => 'Replacement'], $this->newIndex());
		static::assertEquals(['Origin' => 'Replacement'], $build->configuration);
	}

	//------------------------------------------------------------------------------ testExcludeFiles
	public function testExcludeFiles() : void
	{
		$build = new Build([], $this->newIndex(), ['file1.php', 'file2.php']);
		static::assertEquals(['file1.php' => 0, 'file2.php' => 1], $build->exclude_files);
	}

	//-------------------------------------------------------------------------------- testFileTokens
	public function testFileTokens() : void
	{
		$file_tokens        = ['file.php' => token_get_all('<?php echo "Hello World!";')];
		$index              = $this->newIndex();
		$index->file_tokens = $file_tokens;
		$build = new Build([], $index);
		static::assertEquals($file_tokens, $build->class_index->file_tokens);
	}

	//-------------------------------------------------------------------------------- testMinimalist
	public function testMinimalist() : void
	{
		$build = new Build([], $this->newIndex());
		static::assertEquals([], $build->class_index->file_tokens, 'file_tokens');
		static::assertEquals([], $build->configuration,            'configuration');
		static::assertEquals([], $build->exclude_files,            'exclude_files');
	}

}
