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

namespace pocketmine\item;

use PHPUnit\Framework\TestCase;

final class BoatTypeTest extends TestCase{
	public function testNetworkVariantsRoundTrip() : void{
		foreach(BoatType::cases() as $type){
			self::assertSame($type, BoatType::fromNetworkVariant($type->getNetworkVariant()));
		}
		self::assertNull(BoatType::fromNetworkVariant(-1));
	}

	public function testBambooUsesRaftNames() : void{
		self::assertSame("bamboo_raft", BoatType::BAMBOO->getItemName());
		self::assertSame("bamboo_chest_raft", BoatType::BAMBOO->getItemName(true));
		self::assertSame("Bamboo Raft", BoatType::BAMBOO->getVehicleDisplayName());
		self::assertSame("Bamboo Raft with Chest", BoatType::BAMBOO->getVehicleDisplayName(true));
	}
}
