<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;
use function cos;
use function sin;

final class BigJungleTree extends Tree{
	public function __construct(){
		parent::__construct(VanillaBlocks::JUNGLE_LOG(), VanillaBlocks::JUNGLE_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$height = 10 + $random->nextBoundedInt(20);
		$this->treeHeight = $height;
		if($y < 1 || $y + $height + 2 >= 256 || !$this->canPlaceObject($world, $x, $y, $z, $random)){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());
		$this->createCrown($transaction, $x, $y + $height, $z, 2);

		for($branchY = $y + $height - 2 - $random->nextBoundedInt(4); $branchY > $y + $height / 2; $branchY -= 2 + $random->nextBoundedInt(4)){
			$angle = $random->nextFloat() * M_PI * 2.0;
			$branchX = $x;
			$branchZ = $z;
			for($i = 0; $i < 5; ++$i){
				$branchX = (int) ($x + (1.5 + cos($angle) * $i));
				$branchZ = (int) ($z + (1.5 + sin($angle) * $i));
				$transaction->addBlockAt($branchX, $branchY - 3 + intdiv($i, 2), $branchZ, $this->trunkBlock);
			}
			$topOffset = 1 + $random->nextBoundedInt(2);
			for($leafY = $branchY - $topOffset; $leafY <= $branchY; ++$leafY){
				$this->growLeavesLayer($transaction, $branchX, $leafY, $branchZ, 1 - ($leafY - $branchY));
			}
		}

		for($dy = 0; $dy < $height; ++$dy){
			foreach([[0, 0], [1, 0], [1, 1], [0, 1]] as $index => [$dx, $dz]){
				if($dy >= $height - 1 && $index > 0){
					continue;
				}
				if($this->canOverride($transaction->fetchBlockAt($x + $dx, $y + $dy, $z + $dz))){
					$transaction->addBlockAt($x + $dx, $y + $dy, $z + $dz, $this->trunkBlock);
					if($dy > 0){
						$this->maybePlaceVine($transaction, $random, $x + $dx - 1, $y + $dy, $z + $dz, Facing::EAST);
						$this->maybePlaceVine($transaction, $random, $x + $dx, $y + $dy, $z + $dz - 1, Facing::SOUTH);
						$this->maybePlaceVine($transaction, $random, $x + $dx + 1, $y + $dy, $z + $dz, Facing::WEST);
						$this->maybePlaceVine($transaction, $random, $x + $dx, $y + $dy, $z + $dz + 1, Facing::NORTH);
					}
				}
			}
		}

		return $transaction;
	}

	public function canPlaceObject(ChunkManager $world, int $x, int $y, int $z, Random $random) : bool{
		for($dy = 0; $dy <= $this->treeHeight + 1; ++$dy){
			$radius = $dy === 0 ? 0 : ($dy >= $this->treeHeight - 2 ? 2 : 1);
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

	private function createCrown(BlockTransaction $transaction, int $x, int $y, int $z, int $radius) : void{
		for($dy = -2; $dy <= 0; ++$dy){
			$this->growLeavesLayer($transaction, $x, $y + $dy, $z, $radius + 1 - $dy);
		}
	}

	private function growLeavesLayer(BlockTransaction $transaction, int $x, int $y, int $z, int $radius) : void{
		for($dx = -$radius; $dx <= $radius; ++$dx){
			for($dz = -$radius; $dz <= $radius; ++$dz){
				if(abs($dx) === $radius && abs($dz) === $radius){
					continue;
				}
				$block = $transaction->fetchBlockAt($x + $dx, $y, $z + $dz);
				if($block->canBeReplaced() || $block instanceof Leaves){
					$transaction->addBlockAt($x + $dx, $y, $z + $dz, $this->leafBlock);
				}
			}
		}
	}

	private function maybePlaceVine(BlockTransaction $transaction, Random $random, int $x, int $y, int $z, int $attachedTo) : void{
		if($random->nextBoundedInt(3) > 0 && $transaction->fetchBlockAt($x, $y, $z)->canBeReplaced()){
			$transaction->addBlockAt($x, $y, $z, VanillaBlocks::VINES()->setFace($attachedTo, true));
		}
	}
}
