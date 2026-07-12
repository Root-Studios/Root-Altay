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

namespace pocketmine\crafting;

use pocketmine\item\Item;

final class SmithingTransformRecipe implements SmithingRecipe{
	private Item $result;
	public function __construct(
		private readonly RecipeIngredient $input,
		private readonly RecipeIngredient $addition,
		private readonly RecipeIngredient $template,
		Item $result
	){ $this->result = clone $result; }

	public function getInput() : RecipeIngredient{ return $this->input; }
	public function getAddition() : RecipeIngredient{ return $this->addition; }
	public function getTemplate() : RecipeIngredient{ return $this->template; }
	public function getResult() : Item{ return clone $this->result; }
	public function getResultFor(array $inputs) : ?Item{
		foreach($inputs as $item){
			if($this->input->accepts($item)){
				$result = $this->getResult();
				$result->setNamedTag(clone $item->getNamedTag());
				return $result;
			}
		}
		return null;
	}
}
