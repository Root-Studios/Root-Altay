<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\inventory\transaction;

use pocketmine\crafting\SmithingRecipe;
use pocketmine\player\Player;
use function count;

final class SmithingTransaction extends InventoryTransaction{
	public function __construct(Player $source, private readonly SmithingRecipe $recipe, array $actions = []){
		parent::__construct($source, $actions);
	}

	public function validate() : void{
		if(count($this->actions) < 1){ throw new TransactionValidationException("Smithing transaction has no actions"); }
		$inputs = $outputs = [];
		$this->matchItems($outputs, $inputs);
		if(count($inputs) !== 3 || count($outputs) !== 1){
			throw new TransactionValidationException("Smithing requires exactly 3 inputs and 1 output");
		}
		$expected = $this->recipe->getResultFor($inputs);
		if($expected === null || !$expected->equalsExact($outputs[0])){
			throw new TransactionValidationException("Smithing output does not match the selected recipe");
		}
	}
}
