<?php
namespace ITRocks\Build;

trait Cache
{

	//------------------------------------------------------------------------------- CACHE_DIRECTORY
	const CACHE_DIRECTORY = '/cache/build';

	//----------------------------------------------------------------------------- getCacheDirectory
	public function getCacheDirectory() : string
	{
		return $this->class_index->getHome() . static::CACHE_DIRECTORY;
	}

	//--------------------------------------------------------------------------------------- prepare
	public function prepare() : void
	{
		$directory = $this->getCacheDirectory();
		if (!is_dir($directory)) {
			mkdir($directory, 0777, true);
		}
	}

}
