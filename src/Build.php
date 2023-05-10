<?php
namespace ITRocks;

use ITRocks\Build\Cache;
use ITRocks\Build\Implement;
use ITRocks\Build\Replace;
use ITRocks\Class_Use\Index;

class Build
{
	use Cache, Implement, Replace;

	//---------------------------------------------------------------------------------------- PREFIX
	const PREFIX = 'B';

	//---------------------------------------------------------------------------------- $class_index
	public Index $class_index;

	//-------------------------------------------------------------------------------- $configuration
	/** @var array<string,array<string>|string> <$class, <$component> | $replacement> */
	public array $configuration;

	//---------------------------------------------------------------------------------- $file_tokens
	/** @var array<string,array<int,array{int,string,int}|string>> */
	public array $file_tokens;

	//----------------------------------------------------------------------------------- __construct
	/**
	 * @param array<string,array<string>|string> $configuration
	 * @param array<int,string>                  $exclude
	 */
	public function __construct(array $configuration, Index $class_index, array $exclude = [])
	{
		if ($class_index->file_tokens === null) {
			$class_index->file_tokens = [];
		}
		$this->class_index   =  $class_index;
		$this->configuration =  $configuration;
		$this->exclude_files =  array_flip($exclude);
		$this->file_tokens   =& $class_index->file_tokens;
	}

}
