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

namespace pocketmine\inventory\transaction;

use pocketmine\inventory\recipe\TradeRecipe;
use pocketmine\inventory\recipe\TradeRecipeData;
use pocketmine\inventory\TradeInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
use function count;
use function in_array;
use function min;
use function mt_getrandmax;
use function mt_rand;
use function round;

final class TradingTransaction extends InventoryTransaction{
	public function __construct(
		Player $source,
		private readonly TradeRecipeData $recipeData,
		private readonly TradeRecipe $recipe,
		private readonly int $repetitions = 1
	){
		if($repetitions < 1){
			throw new \InvalidArgumentException("Trade repetitions must be at least 1");
		}
		parent::__construct($source);
	}

	private function validateInputSlot(TradeInventory $window, int $slot, ?Item $expectedInput) : void{
		$slotAction = null;
		foreach($this->actions as $action){
			if($action instanceof SlotChangeAction && $action->getInventory() === $window && $action->getSlot() === $slot){
				$slotAction = $action;
				break;
			}
		}

		if($expectedInput === null){
			if($slotAction !== null){
				throw new TransactionValidationException("Unexpected second trade input");
			}
			return;
		}
		if($slotAction === null){
			throw new TransactionValidationException("Trade input slot $slot was not consumed");
		}

		$expectedCount = $expectedInput->getCount() * $this->repetitions;
		$sourceItem = $slotAction->getSourceItem();
		$targetItem = $slotAction->getTargetItem();
		if(!$sourceItem->canStackWith($expectedInput) || $sourceItem->getCount() < $expectedCount){
			throw new TransactionValidationException("Invalid item in trade input slot $slot");
		}

		$remainingCount = $sourceItem->getCount() - $expectedCount;
		if($remainingCount === 0){
			if(!$targetItem->isNull()){
				throw new TransactionValidationException("Trade input slot $slot did not consume the expected amount");
			}
		}elseif(!$targetItem->canStackWith($sourceItem) || $targetItem->getCount() !== $remainingCount){
			throw new TransactionValidationException("Trade input slot $slot did not consume the expected amount");
		}
	}

	/**
	 * Removes matching items from both sides, leaving only the net balance.
	 *
	 * @param Item[] $needItems
	 * @param Item[] $haveItems
	 */
	private function balanceItems(array &$needItems, array &$haveItems) : void{
		foreach($needItems as $needIndex => $needItem){
			foreach($haveItems as $haveIndex => $haveItem){
				if(!$needItem->canStackWith($haveItem)){
					continue;
				}

				$amount = min($needItem->getCount(), $haveItem->getCount());
				$needItem->setCount($needItem->getCount() - $amount);
				$haveItem->setCount($haveItem->getCount() - $amount);
				if($haveItem->getCount() === 0){
					unset($haveItems[$haveIndex]);
				}
				if($needItem->getCount() === 0){
					unset($needItems[$needIndex]);
					break;
				}
			}
		}
	}

	/**
	 * @param Item[] $actualItems
	 * @param Item[] $expectedItems
	 */
	private function validateItemBalance(array $actualItems, array $expectedItems, string $description) : void{
		foreach($expectedItems as $expectedItem){
			$remaining = $expectedItem->getCount();
			foreach($actualItems as $actualIndex => $actualItem){
				if(!$expectedItem->canStackWith($actualItem)){
					continue;
				}

				$amount = min($remaining, $actualItem->getCount());
				$remaining -= $amount;
				$actualItem->setCount($actualItem->getCount() - $amount);
				if($actualItem->getCount() === 0){
					unset($actualItems[$actualIndex]);
				}
				if($remaining === 0){
					break;
				}
			}
			if($remaining !== 0){
				throw new TransactionValidationException("Trade transaction has an invalid $description balance");
			}
		}
		if(count($actualItems) !== 0){
			throw new TransactionValidationException("Trade transaction has an invalid $description balance");
		}
	}

	public function validate() : void{
		$window = $this->source->getCurrentWindow();
		if(!$window instanceof TradeInventory){
			throw new TransactionValidationException("Transaction expected an open " . TradeInventory::class);
		}
		if($window->getRecipeData() !== $this->recipeData || !in_array($this->recipe, $this->recipeData->getRecipes(), true)){
			throw new TransactionValidationException("Trade recipe does not belong to the open inventory");
		}
		if($this->recipe->getTier() > $this->recipeData->getTier()){
			throw new TransactionValidationException("Tried to execute a locked trade recipe");
		}
		if($this->recipe->getUses() + $this->repetitions > $this->recipe->getMaxUses()){
			throw new TransactionValidationException("Tried to execute a disabled trade recipe");
		}

		$this->squashDuplicateSlotChanges();
		if(count($this->actions) === 0){
			throw new TransactionValidationException("Trading transaction must have at least one action");
		}

		$buyA = $this->recipe->getBuyA();
		$buyB = $this->recipe->getBuyB();
		$this->validateInputSlot($window, TradeInventory::SLOT_INPUT_A, $buyA);
		$this->validateInputSlot($window, TradeInventory::SLOT_INPUT_B, $buyB);

		$actualNeedItems = [];
		$actualHaveItems = [];
		$this->matchItems($actualNeedItems, $actualHaveItems);

		$buyA->setCount($buyA->getCount() * $this->repetitions);
		$expectedHaveItems = [$buyA];
		if($buyB !== null){
			$buyB->setCount($buyB->getCount() * $this->repetitions);
			$expectedHaveItems[] = $buyB;
		}
		$sell = $this->recipe->getSell();
		$sell->setCount($sell->getCount() * $this->repetitions);
		$expectedNeedItems = [$sell];
		$this->balanceItems($expectedNeedItems, $expectedHaveItems);

		$this->validateItemBalance($actualHaveItems, $expectedHaveItems, "input");
		$this->validateItemBalance($actualNeedItems, $expectedNeedItems, "output");
	}

	public function execute() : void{
		parent::execute();

		$this->recipe->setUses($this->recipe->getUses() + $this->repetitions);
		$traderExperience = $this->recipe->getTraderExp() * $this->repetitions;
		$this->recipeData->setTradeExperience($this->recipeData->getTradeExperience() + $traderExperience);
		$this->recipeData->updateTierFromExperience();

		$position = $this->source->getPosition();
		if($traderExperience > 0){
			$this->source->getWorld()->dropExperience($position, $traderExperience);
		}
		$this->source->getNetworkSession()->sendDataPacket(PlaySoundPacket::create(
			(bool) mt_rand(0, 1) ? "mob.wanderingtrader.idle" : "mob.wanderingtrader.yes",
			$position->x,
			$position->y,
			$position->z,
			1,
			(float) round(0.8 + 0.4 * (mt_rand() / mt_getrandmax()), 2),
			null
		));
	}
}
