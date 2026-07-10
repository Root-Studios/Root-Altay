<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\Block;
use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;

final class MangroveTree extends Tree{
	private const ROOT_WIDTH_LIMIT = 8;
	private const ROOT_LENGTH_LIMIT = 15;
	private const LEAF_RADIUS = 3;
	private const LEAF_HEIGHT = 2;
	private const LEAF_PLACEMENT_ATTEMPTS = 70;

	public function __construct(){
		parent::__construct(VanillaBlocks::MANGROVE_LOG(), VanillaBlocks::MANGROVE_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$tall = $random->nextFloat() > 0.15;
		$properties = $tall ? [4, 1, 9, 1, 6, 0, 1, 3, 7] : [2, 1, 4, 1, 4, 0, 1, 1, 3];
		[$baseHeight, $heightRandA, $heightRandB, $branchStepsMin, $branchStepsMax, $branchLengthMin, $branchLengthMax, $rootOffsetMin, $rootOffsetMax] = $properties;
		$trunkOffsetY = $rootOffsetMin + $random->nextBoundedInt($rootOffsetMax - $rootOffsetMin + 1);
		$trunkY = $y + $trunkOffsetY;
		$height = $baseHeight + $random->nextBoundedInt($heightRandA + 1) + $random->nextBoundedInt($heightRandB + 1);
		if($trunkY + $height + self::LEAF_HEIGHT + 2 >= 256){
			return null;
		}

		$transaction = new BlockTransaction($world);
		if(!$this->placeRoots($world, $transaction, $random, $x, $y, $z, $trunkY)){
			return null;
		}

		$attachments = $this->placeMangroveTrunk($transaction, $random, $x, $trunkY, $z, $height, $branchStepsMin, $branchStepsMax, $branchLengthMin, $branchLengthMax);
		foreach($attachments as [$fx, $fy, $fz]){
			$this->createRandomSpreadFoliage($transaction, $random, $fx, $fy, $fz);
		}
		$this->placeLeafVinesAndPropagules($transaction, $random, $attachments);
		return $transaction;
	}

	private function placeMangroveTrunk(BlockTransaction $transaction, Random $random, int $x, int $y, int $z, int $height, int $branchStepsMin, int $branchStepsMax, int $branchLengthMin, int $branchLengthMax) : array{
		$attachments = [];
		for($dy = 0; $dy < $height; ++$dy){
			$currentY = $y + $dy;
			if($this->placeLog($transaction, $x, $currentY, $z) && $dy < $height - 1 && $random->nextFloat() < 0.5){
				$dir = Facing::HORIZONTAL[$random->nextBoundedInt(4)];
				$branchLen = $branchLengthMin + $random->nextBoundedInt($branchLengthMax - $branchLengthMin + 1);
				$branchPos = max(0, $branchLen - ($branchLengthMin + $random->nextBoundedInt($branchLengthMax - $branchLengthMin + 1)) - 1);
				$branchSteps = $branchStepsMin + $random->nextBoundedInt($branchStepsMax - $branchStepsMin + 1);
				$this->placeBranch($transaction, $height, $attachments, $x, $currentY, $z, $dir, $branchPos, $branchSteps);
			}
			if($dy === $height - 1){
				$attachments[] = [$x, $currentY + 1, $z];
			}
		}
		return $attachments;
	}

	private function placeBranch(BlockTransaction $transaction, int $height, array &$attachments, int $x, int $y, int $z, int $dir, int $branchPos, int $steps) : void{
		$heightAlongBranch = $y + $branchPos;
		$logX = $x;
		$logZ = $z;
		$index = $branchPos;
		while($index < $height && $steps > 0){
			if($index >= 1){
				$placementY = $y + $index;
				$logX += Facing::OFFSET[$dir][0];
				$logZ += Facing::OFFSET[$dir][2];
				if($this->placeLog($transaction, $logX, $placementY, $logZ)){
					$heightAlongBranch = $placementY + 1;
				}
				$attachments[] = [$logX, $placementY, $logZ];
			}
			++$index;
			--$steps;
		}
		if($heightAlongBranch - $y > 1){
			$attachments[] = [$logX, $heightAlongBranch, $logZ];
			$attachments[] = [$logX, $heightAlongBranch - 2, $logZ];
		}
	}

	private function placeRoots(ChunkManager $world, BlockTransaction $transaction, Random $random, int $x, int $y, int $z, int $trunkY) : bool{
		for($yy = $y; $yy < $trunkY; ++$yy){
			if(!$this->canPlaceRoot($world->getBlockAt($x, $yy, $z))){
				return false;
			}
		}
		$roots = [[$x, $trunkY - 1, $z]];
		foreach(Facing::HORIZONTAL as $dir){
			$rootX = $x + Facing::OFFSET[$dir][0];
			$rootZ = $z + Facing::OFFSET[$dir][2];
			$positions = [];
			if(!$this->simulateRoots($world, $random, $rootX, $trunkY, $rootZ, $dir, $x, $trunkY, $z, $positions, 0)){
				return false;
			}
			$roots[] = [$rootX, $trunkY, $rootZ];
			foreach($positions as $pos){
				$roots[] = $pos;
			}
		}
		foreach($roots as [$rx, $ry, $rz]){
			$transaction->addBlockAt($rx, $ry, $rz, VanillaBlocks::MANGROVE_ROOTS());
		}
		return true;
	}

	private function simulateRoots(ChunkManager $world, Random $random, int $x, int $y, int $z, int $dir, int $originX, int $originY, int $originZ, array &$roots, int $layer) : bool{
		if($layer === self::ROOT_LENGTH_LIMIT || count($roots) > self::ROOT_LENGTH_LIMIT){
			return false;
		}
		foreach($this->potentialRootPositions($random, $x, $y, $z, $dir, $originX, $originY, $originZ) as [$px, $py, $pz]){
			if($this->canPlaceRoot($world->getBlockAt($px, $py, $pz))){
				$roots[] = [$px, $py, $pz];
				if(!$this->simulateRoots($world, $random, $px, $py, $pz, $dir, $originX, $originY, $originZ, $roots, $layer + 1)){
					return false;
				}
			}
		}
		return true;
	}

	private function potentialRootPositions(Random $random, int $x, int $y, int $z, int $dir, int $originX, int $originY, int $originZ) : array{
		$width = abs($x - $originX) + abs($y - $originY) + abs($z - $originZ);
		$below = [$x, $y - 1, $z];
		$next = [$x + Facing::OFFSET[$dir][0], $y, $z + Facing::OFFSET[$dir][2]];
		if($width > self::ROOT_WIDTH_LIMIT - 3 && $width <= self::ROOT_WIDTH_LIMIT){
			return $random->nextFloat() < 0.2 ? [$below, [$next[0], $next[1] - 1, $next[2]]] : [$below];
		}
		if($width > self::ROOT_WIDTH_LIMIT || $random->nextFloat() < 0.2){
			return [$below];
		}
		return $random->nextBoolean() ? [$next] : [$below];
	}

	private function createRandomSpreadFoliage(BlockTransaction $transaction, Random $random, int $x, int $y, int $z) : void{
		for($i = 0; $i < self::LEAF_PLACEMENT_ATTEMPTS; ++$i){
			$this->placeLeaf($transaction, $x + $random->nextBoundedInt(self::LEAF_RADIUS) - $random->nextBoundedInt(self::LEAF_RADIUS), $y + $random->nextBoundedInt(self::LEAF_HEIGHT) - $random->nextBoundedInt(self::LEAF_HEIGHT), $z + $random->nextBoundedInt(self::LEAF_RADIUS) - $random->nextBoundedInt(self::LEAF_RADIUS));
		}
	}

	private function placeLeafVinesAndPropagules(BlockTransaction $transaction, Random $random, array $attachments) : void{
		$leaves = [];
		foreach($attachments as [$x, $y, $z]){
			for($lx = $x - self::LEAF_RADIUS + 1; $lx <= $x + self::LEAF_RADIUS - 1; ++$lx){
				for($ly = $y - self::LEAF_HEIGHT + 1; $ly <= $y + self::LEAF_HEIGHT - 1; ++$ly){
					for($lz = $z - self::LEAF_RADIUS + 1; $lz <= $z + self::LEAF_RADIUS - 1; ++$lz){
						if($transaction->fetchBlockAt($lx, $ly, $lz)->getTypeId() === $this->leafBlock->getTypeId()){
							$leaves["$lx:$ly:$lz"] = [$lx, $ly, $lz];
						}
					}
				}
			}
		}
		$blacklist = [];
		foreach($leaves as [$lx, $ly, $lz]){
			$this->maybePlaceVine($transaction, $random, $lx - 1, $ly, $lz, Facing::EAST);
			$this->maybePlaceVine($transaction, $random, $lx + 1, $ly, $lz, Facing::WEST);
			$this->maybePlaceVine($transaction, $random, $lx, $ly, $lz - 1, Facing::SOUTH);
			$this->maybePlaceVine($transaction, $random, $lx, $ly, $lz + 1, Facing::NORTH);
			$key = $lx . ':' . ($ly - 1) . ':' . $lz;
			if(!isset($blacklist[$key]) && $random->nextFloat() < 0.14 && $transaction->fetchBlockAt($lx, $ly - 1, $lz)->canBeReplaced() && $transaction->fetchBlockAt($lx, $ly - 2, $lz)->canBeReplaced()){
				$transaction->addBlockAt($lx, $ly - 1, $lz, VanillaBlocks::MANGROVE_PROPAGULE()->setHanging(true)->setStage($random->nextBoundedInt(5)));
				for($bx = $lx - 1; $bx <= $lx + 1; ++$bx){
					for($bz = $lz - 1; $bz <= $lz + 1; ++$bz){
						$blacklist["$bx:" . ($ly - 1) . ":$bz"] = true;
					}
				}
			}
		}
	}

	private function placeLog(BlockTransaction $transaction, int $x, int $y, int $z) : bool{
		if(!$this->canOverride($transaction->fetchBlockAt($x, $y, $z))){
			return false;
		}
		$transaction->addBlockAt($x, $y, $z, $this->trunkBlock);
		return true;
	}

	private function placeLeaf(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		$block = $transaction->fetchBlockAt($x, $y, $z);
		if($block->canBeReplaced() || $block instanceof Leaves){
			$transaction->addBlockAt($x, $y, $z, $this->leafBlock);
		}
	}

	private function maybePlaceVine(BlockTransaction $transaction, Random $random, int $x, int $y, int $z, int $attachedTo) : void{
		if($random->nextFloat() < 0.125 && $transaction->fetchBlockAt($x, $y, $z)->canBeReplaced()){
			$transaction->addBlockAt($x, $y, $z, VanillaBlocks::VINES()->setFace($attachedTo, true));
			for($length = 0, $vy = $y - 1; $length < 4 && $transaction->fetchBlockAt($x, $vy, $z)->canBeReplaced(); ++$length, --$vy){
				$transaction->addBlockAt($x, $vy, $z, VanillaBlocks::VINES()->setFace($attachedTo, true));
			}
		}
	}

	private function canPlaceRoot(Block $block) : bool{
		return $block->canBeReplaced() || $block instanceof Leaves || $block->getTypeId() === VanillaBlocks::MANGROVE_ROOTS()->getTypeId() || $block->getTypeId() === VanillaBlocks::MUDDY_MANGROVE_ROOTS()->getTypeId();
	}
}
