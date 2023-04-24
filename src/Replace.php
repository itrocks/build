<?php
namespace ITRocks\Build;

use ITRocks\Class_Use\Repository\Type as Index;
use ITRocks\Class_Use\Tokens_Scanner\Type as Token;

trait Replace
{

	//-------------------------------------------------------------------------------- REPLACED_TYPES
	const REPLACED_TYPES = [
		Token::ATTRIBUTE, Token::CLASS_, Token::EXTENDS, Token::NEW, Token::STATIC, Token::USE
	];

	//---------------------------------------------------------------------------------- $write_files
	/**
	 * @var (array|string)[][]
	 *      ([int $token, string $content, int $line]|string $content)[string $filename][]
	 */
	public array $write_files = [];

	//--------------------------------------------------------------------------------------- replace
	/** Replace references to original classes by reference to the replacement class */
	public function replace() : void
	{
		foreach ($this->configuration as $class => $replacement) {
			foreach (static::REPLACED_TYPES as $search[Index::TYPE]) {
				$search[Index::USE] = $class;
				$class_uses         = $this->class_index->search($search, true);
				if (!$class_uses) {
					continue;
				}
				if (is_array($replacement)) {
					$replacement = $class . '\\B';
				}
				foreach ($class_uses as $class_use) {
					if (
						($search[Index::TYPE] === Token::EXTENDS)
						&& in_array($class_use[Index::CLASS_], $this->configuration)
					) {
						continue;
					}
					$filename = $class_use[Index::FILE];
					if (!isset($this->class_index->file_tokens[$filename])) {
						$this->class_index->file_tokens[$filename]
							= token_get_all(file_get_contents($filename), TOKEN_PARSE);
					}
					if (!isset($this->write_files[$filename])) {
						$this->write_files[$filename] =& $this->class_index->file_tokens[$filename];
					}
					$this->write_files[$filename][$class_use[Index::TOKEN_KEY]] = [
						T_NAME_FULLY_QUALIFIED,
						'\\' . $replacement,
						$class_use[Index::LINE]
					];
				}
			}
		}
		foreach ($this->write_files as $filename => $tokens) {
			$buffer = '';
			foreach ($tokens as $token) {
				$buffer .= is_string($token) ? $token : $token[1];
			}
			$filename = $this->getCacheDirectory() . '/build/'
				. str_replace(['/', '\\'], '-', substr($filename, 0, -4));
			file_put_contents($filename, $buffer);
		}
	}

}
