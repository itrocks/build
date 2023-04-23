<?php
namespace ITRocks\Build;

trait Cache
{

	//------------------------------------------------------------------------------- CACHE_DIRECTORY
	const CACHE_DIRECTORY = '/cache';

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
			mkdir($directory);
		}
		if (!is_dir("$directory/build")) {
			mkdir("$directory/build");
		}
	}

	//-------------------------------------------------------------------- saveCacheConfigurationFile
	protected function saveCacheConfigurationFile(string $file) : void
	{
		$data    = '<?php return [';
		$counter = 0;
		foreach ($this->configuration as $class => $replacement) {
			if ($counter ++) {
				$data .= ',';
			}
			$data .= "$class::class=>";
			if (is_string($replacement)) {
				$data .= "$replacement::class";
			}
			else {
				$data .= '[';
				foreach ($replacement as $key => $interface_trait) {
					if ($key) {
						$data .= ',';
					}
					$data .= "$interface_trait::class";
				}
				$data .= ']';
			}
		}
		$data .= '];';
		file_put_contents($file, $data);
	}

}
