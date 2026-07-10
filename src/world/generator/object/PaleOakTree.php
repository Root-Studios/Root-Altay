<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;

final class PaleOakTree extends Tree{
	public function __construct(){
		parent::__construct(VanillaBlocks::PALE_OAK_LOG(), VanillaBlocks::PALE_OAK_LEAVES(), 6);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		if($random->nextBoundedInt(4) === 0){
			return $this->getSmallTreeTransaction($world, $x, $y, $z, $random);
		}

		$this->treeHeight = 6;
		if(!$this->canPlaceLargeObject($world, $x, $y, $z)){
			return null;
		}

		$transaction = new BlockTransaction($world);
		foreach([[0, 0], [1, 0], [0, 1], [1, 1]] as [$dx, $dz]){
			$transaction->addBlockAt($x + $dx, $y - 1, $z + $dz, VanillaBlocks::DIRT());
		}

		$direction = Facing::HORIZONTAL[$random->nextBoundedInt(4)];
		$bendStart = $this->treeHeight - $random->nextBoundedInt(4);
		$bendLength = 2 - $random->nextBoundedInt(3);
		$trunkX = $x;
		$trunkZ = $z;
		for($dy = 0; $dy < $this->treeHeight; ++$dy){
			if($dy >= $bendStart && $bendLength > 0){
				$trunkX += Facing::OFFSET[$direction][0];
				$trunkZ += Facing::OFFSET[$direction][2];
				--$bendLength;
			}
			foreach([[0, 0], [1, 0], [0, 1], [1, 1]] as [$dx, $dz]){
				if($this->canOverride($transaction->fetchBlockAt($trunkX + $dx, $y + $dy, $trunkZ + $dz))){
					$transaction->addBlockAt($trunkX + $dx, $y + $dy, $trunkZ + $dz, $this->trunkBlock);
				}
			}
		}

		$topY = $y + $this->treeHeight - 1;
		$this->placeDarkOakLikeLeaves($transaction, $random, $x, $z, $trunkX, $topY, $trunkZ);
		return $transaction;
	}

	private function getSmallTreeTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->treeHeight = 4 + $random->nextBoundedInt(3);
		if(!parent::canPlaceObject($world, $x, $y, $z, $random)){
			return null;
		}
		$transaction = new BlockTransaction($world);
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());
		$this->placeTrunk($x, $y, $z, $random, $this->treeHeight, $transaction);
		parent::placeCanopy($x, $y, $z, $random, $transaction);
		return $transaction;
	}

	private function canPlaceLargeObject(ChunkManager $world, int $x, int $y, int $z) : bool{
		for($dy = 0; $dy <= $this->treeHeight + 1; ++$dy){
			$radius = $dy === 0 ? 0 : ($dy >= $this->treeHeight - 1 ? 2 : 1);
			for($dx = -$radius; $dx <= $radius + 1; ++$dx){
				for($dz = -$radius; $dz <= $radius + 1; ++$dz){
					if(!$this->canOverride($world->getBlockAt($x + $dx, $y + $dy, $z + $dz))){
						return false;
					}
				}
			}
		}
		return true;
	}

	private function placeDarkOakLikeLeaves(BlockTransaction $transaction, Random $random, int $baseX, int $baseZ, int $trunkX, int $topY, int $trunkZ) : void{
		for($i3 = -2; $i3 <= 0; ++$i3){
			for($l3 = -2; $l3 <= 0; ++$l3){
				$this->placeLeafAt($transaction, $trunkX + $i3, $topY - 1, $trunkZ + $l3);
				$this->placeLeafAt($transaction, 1 + $trunkX - $i3, $topY - 1, $trunkZ + $l3);
				$this->placeLeafAt($transaction, $trunkX + $i3, $topY - 1, 1 + $trunkZ - $l3);
				$this->placeLeafAt($transaction, 1 + $trunkX - $i3, $topY - 1, 1 + $trunkZ - $l3);
				if(($i3 > -2 || $l3 > -1) && ($i3 !== -1 || $l3 !== -2)){
					$this->placeLeafAt($transaction, $trunkX + $i3, $topY + 1, $trunkZ + $l3);
					$this->placeLeafAt($transaction, 1 + $trunkX - $i3, $topY + 1, $trunkZ + $l3);
					$this->placeLeafAt($transaction, $trunkX + $i3, $topY + 1, 1 + $trunkZ - $l3);
					$this->placeLeafAt($transaction, 1 + $trunkX - $i3, $topY + 1, 1 + $trunkZ - $l3);
				}
			}
		}
		if($random->nextBoolean()){
			foreach([[0, 0], [1, 0], [1, 1], [0, 1]] as [$dx, $dz]){
				$this->placeLeafAt($transaction, $trunkX + $dx, $topY + 2, $trunkZ + $dz);
			}
		}
		for($dx = -3; $dx <= 4; ++$dx){
			for($dz = -3; $dz <= 4; ++$dz){
				if(($dx !== -3 || $dz !== -3) && ($dx !== -3 || $dz !== 4) && ($dx !== 4 || $dz !== -3) && ($dx !== 4 || $dz !== 4) && (abs($dx) < 3 || abs($dz) < 3)){
					$this->placeLeafAt($transaction, $trunkX + $dx, $topY, $trunkZ + $dz);
				}
			}
		}
		for($branchX = -1; $branchX <= 2; ++$branchX){
			for($branchZ = -1; $branchZ <= 2; ++$branchZ){
				if(($branchX < 0 || $branchX > 1 || $branchZ < 0 || $branchZ > 1) && $random->nextBoundedInt(3) === 0){
					$length = $random->nextBoundedInt(3) + 2;
					for($i = 0; $i < $length; ++$i){
						$transaction->addBlockAt($baseX + $branchX, $topY - $i - 1, $baseZ + $branchZ, $this->trunkBlock);
					}
					for($leafX = -1; $leafX <= 1; ++$leafX){
						for($leafZ = -1; $leafZ <= 1; ++$leafZ){
							$this->placeLeafAt($transaction, $trunkX + $branchX + $leafX, $topY, $trunkZ + $branchZ + $leafZ);
						}
					}
					for($leafX = -2; $leafX <= 2; ++$leafX){
						for($leafZ = -2; $leafZ <= 2; ++$leafZ){
							if(abs($leafX) !== 2 || abs($leafZ) !== 2){
								$this->placeLeafAt($transaction, $trunkX + $branchX + $leafX, $topY - 1, $trunkZ + $branchZ + $leafZ);
							}
						}
					}
				}
			}
		}
	}

	private function placeLeafAt(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		if($this->canOverride($transaction->fetchBlockAt($x, $y, $z))){
			$transaction->addBlockAt($x, $y, $z, $this->leafBlock);
		}
	}
}
