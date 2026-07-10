<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\player\Player;

interface RideableEntity {
	public function getRider() : ?Player;
	public function getPassenger() : ?Player;
	public function setRider(?Player $player) : void;
	public function dismountRider(bool $promotePassenger = true, bool $syncPosition = true) : void;

	public function dismountPassenger(bool $syncPosition = true) : void;
	public function handleVehicleInput(Player $player, PlayerAuthInputPacket $packet) : bool;
	public function getRiderTrackingPosition(Vector3 $clientPredicted) : Vector3;
}