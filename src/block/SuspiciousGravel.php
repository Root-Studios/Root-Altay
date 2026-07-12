<?php

declare(strict_types=1);

namespace pocketmine\block;

final class SuspiciousGravel extends SuspiciousBlock{
	protected function getNormalBlock() : Block{ return VanillaBlocks::GRAVEL(); }
}
