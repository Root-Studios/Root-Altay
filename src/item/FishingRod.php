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

use pocketmine\entity\projectile\FishingHook;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ThrowSound;
use function mt_rand;

class FishingRod extends Durable {
	private const COOLDOWN_TICKS = 5;
	private const HOOK_VELOCITY = 0.7;

	public function getMaxDurability() : int {
		return 385;
	}

	public function getCooldownTicks() : int {
		return self::COOLDOWN_TICKS;
	}

	public function getMaxStackSize() : int {
		return 1;
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult {
		$hook = $player->getFishingHook();

		if ($hook !== null && ($hook->isClosed() || $hook->isFlaggedForDespawn())) {
			$player->setFishingHook(null);
			$hook = null;
		}

		if ($hook !== null) {
			$this->handleExistingHook($player, $hook);
			return ItemUseResult::SUCCESS();
		}

		$this->castNewHook($player);
		return ItemUseResult::SUCCESS();
	}

	private function damageRodInHand(Player $player, int $amount) : void {
		$item = $player->getInventory()->getItemInHand();
		if (!$item instanceof self) {
			return;
		}

		$item->setDamage($item->getDamage() + $amount);
		$player->getInventory()->setItemInHand($item);
	}

	private function handleExistingHook(Player $player, FishingHook $hook) : void {
		$damage = 0;

		if ($hook->didCatchSomething()) {
			++$damage;
		}

		if ($hook->getTargetEntity() !== null) {
			$damage += mt_rand(1, 2);
		}

		$hook->reelLine();

		if ($damage > 0) {
			$this->damageRodInHand($player, $damage);
		}
	}

	private function castNewHook(Player $player) : void {
		$location = $player->getLocation();
		$location->y += $player->getEyeHeight();

		$hook = new FishingHook($location, $player);
		$hook->setMotion($player->getDirectionVector()->multiply(self::HOOK_VELOCITY));
		$hook->setWaitingTimer(1);
		$hook->setLureLevel($this->getLureLevelFromItem($player->getInventory()->getItemInHand()));

		$event = new ProjectileLaunchEvent($hook);
		$event->call();

		if ($event->isCancelled()) {
			$hook->flagForDespawn();
			return;
		}

		$hook->spawnToAll();
		$location->getWorld()->addSound($location, new ThrowSound(), [$player]);

		$player->setFishingHook($hook);
	}

	private function getLureLevelFromItem(Item $item) : int {
		$enchant = $item->getEnchantment(VanillaEnchantments::LURE());
		return $enchant?->getLevel() ?? 0;
	}

	public function getFishingLoot() : array {
		return [VanillaItems::RAW_FISH()];
	}
}