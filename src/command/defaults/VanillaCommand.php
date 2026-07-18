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
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use function array_filter;
use function array_reverse;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function in_array;
use function is_numeric;
use function max;
use function min;
use function preg_match;
use function shuffle;
use function strtolower;
use function substr;
use function usort;
use const PHP_FLOAT_MAX;

abstract class VanillaCommand extends Command{
	public const MAX_COORD = 30000000;
	public const MIN_COORD = -30000000;

	public function buildOverloads(array &$hardcodedEnums, array &$softEnums, array &$enumConstraints) : array{
		return [new CommandOverload(chaining: false, parameters: [])];
	}

	protected function isPlayerSelector(string $target) : bool{
		return isset($target[0]) && $target[0] === "@";
	}

	/**
	 * Resolves a player name or Bedrock target selector.
	 *
	 * Supported selectors are @a, @e, @p, @r and @s. The selector arguments type, name, m/gamemode, c/limit,
	 * x, y, z, dx, dy, dz, r and rm are also supported.
	 *
	 * @return Player[]|null
	 * @phpstan-return list<Player>|null
	 */
	protected function fetchPlayerTargets(CommandSender $sender, ?string $target) : ?array{
		if($target === null){
			if($sender instanceof Player){
				return [$sender];
			}
			throw new InvalidCommandSyntaxException();
		}

		if(!$this->isPlayerSelector($target)){
			$player = $sender->getServer()->getPlayerByPrefix($target);
			if($player !== null){
				return [$player];
			}
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_error_playerNotFound($target)->prefix(TextFormat::RED));
			return null;
		}

