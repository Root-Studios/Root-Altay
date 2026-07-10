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

use pocketmine\block\Air;
use pocketmine\block\Water;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityCombustByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerFishEvent;
use pocketmine\item\FishingRod;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\particle\WaterParticle;
use function atan2;
use function cos;
use function max;
use function min;
use function mt_rand;
use function sin;
use function sqrt;
use const M_PI;

final class FishingHook extends Projectile {
	private const HOOK_SIZE = 0.15;
	private const MAX_DISTANCE = 32;

	private const UNDERWATER_MOTION_Y = 0.16;

	private const FISH_BITE_DURATION_MIN = 40;
	private const FISH_BITE_DURATION_MAX = 60;

	private const FISH_PROXIMITY_THRESHOLD = 0.15;

	private const BUBBLE_COUNT = 5;

	private const WAIT_TIME_MIN = 100;
	private const WAIT_TIME_MAX = 420;

	private const LURE_MIN_REDUCTION_PER_LEVEL = 35;
	private const LURE_MAX_REDUCTION_PER_LEVEL = 110;

	private const MIN_WAITING_TICKS = 20;

	private int $waitingTimer = 1;
	private bool $attracted = false;
	private bool $caught = false;
	private int $caughtTimer = 0;
	private ?Vector3 $fish = null;
	private bool $hasCalculatedWaitTime = false;

	private float $fishYaw = 0.0;
	private float $fishZigzagStrength = 1;

	private ?Random $random = null;
	private int $lureLevel = 0;

