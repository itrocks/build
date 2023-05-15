<?php
namespace ITRocks\Build;

use ITRocks\Build;
use ITRocks\Class_Use\Index;
use PHPUnit\Framework\TestCase;

class Cache_Test extends TestCase
{

	//--------------------------------------------------------------------------- purgeCacheDirectory
	protected function purgeCacheDirectory() : void
	{
		foreach (['/cache/build', '/cache/class-use', '/cache'] as $directory) {
			if (is_dir(__DIR__ . $directory)) {
				rmdir(__DIR__ . $directory);
			}
		}
	}

	//------------------------------------------------------------------------- testGetCacheDirectory
	public function testGetCacheDirectory() : void
	{
		/** @noinspection PhpUnhandledExceptionInspection Must have write access to current directory */
		$build = new Build([], new Index(0, __DIR__));
		$this->purgeCacheDirectory();
		static::assertEquals(__DIR__ . '/cache/build', $build->getCacheDirectory());
	}

	//------------------------------------------------------------------------ testPrepareExistingDir
	public function testPrepareExistingDir() : void
	{
		mkdir(__DIR__ . '/cache/build', 0777, true);
		/** @noinspection PhpUnhandledExceptionInspection Must have write access to current directory */
		(new Build([], new Index(0, __DIR__)))->prepare();
		static::assertTrue(is_dir(__DIR__ . '/cache'));
		static::assertTrue(is_dir(__DIR__ . '/cache/build'));
		$this->purgeCacheDirectory();
	}
	
	//----------------------------------------------------------------------------- testPrepareNewDir
	public function testPrepareNewDir() : void
	{
		/** @noinspection PhpUnhandledExceptionInspection Must have write access to current directory */
		(new Build([], new Index(0, __DIR__)))->prepare();
		static::assertTrue(is_dir(__DIR__ . '/cache'));
		static::assertTrue(is_dir(__DIR__ . '/cache/build'));
		$this->purgeCacheDirectory();
	}

}
