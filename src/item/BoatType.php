<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\block\utils\WoodType;
use pocketmine\utils\LegacyEnumShimTrait;
use function strtolower;

/**
 * TODO: These tags need to be removed once we get rid of LegacyEnumShimTrait (PM6)
 *  These are retained for backwards compatibility only.
 *
 * @method static BoatType ACACIA()
 * @method static BoatType BIRCH()
 * @method static BoatType CHERRY()
 * @method static BoatType DARK_OAK()
 * @method static BoatType JUNGLE()
 * @method static BoatType MANGROVE()
 * @method static BoatType OAK()
 * @method static BoatType PALE_OAK()
 * @method static BoatType SPRUCE()
 * @method static BoatType BAMBOO()
 */
enum BoatType{
	use LegacyEnumShimTrait;

	case OAK;
	case SPRUCE;
	case BIRCH;
	case JUNGLE;
	case ACACIA;
	case DARK_OAK;
	case MANGROVE;
	case CHERRY;
	case PALE_OAK;
	case BAMBOO;

	public function getWoodType() : WoodType{
		return match($this){
			self::OAK => WoodType::OAK,
			self::SPRUCE => WoodType::SPRUCE,
			self::BIRCH => WoodType::BIRCH,
			self::JUNGLE => WoodType::JUNGLE,
			self::ACACIA => WoodType::ACACIA,
			self::DARK_OAK => WoodType::DARK_OAK,
			self::MANGROVE => WoodType::MANGROVE,
			self::CHERRY => WoodType::CHERRY,
			self::PALE_OAK => WoodType::PALE_OAK,
			self::BAMBOO => WoodType::BAMBOO,
		};
	}

	public function getItemName(bool $withChest = false) : string{
		if($this === self::BAMBOO){
			return $withChest ? "bamboo_chest_raft" : "bamboo_raft";
		}
		return strtolower($this->name) . ($withChest ? "_chest_boat" : "_boat");
	}

	public function getDisplayName() : string{
		return $this->getWoodType()->getDisplayName();
	}

	public function getVehicleDisplayName(bool $withChest = false) : string{
		$name = $this === self::BAMBOO ? "Raft" : "Boat";
		return $this->getDisplayName() . " " . $name . ($withChest ? " with Chest" : "");
	}

	public function getNetworkVariant() : int{
		return match($this){
			self::OAK => 0,
			self::SPRUCE => 1,
			self::BIRCH => 2,
			self::JUNGLE => 3,
			self::ACACIA => 4,
			self::DARK_OAK => 5,
			self::MANGROVE => 6,
			self::BAMBOO => 7,
			self::CHERRY => 8,
			self::PALE_OAK => 9,
		};
	}

	public static function fromNetworkVariant(int $variant) : ?self{
		return match($variant){
			0 => self::OAK,
			1 => self::SPRUCE,
			2 => self::BIRCH,
			3 => self::JUNGLE,
			4 => self::ACACIA,
			5 => self::DARK_OAK,
			6 => self::MANGROVE,
			7 => self::BAMBOO,
			8 => self::CHERRY,
			9 => self::PALE_OAK,
			default => null,
		};
	}
}
