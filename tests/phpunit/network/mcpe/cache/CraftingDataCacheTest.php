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

namespace pocketmine\network\mcpe\cache;

use PHPUnit\Framework\TestCase;
use pocketmine\crafting\CraftingManager;
use pocketmine\crafting\ExactRecipeIngredient;
use pocketmine\crafting\ShapelessRecipe;
use pocketmine\crafting\ShapelessRecipeType;
use pocketmine\item\VanillaItems;
use pocketmine\timings\Timings;

class CraftingDataCacheTest extends TestCase{

	public static function setUpBeforeClass() : void{
		Timings::init();
	}

	public function testRecipeNetworkIdsMapBackToManagerIndexes() : void{
		$manager = new CraftingManager();
		$manager->registerShapelessRecipe(new ShapelessRecipe(
			[new ExactRecipeIngredient(VanillaItems::COAL())],
			[VanillaItems::DIAMOND()->setCount(9)],
			ShapelessRecipeType::CRAFTING
		));
		$manager->registerShapelessRecipe(new ShapelessRecipe(
			[new ExactRecipeIngredient(VanillaItems::DIAMOND())],
			[VanillaItems::EMERALD()],
			ShapelessRecipeType::CRAFTING
		));

		$packet = CraftingDataCache::getInstance()->getCache($manager);
		self::assertCount(2, $packet->recipesWithTypeIds);
		foreach($packet->recipesWithTypeIds as $index => $recipe){
			$networkId = $recipe->getRecipeNetId();
			self::assertSame($index + CraftingDataCache::RECIPE_ID_OFFSET, $networkId);
			self::assertSame($manager->getCraftingRecipeFromIndex($index), $manager->getCraftingRecipeFromIndex($networkId - CraftingDataCache::RECIPE_ID_OFFSET));
		}
	}
}
