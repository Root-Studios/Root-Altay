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

namespace pocketmine\entity;

use pocketmine\entity\utils\VillagerBiome;
use pocketmine\entity\utils\VillagerProfession;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

class Villager extends Living implements Ageable{

	private const TAG_PROFESSION = "Profession"; // TAG_Int
	private const TAG_BIOME = "Biome"; // TAG_Int

	private bool $baby = false;

	private VillagerProfession $profession = VillagerProfession::FARMER;
	private VillagerBiome $biome = VillagerBiome::PLAINS;

	public static function getNetworkTypeId() : string{
		return EntityIds::VILLAGER_V2;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6);
	}

	public function getName() : string{
		return "Villager";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$professionId = $nbt->getInt(self::TAG_PROFESSION, VillagerProfession::FARMER->value);
		$biomeId = $nbt->getInt(self::TAG_BIOME, VillagerBiome::PLAINS->value);

		$this->setProfession(
			VillagerProfession::tryFrom($professionId) ?? VillagerProfession::FARMER
		);

		$this->setBiome(
			VillagerBiome::tryFrom($biomeId) ?? VillagerBiome::PLAINS
		);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setInt(self::TAG_PROFESSION, $this->profession->value);
		$nbt->setInt(self::TAG_BIOME, $this->biome->value);

		return $nbt;
	}

	public function setProfession(VillagerProfession $profession) : void{
		$this->profession = $profession;
		$this->networkPropertiesDirty = true;
	}

	public function getProfession() : VillagerProfession{
		return $this->profession;
	}

	public function setBiome(VillagerBiome $biome) : void{
		$this->biome = $biome;
		$this->networkPropertiesDirty = true;
	}

	public function getBiome() : VillagerBiome{
		return $this->biome;
	}

	public function setBaby(bool $baby = true) : void{
		$this->baby = $baby;
		$this->networkPropertiesDirty = true;
	}

	public function isBaby() : bool{
		return $this->baby;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::VILLAGER_SPAWN_EGG();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->baby);

		$properties->setInt(
			EntityMetadataProperties::VARIANT,
			$this->profession->value
		);

		$properties->setInt(
			EntityMetadataProperties::MARK_VARIANT,
			$this->biome->value
		);
	}
}
