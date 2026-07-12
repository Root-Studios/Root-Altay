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
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\MaceSmashSound;
use function max;
use function min;

class Mace extends Tool{
	private const MIN_SMASH_FALL_DISTANCE = 1.5;

	public function getMaxDurability() : int{
		return 500;
	}

	public function getAttackPoints() : int{
		return 6;
	}

	public function getEnchantability() : int{
		return 15;
	}

	public function isSmashAttack(float $fallDistance) : bool{
		return $fallDistance >= self::MIN_SMASH_FALL_DISTANCE;
	}

	public function getSmashDamageBonus(float $fallDistance) : float{
		if(!$this->isSmashAttack($fallDistance)){
			return 0.0;
		}

		$firstThreeBlocks = min($fallDistance, 3.0);
		$nextFiveBlocks = min(max($fallDistance - 3.0, 0.0), 5.0);
		$remainingBlocks = max($fallDistance - 8.0, 0.0);
		$baseBonus = $firstThreeBlocks * 4.0 + $nextFiveBlocks * 2.0 + $remainingBlocks;
		$densityBonus = $fallDistance * 0.5 * $this->getEnchantmentLevel(VanillaEnchantments::DENSITY());

		return $baseBonus + $densityBonus;
	}

	public function getArmorEffectivenessReduction() : float{
		return min(0.6, $this->getEnchantmentLevel(VanillaEnchantments::BREACH()) * 0.15);
	}

	public function onSuccessfulSmash(Player $attacker, Entity $victim, float $fallDistance) : void{
		if(!$this->isSmashAttack($fallDistance)){
			return;
		}

		$attacker->resetFallDistance();
		$world = $victim->getWorld();
		$impactPosition = $victim->getPosition();
		$world->addSound($impactPosition, new MaceSmashSound($fallDistance >= 5.0));

		$area = AxisAlignedBB::one()->offset($impactPosition->x, $impactPosition->y, $impactPosition->z)->expandedCopy(3.5, 2.0, 3.5);
		foreach($world->getNearbyEntities($area, $victim) as $entity){
			if(!$entity instanceof Living || $entity === $attacker){
				continue;
			}
			$entity->knockBack($entity->getPosition()->x - $impactPosition->x, $entity->getPosition()->z - $impactPosition->z, 0.7, 0.7);
		}

		$windBurstLevel = $this->getEnchantmentLevel(VanillaEnchantments::WIND_BURST());
		if($windBurstLevel > 0){
			$verticalVelocity = [1 => 1.2, 2 => 1.75, 3 => 2.2][min(3, $windBurstLevel)];
			$motion = $attacker->getMotion();
			$attacker->setMotion(new Vector3($motion->x, $verticalVelocity, $motion->z));
		}
	}

	public function onAttackEntity(Entity $victim, array &$returnedItems) : bool{
		return $this->applyDamage(1);
	}

	public function onDestroyBlock(Block $block, array &$returnedItems) : bool{
		if(!$block->getBreakInfo()->breaksInstantly()){
			return $this->applyDamage(2);
		}
		return false;
	}
}
