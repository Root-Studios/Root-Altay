<?php

declare(strict_types=1);

namespace pocketmine\world\particle;

use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\ParticleIds;

final class BrushDustParticle implements Particle{
	public function __construct(private Block $block){}
	public function encode(Vector3 $pos) : array{
		return [LevelEventPacket::standardParticle(ParticleIds::BRUSH_DUST, TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($this->block->getStateId()), $pos)];
	}
}
