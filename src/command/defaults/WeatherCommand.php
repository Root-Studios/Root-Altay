<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use function count;
use function random_int;
use function strtolower;

final class WeatherCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct("weather", "Changes the weather", "/weather <clear|rain|thunder> [duration]");
		$this->setPermission(DefaultPermissionNames::COMMAND_WEATHER);
	}

	public function buildOverloads(array &$hardcodedEnums, array &$softEnums, array &$enumConstraints) : array{
		$weather = $this->getHardEnum($hardcodedEnums, "Weather", ["clear", "rain", "thunder"]);
		return [new CommandOverload(chaining: false, parameters: [
			CommandParameter::enum("weather", $weather, 0),
			CommandParameter::standard("duration", AvailableCommandsPacket::ARG_TYPE_INT, 0, true),
		])];
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender)){
			return true;
		}
		if(count($args) < 1){
			throw new InvalidCommandSyntaxException();
		}

		$world = $sender instanceof Player ? $sender->getWorld() : $sender->getServer()->getWorldManager()->getDefaultWorld();
		if($world === null){
			$sender->sendMessage("No default world is loaded.");
			return true;
		}
		if(!$world->isWeatherEnabled()){
			$sender->sendMessage("Weather is disabled in world " . $world->getFolderName() . ".");
			return true;
		}
		$duration = isset($args[1]) ? $this->getInteger($sender, $args[1], 1, 1000000) * 20 : random_int(6000, 18000);

		switch(strtolower($args[0])){
			case "clear":
				$world->setRainLevel(0.0);
				$world->setRainTime($duration);
				break;
			case "rain":
				$world->setLightningLevel(0.0);
				$world->setRainLevel(1.0);
				$world->setRainTime($duration);
				break;
			case "thunder":
				$world->setLightningLevel(1.0);
				$world->setRainTime($duration);
				$world->setLightningTime($duration);
				break;
			default:
				throw new InvalidCommandSyntaxException();
		}

		$sender->sendMessage("Weather changed to " . strtolower($args[0]) . " in " . $world->getFolderName() . ".");
		Command::broadcastCommandMessage($sender, "Weather changed.");
		return true;
	}
}
