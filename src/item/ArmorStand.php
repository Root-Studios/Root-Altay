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

use pocketmine\block\Block;
use pocketmine\entity\ArmorStand as ArmorStandEntity;
use pocketmine\entity\Location;
use pocketmine\event\player\PlayerPlaceArmorStandEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ArmorStandPlaceSound;
use function count;
use function fmod;
use function round;

class ArmorStand extends Item{
	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if($face !== Facing::UP || !$blockClicked->getSupportType(Facing::UP)->hasCenterSupport()){
			return ItemUseResult::NONE;
		}

		$world = $blockReplace->getPosition()->getWorld();
		$spawnPos = $blockReplace->getPosition()->add(0.5, 0.0, 0.5);
		$boundingBox = (new AxisAlignedBB(-0.25, 0.0, -0.25, 0.25, 1.975, 0.25))
			->offset($spawnPos->x, $spawnPos->y, $spawnPos->z);
		if(count($world->getCollidingEntities($boundingBox)) > 0 || count($world->getBlockCollisionBoxes($boundingBox)) > 0){
			return ItemUseResult::NONE;
		}

		$yaw = round(fmod($player->getLocation()->getYaw() + 180.0, 360.0) / 45.0) * 45.0;
		$event = new PlayerPlaceArmorStandEvent($player, Location::fromObject($spawnPos, $world, $yaw, 0.0));
		$event->call();
		if($event->isCancelled()){
			return ItemUseResult::NONE;
		}

		$entity = new ArmorStandEntity($event->getLocation());
		$entity->spawnToAll();
		$world->addSound($entity->getPosition(), new ArmorStandPlaceSound());

		if($player->hasFiniteResources()){
			$this->pop();
		}
		return ItemUseResult::SUCCESS;
	}
}
