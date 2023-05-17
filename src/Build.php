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
	/** @var array<string,list<string>|string> <$class, <$component> | $replacement> */
	public array $configuration;

	//----------------------------------------------------------------------------------- __construct
	/**
	 * @param array<string,list<string>|string> $configuration
	 * @param list<string>                      $exclude_files
	 */
	public function __construct(array $configuration, Index $class_index, array $exclude_files = [])
	{
		if ($class_index->file_tokens === null) {
			$class_index->file_tokens = [];
		}
		$this->class_index   = $class_index;
		$this->configuration = $configuration;
		$this->exclude_files = array_flip($exclude_files);
	}

}
