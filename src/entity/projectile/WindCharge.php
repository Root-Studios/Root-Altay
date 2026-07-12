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

namespace pocketmine\entity\projectile;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\particle\WindChargeBurstParticle;
use pocketmine\world\sound\WindChargeBurstSound;
use function max;

class WindCharge extends Throwable{
	private const BURST_RADIUS = 4.0;
	private const KNOCKBACK_SCALING = 1.22;

	public static function getNetworkTypeId() : string{
		return EntityIds::WIND_CHARGE_PROJECTILE;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.3125, 0.3125);
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setBaseDamage(1.0);
	}

	protected function onHit(ProjectileHitEvent $event) : void{
		$world = $this->getWorld();
		$center = $this->getPosition();
		$world->addParticle($center, new WindChargeBurstParticle());
		$world->addSound($center, new WindChargeBurstSound());

		$area = AxisAlignedBB::one()->offset($center->x, $center->y, $center->z)->expandedCopy(self::BURST_RADIUS, self::BURST_RADIUS, self::BURST_RADIUS);
		foreach($world->getNearbyEntities($area, $this) as $entity){
			$this->applyBurstKnockback($entity, $center);
		}
	}

	private function applyBurstKnockback(Entity $entity, Vector3 $center) : void{
		$offset = $entity->getPosition()->subtractVector($center);
		$distance = $offset->length();
		if($distance <= 0.0 || $distance > self::BURST_RADIUS){
			return;
		}

		$strength = max(0.0, 1.0 - $distance / self::BURST_RADIUS) * self::KNOCKBACK_SCALING;
		$impulse = $offset->normalize()->multiply($strength);
		$entity->setMotion($entity->getMotion()->addVector($impulse));
		$entity->resetFallDistance();
	}
}
