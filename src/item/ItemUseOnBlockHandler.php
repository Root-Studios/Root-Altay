<?php

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

interface ItemUseOnBlockHandler{
	public function canStartUsingItemOnBlock(Player $player, Block $block, int $face, Vector3 $clickVector) : bool;

	/** @param Item[] &$returnedItems */
	public function onUsingItemOnBlockTick(Player $player, Block $block, int $face, Vector3 $clickVector, int $useDuration, array &$returnedItems) : ItemUseResult;
}
