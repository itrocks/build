<?php
namespace ITRocks\Build;

use ITRocks\Extend;

trait Replace
{

	//------------------------------------------------------------------------ EXCLUDE_ATTRIBUTE_USES
	/** @var list<string> Class uses into these attributes are not replaced */
	protected const EXCLUDE_ATTRIBUTE_USES = [Extend::class, Implement::class];

	//-------------------------------------------------------------------------------- REPLACED_TYPES
	/** @var list<int> Only these class use types are replaced */
	protected const REPLACED_TYPES = [T_ATTRIBUTE, T_CLASS, T_EXTENDS, T_NEW, T_STATIC, T_USE];

	//-------------------------------------------------------------------------------- $exclude_files
	/** @var array<string,int> <string $filename, int $key> Relative to the project home directory. */
	public array $exclude_files;

	//---------------------------------------------------------------------------------- $write_files
	/**
	 * @var array<string,array<int,array{int,string,int}|string>> Files tokens to write
	 * <string $filename, <{int $token_index, string $content, int $line}>|string $character>
	 */
	public array $write_files = [];

	//--------------------------------------------------------------------------------------- replace
	/** Replace references to original classes by reference to the replacement class */
	public function replace() : void
	{
		$search = [];
		foreach ($this->configuration as $class => $replacement) {
			$search[T_USE] = $class;
			$class_uses    = $this->class_index->search($search, true);
			if ($class_uses === []) {
				continue;
			}
			if (is_array($replacement)) {
				$replacement = $class . '\\B';
			}
			foreach ($class_uses as $class_use) {
				$type = $class_use[T_TYPE];
				if (
					(is_int($type) && !in_array($type, static::REPLACED_TYPES, true))
					|| (is_string($type) && in_array($type, static::EXCLUDE_ATTRIBUTE_USES, true))
				) {
					continue;
				}
				if (($type === T_EXTENDS) && in_array($class_use[T_CLASS], $this->configuration, true)) {
					continue;
				}
				/** @var string $file phpstan fault */
				$file = $class_use[T_FILE];
				if (isset($this->exclude_files[$file])) {
					continue;
				}
				if (!isset($this->class_index->file_tokens[$file])) {
					$file_content = file_get_contents($this->class_index->getHome() . '/' . $file);
					if ($file_content === false) $file_content = '';
					$this->class_index->file_tokens[$file] = token_get_all($file_content);
				}
				if (!isset($this->write_files[$file])) {
					$this->write_files[$file] =& $this->class_index->file_tokens[$file];
				}
				/** @var int $line phpstan fault */
				$line = $class_use[T_LINE];
				/** @var int $token_key phpstan fault */
				$token_key = $class_use[T_TOKEN_KEY];
				$this->write_files[$file][$token_key] = [
					T_NAME_FULLY_QUALIFIED,
					'\\' . $replacement,
					$line
				];
			}
		}
	}

	//------------------------------------------------------------------------------------------ save
	public function save() : void
	{
		foreach ($this->write_files as $file => $tokens) {
			$buffer = '';
			foreach ($tokens as $token) {
				$buffer .= is_string($token) ? $token : $token[1];
			}
			$file = $this->getCacheDirectory() . '/'
				. str_replace(['/', '\\'], '-', substr($file, 0, -4));
			file_put_contents($file, $buffer);
		}
	}

}
