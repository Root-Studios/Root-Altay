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

namespace pocketmine\item\fishing;

use PHPUnit\Framework\TestCase;
use pocketmine\item\VanillaItems;
use pocketmine\utils\Random;

final class FishingLootTableTest extends TestCase{
	public function testLuckChangesCategoryChances() : void{
		$normal = FishingLootTable::getCategoryChances(0);
		$lucky = FishingLootTable::getCategoryChances(3);

		self::assertEqualsWithDelta(1.0, array_sum($normal), 0.000001);
		self::assertEqualsWithDelta(1.0, array_sum($lucky), 0.000001);
		self::assertGreaterThan($normal[FishingLootTable::CATEGORY_TREASURE], $lucky[FishingLootTable::CATEGORY_TREASURE]);
		self::assertLessThan($normal[FishingLootTable::CATEGORY_JUNK], $lucky[FishingLootTable::CATEGORY_JUNK]);
	}

	public function testFishPoolContainsCodAndTropicalFish() : void{
		$random = new Random(123456);
		$codFound = false;
		$tropicalFishFound = false;
		for($i = 0; $i < 10000 && (!$codFound || !$tropicalFishFound); ++$i){
			$item = FishingLootTable::roll($random, 0);
			$codFound = $codFound || $item->getTypeId() === VanillaItems::RAW_FISH()->getTypeId();
			$tropicalFishFound = $tropicalFishFound || $item->getTypeId() === VanillaItems::CLOWNFISH()->getTypeId();
		}

		self::assertTrue($codFound, "Cod was not selectable from the fishing table");
		self::assertTrue($tropicalFishFound, "Tropical fish was not selectable from the fishing table");
	}
}