		if(preg_match('/^@([aeprs])(?:\[([^\]]*)\])?$/i', $target, $matches) !== 1){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_error_playerNotFound($target)->prefix(TextFormat::RED));
			return null;
		}

		$selector = strtolower($matches[1]);
		$options = [];
		if(isset($matches[2]) && $matches[2] !== ""){
			foreach(explode(",", $matches[2], 15) as $option){
				$parts = explode("=", $option, 2);
				if(count($parts) !== 2 || $parts[0] === ""){
					$sender->sendMessage(KnownTranslationFactory::pocketmine_command_error_playerNotFound($target)->prefix(TextFormat::RED));
					return null;
				}
				$options[strtolower($parts[0])] = $parts[1];
			}
		}

		$players = match($selector){
			"s" => $sender instanceof Player ? [$sender] : [],
			default => array_values($sender->getServer()->getOnlinePlayers()),
		};
		$origin = $sender instanceof Player ? $sender->getPosition() : $sender->getServer()->getWorldManager()->getDefaultWorld()?->getSpawnLocation();
		$originX = isset($options["x"]) ? (float) $options["x"] : ($origin->x ?? 0.0);
		$originY = isset($options["y"]) ? (float) $options["y"] : ($origin->y ?? 0.0);
		$originZ = isset($options["z"]) ? (float) $options["z"] : ($origin->z ?? 0.0);

		$knownOptions = ["type", "name", "m", "gamemode", "c", "limit", "x", "y", "z", "dx", "dy", "dz", "r", "rm"];
		foreach(Utils::stringifyKeys($options) as $name => $_value){
			if(!in_array($name, $knownOptions, true)){
				$sender->sendMessage(KnownTranslationFactory::pocketmine_command_error_playerNotFound($target)->prefix(TextFormat::RED));
				return null;
			}
		}
		foreach(["x", "y", "z", "dx", "dy", "dz", "r", "rm"] as $numericOption){
			if(isset($options[$numericOption]) && !is_numeric($options[$numericOption])){
				$sender->sendMessage(KnownTranslationFactory::pocketmine_command_error_playerNotFound($target)->prefix(TextFormat::RED));
				return null;
			}
		}
		foreach(["c", "limit"] as $integerOption){
			if(isset($options[$integerOption]) && preg_match('/^-?\d+$/D', $options[$integerOption]) !== 1){
				$sender->sendMessage(KnownTranslationFactory::pocketmine_command_error_playerNotFound($target)->prefix(TextFormat::RED));
				return null;
			}
		}
		if((isset($options["r"]) && (float) $options["r"] < 0) || (isset($options["rm"]) && (float) $options["rm"] < 0)){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_error_playerNotFound($target)->prefix(TextFormat::RED));
			return null;
		}

		$players = array_values(array_filter($players, function(Player $player) use ($options, $origin, $originX, $originY, $originZ) : bool{
			if(isset($options["type"])){
				$type = strtolower($options["type"]);
				$negated = isset($type[0]) && $type[0] === "!";
				if($negated){
					$type = substr($type, 1);
				}
				$isPlayer = $type === "player" || $type === "minecraft:player";
				if($negated === $isPlayer){
					return false;
				}
			}

			if(isset($options["name"])){
				$name = $options["name"];
				$negated = isset($name[0]) && $name[0] === "!";
				if($negated){
					$name = substr($name, 1);
				}
				$matches = strtolower($player->getName()) === strtolower($name);
				if($negated === $matches){
					return false;
				}
			}

			$gameModeOption = $options["m"] ?? $options["gamemode"] ?? null;
			if($gameModeOption !== null){
				$negated = isset($gameModeOption[0]) && $gameModeOption[0] === "!";
				if($negated){
					$gameModeOption = substr($gameModeOption, 1);
				}
				$matches = $player->getGamemode() === \pocketmine\player\GameMode::fromString($gameModeOption);
				if($negated === $matches){
					return false;
				}
			}

			if($origin !== null){
				if($player->getWorld() !== $origin->getWorld()){
					return !isset($options["r"]) && !isset($options["rm"]) && !isset($options["dx"]) && !isset($options["dy"]) && !isset($options["dz"]);
				}
				$position = $player->getPosition();
				$distanceSquared = ($position->x - $originX) ** 2 + ($position->y - $originY) ** 2 + ($position->z - $originZ) ** 2;
				if(isset($options["r"]) && $distanceSquared > (float) $options["r"] ** 2){
					return false;
				}
				if(isset($options["rm"]) && $distanceSquared < (float) $options["rm"] ** 2){
					return false;
				}

				$axisOrigins = ["x" => $originX, "y" => $originY, "z" => $originZ];
				foreach(["x" => "dx", "y" => "dy", "z" => "dz"] as $axis => $deltaName){
					if(isset($options[$deltaName])){
						$start = $axisOrigins[$axis];
						$end = $start + (float) $options[$deltaName];
						$coordinate = match($axis){
							"x" => $position->x,
							"y" => $position->y,
							"z" => $position->z,
						};
						if($coordinate < min($start, $end) || $coordinate > max($start, $end)){
							return false;
						}
					}
				}
			}
			return true;
		}));

		if($selector === "p" && $origin !== null){
			$selectorOrigin = new Vector3($originX, $originY, $originZ);
			usort($players, static function(Player $a, Player $b) use ($origin, $selectorOrigin) : int{
				$aDistance = $a->getWorld() === $origin->getWorld() ? $a->getPosition()->distanceSquared($selectorOrigin) : PHP_FLOAT_MAX;
				$bDistance = $b->getWorld() === $origin->getWorld() ? $b->getPosition()->distanceSquared($selectorOrigin) : PHP_FLOAT_MAX;
				return $aDistance <=> $bDistance;
			});
		}elseif($selector === "r"){
			shuffle($players);
		}

		$limit = isset($options["c"]) ? (int) $options["c"] : (isset($options["limit"]) ? (int) $options["limit"] : null);
		if($limit === null && ($selector === "p" || $selector === "r")){
			$limit = 1;
		}
		if($limit !== null){
			if($limit < 0){
				$players = array_reverse($players);
				$limit = -$limit;
			}
			$players = array_slice($players, 0, $limit);
		}

		if(count($players) === 0){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_error_playerNotFound($target)->prefix(TextFormat::RED));
			return null;
		}
		return $players;
	}

	/**
	 * @return Player[]|null
	 * @phpstan-return list<Player>|null
	 */
	protected function fetchPermittedPlayerTargets(CommandSender $sender, ?string $target, string $selfPermission, string $otherPermission) : ?array{
		$players = $this->fetchPlayerTargets($sender, $target);
		if($players === null){
			return null;
		}

		$requiresSelfPermission = false;
		$requiresOtherPermission = false;
		foreach($players as $player){
			if($player === $sender){
				$requiresSelfPermission = true;
			}else{
				$requiresOtherPermission = true;
			}
		}
		if($requiresSelfPermission && !$this->testPermission($sender, $selfPermission)){
			return null;
		}
		if($requiresOtherPermission && !$this->testPermission($sender, $otherPermission)){
			return null;
		}
		return $players;
	}

	protected function fetchPermittedPlayerTarget(CommandSender $sender, ?string $target, string $selfPermission, string $otherPermission) : ?Player{
		$players = $this->fetchPermittedPlayerTargets($sender, $target, $selfPermission, $otherPermission);
		if($players === null){
			return null;
		}
		if(count($players) !== 1){
			$sender->sendMessage(TextFormat::RED . "Target selector must match exactly one player");
			return null;
		}
		return $players[0];
	}

	protected function getInteger(CommandSender $sender, string $value, int $min = self::MIN_COORD, int $max = self::MAX_COORD) : int{
		$i = (int) $value;

		if($i < $min){
			$i = $min;
		}elseif($i > $max){
			$i = $max;
		}

		return $i;
	}

	protected function getRelativeDouble(float $original, CommandSender $sender, string $input, float $min = self::MIN_COORD, float $max = self::MAX_COORD) : float{
		if($input[0] === "~"){
			$value = $this->getDouble($sender, substr($input, 1));

			return $original + $value;
		}

		return $this->getDouble($sender, $input, $min, $max);
	}

	protected function getDouble(CommandSender $sender, string $value, float $min = self::MIN_COORD, float $max = self::MAX_COORD) : float{
		$i = (float) $value;

		if($i < $min){
			$i = $min;
		}elseif($i > $max){
			$i = $max;
		}

		return $i;
	}

	protected function getBoundedInt(CommandSender $sender, string $input, int $min, int $max) : ?int{
		if(!is_numeric($input)){
			throw new InvalidCommandSyntaxException();
		}

		$v = (int) $input;
		if($v > $max){
			$sender->sendMessage(KnownTranslationFactory::commands_generic_num_tooBig($input, (string) $max)->prefix(TextFormat::RED));
			return null;
		}
		if($v < $min){
			$sender->sendMessage(KnownTranslationFactory::commands_generic_num_tooSmall($input, (string) $min)->prefix(TextFormat::RED));
			return null;
		}

		return $v;
	}
}
