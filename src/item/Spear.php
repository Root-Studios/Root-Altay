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
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\SpearAttackSound;
use pocketmine\world\sound\SpearUseSound;
use function abs;

class Spear extends TieredTool implements Releasable{
	private const MINIMUM_VELOCITY_DAMAGE = 4.6;
	private const MINIMUM_VELOCITY_KNOCKBACK = 5.1;
	private const MINIMUM_DISTANCE = 2.0;
	private const MAXIMUM_DISTANCE = 5.0;
	private const MAX_HOLD_DURATION = 9.0;
	private const STAGE_ENGAGED = 1;
	private const STAGE_TIRED = 2;
	private const STAGE_DISENGAGED = 3;
	private const STAGE_TIRED_DELAY = 3.0;
	private const STAGE_DISENGAGED_DELAY = 4.0;

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
		$distance = $player->getEyePos()->distance($target->getPosition()->add(0.0, $target->size->getHeight() / 2, 0.0));
		return $distance >= self::MINIMUM_DISTANCE && $distance <= self::MAXIMUM_DISTANCE;
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		$player->getWorld()->addSound($player->getPosition(), new SpearUseSound($this->tier));
		return ItemUseResult::NONE;
	}

	/**
	 * Executes the kinetic charge while the use button is held.
	 */
	public function onUsingTick(Player $player, int $ticksUsed) : void{
		$secondsUsed = $ticksUsed / 20;
		if($secondsUsed >= self::MAX_HOLD_DURATION){
			$player->setUsingItem(false);
			return;
		}

		$stage = $secondsUsed >= self::STAGE_DISENGAGED_DELAY ? self::STAGE_DISENGAGED :
			($secondsUsed >= self::STAGE_TIRED_DELAY ? self::STAGE_TIRED :
				($secondsUsed >= $this->getTierActivationDelay() ? self::STAGE_ENGAGED : 0));
		if($stage < self::STAGE_ENGAGED){
			return;
		}

		$direction = $player->getDirectionVector()->normalize()->multiply(1.5);
		$hitBox = $player->getBoundingBox()->expandedCopy(1.5, 1.0, 1.5)->offset($direction->x, $direction->y, $direction->z);
		foreach($player->getWorld()->getNearbyEntities($hitBox, $player) as $entity){
			if(!$entity instanceof Living || !$entity->isAlive() || !$this->isTargetInJabRange($player, $entity)){
				continue;
			}

			$targetVelocity = $entity instanceof Player ? $entity->getCurrentVelocity() : 0.0;
			$relativeVelocity = abs($player->getCurrentVelocity() - $targetVelocity);
			if($relativeVelocity >= self::MINIMUM_VELOCITY_DAMAGE){
				$this->dealDamage($player, $entity, $relativeVelocity * $this->getChargeDamageMultiplier(), $stage);
			}
		}
	}

	/**
	 * Performs the long-range jab when a player swings without a selected target.
	 */
	public function handleJabAttack(Player $player) : bool{
		if($player->hasItemCooldown($this)){
			return false;
		}

		$player->resetItemCooldown($this, $this->getTierCooldown());
		$eyePos = $player->getEyePos();
		$facingDirection = $player->getDirectionVector()->normalize();
		$hitBox = $player->getBoundingBox()->expandedCopy(self::MAXIMUM_DISTANCE * 1.7, self::MAXIMUM_DISTANCE * 1.7, self::MAXIMUM_DISTANCE * 2.5);
		$bestScore = -1.0;
		$target = null;

		foreach($player->getWorld()->getNearbyEntities($hitBox, $player) as $nearbyEntity){
			if(!$nearbyEntity instanceof Living || !$nearbyEntity->isAlive()){
				continue;
			}
			$entityBody = $nearbyEntity->getPosition()->add(0.0, $nearbyEntity->getEyeHeight() / 2, 0.0);
			$distance = $eyePos->distance($entityBody);
			$facingDot = $facingDirection->dot($entityBody->subtractVector($eyePos)->normalize());
			if($distance < self::MINIMUM_DISTANCE || $distance > self::MAXIMUM_DISTANCE || $facingDot < 0.516){
				continue;
			}
			$score = $facingDot - ($distance / self::MAXIMUM_DISTANCE) * 0.05;
			if($score > $bestScore){
				$bestScore = $score;
				$target = $nearbyEntity;
			}
		}

		if($target === null){
			$player->getWorld()->addSound($player->getPosition(), new SpearAttackSound($this->tier, false));
			return false;
		}

		return $this->dealDamage($player, $target, 0.0, 0);
	}

	private function dealDamage(Player $player, Living $target, float $bonusDamage, int $stage) : bool{
		$noKnockback = $stage === self::STAGE_DISENGAGED || ($player->isUsingItem() && $player->getCurrentVelocity() < self::MINIMUM_VELOCITY_KNOCKBACK);
		$event = new EntityDamageByEntityEvent($player, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->getAttackPoints() + $bonusDamage);
		if($noKnockback){
			$event->setKnockBack(0.0);
		}
		$target->attack($event);
		if($event->isCancelled()){
			return false;
		}

		// Updating the held slot while the use action is active makes Bedrock cancel the
		// charge animation. Defer the durability sync until the player releases the spear.
		if($player->isUsingItem()){
			$player->queueSpearDurabilityDamage();
		}else{
			$this->applyDamage(1);
			$player->getInventory()->setItemInHand($this);
		}
		$player->getWorld()->addSound($player->getPosition(), new SpearAttackSound($this->tier, true));
		return true;
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

	public function getTierActivationDelay() : float{
		$level = $this->tier->getHarvestLevel();
		return $level <= 4 ? 0.80 - $level * 0.05 : 0.60 - ($level - 4) * 0.10;
	}

	public function getTierCooldown() : int{
		return 11 + $this->tier->getHarvestLevel() * 2;
	}

	public function tryLunge(Player $player, bool $applyDurabilityDamage = false) : bool{
		$level = $this->getEnchantmentLevel(VanillaEnchantments::LUNGE());
		if($level <= 0 || $player->isUnderwater() || $player->isGliding() || $player->getHungerManager()->getFood() < 6){
			return false;
		}

		$direction = $player->getDirectionVector();
		$horizontalLength = (new Vector3($direction->x, 0.0, $direction->z))->length();
		if($horizontalLength <= 0.0){
			return false;
		}

		$force = 0.8 + 0.4 * $level;
		// setMotion() also sends SetActorMotion to the owning client. Adding to the
		// current motion prevents the jab dash from cancelling an existing knockback.
		$player->setMotion($player->getMotion()->add($direction->x * $force, 0.0, $direction->z * $force));
		$player->getHungerManager()->exhaust(4.0 * $level, PlayerExhaustEvent::CAUSE_CUSTOM);
		if($applyDurabilityDamage){
			$this->applyDamage(1);
		}
		return true;
	}

	public function canStartUsingItem(Player $player) : bool{
		return !$this->isBroken() && !$player->hasItemCooldown($this);
	}

	public function getCooldownTag() : ?string{
		return ItemCooldownTags::SPEAR;
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
