<?php
namespace ITRocks\Build\Examples;

use Attribute;
use ITRocks\Build;
use ITRocks\Class_Use\Index;
use ITRocks\Extend;
use ITRocks\Extend\Implement;
use ReflectionClass;

include __DIR__ . '/autoload.php';

// phpcs:disable
#[Attribute]
class An_Attribute { public function __construct(string $class) {} }

interface An_Interface
{
	public function calculate();
}

#[Extend(Has_Code::class), Implement(An_Interface::class)]
trait Calculate_Code
{
	public function calculate() : string
	{
		return uniqid();
	}
}

#[Extend(Calculate_Code::class)]
trait Calculate_Code_2
{
	public function calculate() : string
	{
		/** @noinspection PhpUndefinedClassInspection #Extend */
		return parent::calculate(). '-2';
	}
}

class ExtendedReflectionClass extends ReflectionClass
{}

#[Extend(Item::class)] // Item will not be replaced (#Extend and #Implement protect class names)
trait Has_Code
{
	public string $code;
}

class Item
{
	#[An_Attribute(User::class)] // User will be replaced
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
// phpcs:enable

$class = new ReflectionClass(User::class);
$item  = new Item;
$user  = new User;

$configuration = include(__DIR__ . '/configuration.php');

$class_index = new Index(Index::RESET, __DIR__);
$class_index->keepFileTokens();
$class_index->update();

$build = new Build($configuration, $class_index, ['configuration.php']);
$build->prepare();
$build->implement();
$build->replace();
$build->save();

echo "\nReplacement class for Calculate_Code :\n\n";
readfile(__DIR__ . '/cache/build/ITRocks-Build-Examples-Calculate_Code-B');
echo "\nReplacement class for Item :\n\n";
readfile(__DIR__ . '/cache/build/ITRocks-Build-Examples-Item-B');
echo "\nReplacement code for " . __FILE__ . " :\n\n";
readfile(__DIR__ . '/cache/build/complete');
