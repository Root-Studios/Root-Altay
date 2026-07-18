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

use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\permission\DefaultPermissionNames;
use function array_slice;
use function count;
use function implode;

class TitleCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"title",
			KnownTranslationFactory::pocketmine_command_title_description(),
			KnownTranslationFactory::commands_title_usage()
		);
		$this->setPermissions([
			DefaultPermissionNames::COMMAND_TITLE_SELF,
			DefaultPermissionNames::COMMAND_TITLE_OTHER
		]);
	}

	public function buildOverloads(array &$hardcodedEnums, array &$softEnums, array &$enumConstraints) : array{
		$simpleAction = $this->getHardEnum($hardcodedEnums, "TitleSimpleAction", ["clear", "reset"]);
		$textAction = $this->getHardEnum($hardcodedEnums, "TitleTextAction", ["title", "subtitle", "actionbar"]);
		$timesAction = $this->getHardEnum($hardcodedEnums, "TitleTimesAction", ["times"]);
		return [
			new CommandOverload(chaining: false, parameters: [
				CommandParameter::standard("player", AvailableCommandsPacket::ARG_TYPE_TARGET),
				CommandParameter::enum("action", $simpleAction, 0),
			]),
			new CommandOverload(chaining: false, parameters: [
				CommandParameter::standard("player", AvailableCommandsPacket::ARG_TYPE_TARGET),
				CommandParameter::enum("action", $textAction, 0),
				CommandParameter::standard("text", AvailableCommandsPacket::ARG_TYPE_MESSAGE),
			]),
			new CommandOverload(chaining: false, parameters: [
				CommandParameter::standard("player", AvailableCommandsPacket::ARG_TYPE_TARGET),
				CommandParameter::enum("action", $timesAction, 0),
				CommandParameter::standard("fadeIn", AvailableCommandsPacket::ARG_TYPE_INT),
				CommandParameter::standard("stay", AvailableCommandsPacket::ARG_TYPE_INT),
				CommandParameter::standard("fadeOut", AvailableCommandsPacket::ARG_TYPE_INT),
			]),
		];
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) < 2){
			throw new InvalidCommandSyntaxException();
		}

		$players = $this->fetchPermittedPlayerTargets($sender, $args[0], DefaultPermissionNames::COMMAND_TITLE_SELF, DefaultPermissionNames::COMMAND_TITLE_OTHER);
		if($players === null){
			return true;
		}

		switch($args[1]){
			case "clear":
				foreach($players as $player){
					$player->removeTitles();
				}
				break;
			case "reset":
				foreach($players as $player){
					$player->resetTitles();
				}
				break;
			case "title":
				if(count($args) < 3){
					throw new InvalidCommandSyntaxException();
				}

				$text = implode(" ", array_slice($args, 2));
				foreach($players as $player){
					$player->sendTitle($text);
				}
				break;
			case "subtitle":
				if(count($args) < 3){
					throw new InvalidCommandSyntaxException();
				}

				$text = implode(" ", array_slice($args, 2));
				foreach($players as $player){
					$player->sendSubTitle($text);
				}
				break;
			case "actionbar":
				if(count($args) < 3){
					throw new InvalidCommandSyntaxException();
				}

				$text = implode(" ", array_slice($args, 2));
				foreach($players as $player){
					$player->sendActionBarMessage($text);
				}
				break;
			case "times":
				if(count($args) < 5){
					throw new InvalidCommandSyntaxException();
				}

				$fadeIn = $this->getInteger($sender, $args[2]);
				$stay = $this->getInteger($sender, $args[3]);
				$fadeOut = $this->getInteger($sender, $args[4]);
				foreach($players as $player){
					$player->setTitleDuration($fadeIn, $stay, $fadeOut);
				}
				break;
			default:
				throw new InvalidCommandSyntaxException();
		}

		$sender->sendMessage(KnownTranslationFactory::commands_title_success());

		return true;
	}
}
