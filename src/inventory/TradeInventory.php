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

namespace pocketmine\inventory;

use pocketmine\entity\Entity;
use pocketmine\inventory\recipe\TradeRecipe;
use pocketmine\inventory\recipe\TradeRecipeData;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\network\mcpe\protocol\UpdateTradePacket;
use pocketmine\player\Player;
use function array_values;

final class TradeInventory extends SimpleInventory implements TemporaryInventory{
	public const SLOT_INPUT_A = 0;
	public const SLOT_INPUT_B = 1;

	private CompoundTag $offers;

	public function __construct(
		private readonly string $name,
		private readonly Entity $entity,
		private readonly TradeRecipeData $recipeData
	){
		parent::__construct(2);
		$this->parseTradeData();
	}

	public function getName() : string{
		return $this->name;
	}

	public function getRecipeData() : TradeRecipeData{
		return $this->recipeData;
	}

	public function parseTradeData() : void{
		$recipeTags = [];
		foreach($this->recipeData->getRecipes() as $index => $recipe){
			$recipeTags[] = $recipe->serialize()->setInt(TradeRecipe::TAG_NET_ID, $index + 1);
		}

		$tierExpRequirementTags = [];
		foreach($this->recipeData->getTierExpRequirements() as $tier => $expRequirement){
			$tierExpRequirementTags[] = CompoundTag::create()->setInt((string) $tier, $expRequirement);
		}

		$this->offers = CompoundTag::create()
			->setTag(TradeRecipeData::TAG_RECIPES, new ListTag($recipeTags))
			->setTag(TradeRecipeData::TAG_TIER_EXP_REQUIREMENTS, new ListTag($tierExpRequirementTags));
	}

	public function getHolder() : Entity{
		return $this->entity;
	}

	public function onOpen(Player $who) : void{
		parent::onOpen($who);
		$this->entity->getNetworkProperties()->setLong(EntityMetadataProperties::TRADING_PLAYER_EID, $who->getId());
	}

	public function onClose(Player $who) : void{
		parent::onClose($who);

		$viewers = $this->getViewers();
		$remainingViewer = $viewers === [] ? null : array_values($viewers)[0];
		$this->entity->getNetworkProperties()->setLong(EntityMetadataProperties::TRADING_PLAYER_EID, $remainingViewer?->getId() ?? -1);
	}

	/**
	 * @return ClientboundPacket[]
	 * @phpstan-return list<ClientboundPacket>
	 */
	public function createInventoryOpenPackets(int $id) : array{
		$this->parseTradeData();
		return [
			UpdateTradePacket::create(
				$id,
				WindowTypes::TRADING,
				0,
				$this->recipeData->getTier(),
				$this->entity->getId(),
				-1,
				$this->name,
				true,
				true,
				new CacheableNbt($this->offers)
			)
		];
	}
}
