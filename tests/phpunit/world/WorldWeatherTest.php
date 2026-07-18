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

namespace pocketmine\world;

use PHPUnit\Framework\TestCase;

class WorldWeatherTest extends TestCase{

	public function testDisableWeather() : void{
		$reflect = new \ReflectionClass(World::class);
		$world = $reflect->newInstanceWithoutConstructor();
		$reflect->getProperty("rainLevel")->setValue($world, 1.0);
		$reflect->getProperty("lightningLevel")->setValue($world, 1.0);

		$world->disableWeather();

		self::assertFalse($world->isWeatherEnabled());
		self::assertSame(0.0, $world->getRainLevel());
		self::assertSame(0.0, $world->getLightningLevel());

		$world->setRainLevel(1.0);
		$world->setLightningLevel(1.0);
		self::assertSame(0.0, $world->getRainLevel());
		self::assertSame(0.0, $world->getLightningLevel());

		$world->enableWeather();
		self::assertTrue($world->isWeatherEnabled());

		$world->setLightningLevel(1.0);
		self::assertSame(1.0, $world->getRainLevel());
		self::assertSame(1.0, $world->getLightningLevel());
	}
}
