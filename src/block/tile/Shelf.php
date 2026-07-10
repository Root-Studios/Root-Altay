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

namespace pocketmine\block\tile;

use pocketmine\block\inventory\ShelfInventory;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\world\World;

class Shelf extends Spawnable implements Container{
	use ContainerTrait;

	protected ShelfInventory $inventory;

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = new ShelfInventory($this->position);
		$this->inventory->getListeners()->add(CallbackInventoryListener::onAnyChange(
			function(Inventory $unused) use ($world, $pos) : void{
				$this->clearSpawnCompoundCache();
				$world->setBlock($pos, $world->getBlock($pos));
			}
		));
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->loadItems($nbt);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$this->saveItems($nbt);
	}

	public function close() : void{
		if(!$this->closed){
			$this->inventory->removeAllViewers();
			parent::close();
		}
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$items = [];
		foreach($this->inventory->getContents() as $slot => $item){
			$items[] = $item->nbtSerialize($slot);
		}
		$nbt->setTag(Container::TAG_ITEMS, new ListTag($items, NBT::TAG_Compound));
	}

	public function getInventory() : ShelfInventory{
		return $this->inventory;
	}

	public function getRealInventory() : ShelfInventory{
		return $this->inventory;
	}
}
