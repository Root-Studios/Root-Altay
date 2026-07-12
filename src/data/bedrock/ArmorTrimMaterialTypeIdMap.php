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

namespace pocketmine\data\bedrock;

use pocketmine\item\ArmorTrimMaterial;
use pocketmine\item\Item;
use pocketmine\item\VanillaArmorTrimMaterials;
use pocketmine\utils\SingletonTrait;
use function array_key_exists;
use function array_values;
use function spl_object_id;
use function strtolower;

final class ArmorTrimMaterialTypeIdMap{
	use SingletonTrait;
	/** @var array<string, ArmorTrimMaterial> */
	private array $byId = [];
	/** @var array<int, ArmorTrimMaterial> */
	private array $byItemState = [];
	/** @var array<int, string> */
	private array $idsByObject = [];

	public function __construct(){
		foreach(VanillaArmorTrimMaterials::getAll() as $id => $material){ $this->register(strtolower($id), $material); }
	}

	public function register(string $id, ArmorTrimMaterial $material) : void{
		if(isset($this->byId[$id]) || isset($this->byItemState[$material->getItem()->getStateId()])){
			throw new \InvalidArgumentException("Duplicate armor trim material mapping: $id");
		}
		$this->byId[$id] = $material;
		$this->byItemState[$material->getItem()->getStateId()] = $material;
		$this->idsByObject[spl_object_id($material)] = $id;
	}

	public function fromId(string $id) : ?ArmorTrimMaterial{ return $this->byId[$id] ?? null; }
	public function fromItem(Item $item) : ?ArmorTrimMaterial{ return $this->byItemState[$item->getStateId()] ?? null; }
	public function toId(ArmorTrimMaterial $material) : string{
		$key = spl_object_id($material);
		if(!array_key_exists($key, $this->idsByObject)){ throw new \InvalidArgumentException("Unregistered armor trim material"); }
		return $this->idsByObject[$key];
	}
	/** @return list<ArmorTrimMaterial> */
	public function getAllMaterials() : array{ return array_values($this->byId); }
}
