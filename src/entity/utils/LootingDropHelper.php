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

namespace pocketmine\entity\utils;

use pocketmine\entity\Human;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\enchantment\VanillaEnchantments;
use function min;
use function mt_getrandmax;
use function mt_rand;

final class LootingDropHelper{
	public static function getLevel(Living $victim) : int{
		$lastDamageCause = $victim->getLastDamageCause();
		if(!$lastDamageCause instanceof EntityDamageByEntityEvent){
			return 0;
		}

		$damager = $lastDamageCause->getDamager();
		if(!$damager instanceof Human){
			return 0;
		}

		return $damager->getInventory()->getItemInHand()->getEnchantmentLevel(VanillaEnchantments::LOOTING());
	}

	/**
	 * Adds an equal-probability range of bonus drops for each Looting level.
	 */
	public static function discrete(Living $victim, int $min, int $maxBase, int $bonusPerLevel = 1) : int{
		if($maxBase < $min){
			throw new \InvalidArgumentException("Maximum base drop amount must be greater than or equal to minimum drop amount");
		}
		if($bonusPerLevel < 0){
			throw new \InvalidArgumentException("Bonus per Looting level must not be negative");
		}

		return mt_rand($min, $maxBase + self::getLevel($victim) * $bonusPerLevel);
	}

	/**
	 * Increases a rare-drop chance by a fixed amount for each Looting level.
	 */
	public static function bonusChanceFixed(Living $victim, float $chanceBase, float $addedChancePerLevel) : bool{
		$chance = min(1.0, $chanceBase + self::getLevel($victim) * $addedChancePerLevel);
		return mt_rand() / mt_getrandmax() < $chance;
	}
}
