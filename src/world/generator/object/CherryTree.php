<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;
use function max;

final class CherryTree extends Tree{
	private const LEAVES_RADIUS = 4;

	public function __construct(){
		parent::__construct(VanillaBlocks::CHERRY_LOG(), VanillaBlocks::CHERRY_LEAVES(), 10);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		if($random->nextBoolean()){
			$transaction = $this->generateBigTree($world, $x, $y, $z, $random);
			if($transaction !== null){
				return $transaction;
			}
		}
		return $this->generateSmallTree($world, $x, $y, $z, $random);
	}

	private function generateBigTree(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$mainTrunkHeight = ($random->nextBoolean() ? 1 : 0) + 10;
		if(!$this->canPlaceHeight($world, $mainTrunkHeight, $x, $y, $z)){
			return null;
		}

		$onXAxis = $random->nextBoolean();
		$xMultiplier = $onXAxis ? 1 : 0;
		$zMultiplier = $onXAxis ? 0 : 1;
		$leftLength = $random->nextRange(2, 4);
		$leftHeight = $random->nextRange(3, 5);
		$leftStart = $random->nextRange(4, 5);
		if(!$this->canPlaceHeight($world, $leftHeight, $x - $leftLength * $xMultiplier, $y + $leftStart, $z - $leftLength * $zMultiplier)){
			$onXAxis = !$onXAxis;
			$xMultiplier = $onXAxis ? 1 : 0;
			$zMultiplier = $onXAxis ? 0 : 1;
			if(!$this->canPlaceHeight($world, $leftHeight, $x - $leftLength * $xMultiplier, $y + $leftStart, $z - $leftLength * $zMultiplier)){
				return null;
			}
		}

		$rightLength = $random->nextRange(2, 4);
		$rightHeight = $random->nextRange(3, 5);
		$rightStart = $random->nextRange(4, 5);
		if(!$this->canPlaceHeight($world, $rightHeight, $x + $rightLength * $xMultiplier, $y + $rightStart, $z + $rightLength * $zMultiplier)){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());
		for($dy = 0; $dy < $mainTrunkHeight; ++$dy){
			$transaction->addBlockAt($x, $y + $dy, $z, $this->log(Axis::Y));
		}
		$sideAxis = $onXAxis ? Axis::X : Axis::Z;
		$this->placeBigSideTrunk($transaction, $x, $y, $z, -1, $xMultiplier, $zMultiplier, $leftLength, $leftHeight, $leftStart, $sideAxis);
		$this->placeBigSideTrunk($transaction, $x, $y, $z, 1, $xMultiplier, $zMultiplier, $rightLength, $rightHeight, $rightStart, $sideAxis);

		$this->placeLeaves($transaction, $x, $y + $mainTrunkHeight + 1, $z, $random);
		$this->placeLeaves($transaction, $x - $leftLength * $xMultiplier, $y + $leftStart + $leftHeight + 1, $z - $leftLength * $zMultiplier, $random);
		$this->placeLeaves($transaction, $x + $rightLength * $xMultiplier, $y + $rightStart + $rightHeight + 1, $z + $rightLength * $zMultiplier, $random);
		return $transaction;
	}

	private function placeBigSideTrunk(BlockTransaction $transaction, int $x, int $y, int $z, int $sign, int $xMultiplier, int $zMultiplier, int $length, int $height, int $start, int $sideAxis) : void{
		$endX = $x + $sign * $length * $xMultiplier;
		$endZ = $z + $sign * $length * $zMultiplier;
		for($step = 1; $step <= $length; ++$step){
			$this->placeLogIfPossible($transaction, $x + $sign * $step * $xMultiplier, $y + $start, $z + $sign * $step * $zMultiplier, $sideAxis);
		}
		for($dy = 1; $dy < $height; ++$dy){
			$this->placeLogIfPossible($transaction, $endX, $y + $start + $dy, $endZ, Axis::Y);
		}

		// PNX moves the elbow upward for branches starting at y=4.
		if($start === 4){
			$transaction->addBlockAt($endX, $y + $start, $endZ, VanillaBlocks::AIR());
			$this->placeLogIfPossible($transaction, $endX - $sign * $xMultiplier, $y + $start + 1, $endZ - $sign * $zMultiplier, Axis::Y);
			$this->placeLogIfPossible($transaction, $endX, $y + $start + 1, $endZ, $sideAxis);
		}
	}

