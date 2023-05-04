<?php

use ITRocks\Build\Examples\Calculate_Code;
use ITRocks\Build\Examples\Calculate_Code_2;
use ITRocks\Build\Examples\Final_User;
use ITRocks\Build\Examples\Has_Code;
use ITRocks\Build\Examples\Item;
use ITRocks\Build\Examples\User;

return [
	Calculate_Code::class => [Calculate_Code_2::class],
	Item::class           => [Calculate_Code::class, Has_Code::class],
	User::class           => Final_User::class
];
