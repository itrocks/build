<?php
namespace ITRocks\Build;

trait Replace
{

	//-------------------------------------------------------------------------------- REPLACED_TYPES
	/** @var int[] */
	protected const REPLACED_TYPES = [T_ATTRIBUTE, T_CLASS, T_EXTENDS, T_NEW, T_STATIC, T_USE];

	//-------------------------------------------------------------------------------- $exclude_files
	/** @var array<string,int> <string $filename, int $key> Relative to the project home directory. */
	public array $exclude_files;

	//---------------------------------------------------------------------------------- $write_files
	/**
	 * @var array<string,array<array{int,string,int}|string>> Files tokens to write
	 * <string $filename, <{int $token_index, string $content, int $line}>|string $character>
	 */
	public array $write_files = [];

	//--------------------------------------------------------------------------------------- replace
	/** Replace references to original classes by reference to the replacement class */
	public function replace() : void
	{
		foreach ($this->configuration as $class => $replacement) {
			foreach (static::REPLACED_TYPES as $search[T_TYPE]) {
				$search[T_USE] = $class;
				$class_uses    = $this->class_index->search($search, true);
				if ($class_uses === []) {
					continue;
				}
				if (is_array($replacement)) {
					$replacement = $class . '\\B';
				}
				foreach ($class_uses as $class_use) {
					if (
						($search[T_TYPE] === T_EXTENDS)
						&& in_array($class_use[T_CLASS], $this->configuration, true)
					) {
						continue;
					}
					/** @var string $file phpstan fault */
					$file = $class_use[T_FILE];
					if (isset($this->exclude_files[$file])) {
						continue;
					}
					if (!isset($this->class_index->file_tokens[$file])) {
						$file_content = file_get_contents($file);
						if ($file_content === false) $file_content = '';
						$this->class_index->file_tokens[$file] = token_get_all($file_content);
					}
					if (!isset($this->write_files[$file])) {
						$this->write_files[$file] =& $this->class_index->file_tokens[$file];
					}
					/** @var int $line phpstan fault */
					$line = $class_use[T_LINE];
					$this->write_files[$file][$class_use[T_TOKEN_KEY]] = [
						T_NAME_FULLY_QUALIFIED,
						'\\' . $replacement,
						$line
					];
				}
			}
		}
		foreach ($this->write_files as $file => $tokens) {
			$buffer = '';
			foreach ($tokens as $token) {
				$buffer .= is_string($token) ? $token : $token[1];
			}
			$file = $this->getCacheDirectory() . '/build/'
				. str_replace(['/', '\\'], '-', substr($file, 0, -4));
			file_put_contents($file, $buffer);
		}
	}

}
