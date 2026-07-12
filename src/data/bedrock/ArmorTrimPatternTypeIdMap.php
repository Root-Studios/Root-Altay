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

use pocketmine\item\ArmorTrimPattern;
use pocketmine\item\Item;
use pocketmine\item\VanillaArmorTrimPatterns;
use pocketmine\utils\SingletonTrait;
use function array_key_exists;
use function array_values;
use function spl_object_id;
use function strtolower;

final class ArmorTrimPatternTypeIdMap{
	use SingletonTrait;
	/** @var array<string, ArmorTrimPattern> */
	private array $byId = [];
	/** @var array<int, ArmorTrimPattern> */
	private array $byItemState = [];
	/** @var array<int, string> */
	private array $idsByObject = [];

	public function __construct(){
		foreach(VanillaArmorTrimPatterns::getAll() as $id => $pattern){ $this->register(strtolower($id), $pattern); }
	}

	public function register(string $id, ArmorTrimPattern $pattern) : void{
		if(isset($this->byId[$id]) || isset($this->byItemState[$pattern->getItem()->getStateId()])){
			throw new \InvalidArgumentException("Duplicate armor trim pattern mapping: $id");
		}
		$this->byId[$id] = $pattern;
		$this->byItemState[$pattern->getItem()->getStateId()] = $pattern;
		$this->idsByObject[spl_object_id($pattern)] = $id;
	}

	public function fromId(string $id) : ?ArmorTrimPattern{ return $this->byId[$id] ?? null; }
	public function fromItem(Item $item) : ?ArmorTrimPattern{ return $this->byItemState[$item->getStateId()] ?? null; }
	public function toId(ArmorTrimPattern $pattern) : string{
		$key = spl_object_id($pattern);
		if(!array_key_exists($key, $this->idsByObject)){ throw new \InvalidArgumentException("Unregistered armor trim pattern"); }
		return $this->idsByObject[$key];
	}
	/** @return list<ArmorTrimPattern> */
	public function getAllPatterns() : array{ return array_values($this->byId); }
}
