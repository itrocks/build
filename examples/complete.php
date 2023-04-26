<?php
namespace ITRocks\Build\Examples;

use ITRocks\Build;
use ITRocks\Class_Use\Index;
use ITRocks\Extend;
use ITRocks\Extend\Implement;

include __DIR__ . '/autoload.php';

interface An_Interface
{
	public function calculate();
}

#[Extend(Has_Code::class), Implement(An_Interface::class)]
trait Calculate_Code
{
	public function calculate()
	{
		$this->code = uniqid();
	}
}

trait Calculate_Code_2
{
	public function calculate()
	{
		$this->code = parent::calculate(). '-2';
	}
}

trait Has_Code
{
	public string $code;
}

class Item
{
	public string $name;
}

class User
{
	public string $login;
}

class Final_User extends User
{
}

trait Other_Trait
{
	use Calculate_Code;
}

class Other_Item extends Item
{
}

class Other_User extends User
{
}

$item = new Item;
$user = new User;

$configuration = [
	Calculate_Code::class => [Calculate_Code_2::class],
	Item::class => [Calculate_Code::class, Has_Code::class],
	User::class => Final_User::class
];

$class_index = new Index(Index::RESET, __DIR__);
$class_index->keepFileTokens();
$class_index->update();

$build = new Build($configuration, $class_index);
$build->prepare();
$build->implement();
$build->replace();

echo "\nReplacement class for Calculate_Code :\n\n";
readfile(__DIR__ . '/cache/build/ITRocks-Build-Examples-Calculate_Code-B');
echo "\nReplacement class for Item :\n\n";
readfile(__DIR__ . '/cache/build/ITRocks-Build-Examples-Item-B');