	private function generateSmallTree(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$mainTrunkHeight = ($random->nextBoolean() ? 1 : 0) + 4;
		$sideTrunkHeight = $random->nextRange(3, 5);
		if(!$this->canPlaceHeight($world, $mainTrunkHeight + 1, $x, $y, $z)){
			return null;
		}

		$directions = [[-1, 0], [1, 0], [0, -1], [0, 1]];
		$direction = $random->nextBoundedInt(4);
		$xMultiplier = 0;
		$zMultiplier = 0;
		$canPlace = false;
		for($i = 0; $i < 4; ++$i){
			$direction = ($direction + 1) % 4;
			[$xMultiplier, $zMultiplier] = $directions[$direction];
			if($this->canPlaceHeight($world, $sideTrunkHeight, $x + $xMultiplier * $sideTrunkHeight, $y, $z + $zMultiplier * $sideTrunkHeight)){
				$canPlace = true;
				break;
			}
		}
		if(!$canPlace){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());
		for($dy = 0; $dy < $mainTrunkHeight; ++$dy){
			$this->placeLogIfPossible($transaction, $x, $y + $dy, $z, Axis::Y);
		}
		$sideAxis = $xMultiplier === 0 ? Axis::Z : Axis::X;
		for($step = 1; $step <= $sideTrunkHeight; ++$step){
			$branchX = $x + $step * $xMultiplier;
			$branchY = $y + $mainTrunkHeight + $step - 2;
			$branchZ = $z + $step * $zMultiplier;
			$this->placeLogIfPossible($transaction, $branchX, $branchY, $branchZ, $sideAxis);
			if($step === $sideTrunkHeight - 1 && $sideTrunkHeight > 3){
				continue;
			}
			$this->placeLogIfPossible($transaction, $branchX, $branchY + 1, $branchZ, Axis::Y);
		}
		$this->placeLeaves($transaction, $x + $sideTrunkHeight * $xMultiplier, $y + $mainTrunkHeight + $sideTrunkHeight, $z + $sideTrunkHeight * $zMultiplier, $random);
		return $transaction;
	}

	private function placeLeaves(BlockTransaction $transaction, int $x, int $y, int $z, Random $random) : void{
		for($dy = -2; $dy <= 2; ++$dy){
			$radius = self::LEAVES_RADIUS - max(1, abs($dy));
			for($dx = -self::LEAVES_RADIUS; $dx <= self::LEAVES_RADIUS; ++$dx){
				for($dz = -self::LEAVES_RADIUS; $dz <= self::LEAVES_RADIUS; ++$dz){
					if($dx * $dx + $dz * $dz > $radius * $radius){
						continue;
					}
					$this->placeLeafIfPossible($transaction, $x + $dx, $y + $dy, $z + $dz);
					if($dy === -2 && $random->nextBoundedInt(3) === 0){
						$this->placeLeafIfPossible($transaction, $x + $dx, $y - 3, $z + $dz);
					}
				}
			}
		}
	}

	private function canPlaceHeight(ChunkManager $world, int $height, int $x, int $y, int $z) : bool{
		$radius = 0;
		for($dy = 0; $dy < $height + 3; ++$dy){
			if($dy === 1 || $dy === $height){
				++$radius;
			}
			for($dx = -$radius; $dx <= $radius; ++$dx){
				for($dz = -$radius; $dz <= $radius; ++$dz){
					if(!$this->canOverride($world->getBlockAt($x + $dx, $y + $dy, $z + $dz))){
						return false;
					}
				}
			}
		}
		return true;
	}

	private function placeLogIfPossible(BlockTransaction $transaction, int $x, int $y, int $z, int $axis) : void{
		if($this->canOverride($transaction->fetchBlockAt($x, $y, $z))){
			$transaction->addBlockAt($x, $y, $z, $this->log($axis));
		}
	}

	private function placeLeafIfPossible(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		if($this->canOverride($transaction->fetchBlockAt($x, $y, $z))){
			$transaction->addBlockAt($x, $y, $z, $this->leafBlock);
		}
	}

	private function log(int $axis) : \pocketmine\block\Wood{
		return VanillaBlocks::CHERRY_LOG()->setAxis($axis);
	}
}
