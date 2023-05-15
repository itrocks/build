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
			if ($counter > 0) {
				$data .= ',';
			}
			$counter ++;
			$data .= "$class::class=>";
			if (is_string($replacement)) {
				$data .= "$replacement::class";
			}
			else {
				$data .= '[';
				$first = true;
				foreach ($replacement as $interface_trait) {
					if ($first) {
						$first = false;
					}
					else {
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
