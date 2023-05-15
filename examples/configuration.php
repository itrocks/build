<?php
namespace ITRocks\Build\Examples;

use ReflectionClass;

return [
	Calculate_Code::class  => [Calculate_Code_2::class],
	Item::class            => [Calculate_Code::class, Has_Code::class],
	ReflectionClass::class => ExtendedReflectionClass::class,
	User::class            => Final_User::class
];
