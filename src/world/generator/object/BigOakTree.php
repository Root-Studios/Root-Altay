<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;
use function cos;
use function floor;
use function max;
use function min;
use function pow;
use function sin;
use function sqrt;

final class BigOakTree extends Tree{
	private const TRUNK_SCALE = 0.618;
	private const CLUSTER_DENSITY = 1.382;
	private const BRANCH_SLOPE = 0.381;
	private const BRANCH_LENGTH = 0.328;
	private const FOLIAGE_HEIGHT = 4;
	private const FOLIAGE_RADIUS = 2;
	private const FOLIAGE_OFFSET = 4;

	public function __construct(){
		parent::__construct(VanillaBlocks::OAK_LOG(), VanillaBlocks::OAK_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$height = $random->nextBoundedInt(12) + 5;
		if($y < 1 || $y + $height + 3 >= 256){
			return null;
		}
		$ground = $world->getBlockAt($x, $y - 1, $z);
		if(!$ground->hasTypeTag(\pocketmine\block\BlockTypeTags::DIRT)){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$trunkHeight = (int) floor(($height + 2) * self::TRUNK_SCALE);
		$clustersPerY = min(1, (int) floor(self::CLUSTER_DENSITY + pow(($height + 2) / 13.0, 2.0)));
		$trunkTop = $y + $trunkHeight;
		$relativeY = $height - 3;
		$foliage = [[[ $x, $y + $relativeY, $z ], $trunkTop]];

		for(; $relativeY >= 0; --$relativeY){
			$shape = $this->treeShape($height + 2, $relativeY);
			if($shape < 0.0){
				continue;
			}
			for($i = 0; $i < $clustersPerY; ++$i){
				$radius = $shape * ($random->nextFloat() + self::BRANCH_LENGTH);
				$angle = $random->nextFloat() * 2.0 * M_PI;
				$checkX = $x + (int) floor($radius * sin($angle) + 0.5);
				$checkZ = $z + (int) floor($radius * cos($angle) + 0.5);
				$checkY = $y + $relativeY - 1;
				if($this->makeLimb($transaction, $checkX, $checkY, $checkZ, $checkX, $checkY + 5, $checkZ, false)){
					$dx = $x - $checkX;
					$dz = $z - $checkZ;
					$branchHeight = $checkY - sqrt($dx * $dx + $dz * $dz) * self::BRANCH_SLOPE;
					$branchTop = $branchHeight > $trunkTop ? $trunkTop : (int) $branchHeight;
					if($this->makeLimb($transaction, $x, $branchTop, $z, $checkX, $checkY, $checkZ, false)){
						$foliage[] = [[ $checkX, $checkY, $checkZ ], $branchTop];
					}
				}
			}
		}

		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());
		$this->makeLimb($transaction, $x, $y, $z, $x, $y + $trunkHeight, $z, true);
		foreach($foliage as [$end, $branchBase]){
			[$fx, $fy, $fz] = $end;
			if($this->trimBranches($height + 2, $branchBase - $y)){
				$this->makeLimb($transaction, $x, $branchBase, $z, $fx, $fy, $fz, true);
				$this->createFoliage($transaction, $fx, $fy, $fz);
			}
		}

		return $transaction;
	}

	private function makeLimb(BlockTransaction $transaction, int $sx, int $sy, int $sz, int $ex, int $ey, int $ez, bool $place) : bool{
		$dx = $ex - $sx;
		$dy = $ey - $sy;
		$dz = $ez - $sz;
		$steps = max(abs($dx), abs($dy), abs($dz));
		if($steps === 0){
			return true;
		}
		$xStep = $dx / $steps;
		$yStep = $dy / $steps;
		$zStep = $dz / $steps;
		for($i = 0; $i <= $steps; ++$i){
			$px = $sx + (int) floor(0.5 + $i * $xStep);
			$py = $sy + (int) floor(0.5 + $i * $yStep);
			$pz = $sz + (int) floor(0.5 + $i * $zStep);
			if($place){
				if($this->canOverride($transaction->fetchBlockAt($px, $py, $pz))){
					$axis = Axis::Y;
					$xdiff = abs($px - $sx);
					$zdiff = abs($pz - $sz);
					if(max($xdiff, $zdiff) > 0){
						$axis = $xdiff === max($xdiff, $zdiff) ? Axis::X : Axis::Z;
					}
					$transaction->addBlockAt($px, $py, $pz, VanillaBlocks::OAK_LOG()->setAxis($axis));
				}
			}elseif(!$this->canOverride($transaction->fetchBlockAt($px, $py, $pz))){
				return false;
			}
		}
		return true;
	}

	private function createFoliage(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		for($yo = self::FOLIAGE_OFFSET; $yo >= self::FOLIAGE_OFFSET - self::FOLIAGE_HEIGHT; --$yo){
			$radius = self::FOLIAGE_RADIUS + ($yo !== self::FOLIAGE_OFFSET && $yo !== self::FOLIAGE_OFFSET - self::FOLIAGE_HEIGHT ? 1 : 0);
			for($dx = -$radius; $dx <= $radius; ++$dx){
				for($dz = -$radius; $dz <= $radius; ++$dz){
					if(($dx + 0.5) * ($dx + 0.5) + ($dz + 0.5) * ($dz + 0.5) <= $radius * $radius){
						$block = $transaction->fetchBlockAt($x + $dx, $y + $yo, $z + $dz);
						if(!$block->isSolid() || $block instanceof Leaves){
							$transaction->addBlockAt($x + $dx, $y + $yo, $z + $dz, $this->leafBlock);
						}
					}
				}
			}
		}
	}

	private function treeShape(int $height, int $y) : float{
		if($y < $height * 0.3){
			return -1.0;
		}
		$radius = $height / 2.0;
		$adjacent = $radius - $y;
		if($adjacent === 0.0){
			return $radius * 0.5;
		}
		if(abs((int) $adjacent) >= $radius){
			return 0.0;
		}
		return (float) (sqrt($radius * $radius - $adjacent * $adjacent) * 0.5);
	}

	private function trimBranches(int $height, int $localY) : bool{
		return $localY >= $height * 0.2;
	}
}
