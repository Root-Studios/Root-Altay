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

namespace pocketmine\entity\utils;

enum ArmorStandPose : int{
	case DEFAULT = 0;
	case NO = 1;
	case SOLEMN = 2;
	case ATHENA = 3;
	case BRANDISH = 4;
	case HONOR = 5;
	case ENTERTAIN = 6;
	case SALUTE = 7;
	case HERO = 8;
	case RIPOSTE = 9;
	case ZOMBIE = 10;
	case CAN_CAN_A = 11;
	case CAN_CAN_B = 12;

	public function next() : self{
		return self::tryFrom($this->value + 1) ?? self::DEFAULT;
	}
}
