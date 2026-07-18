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
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_filter;
use function array_shift;
use function array_values;
use function count;
use function implode;

class TellCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"tell",
			KnownTranslationFactory::pocketmine_command_tell_description(),
			KnownTranslationFactory::commands_message_usage(),
			["w", "msg"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_TELL);
	}

	public function buildOverloads(array &$hardcodedEnums, array &$softEnums, array &$enumConstraints) : array{
		return [new CommandOverload(chaining: false, parameters: [
			CommandParameter::standard("player", AvailableCommandsPacket::ARG_TYPE_TARGET),
			CommandParameter::standard("message", AvailableCommandsPacket::ARG_TYPE_MESSAGE),
		])];
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) < 2){
			throw new InvalidCommandSyntaxException();
		}

		$players = $this->fetchPlayerTargets($sender, array_shift($args));
		if($players === null){
			return true;
		}
		$players = array_values(array_filter($players, static fn(Player $player) : bool => $player !== $sender));
		if(count($players) === 0){
			$sender->sendMessage(KnownTranslationFactory::commands_message_sameTarget()->prefix(TextFormat::RED));
			return true;
		}

		$message = implode(" ", $args);
		$name = $sender instanceof Player ? $sender->getDisplayName() : $sender->getName();
		foreach($players as $player){
			$sender->sendMessage(KnownTranslationFactory::commands_message_display_outgoing($player->getDisplayName(), $message)->prefix(TextFormat::GRAY . TextFormat::ITALIC));
			$player->sendMessage(KnownTranslationFactory::commands_message_display_incoming($name, $message)->prefix(TextFormat::GRAY . TextFormat::ITALIC));
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_message_display_outgoing($player->getDisplayName(), $message), false);
		}

		return true;
	}
}