	public static function getNetworkTypeId() : string {
		return EntityIds::FISHING_HOOK;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo {
		return new EntitySizeInfo(self::HOOK_SIZE, self::HOOK_SIZE);
	}

	protected function initEntity(CompoundTag $nbt) : void {
		parent::initEntity($nbt);
		$this->setCanSaveWithChunk(false);
		$this->waitingTimer = 1;
		$this->hasCalculatedWaitTime = false;
	}

	protected function getInitialDragMultiplier() : float {
		return 0.02;
	}

	protected function getInitialGravity() : float {
		return 0.05;
	}

	public function setWaitingTimer(int $waitingTimer) : void {
		$this->waitingTimer = max(1, $waitingTimer);
	}

	public function setLureLevel(int $lureLevel) : void {
		$this->lureLevel = max(0, $lureLevel);
	}

	public function getLureLevel() : int {
		return $this->lureLevel;
	}

	private function computeWaitingTime() : int {
		$min = self::WAIT_TIME_MIN;
		$max = self::WAIT_TIME_MAX;

		if ($this->lureLevel > 0) {
			$min -= $this->lureLevel * self::LURE_MIN_REDUCTION_PER_LEVEL;
			$max -= $this->lureLevel * self::LURE_MAX_REDUCTION_PER_LEVEL;
		}

		$min = max(self::MIN_WAITING_TICKS, $min);
		$max = max($min, $max);

		return mt_rand($min, $max);
	}

	public function didCatchSomething() : bool {
		return $this->caught;
	}

	public function canCollideWith(Entity $entity) : bool {
		return $this->getTargetEntity() === null && parent::canCollideWith($entity);
	}

	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void {
		if ($this->getTargetEntity() !== null) {
			return;
		}

		$damage = $this->getResultDamage();
		if ($damage < 0) {
			return;
		}

		$event = $this->getOwningEntity() === null
			? new EntityDamageByEntityEvent($this, $entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage)
			: new EntityDamageByChildEntityEvent($this->getOwningEntity(), $this, $entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage);

		$entityHit->attack($event);
		if ($event->isCancelled()) {
			return;
		}

		$this->setTargetEntity($entityHit);

		if ($this->isOnFire()) {
			$combust = new EntityCombustByEntityEvent($this, $entityHit, mt_rand(3, 5));
			$combust->call();
			if (!$combust->isCancelled()) {
				$entityHit->setOnFire($combust->getDuration());
			}
		}
	}

	public function onUpdate(int $currentTick) : bool {
		if ($this->closed) {
			return false;
		}

		$owner = $this->getOwningEntity();
		if (!($owner instanceof Player) || !$owner->isAlive() || $owner->isClosed()) {
			$this->flagForDespawn();
			return false;
		}

		if (!($owner->getInventory()->getItemInHand() instanceof FishingRod)) {
			$this->flagForDespawn();
			$owner->setFishingHook(null);
			return false;
		}

		if ($owner->getPosition()->distance($this->getPosition()) >= self::MAX_DISTANCE) {
			$this->flagForDespawn();
			$owner->setFishingHook(null);
			return false;
		}

		$target = $this->getTargetEntity();
		if ($target !== null) {
			if ($target->isAlive()) {
				$newPos = $target->getPosition()->add(0, $target->getEyeHeight(), 0);
				$this->setPositionAndRotation($newPos, 0.0, 0.0);
				$this->setForceMovementUpdate();
			} else {
				$this->setTargetEntity(null);
			}
		}

		$hasUpdate = parent::onUpdate($currentTick);
		if (!$hasUpdate) {
			return false;
		}

		$this->handleMotion();
		$this->handleFishingLogic();
		return true;
	}

	private function handleMotion() : void {
		if ($this->isUnderwater()) {
			$this->motion = $this->motion->withComponents(0, self::UNDERWATER_MOTION_Y, 0);
		} elseif ($this->isCollided && $this->keepMovement) {
			$this->motion = $this->motion->withComponents(0, $this->motion->y, 0);
			$this->keepMovement = false;
		}
	}

	public function reelLine() : void {
		$player = $this->getOwningEntity();
		if (!($player instanceof Player)) {
			$this->flagForDespawn();
			return;
		}

		$target = $this->getTargetEntity();
		if ($target !== null) {
			$delta = $player->getPosition()->subtractVector($this->getPosition());
			$dist = max(0.0001, $delta->length());
			$motion = $delta->multiply(0.1);
			$motion = $motion->withComponents(
				$motion->x,
				$motion->y + sqrt($dist) * 0.08,
				$motion->z
			);
			$target->setMotion($motion);
		}

		if ($this->caught) {
			$rod = $player->getInventory()->getItemInHand();
			if ($rod instanceof FishingRod) {
				$loots = $rod->getFishingLoot();
				$xp = mt_rand(1, 3);

				$ev = new PlayerFishEvent($player, $rod, $loots, $xp);
				if (!$ev->isCancelled()) {
					$ev->call();

					foreach ($loots as $loot) {
						if ($player->getInventory()->canAddItem($loot)) {
							$player->getInventory()->addItem($loot);
						} else {
							$player->getWorld()->dropItem($player->getPosition(), $loot);
						}
					}

					$player->getXpManager()->addXp($xp);
				}
			}
		}

		if ($player->getFishingHook() === $this) {
			$player->setFishingHook(null);
		}

		$this->flagForDespawn();
	}

	private function handleFishingLogic() : void {
		if (!$this->isInOrOnWater()) {
			$this->attracted = false;
			$this->caught = false;
			$this->fish = null;
			$this->hasCalculatedWaitTime = false;
			$this->waitingTimer = 1;
			return;
		}

		if (!$this->hasCalculatedWaitTime) {
			$this->waitingTimer = $this->computeWaitingTime();
			$this->hasCalculatedWaitTime = true;
		}

		if (!$this->attracted) {
			if ($this->waitingTimer > 0) {
				--$this->waitingTimer;
				return;
			}

			$this->spawnFish();
			$this->caught = false;
			$this->attracted = true;
			return;
		}

		if (!$this->caught) {
			if (!$this->attractFish()) {
				return;
			}

			$this->caughtTimer = mt_rand(self::FISH_BITE_DURATION_MIN, self::FISH_BITE_DURATION_MAX);
			$this->fishBites();
			$this->caught = true;
			return;
		}

		if ($this->caughtTimer > 0) {
			--$this->caughtTimer;
			return;
		}

		$this->attracted = false;
		$this->caught = false;
		$this->fish = null;
		$this->waitingTimer = $this->computeWaitingTime();
	}

	private function spawnFish() : void {
		$r = $this->getRandom();
		$p = $this->getPosition();

		$this->fish = new Vector3(
			$p->x + ($r->nextFloat() * 1.2 + mt_rand(1, 4)) * ($r->nextBoolean() ? -1 : 1),
			(float) $this->getWaterHeight(),
			$p->z + ($r->nextFloat() * 1.2 + mt_rand(1, 4)) * ($r->nextBoolean() ? -1 : 1)
		);

		$dx = $p->x - $this->fish->x;
		$dz = $p->z - $this->fish->z;
		$this->fishYaw = atan2($dz, $dx);
	}

	private function attractFish() : bool {
		if ($this->fish === null) {
			return false;
		}

		$p = $this->getPosition();
		$r = $this->getRandom();

		$dx = $p->x - $this->fish->x;
		$dz = $p->z - $this->fish->z;
		$distance = sqrt($dx * $dx + $dz * $dz);

		if ($distance <= 0.0001) {
			return true;
		}

		$targetYaw = atan2($dz, $dx);

		$yawDiff = $targetYaw - $this->fishYaw;

		while ($yawDiff > M_PI) {
			$yawDiff -= M_PI * 2;
		}
		while ($yawDiff < -M_PI) {
			$yawDiff += M_PI * 2;
		}

		$this->fishYaw += $yawDiff * 0.25;
		$this->fishYaw += ($r->nextFloat() - 0.5) * $this->fishZigzagStrength;

		$speed = min(0.28, max(0.08, $distance * 0.12));

		$newX = $this->fish->x + cos($this->fishYaw) * $speed;
		$newZ = $this->fish->z + sin($this->fishYaw) * $speed;

		$this->fish = new Vector3(
			$newX,
			$this->fish->y,
			$newZ
		);

		$this->getWorld()->addParticle($this->fish, new WaterParticle());

		$ndx = $p->x - $this->fish->x;
		$ndz = $p->z - $this->fish->z;

		return sqrt($ndx * $ndx + $ndz * $ndz) < self::FISH_PROXIMITY_THRESHOLD;
	}

	private function fishBites() : void {
		$this->sendFishBitePackets();
		$this->spawnBubbleParticles();
		$this->motion->y -= 0.2;

		$this->scheduleUpdate();
	}

	private function sendFishBitePackets() : void {
		$packets = [
			$this->createActorEventPacket(ActorEvent::FISH_HOOK_HOOK),
			$this->createActorEventPacket(ActorEvent::FISH_HOOK_BUBBLE),
			$this->createActorEventPacket(ActorEvent::FISH_HOOK_TEASE),
		];

		foreach ($this->getViewers() as $viewer) {
			foreach ($packets as $packet) {
				$viewer->getNetworkSession()->sendDataPacket($packet);
			}
		}
	}

	private function createActorEventPacket(int $eventId) : ActorEventPacket {
		$packet = new ActorEventPacket();
		$packet->actorRuntimeId = $this->getId();
		$packet->eventId = $eventId;
		return $packet;
	}

	private function spawnBubbleParticles() : void {
		$r = $this->getRandom();
		$p = $this->getPosition();
		$y = (float) $this->getWaterHeight();

		for ($i = 0; $i < self::BUBBLE_COUNT; $i++) {
			$pos = $p->withComponents(
				$p->x + $r->nextFloat() * 0.5 - 0.25,
				$y,
				$p->z + $r->nextFloat() * 0.5 - 0.25
			);
			$this->getWorld()->addParticle($pos, new BubbleParticle());
		}
	}

	private function getWaterHeight() : int {
		$pos = $this->getPosition();
		$maxY = min(256, $pos->getFloorY() + 64);

		for ($y = $pos->getFloorY(); $y < $maxY; $y++) {
			$block = $this->getWorld()->getBlockAt($pos->getFloorX(), $y, $pos->getFloorZ());
			if ($block instanceof Air) {
				return $y;
			}
		}
		return $pos->getFloorY();
	}

	private function isInOrOnWater() : bool {
		$p = $this->getPosition();
		$world = $this->getWorld();

		$b0 = $world->getBlockAt($p->getFloorX(), $p->getFloorY(), $p->getFloorZ());
		$b1 = $world->getBlockAt($p->getFloorX(), $p->getFloorY() - 1, $p->getFloorZ());

		return ($b0 instanceof Water) || ($b1 instanceof Water);
	}

	private function getRandom() : Random {
		return $this->random ??= new Random();
	}

	public function close() : void{
		parent::close();

		$owner = $this->getOwningEntity();
		if ($owner instanceof Player) {
			if ($owner->getFishingHook() === $this) {
				$owner->setFishingHook(null);
			}
		}
	}
}