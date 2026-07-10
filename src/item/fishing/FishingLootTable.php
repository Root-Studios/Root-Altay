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

namespace pocketmine\item\fishing;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\utils\Random;
use function max;
use function min;

/**
 * Vanilla-shaped fishing categories with deterministic, testable weighting.
 */
final class FishingLootTable{
	public const CATEGORY_FISH = "fish";
	public const CATEGORY_JUNK = "junk";
	public const CATEGORY_TREASURE = "treasure";

	/** @return array<string, float> */
	public static function getCategoryChances(int $luckOfTheSeaLevel) : array{
		$luck = max(0, min(3, $luckOfTheSeaLevel));
		$junk = max(0.0, 0.10 - 0.025 * $luck);
		$treasure = min(1.0, 0.05 + 0.01 * $luck);
		return [
			self::CATEGORY_FISH => 1.0 - $junk - $treasure,
			self::CATEGORY_JUNK => $junk,
			self::CATEGORY_TREASURE => $treasure,
		];
	}

	public static function roll(Random $random, int $luckOfTheSeaLevel) : Item{
		$chances = self::getCategoryChances($luckOfTheSeaLevel);
		$roll = $random->nextFloat();
		$category = match(true){
			$roll < $chances[self::CATEGORY_TREASURE] => self::CATEGORY_TREASURE,
			$roll < $chances[self::CATEGORY_TREASURE] + $chances[self::CATEGORY_JUNK] => self::CATEGORY_JUNK,
			default => self::CATEGORY_FISH,
		};
		return self::rollWeighted($random, self::getEntries($category));
	}

	/**
	 * @return array<int, array{int, \Closure() : Item}>
	 */
	private static function getEntries(string $category) : array{
		return match($category){
			self::CATEGORY_FISH => [
				[60, fn() => VanillaItems::RAW_FISH()],
				[25, fn() => VanillaItems::RAW_SALMON()],
				[13, fn() => VanillaItems::PUFFERFISH()],
				[2, fn() => VanillaItems::CLOWNFISH()],
			],
			self::CATEGORY_JUNK => [
				[10, fn() => VanillaItems::BOWL()],
				[10, fn() => VanillaItems::LEATHER()],
				[10, fn() => VanillaItems::LEATHER_BOOTS()],
				[10, fn() => VanillaItems::STICK()],
				[5, fn() => VanillaItems::STRING()],
				[10, fn() => VanillaItems::BONE()],
				[10, fn() => VanillaItems::INK_SAC()],
				[10, fn() => VanillaItems::ROTTEN_FLESH()],
			],
			self::CATEGORY_TREASURE => [
				[1, fn() => VanillaItems::BOW()],
				[1, fn() => VanillaItems::ENCHANTED_BOOK()],
				[1, fn() => VanillaItems::FISHING_ROD()],
				[1, fn() => VanillaItems::NAME_TAG()],
				[1, fn() => VanillaItems::NAUTILUS_SHELL()],
			],
			default => throw new \InvalidArgumentException("Unknown fishing loot category $category"),
		};
	}

	/**
	 * @param array<int, array{int, \Closure() : Item}> $entries
	 */
	private static function rollWeighted(Random $random, array $entries) : Item{
		$totalWeight = 0;
		foreach($entries as [$weight]){
			$totalWeight += $weight;
		}
		$roll = $random->nextBoundedInt($totalWeight);
		foreach($entries as [$weight, $factory]){
			if($roll < $weight){
				return $factory();
			}
			$roll -= $weight;
		}
		throw new \LogicException("Fishing loot table has no selectable entry");
	}
}
