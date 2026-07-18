<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_| \_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
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

namespace pocketmine\command;

use PHPUnit\Framework\TestCase;
use pocketmine\command\defaults\VanillaCommand;
use pocketmine\network\mcpe\protocol\serializer\AvailableCommandsPacketAssembler;
use pocketmine\network\mcpe\protocol\types\command\CommandData;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;
use function array_values;
use function basename;
use function count;
use function dirname;
use function glob;

class CommandOverloadsTest extends TestCase{

	public function testAllVanillaCommandsBuildValidOverloads() : void{
		$hardcodedEnums = [];
		$softEnums = [];
		$enumConstraints = [];
		$commandData = [];
		$paths = glob(dirname(__DIR__, 3) . "/src/command/defaults/*Command.php");
		self::assertNotFalse($paths);

		foreach($paths as $path){
			$shortName = basename($path, ".php");
			if($shortName === "VanillaCommand"){
				continue;
			}

			$className = "pocketmine\\command\\defaults\\" . $shortName;
			self::assertTrue(is_subclass_of($className, VanillaCommand::class));
			/** @var class-string<VanillaCommand> $className */
			$reflection = new \ReflectionClass($className);
			$command = $reflection->newInstanceWithoutConstructor();
			self::assertNotSame(Command::class, $reflection->getMethod("buildOverloads")->getDeclaringClass()->getName());

			$overloads = $command->buildOverloads($hardcodedEnums, $softEnums, $enumConstraints);
			self::assertNotEmpty($overloads, "$shortName must expose at least one overload");
			foreach($overloads as $overload){
				$optionalParameterFound = false;
				foreach($overload->getParameters() as $parameter){
					if($parameter->isOptional){
						$optionalParameterFound = true;
					}else{
						self::assertFalse($optionalParameterFound, "$shortName has a required parameter after an optional parameter");
					}
				}
			}

			$commandData[] = new CommandData(
				name: "test" . count($commandData),
				description: "",
				flags: 0,
				permission: CommandPermissions::NORMAL,
				aliases: null,
				overloads: $overloads,
				chainedSubCommandData: []
			);
		}

		$packet = AvailableCommandsPacketAssembler::assemble($commandData, array_values($hardcodedEnums), array_values($softEnums));
		foreach($enumConstraints as $enumConstraint){
			$packet->enumConstraints[] = $enumConstraint;
		}

		self::assertCount(42, $commandData);
		self::assertCount(42, $packet->commandData);
	}
}
