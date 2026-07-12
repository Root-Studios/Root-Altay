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

use pocketmine\data\bedrock\ArmorTrimMaterialTypeIdMap;
use pocketmine\data\bedrock\ArmorTrimPatternTypeIdMap;
use pocketmine\item\Armor;
use pocketmine\item\ArmorTrim;

final class SmithingTrimRecipe implements SmithingRecipe{
	public function __construct(
		private readonly RecipeIngredient $input,
		private readonly RecipeIngredient $addition,
		private readonly RecipeIngredient $template
	){}
	public function getInput() : RecipeIngredient{ return $this->input; }
	public function getAddition() : RecipeIngredient{ return $this->addition; }
	public function getTemplate() : RecipeIngredient{ return $this->template; }
	public function getResultFor(array $inputs) : ?Armor{
		$armor = $materialItem = $templateItem = null;
		foreach($inputs as $item){
			if($item instanceof Armor && $this->input->accepts($item)){ $armor = $item; }
			elseif($this->addition->accepts($item)){ $materialItem = $item; }
			elseif($this->template->accepts($item)){ $templateItem = $item; }
		}
		if($armor === null || $materialItem === null || $templateItem === null){ return null; }
		$material = ArmorTrimMaterialTypeIdMap::getInstance()->fromItem($materialItem);
		$pattern = ArmorTrimPatternTypeIdMap::getInstance()->fromItem($templateItem);
		return $material !== null && $pattern !== null ? (clone $armor)->setTrim(new ArmorTrim($material, $pattern)) : null;
	}
}
