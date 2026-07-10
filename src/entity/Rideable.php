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

namespace pocketmine\entity;

use pocketmine\entity\utils\RidingTrait;
use pocketmine\player\Player;

abstract class Rideable extends Entity implements RideableEntity{
	use RidingTrait;

	public static function dismountFrom(Player $player, bool $syncPosition = true) : void{
		foreach($player->getWorld()->getNearbyEntities($player->getBoundingBox()->expandedCopy(4.0, 4.0, 4.0)) as $entity){
			if($entity instanceof RideableEntity){
				if($entity->getRider() === $player){
					$entity->dismountRider(syncPosition: $syncPosition);
				}elseif($entity->getPassenger() === $player){
					$entity->dismountPassenger($syncPosition);
				}
			}
		}
	}
}
