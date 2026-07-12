<?php

declare(strict_types=1);

namespace pocketmine\block\tile;

use pocketmine\block\SuspiciousSand;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use function max;
use function min;

final class BrushableBlock extends Spawnable{
	private const TAG_ITEM = "item";
	private const TAG_BRUSH_COUNT = "brush_count";
	private const TAG_BRUSH_DIRECTION = "brush_direction";
	private const TAG_TYPE = "type";
	private const TAG_LAST_BRUSH_TICK = "last_brush_tick";

	private Item $item;
	private int $brushCount = 0;
	private int $brushDirection = 0;
	private int $lastBrushTick = 0;

	public function __construct(World $world, Vector3 $pos){
		$this->item = VanillaItems::AIR();
		parent::__construct($world, $pos);
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->brushCount = max(0, min(3, $nbt->getInt(self::TAG_BRUSH_COUNT, 0)));
		$this->brushDirection = $nbt->getByte(self::TAG_BRUSH_DIRECTION, 0);
		$this->lastBrushTick = $nbt->getInt(self::TAG_LAST_BRUSH_TICK, 0);
		$itemTag = $nbt->getCompoundTag(self::TAG_ITEM);
		if($itemTag !== null){
			$this->item = Item::safeNbtDeserialize($itemTag, "Brushable block loot at $this->position");
		}
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setInt(self::TAG_BRUSH_COUNT, $this->brushCount)
			->setByte(self::TAG_BRUSH_DIRECTION, $this->brushDirection)
			->setInt(self::TAG_LAST_BRUSH_TICK, $this->lastBrushTick);
		if(!$this->item->isNull()){
			$nbt->setTag(self::TAG_ITEM, $this->item->nbtSerialize());
		}
	}

	public function getItem() : Item{ return clone $this->item; }
	public function setItem(?Item $item) : void{
		$this->item = $item !== null && !$item->isNull() ? clone $item : VanillaItems::AIR();
		$this->clearSpawnCompoundCache();
	}
	public function popItem() : Item{ $item = $this->getItem(); $this->setItem(null); return $item; }
	public function getBrushCount() : int{ return $this->brushCount; }
	public function setBrushCount(int $count) : void{ $this->brushCount = max(0, min(3, $count)); $this->clearSpawnCompoundCache(); }
	public function setBrushDirection(int $face) : void{ $this->brushDirection = $face & 0xff; $this->clearSpawnCompoundCache(); }
	public function setLastBrushTick(int $tick) : void{ $this->lastBrushTick = $tick; }

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$nbt->setInt(self::TAG_BRUSH_COUNT, $this->brushCount)
			->setByte(self::TAG_BRUSH_DIRECTION, $this->brushDirection)
			->setString(self::TAG_TYPE, $this->position->getWorld()->getBlock($this->position) instanceof SuspiciousSand ? BlockTypeNames::SUSPICIOUS_SAND : BlockTypeNames::SUSPICIOUS_GRAVEL);
		if(!$this->item->isNull()){
			$nbt->setTag(self::TAG_ITEM, $this->item->nbtSerialize());
		}
	}
}
