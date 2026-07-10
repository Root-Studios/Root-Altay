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

use pocketmine\block\tile\Shelf as ShelfTile;
use pocketmine\block\utils\FacesOppositePlacingPlayerTrait;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function array_slice;
use function array_unshift;
use function count;
use function floor;
use function intdiv;
use function max;
use function min;

class Shelf extends Transparent implements HorizontalFacing, PoweredByRedstone{
	use HorizontalFacingTrait;
	use FacesOppositePlacingPlayerTrait;

	public const TYPE_UNCONNECTED = 0;
	public const TYPE_RIGHT = 1;
	public const TYPE_CENTER = 2;
	public const TYPE_LEFT = 3;

	private const SLOT_COUNT = 3;
	private const MAX_CONNECTED_SHELVES = 3;

	private int $shelfType = self::TYPE_UNCONNECTED;
	private bool $powered = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->boundedIntAuto(self::TYPE_UNCONNECTED, self::TYPE_LEFT, $this->shelfType);
		$w->bool($this->powered);
	}

	public function getShelfType() : int{
		return $this->shelfType;
	}

	/** @return $this */
	public function setShelfType(int $shelfType) : self{
		if($shelfType < self::TYPE_UNCONNECTED || $shelfType > self::TYPE_LEFT){
			throw new \InvalidArgumentException("Shelf type must be in range 0 ... 3, got $shelfType");
		}
		$this->shelfType = $shelfType;
		return $this;
	}

	public function isPowered() : bool{
		return $this->powered;
	}

	/** @return $this */
	public function setPowered(bool $powered) : self{
		$this->powered = $powered;
		if(!$powered){
			$this->shelfType = self::TYPE_UNCONNECTED;
		}
		return $this;
	}

	public function onPostPlace() : void{
		$this->updateConnectionState();
	}

	public function onNearbyBlockChange() : void{
		$this->updateConnectionState();
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player === null || $face !== $this->facing){
			return false;
		}

		if($this->powered){
			$this->swapConnectedShelvesWithHotbar($player);
		}else{
			$this->swapSlotWithMainHand($player, $this->calculateSlot($clickVector));
		}
		return true;
	}

	private function swapSlotWithMainHand(Player $player, int $slot) : void{
		$tile = $this->getShelfTile();
		if($tile === null){
			return;
		}

		$playerInventory = $player->getInventory();
		$shelfInventory = $tile->getInventory();
		$shelfItem = $shelfInventory->getItem($slot);
		$shelfInventory->setItem($slot, $playerInventory->getItemInHand());
		$playerInventory->setItemInHand($shelfItem);
	}

	private function swapConnectedShelvesWithHotbar(Player $player) : void{
		/** @var list<Shelf> $shelves */
		$shelves = $this->getConnectedShelfGroup();
		$slotCount = count($shelves) * self::SLOT_COUNT;
		$firstHotbarSlot = $player->getInventory()->getHotbarSize() - $slotCount;

		foreach($shelves as $shelfOffset => $shelf){
			$tile = $shelf->getShelfTile();
			if($tile === null){
				continue;
			}
			$shelfInventory = $tile->getInventory();
			for($slot = 0; $slot < self::SLOT_COUNT; ++$slot){
				$hotbarSlot = $firstHotbarSlot + $shelfOffset * self::SLOT_COUNT + $slot;
				$hotbarItem = $player->getInventory()->getHotbarSlotItem($hotbarSlot);
				$shelfItem = $shelfInventory->getItem($slot);
				$shelfInventory->setItem($slot, $hotbarItem);
				$player->getInventory()->setItem($hotbarSlot, $shelfItem);
			}
		}
	}

	private function calculateSlot(Vector3 $click) : int{
		$x = Facing::axis($this->facing) === Axis::X ? $click->z : $click->x;
		if(Facing::isPositive(Facing::rotateY($this->facing, true))){
			$x = 1 - $x;
		}

		return min(self::SLOT_COUNT - 1, max(0, (int) floor($x * self::SLOT_COUNT)));
	}

	private function getShelfTile() : ?ShelfTile{
		$tile = $this->position->getWorld()->getTile($this->position);
		return $tile instanceof ShelfTile ? $tile : null;
	}

	private function isConnectableShelf(Block $block) : bool{
		return $block instanceof self && $block->powered && $block->facing === $this->facing;
	}

	/** @return list<Shelf> Shelves ordered from visual left to visual right. */
	private function getConnectedShelfLine() : array{
		$left = Facing::rotateY($this->facing, false);
		$right = Facing::opposite($left);
		$result = [$this];

		for($distance = 1; ; ++$distance){
			$block = $this->getSide($left, $distance);
			if(!$block instanceof self || !$this->isConnectableShelf($block)){
				break;
			}
			array_unshift($result, $block);
		}
		for($distance = 1; ; ++$distance){
			$block = $this->getSide($right, $distance);
			if(!$block instanceof self || !$this->isConnectableShelf($block)){
				break;
			}
			$result[] = $block;
		}
		return $result;
	}

	/** @return list<Shelf> */
	private function getConnectedShelfGroup() : array{
		/** @var list<Shelf> $line */
		$line = $this->getConnectedShelfLine();
		$selfIndex = 0;
		foreach($line as $index => $shelf){
			if($shelf->position->equals($this->position)){
				$selfIndex = $index;
				break;
			}
		}
		$groupStart = intdiv($selfIndex, self::MAX_CONNECTED_SHELVES) * self::MAX_CONNECTED_SHELVES;
		return array_slice($line, $groupStart, self::MAX_CONNECTED_SHELVES);
	}

	private function updateConnectionState() : void{
		$newType = self::TYPE_UNCONNECTED;
		if($this->powered){
			/** @var list<Shelf> $group */
			$group = $this->getConnectedShelfGroup();
			if(count($group) > 1){
				foreach($group as $index => $shelf){
					if($shelf->position->equals($this->position)){
						$newType = match($index){
							0 => self::TYPE_LEFT,
							count($group) - 1 => self::TYPE_RIGHT,
							default => self::TYPE_CENTER
						};
						break;
					}
				}
			}
		}

		if($newType !== $this->shelfType){
			$this->shelfType = $newType;
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}
}
