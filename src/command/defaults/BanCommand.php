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

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\permission\DefaultPermissionNames;
use function array_shift;
use function count;
use function implode;

class BanCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"ban",
			KnownTranslationFactory::pocketmine_command_ban_player_description(),
			KnownTranslationFactory::commands_ban_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_BAN_PLAYER);
	}

	public function buildOverloads(array &$hardcodedEnums, array &$softEnums, array &$enumConstraints) : array{
		return [new CommandOverload(chaining: false, parameters: [
			CommandParameter::standard("player", AvailableCommandsPacket::ARG_TYPE_TARGET),
			CommandParameter::standard("reason", AvailableCommandsPacket::ARG_TYPE_MESSAGE, 0, true),
		])];
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) === 0){
			throw new InvalidCommandSyntaxException();
		}

		$name = array_shift($args);
		$reason = implode(" ", $args);

		if($this->isPlayerSelector($name)){
			$players = $this->fetchPlayerTargets($sender, $name);
			if($players === null){
				return true;
			}
		}else{
			$player = $sender->getServer()->getPlayerExact($name);
			$players = $player !== null ? [$player] : [];
		}

		if(count($players) === 0){
			$sender->getServer()->getNameBans()->addBan($name, $reason, null, $sender->getName());
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_ban_success($name));
		}else{
			foreach($players as $player){
				$playerName = $player->getName();
				$sender->getServer()->getNameBans()->addBan($playerName, $reason, null, $sender->getName());
				$player->kick($reason !== "" ? KnownTranslationFactory::pocketmine_disconnect_ban($reason) : KnownTranslationFactory::pocketmine_disconnect_ban_noReason());
				Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_ban_success($playerName));
			}
		}

		return true;
	}
}
