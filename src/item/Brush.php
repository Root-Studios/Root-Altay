<?php

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\block\Block;
use pocketmine\block\SuspiciousBlock;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\particle\BrushDustParticle;
use pocketmine\world\sound\BrushSound;

final class Brush extends Tool implements ItemUseOnBlockHandler{
	private const BRUSH_INTERVAL_TICKS = 10;

	public function getMaxDurability() : int{
		return 64;
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		return $blockClicked instanceof SuspiciousBlock ? ItemUseResult::SUCCESS : ItemUseResult::NONE;
	}

	public function canStartUsingItemOnBlock(Player $player, Block $block, int $face, Vector3 $clickVector) : bool{
		return $block instanceof SuspiciousBlock;
	}

	public function onUsingItemOnBlockTick(Player $player, Block $block, int $face, Vector3 $clickVector, int $useDuration, array &$returnedItems) : ItemUseResult{
		if(!$block instanceof SuspiciousBlock){
			return ItemUseResult::NONE;
		}
		if($useDuration % self::BRUSH_INTERVAL_TICKS !== 0){
			return ItemUseResult::NONE;
		}

		[$offsetX, $offsetY, $offsetZ] = Facing::OFFSET[$face];
		$effectPosition = $block->getPosition()->add(
			$clickVector->x + $offsetX * 0.51,
			$clickVector->y + $offsetY * 0.51,
			$clickVector->z + $offsetZ * 0.51
		);
		$world = $player->getWorld();
		$world->addParticle($effectPosition, new BrushDustParticle($block));
		$world->addSound($effectPosition, new BrushSound($block));

		if($block->brush($effectPosition, $face, $useDuration, $player->getServer()->getTick())){
			//Vanilla consumes durability for a completed excavation, not for every dust effect.
			$this->applyDamage(1);
		}
		return ItemUseResult::SUCCESS;
	}
}
