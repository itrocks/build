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
	/** @var (string|string[])[] [string $class => string $replacement | [string $interface_trait]] */
	public array $configuration;

	//---------------------------------------------------------------------------------- $file_tokens
	/** @var ((int|string)[]|string)[] */
	public array $file_tokens;

	//----------------------------------------------------------------------------------- __construct
	public function __construct(array $configuration, Index $class_index, array $exclude = [])
	{
		$this->class_index   = $class_index;
		$this->configuration = $configuration;
		$this->exclude_files = array_flip($exclude);
		$this->file_tokens   = $class_index->file_tokens;
	}

}
