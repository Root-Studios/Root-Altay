<?php

declare(strict_types=1);

namespace pocketmine\world\sound;

use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;

final class BrushCompletedSound implements Sound{
	public function __construct(private Block $block){}
	public function encode(Vector3 $pos) : array{
		return [LevelSoundEventPacket::nonActorSound(LevelSoundEvent::BRUSH_COMPLETED, $pos, false, TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($this->block->getStateId()))];
	}
}
