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

namespace pocketmine\block;

use pocketmine\block\utils\Fallable;
use pocketmine\block\utils\FallableTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\object\FallingBlock;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function min;

final class Scaffolding extends Transparent implements Fallable{
	use FallableTrait;

	public const MAX_STABILITY = 7;

	private int $stability = 0;
	private bool $stabilityCheck = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(0, self::MAX_STABILITY, $this->stability);
		$w->bool($this->stabilityCheck);
	}

	public function getStability() : int{ return $this->stability; }

	/** @return $this */
	public function setStability(int $stability) : self{
		if($stability < 0 || $stability > self::MAX_STABILITY){
			throw new \InvalidArgumentException("Stability must be in range 0 ... " . self::MAX_STABILITY);
		}
		$this->stability = $stability;
		return $this;
	}

	public function isStabilityCheckPending() : bool{ return $this->stabilityCheck; }

	/** @return $this */
	public function setStabilityCheckPending(bool $stabilityCheck) : self{
		$this->stabilityCheck = $stabilityCheck;
		return $this;
	}

	public function canClimb() : bool{
		return true;
	}

	public function isSolid() : bool{
		return false;
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	protected function recalculateCollisionBoxes() : array{
		return [AxisAlignedBB::one()->trim(Facing::DOWN, 14 / 16)];
	}

	public function hasEntityCollision() : bool{
		return true;
	}

	public function onEntityInside(Entity $entity) : bool{
		$entity->resetFallDistance();
		return true;
	}

	public function getFuelTime() : int{
		return 500;
	}

	public function getFlameEncouragement() : int{
		return 60;
	}

	public function getFlammability() : int{
		return 20;
	}

	public function canBePlacedAt(Block $blockReplace, Vector3 $clickVector, int $face, bool $isClickedBlock) : bool{
		return !$blockReplace instanceof Lava && parent::canBePlacedAt($blockReplace, $clickVector, $face, $isClickedBlock);
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->stability = self::calculateStability($blockReplace);
		$this->stabilityCheck = true;
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onPostPlace() : void{
		$this->updateStability();
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if(!$item instanceof ItemBlock || !$item->getBlock()->hasSameTypeId($this)){
			return false;
		}

		$world = $this->position->getWorld();
		$target = $this->getSide(Facing::UP);
		while($target->hasSameTypeId($this)){
			$target = $target->getSide(Facing::UP);
		}

		$targetPosition = $target->getPosition();
		if(
			!$world->isInWorld($targetPosition->getFloorX(), $targetPosition->getFloorY(), $targetPosition->getFloorZ()) ||
			!$target->canBeReplaced() ||
			$target instanceof Lava
		){
			return true;
		}

		$newBlock = VanillaBlocks::SCAFFOLDING()
			->setStability(self::calculateStability($target))
			->setStabilityCheckPending(true);
		$world->setBlock($targetPosition, $newBlock);
		$item->pop();
		return true;
	}

	public function onNearbyBlockChange() : void{
		$this->updateStability();
	}

	private function updateStability() : void{
		$stability = self::calculateStability($this);
		if($stability === self::MAX_STABILITY){
			$this->startFalling();
			return;
		}

		if($stability !== $this->stability || $this->stabilityCheck){
			$this->position->getWorld()->setBlock(
				$this->position,
				(clone $this)->setStability($stability)->setStabilityCheckPending(false)
			);
		}
	}

	private function startFalling() : void{
		$world = $this->position->getWorld();
		$world->setBlock($this->position, VanillaBlocks::AIR());

		$fallingBlock = new FallingBlock(Location::fromObject($this->position->add(0.5, 0, 0.5), $world), $this);
		$fallingBlock->spawnToAll();
	}

	private static function calculateStability(Block $block) : int{
		$down = $block->getSide(Facing::DOWN);
		if(!$down instanceof self && $down->getSupportType(Facing::UP) === SupportType::FULL){
			return 0;
		}

		$stability = $down instanceof self ? $down->stability : self::MAX_STABILITY;
		foreach(Facing::HORIZONTAL as $facing){
			$side = $block->getSide($facing);
			if($side instanceof self){
				$stability = min($stability, $side->stability + 1);
			}
		}
		return min($stability, self::MAX_STABILITY);
	}
}
