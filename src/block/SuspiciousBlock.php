<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\tile\BrushableBlock;
use pocketmine\block\utils\Fallable;
use pocketmine\block\utils\FallableTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Location;
use pocketmine\entity\object\FallingBlock;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\BrushCompletedSound;

abstract class SuspiciousBlock extends Opaque implements Fallable{
	use FallableTrait;

	public const TAG_BRUSH_LOOT = "BrushLoot";
	public const MIN_BRUSHED_PROGRESS = 0;
	public const MAX_BRUSHED_PROGRESS = 3;
	private const COMPLETE_BRUSH_TICKS = 96;

	private int $brushedProgress = self::MIN_BRUSHED_PROGRESS;
	private bool $hanging = false;
	private ?Item $itemInside = null;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(self::MIN_BRUSHED_PROGRESS, self::MAX_BRUSHED_PROGRESS, $this->brushedProgress);
		$w->bool($this->hanging);
	}

	public function getBrushedProgress() : int{ return $this->brushedProgress; }

	public function setBrushedProgress(int $progress) : self{
		if($progress < self::MIN_BRUSHED_PROGRESS || $progress > self::MAX_BRUSHED_PROGRESS){
			throw new \InvalidArgumentException("Brushed progress must be between 0 and 3");
		}
		$this->brushedProgress = $progress;
		return $this;
	}

	public function isHanging() : bool{ return $this->hanging; }
	public function setHanging(bool $hanging) : self{ $this->hanging = $hanging; return $this; }

	public function setItemInside(?Item $item) : self{
		$this->itemInside = $item !== null && !$item->isNull() ? clone $item : null;
		return $this;
	}

	public function getItemInside() : ?Item{ return $this->itemInside !== null ? clone $this->itemInside : null; }
	public function hasItemInside() : bool{ return $this->itemInside !== null; }

	public function asItem() : Item{
		$item = parent::asItem();
		if($this->itemInside !== null){
			$item->setNamedTag($item->getNamedTag()->setTag(self::TAG_BRUSH_LOOT, $this->itemInside->nbtSerialize()));
		}
		return $item;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if(!parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player)){
			return false;
		}
		$itemTag = $item->getNamedTag()->getCompoundTag(self::TAG_BRUSH_LOOT);
		$this->itemInside = $itemTag !== null ? Item::safeNbtDeserialize($itemTag, "Suspicious block loot") : $this->itemInside;
		return true;
	}

	public function onPostPlace() : void{
		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof BrushableBlock){
			$tile->setItem($this->itemInside ?? VanillaItems::DIAMOND());
			$tile->setBrushCount($this->brushedProgress);
		}
	}

	public function onNearbyBlockChange() : void{
		$world = $this->position->getWorld();
		if(!$world->getBlock($this->position->getSide(Facing::DOWN))->canBeReplaced()){
			return;
		}
		$fallingBlock = clone $this;
		$tile = $world->getTile($this->position);
		if($tile instanceof BrushableBlock){
			$fallingBlock->setItemInside($tile->getItem());
		}
		$world->setBlock($this->position, VanillaBlocks::AIR());
		(new FallingBlock(Location::fromObject($this->position->add(0.5, 0, 0.5), $world), $fallingBlock))->spawnToAll();
	}

	public function brush(Vector3 $dropPosition, int $face, int $useDuration, int $currentTick) : bool{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		$progress = self::progressForDuration($useDuration);
		if($tile instanceof BrushableBlock){
			$tile->setBrushDirection($face);
			$tile->setBrushCount($progress);
			$tile->setLastBrushTick($currentTick);
		}
		if($progress !== $this->brushedProgress){
			$world->setBlock($this->position, (clone $this)->setBrushedProgress($progress), false);
		}
		if($useDuration < self::COMPLETE_BRUSH_TICKS){
			return false;
		}
		if($tile instanceof BrushableBlock){
			$item = $tile->popItem();
			if(!$item->isNull()){
				$world->dropItem($dropPosition, $item);
			}
			$world->removeTile($tile);
		}
		$world->addSound($this->position->add(0.5, 0.5, 0.5), new BrushCompletedSound($this));
		$world->setBlock($this->position, $this->getNormalBlock(), false);
		return true;
	}

	public static function progressForDuration(int $useDuration) : int{
		return match(true){
			$useDuration >= 68 => 3,
			$useDuration >= 40 => 2,
			$useDuration >= 8 => 1,
			default => 0
		};
	}

	public function getDrops(Item $item) : array{ return []; }

	abstract protected function getNormalBlock() : Block;
}
