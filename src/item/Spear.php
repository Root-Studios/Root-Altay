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
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function max;

class Spear extends TieredTool implements Releasable{
	private const MIN_REACH = 2.0;
	private const MIN_CHARGE_SPEED = 4.6;

	public function getAttackPoints() : int{
		return match($this->tier){
			ToolTier::WOOD, ToolTier::GOLD => 2,
			ToolTier::STONE, ToolTier::COPPER => 3,
			ToolTier::IRON => 4,
			ToolTier::DIAMOND => 5,
			ToolTier::NETHERITE => 6
		};
	}

	public function getMaxDurability() : int{
		return match($this->tier){
			ToolTier::WOOD => 60,
			ToolTier::STONE => 130,
			ToolTier::COPPER => 190,
			ToolTier::IRON => 250,
			ToolTier::GOLD => 30,
			ToolTier::DIAMOND => 1560,
			ToolTier::NETHERITE => 2030
		};
	}

	public function isTargetInJabRange(Player $player, Entity $target) : bool{
		return $player->getEyePos()->distance($target->getPosition()->add(0.0, $target->size->getHeight() / 2, 0.0)) >= self::MIN_REACH;
	}

	public function getChargeAttackDamage(Player $player, Entity $target) : ?float{
		if(!$player->isUsingItem() || $player->getItemUseDuration() < $this->getChargeDelayTicks()){
			return null;
		}

		$relativeMotion = $player->getMotion()->subtractVector($target->getMotion());
		$direction = $player->getDirectionVector();
		$relativeSpeed = max(0.0, $relativeMotion->x * $direction->x + $relativeMotion->y * $direction->y + $relativeMotion->z * $direction->z) * 20.0;
		if($relativeSpeed < self::MIN_CHARGE_SPEED){
			return null;
		}

		return max($this->getAttackPoints(), $relativeSpeed * $this->getChargeDamageMultiplier());
	}

	private function getChargeDelayTicks() : int{
		return match($this->tier){
			ToolTier::WOOD => 15,
			ToolTier::STONE, ToolTier::GOLD => 14,
			ToolTier::COPPER => 13,
			ToolTier::IRON => 12,
			ToolTier::DIAMOND => 10,
			ToolTier::NETHERITE => 8
		};
	}

	private function getChargeDamageMultiplier() : float{
		return match($this->tier){
			ToolTier::WOOD, ToolTier::GOLD => 0.7,
			ToolTier::STONE, ToolTier::COPPER => 0.82,
			ToolTier::IRON => 0.95,
			ToolTier::DIAMOND => 1.075,
			ToolTier::NETHERITE => 1.2
		};
	}

	public function tryLunge(Player $player, bool $applyDurabilityDamage = false) : bool{
		$level = $this->getEnchantmentLevel(VanillaEnchantments::LUNGE());
		if($level <= 0 || $player->isUsingItem() || $player->isUnderwater() || $player->isGliding() || $player->getHungerManager()->getFood() < 6){
			return false;
		}

		$direction = $player->getDirectionVector();
		$horizontalLength = (new Vector3($direction->x, 0.0, $direction->z))->length();
		if($horizontalLength <= 0.0){
			return false;
		}

		$force = 0.8 + 0.4 * $level;
		$player->setMotion(new Vector3($direction->x * $force, $player->getMotion()->y, $direction->z * $force));
		$player->getHungerManager()->exhaust(4.0 * $level, PlayerExhaustEvent::CAUSE_CUSTOM);
		if($applyDurabilityDamage){
			$this->applyDamage(1);
		}
		return true;
	}

	public function canStartUsingItem(Player $player) : bool{
		return !$this->isBroken();
	}

	public function getMinUseDuration() : int{
		return 0;
	}

	public function onAttackEntity(Entity $victim, array &$returnedItems) : bool{
		return $this->applyDamage(1);
	}

	public function onDestroyBlock(Block $block, array &$returnedItems) : bool{
		return false;
	}
}
