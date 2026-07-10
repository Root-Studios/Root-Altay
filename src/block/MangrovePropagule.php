<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\SaplingType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\event\block\StructureGrowEvent;
use pocketmine\item\Fertilizer;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\generator\object\TreeFactory;
use pocketmine\world\generator\object\TreeType;
use function max;
use function min;
use function mt_rand;

class MangrovePropagule extends Sapling{
	private bool $hanging = false;
	private int $stage = 0;

	public function __construct(BlockIdentifier $idInfo, string $name, BlockTypeInfo $typeInfo){
		parent::__construct($idInfo, $name, $typeInfo, SaplingType::MANGROVE);
	}

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->ready);
		$w->bool($this->hanging);
		$w->boundedIntAuto(0, 4, $this->stage);
	}

	public function isHanging() : bool{ return $this->hanging; }

	/** @return $this */
	public function setHanging(bool $hanging) : self{
		$this->hanging = $hanging;
		return $this;
	}

	public function getStage() : int{ return $this->stage; }

	/** @return $this */
	public function setStage(int $stage) : self{
		$this->stage = min(4, max(0, $stage));
		return $this;
	}

	public function canBePlacedAt(Block $blockReplace, Vector3 $clickVector, int $face, bool $isClickedBlock) : bool{
		return ($face === Facing::DOWN ? $this->canHangAt($blockReplace) : $this->canStandAt($blockReplace)) &&
			(!$this->canBeFlowedInto() || !$blockReplace instanceof Liquid) &&
			$blockReplace->canBeReplaced();
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->hanging = $face === Facing::DOWN;
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onNearbyBlockChange() : void{
		if(!$this->canBeSupportedAt($this)){
			$this->position->getWorld()->useBreakOn($this->position);
		}
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if(!$item instanceof Fertilizer){
			return false;
		}

		if($this->hanging){
			if($this->stage < 4){
				$item->pop();
				$this->stage++;
				$this->position->getWorld()->setBlock($this->position, $this);
				return true;
			}
			return false;
		}

		if($this->grow($player)){
			$item->pop();
			return true;
		}
		return false;
	}

	public function onRandomTick() : void{
		if($this->hanging){
			if($this->stage < 4 && mt_rand(1, 7) === 1){
				$this->stage++;
				$this->position->getWorld()->setBlock($this->position, $this);
			}
			return;
		}
		parent::onRandomTick();
	}

	private function grow(?Player $player) : bool{
		$random = new Random(mt_rand());
		$tree = TreeFactory::get($random, TreeType::MANGROVE);
		$transaction = $tree?->getBlockTransaction($this->position->getWorld(), $this->position->getFloorX(), $this->position->getFloorY(), $this->position->getFloorZ(), $random);
		if($transaction === null){
			return false;
		}

		$ev = new StructureGrowEvent($this, $transaction, $player);
		$ev->call();
		if(!$ev->isCancelled()){
			return $transaction->apply();
		}
		return false;
	}

	private function canBeSupportedAt(Block $block) : bool{
		return $this->hanging ? $this->canHangAt($block) : $this->canStandAt($block);
	}

	private function canStandAt(Block $block) : bool{
		$supportBlock = $block->getSide(Facing::DOWN);
		return $supportBlock->hasTypeTag(BlockTypeTags::DIRT) || $supportBlock->hasTypeTag(BlockTypeTags::MUD);
	}

	private function canHangAt(Block $block) : bool{
		$supportBlock = $block->getSide(Facing::UP);
		return $supportBlock instanceof Leaves || $supportBlock->isFullCube();
	}
}
