<?php

declare(strict_types=1);

namespace pocketmine\block;

final class SuspiciousSand extends SuspiciousBlock{
	protected function getNormalBlock() : Block{ return VanillaBlocks::SAND(); }
}
