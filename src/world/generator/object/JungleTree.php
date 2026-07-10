<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;
use function intdiv;

final class JungleTree extends Tree{

	public function __construct(){
		parent::__construct(VanillaBlocks::JUNGLE_LOG(), VanillaBlocks::JUNGLE_LEAVES(), 10);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		// PNX is constructed with min=4 and max=7: heights are therefore 4..10.
		$this->treeHeight = $random->nextBoundedInt(7) + 4;
		if(!$this->canPlaceObject($world, $x, $y, $z, $random)){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());
		$this->placeCanopy($x, $y, $z, $random, $transaction);
		$this->placeTrunk($x, $y, $z, $random, $this->treeHeight, $transaction);
		$this->placeHangingVines($x, $y, $z, $random, $transaction);
		return $transaction;
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		// Exact PNX small-jungle foliage profile: 3/3/2/2 radii from bottom to top.
		for($yy = $y - 3 + $this->treeHeight; $yy <= $y + $this->treeHeight; ++$yy){
			$offset = $yy - ($y + $this->treeHeight);
			$radius = 1 - intdiv($offset, 2);
			for($xx = $x - $radius; $xx <= $x + $radius; ++$xx){
				for($zz = $z - $radius; $zz <= $z + $radius; ++$zz){
					if(abs($xx - $x) === $radius && abs($zz - $z) === $radius && $offset !== 0 && $random->nextBoundedInt(2) !== 0){
						continue;
					}
					if($this->canOverride($transaction->fetchBlockAt($xx, $yy, $zz))){
						$transaction->addBlockAt($xx, $yy, $zz, $this->leafBlock);
					}
				}
			}
		}
	}

	protected function placeTrunk(int $x, int $y, int $z, Random $random, int $trunkHeight, BlockTransaction $transaction) : void{
		for($dy = 0; $dy < $trunkHeight; ++$dy){
			if($this->canOverride($transaction->fetchBlockAt($x, $y + $dy, $z))){
				$transaction->addBlockAt($x, $y + $dy, $z, $this->trunkBlock);
			}
		}
	}

	private function placeHangingVines(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		for($yy = $y - 3 + $this->treeHeight; $yy <= $y + $this->treeHeight; ++$yy){
			$radius = 2 - intdiv($yy - ($y + $this->treeHeight), 2);
			for($xx = $x - $radius; $xx <= $x + $radius; ++$xx){
				for($zz = $z - $radius; $zz <= $z + $radius; ++$zz){
					if($transaction->fetchBlockAt($xx, $yy, $zz)->getTypeId() !== $this->leafBlock->getTypeId()){
						continue;
					}
					foreach([[Facing::WEST, -1, 0], [Facing::EAST, 1, 0], [Facing::NORTH, 0, -1], [Facing::SOUTH, 0, 1]] as [$face, $dx, $dz]){
						if($random->nextBoundedInt(4) === 0){
							$this->placeVineColumn($transaction, $xx + $dx, $yy, $zz + $dz, Facing::opposite($face));
						}
					}
				}
			}
		}
	}

	private function placeVineColumn(BlockTransaction $transaction, int $x, int $y, int $z, int $face) : void{
		for($length = 0; $length < 5; ++$length){
			if(!$transaction->fetchBlockAt($x, $y - $length, $z)->canBeReplaced()){
				break;
			}
			$transaction->addBlockAt($x, $y - $length, $z, VanillaBlocks::VINES()->setFace($face, true));
		}
	}
}
