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

namespace pocketmine\event\player;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class PlayerFishEvent extends PlayerEvent implements Cancellable
{
	use CancellableTrait;

	public function __construct(
		Player $player,
		protected Item $fishingRod,
		protected array $loots,
		protected int $experience
	) {
		$this->player = $player;
	}

	public function getFishingRod() : Item {
		return $this->fishingRod;
	}

	public function setFishingRod(Item $fishingRod) : void {
		$this->fishingRod = $fishingRod;
	}

	public function getLoots() : array {
		return $this->loots;
	}

	public function setLoot(array $loots) : void {
		$this->loots = $loots;
	}

	public function getExperience() : int {
		return $this->experience;
	}

	public function setExperience(int $experience) : void {
		$this->experience = $experience;
	}
}