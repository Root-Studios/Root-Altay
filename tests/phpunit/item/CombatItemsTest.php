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

use PHPUnit\Framework\TestCase;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;

final class CombatItemsTest extends TestCase{
	public function testMaceSmashDamage() : void{
		$mace = VanillaItems::MACE();
		self::assertSame(0.0, $mace->getSmashDamageBonus(1.49));
		self::assertSame(12.0, $mace->getSmashDamageBonus(3.0));
		self::assertSame(22.0, $mace->getSmashDamageBonus(8.0));
		self::assertSame(24.0, $mace->getSmashDamageBonus(10.0));

		$mace->addEnchantment(new EnchantmentInstance(VanillaEnchantments::DENSITY(), 5));
		self::assertSame(49.0, $mace->getSmashDamageBonus(10.0));
	}

	public function testBreachArmorReduction() : void{
		$mace = VanillaItems::MACE();
		$mace->addEnchantment(new EnchantmentInstance(VanillaEnchantments::BREACH(), 4));
		self::assertSame(0.6, $mace->getArmorEffectivenessReduction());
		self::assertFalse(VanillaEnchantments::BREACH()->isCompatibleWith(VanillaEnchantments::DENSITY()));
	}

	public function testSpearStats() : void{
		$expectedAttackPoints = [2, 3, 3, 4, 2, 5, 6];
		$expectedDurability = [60, 130, 190, 250, 30, 1560, 2030];
		$spears = [
			VanillaItems::WOODEN_SPEAR(),
			VanillaItems::STONE_SPEAR(),
			VanillaItems::COPPER_SPEAR(),
			VanillaItems::IRON_SPEAR(),
			VanillaItems::GOLDEN_SPEAR(),
			VanillaItems::DIAMOND_SPEAR(),
			VanillaItems::NETHERITE_SPEAR()
		];

		foreach($spears as $index => $spear){
			self::assertSame($expectedAttackPoints[$index], $spear->getAttackPoints());
			self::assertSame($expectedDurability[$index], $spear->getMaxDurability());
			self::assertContains(VanillaEnchantments::LUNGE(), AvailableEnchantmentRegistry::getInstance()->getPrimaryEnchantmentsForItem($spear));
			self::assertContains(VanillaEnchantments::LOOTING(), AvailableEnchantmentRegistry::getInstance()->getPrimaryEnchantmentsForItem($spear));
		}
	}

	public function testNewEnchantmentNetworkIds() : void{
		$map = EnchantmentIdMap::getInstance();
		self::assertSame(VanillaEnchantments::WIND_BURST(), $map->fromId(EnchantmentIds::WIND_BURST));
		self::assertSame(VanillaEnchantments::DENSITY(), $map->fromId(EnchantmentIds::DENSITY));
		self::assertSame(VanillaEnchantments::BREACH(), $map->fromId(EnchantmentIds::BREACH));
		self::assertSame(VanillaEnchantments::LUNGE(), $map->fromId(EnchantmentIds::LUNGE));
		self::assertSame(VanillaEnchantments::LOOTING(), $map->fromId(EnchantmentIds::LOOTING));
	}
}
