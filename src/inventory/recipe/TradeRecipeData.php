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

namespace pocketmine\inventory\recipe;

use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\utils\Utils;
use function array_map;
use function array_push;
use function array_values;
use function is_int;
use function ksort;

final class TradeRecipeData{
	public const TAG_TRADE_TIER = "TradeTier";
	public const TAG_OFFERS = "Offers";
	public const TAG_RECIPES = "Recipes";
	public const TAG_TRADE_EXPERIENCE = "TradeExperience";
	public const TAG_TIER_EXP_REQUIREMENTS = "TierExpRequirements";

	public const DEFAULT_TIER_EXP_REQUIREMENTS = [
		0 => 0,
		1 => 10,
		2 => 70,
		3 => 150,
		4 => 250
	];

	/**
	 * @var TradeRecipe[]
	 * @phpstan-var list<TradeRecipe>
	 */
	private array $recipes;
	/**
	 * @var int[]
	 * @phpstan-var array<int, int>
	 */
	private array $tierExpRequirements;

	/**
	 * @param TradeRecipe[] $recipes
	 * @phpstan-param list<TradeRecipe> $recipes
	 * @param int[] $tierExpRequirements
	 * @phpstan-param array<int, int> $tierExpRequirements
	 */
	public function __construct(
		array $recipes,
		private int $tier = 0,
		private int $tradeExperience = 0,
		array $tierExpRequirements = self::DEFAULT_TIER_EXP_REQUIREMENTS
	){
		Utils::validateArrayValueType($recipes, function(TradeRecipe $_) : void{});
		$this->recipes = array_values($recipes);

		if($tier < 0 || $tradeExperience < 0){
			throw new \InvalidArgumentException("Trade tier and experience cannot be negative");
		}
		foreach($tierExpRequirements as $requirementTier => $experience){
			if(!is_int($requirementTier) || !is_int($experience) || $requirementTier < 0 || $experience < 0){
				throw new \InvalidArgumentException("Tier experience requirements must contain non-negative integer keys and values");
			}
		}
		ksort($tierExpRequirements);
		$this->tierExpRequirements = $tierExpRequirements;
	}

	public function addRecipe(TradeRecipe ...$recipes) : void{
		array_push($this->recipes, ...$recipes);
	}

	public function setTierExpRequirement(int $tier, int $expRequirement) : void{
		if($tier < 0 || $expRequirement < 0){
			throw new \InvalidArgumentException("Tier and experience requirement cannot be negative");
		}
		$this->tierExpRequirements[$tier] = $expRequirement;
		ksort($this->tierExpRequirements);
	}

	public function setTier(int $tier) : void{
		if($tier < 0){
			throw new \InvalidArgumentException("Tier cannot be negative");
		}
		$this->tier = $tier;
	}

	public function setTradeExperience(int $tradeExperience) : void{
		if($tradeExperience < 0){
			throw new \InvalidArgumentException("Trade experience cannot be negative");
		}
		$this->tradeExperience = $tradeExperience;
	}

	/**
	 * @return TradeRecipe[]
	 * @phpstan-return list<TradeRecipe>
	 */
	public function getRecipes() : array{
		return $this->recipes;
	}

	/**
	 * @return int[]
	 * @phpstan-return array<int, int>
	 */
	public function getTierExpRequirements() : array{
		return $this->tierExpRequirements;
	}

	public function getRecipe(int $index) : ?TradeRecipe{
		return $this->recipes[$index] ?? null;
	}

	public function getTier() : int{
		return $this->tier;
	}

	public function getTradeExperience() : int{
		return $this->tradeExperience;
	}

	/**
	 * @return Item[][]
	 * @phpstan-return list<array{buyA: Item, buyB: Item|null, sell: Item}>
	 */
	public function getItems() : array{
		$items = [];
		foreach($this->recipes as $recipe){
			$items[] = [
				"buyA" => $recipe->getBuyA(),
				"buyB" => $recipe->getBuyB(),
				"sell" => $recipe->getSell()
			];
		}
		return $items;
	}

	public function updateTierFromExperience() : void{
		foreach($this->tierExpRequirements as $tier => $experienceRequirement){
			if($tier > $this->tier && $this->tradeExperience >= $experienceRequirement){
				$this->tier = $tier;
			}
		}
	}

	public function serialize(?CompoundTag $nbt = null) : CompoundTag{
		$nbt ??= CompoundTag::create();
		$nbt->setInt(self::TAG_TRADE_EXPERIENCE, $this->tradeExperience);
		$nbt->setInt(self::TAG_TRADE_TIER, $this->tier);

		$recipeTags = array_map(
			static fn(TradeRecipe $recipe) : Tag => $recipe->serialize(),
			$this->recipes
		);
		$tierExpRequirementTags = [];
		foreach($this->tierExpRequirements as $tier => $expRequirement){
			$tierExpRequirementTags[] = CompoundTag::create()->setInt((string) $tier, $expRequirement);
		}

		$nbt->setTag(self::TAG_OFFERS, CompoundTag::create()
			->setTag(self::TAG_RECIPES, new ListTag($recipeTags))
			->setTag(self::TAG_TIER_EXP_REQUIREMENTS, new ListTag($tierExpRequirementTags))
		);
		return $nbt;
	}
}
