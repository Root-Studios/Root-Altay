<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\BlockTypeTags;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;

final class BigSpruceTree extends Tree{
	private const FOLIAGES = [
		[1, 0, 0, 1, 2, 1, 1, 2, 3, 2, 2, 3, 4, 3],
		[1, 0, 1, 2, 1, 2, 1, 1, 2, 3, 2, 2, 3, 4, 3],
		[1, 2, 3],
		[1, 2, 1, 3, 2, 4, 3],
	];

	public function __construct(){
		parent::__construct(VanillaBlocks::SPRUCE_LOG(), VanillaBlocks::SPRUCE_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$height = 24 + $random->nextBoundedInt(8);
		if($y < 1 || $y + $height + 5 >= 256){
			return null;
		}
		$ground = $world->getBlockAt($x, $y - 1, $z);
		if(!$ground->hasTypeTag(BlockTypeTags::DIRT)){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$midX = $x + 1;
		$midZ = $z + 1;
		for($dx = -7; $dx <= 6; ++$dx){
			for($dz = -7; $dz <= 6; ++$dz){
				$calcX = $dx + 0.5;
				$calcZ = $dz + 0.5;
				if($calcX * $calcX + $calcZ * $calcZ < 6.8 * 6.8){
					$this->placePodzolAt($world, $transaction, $midX + $dx, $midZ + $dz);
				}
			}
		}

		$leafRadii = self::FOLIAGES[$random->nextBoundedInt(count(self::FOLIAGES))];
		foreach([[0, 0], [1, 0], [0, 1], [1, 1]] as [$dx, $dz]){
			$this->placeLeafAt($transaction, $x + $dx, $y + $height + 1, $z + $dz);
		}

		for($dy = $height; $dy >= 0; --$dy){
			foreach([[0, 0], [1, 0], [0, 1], [1, 1]] as [$dx, $dz]){
				if($this->canOverride($transaction->fetchBlockAt($x + $dx, $y + $dy, $z + $dz))){
					$transaction->addBlockAt($x + $dx, $y + $dy, $z + $dz, $this->trunkBlock);
				}
			}
			$index = $height - $dy;
			if(isset($leafRadii[$index])){
				$radius = $leafRadii[$index];
				for($lx = -$radius - 1; $lx <= $radius; ++$lx){
					for($lz = -$radius - 1; $lz <= $radius; ++$lz){
						$calcX = $lx + 0.5;
						$calcZ = $lz + 0.5;
						if($calcX * $calcX + $calcZ * $calcZ < ($radius + 0.7) * ($radius + 0.7)){
							$this->placeLeafAt($transaction, $midX + $lx, $y + $dy, $midZ + $lz);
						}
					}
				}
			}
		}

		return $transaction;
	}

	private function placePodzolAt(ChunkManager $world, BlockTransaction $transaction, int $x, int $z) : void{
		for($y = 255; $y >= 0; --$y){
			$block = $world->getBlockAt($x, $y, $z);
			if(!$block->canBeReplaced()){
				if($block->hasTypeTag(BlockTypeTags::DIRT)){
					$transaction->addBlockAt($x, $y, $z, VanillaBlocks::PODZOL());
				}
				return;
			}
		}
	}

	private function placeLeafAt(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		$block = $transaction->fetchBlockAt($x, $y, $z);
		if($block->canBeReplaced() || $block instanceof \pocketmine\block\Leaves){
			$transaction->addBlockAt($x, $y, $z, $this->leafBlock);
		}
	}
}
