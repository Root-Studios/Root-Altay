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

namespace pocketmine\entity\utils;

use pocketmine\inventory\ArmorInventory;
use pocketmine\math\Vector3;
use function abs;

final class ArmorStandEquipmentSlotFinder{
	private function __construct(){
		//NOOP
	}

	public static function findArmorSlot(Vector3 $offset) : int{
		return match(true){
			$offset->y >= 1.6 => ArmorInventory::SLOT_HEAD,
			$offset->y >= 0.9 => ArmorInventory::SLOT_CHEST,
			$offset->y >= 0.4 => ArmorInventory::SLOT_LEGS,
			default => ArmorInventory::SLOT_FEET
		};
	}

	public static function isMainHand(Vector3 $offset) : bool{
		return $offset->y >= 0.8 && $offset->y <= 1.6 && abs($offset->x) >= 0.25;
	}
}
