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

use pocketmine\block\BlockTypeIds;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\utils\ArmorStandEquipmentSlotFinder;
use pocketmine\entity\utils\ArmorStandPose;
use pocketmine\event\entity\ArmorStandMoveEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\PlayerChangeArmorStandArmorEvent;
use pocketmine\event\entity\PlayerChangeArmorStandHeldItemEvent;
use pocketmine\event\entity\PlayerChangeArmorStandPoseEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\world\sound\ArmorStandBreakSound;
use pocketmine\world\sound\ArmorStandHitSound;
use function array_values;
use function in_array;
use function max;
use function str_replace;
use function strtolower;

class ArmorStand extends Living{
	private const TAG_ARMOR_INVENTORY = "ArmorInventory";
	private const TAG_HELD_ITEM = "HeldItem";
	private const TAG_POSE = "Pose";

	private ArmorStandPose $pose = ArmorStandPose::DEFAULT;
	private Item $itemInHand;
	private int $wobbleTicks = 0;

	protected int $maxDeadTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::ARMOR_STAND;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.975, 0.5);
	}

	public function getName() : string{
		return "Armor Stand";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(6);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::ARMOR_STAND_POSE_INDEX, $this->pose->value);
		$properties->setInt(EntityMetadataProperties::HURT_TIME, $this->wobbleTicks);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if(!$player->canInteract($this->location, 10.0)){
			return false;
		}

		if($player->isSneaking()){
			$event = new PlayerChangeArmorStandPoseEvent($this, $this->pose, $this->pose->next(), $player);
			$event->call();
			if(!$event->isCancelled()){
				$this->setPose($event->getNewPose());
			}
			return true;
		}

		$offset = $this->getEquipmentClickOffset($clickPos);
		$heldItem = $player->getInventory()->getItemInHand();
		if($heldItem instanceof Armor){
			$this->swapArmor($player, $heldItem->getArmorSlot());
		}elseif($heldItem instanceof ItemBlock && in_array($heldItem->getBlock()->getTypeId(), [BlockTypeIds::CARVED_PUMPKIN, BlockTypeIds::MOB_HEAD], true)){
			$this->swapArmor($player, ArmorInventory::SLOT_HEAD);
		}elseif(!$heldItem->isNull() || ArmorStandEquipmentSlotFinder::isMainHand($offset)){
			$this->swapMainHand($player);
		}else{
			$this->swapArmor($player, ArmorStandEquipmentSlotFinder::findArmorSlot($offset));
		}
		return true;
	}

	private function getEquipmentClickOffset(Vector3 $clickPos) : Vector3{
		if($this->boundingBox->expandedCopy(0.25, 0.25, 0.25)->isVectorInside($clickPos)){
			return $clickPos->subtractVector($this->location);
		}
		return $clickPos;
	}

	private function swapArmor(Player $player, int $slot) : void{
		$inventory = $this->getArmorInventory();
		$oldItem = $inventory->getItem($slot);
		$heldItem = $player->getInventory()->getItemInHand();
		if($heldItem->isNull() && $oldItem->isNull()){
			return;
		}

		$newItem = $this->oneItem($heldItem);
		$event = new PlayerChangeArmorStandArmorEvent($this, $slot, $oldItem, $newItem, $player);
		$event->call();
		if($event->isCancelled()){
			return;
		}

		$this->finishPlayerSideSwap($player, $heldItem, $oldItem);
		$inventory->setItem($slot, $event->getNewItem());
	}

	private function swapMainHand(Player $player) : void{
		$oldItem = $this->itemInHand;
		$heldItem = $player->getInventory()->getItemInHand();
		if($heldItem->isNull() && $oldItem->isNull()){
			return;
		}

		$event = new PlayerChangeArmorStandHeldItemEvent($this, $oldItem, $this->oneItem($heldItem), $player);
		$event->call();
		if($event->isCancelled()){
			return;
		}

		$this->finishPlayerSideSwap($player, $heldItem, $oldItem);
		$this->setItemInHand($event->getNewItem());
	}

	private function oneItem(Item $item) : Item{
		if($item->isNull()){
			return VanillaItems::AIR();
		}
		$result = clone $item;
		$result->setCount(1);
		return $result;
	}

	private function finishPlayerSideSwap(Player $player, Item $heldItem, Item $oldItem) : void{
		$playerInventory = $player->getInventory();
		if($heldItem->isNull()){
			$playerInventory->setItemInHand($oldItem);
			return;
		}

		if($player->hasFiniteResources()){
			$heldItem->pop();
			$playerInventory->setItemInHand($heldItem);
		}
		if(!$oldItem->isNull()){
			foreach($playerInventory->addItem($oldItem) as $leftover){
				$player->getWorld()->dropItem($player->getEyePos(), $leftover);
			}
		}
	}

	public function getItemInHand() : Item{
		return clone $this->itemInHand;
	}

	public function setItemInHand(Item $item) : void{
		$this->itemInHand = clone $item;
		$packet = $this->createHeldItemPacket();
		foreach($this->getViewers() as $viewer){
			$viewer->getNetworkSession()->sendDataPacket($packet);
		}
	}

	private function createHeldItemPacket() : MobEquipmentPacket{
		return MobEquipmentPacket::create(
			$this->getId(),
			ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($this->itemInHand)),
			0,
			0,
			ContainerIds::INVENTORY
		);
	}

	public function getPose() : ArmorStandPose{
		return $this->pose;
	}

	public function setPose(ArmorStandPose $pose) : void{
		if($this->pose !== $pose){
			$this->pose = $pose;
			$this->networkPropertiesDirty = true;
			$this->scheduleUpdate();
		}
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		if(($armorTag = $nbt->getListTag(self::TAG_ARMOR_INVENTORY, CompoundTag::class)) !== null){
			foreach($armorTag as $itemTag){
				$slot = $itemTag->getByte("Slot", -1);
				if($slot >= 0 && $slot < $this->getArmorInventory()->getSize()){
					$this->getArmorInventory()->setItem($slot, Item::safeNbtDeserialize($itemTag, "Armor stand armor slot $slot"));
				}
			}
		}

		$heldItemTag = $nbt->getCompoundTag(self::TAG_HELD_ITEM);
		$this->itemInHand = $heldItemTag !== null ? Item::safeNbtDeserialize($heldItemTag, "Armor stand held item") : VanillaItems::AIR();

		$poseTag = $nbt->getTag(self::TAG_POSE);
		if($poseTag instanceof IntTag){
			$this->pose = ArmorStandPose::tryFrom($poseTag->getValue()) ?? ArmorStandPose::DEFAULT;
		}elseif($poseTag instanceof StringTag){
			$legacyName = str_replace("_", "", strtolower($poseTag->getValue()));
			foreach(ArmorStandPose::cases() as $pose){
				if(str_replace("_", "", strtolower($pose->name)) === $legacyName){
					$this->pose = $pose;
					break;
				}
			}
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$armorItems = [];
		foreach($this->getArmorInventory()->getContents() as $slot => $item){
			$armorItems[] = $item->nbtSerialize($slot);
		}
		$nbt->setTag(self::TAG_ARMOR_INVENTORY, new ListTag($armorItems, NBT::TAG_Compound));
		if(!$this->itemInHand->isNull()){
			$nbt->setTag(self::TAG_HELD_ITEM, $this->itemInHand->nbtSerialize());
		}
		$nbt->setInt(self::TAG_POSE, $this->pose->value);
		return $nbt;
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		$player->getNetworkSession()->sendDataPacket($this->createHeldItemPacket());
	}

	public function getDrops() : array{
		$drops = array_values($this->getArmorInventory()->getContents());
		if(!$this->itemInHand->isNull()){
			$drops[] = clone $this->itemInHand;
		}

		$dropStand = !in_array($this->lastDamageCause?->getCause(), [
			EntityDamageEvent::CAUSE_PROJECTILE,
			EntityDamageEvent::CAUSE_BLOCK_EXPLOSION,
			EntityDamageEvent::CAUSE_ENTITY_EXPLOSION
		], true);
		$damager = $this->lastDamageCause instanceof EntityDamageByEntityEvent ? $this->lastDamageCause->getDamager() : null;
		if($damager instanceof Player && !$damager->hasFiniteResources()){
			$dropStand = false;
		}
		if($dropStand){
			$drops[] = VanillaItems::ARMOR_STAND();
		}
		return $drops;
	}

	public function attack(EntityDamageEvent $source) : void{
		if(in_array($source->getCause(), [
			EntityDamageEvent::CAUSE_CONTACT,
			EntityDamageEvent::CAUSE_FIRE,
			EntityDamageEvent::CAUSE_FIRE_TICK,
			EntityDamageEvent::CAUSE_LAVA,
			EntityDamageEvent::CAUSE_FALL
		], true)){
			$source->cancel();
		}

		if($source instanceof EntityDamageByChildEntityEvent && $source->getChild() instanceof Arrow){
			$source->setBaseDamage($this->getHealth());
		}
		parent::attack($source);
	}

	public function applyDamageModifiers(EntityDamageEvent $source) : void{
		// Armor displayed by a stand must not protect the stand itself.
	}

	public function knockBack(float $x, float $z, float $force = self::DEFAULT_KNOCKBACK_FORCE, ?float $verticalLimit = self::DEFAULT_KNOCKBACK_VERTICAL_LIMIT) : void{
		// Armor stands wobble instead of being knocked back by attacks.
	}

	protected function doHitAnimation() : void{
		$this->wobbleTicks = 9;
		$this->networkPropertiesDirty = true;
		$this->scheduleUpdate();
		$this->broadcastSound(new ArmorStandHitSound());
	}

	protected function onDeath() : void{
		parent::onDeath();
		$this->broadcastSound(new ArmorStandBreakSound());
	}

	protected function startDeathAnimation() : void{
		// The client removes armor stands immediately.
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$result = parent::entityBaseTick($tickDiff);
		if($this->wobbleTicks > 0){
			$this->wobbleTicks = max(0, $this->wobbleTicks - $tickDiff);
			$this->networkPropertiesDirty = true;
			$result = true;
		}
		return $result;
	}

	protected function move(float $dx, float $dy, float $dz) : void{
		$from = $this->location->asLocation();
		parent::move($dx, $dy, $dz);
		$to = $this->location->asLocation();
		if(!$from->equals($to)){
			(new ArmorStandMoveEvent($this, $from, $to))->call();
		}
	}

	public function canBeMovedByCurrents() : bool{
		return true;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::ARMOR_STAND();
	}
}
