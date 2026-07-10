<?php

/**
 * .--.  .--.  .--. .---.
 * |   ):    ::    :  |
 * |--' |    ||    |  |
 * |  \ :    ;:    ;  |
 * '   ` `--'  `--'   '
 *       by Valres.
 *
 * FRA:
 * Ce code source est la propriété exclusive de Valres.
 * Toute utilisation, reproduction, modification ou distribution de ce code
 * sans autorisation écrite explicite est strictement interdite.
 *
 * ENG:
 * This source code is the exclusive property of Valres.
 * Any use, reproduction, modification, or distribution of this code
 * without explicit written authorization is strictly prohibited.
 */

declare(strict_types=1);

namespace pocketmine\entity\utils;

enum VillagerProfession: int{
	case ARMORER = 0;
	case BUTCHER = 1;
	case CARTOGRAPHER = 2;
	case CLERIC = 3;
	case FARMER = 4;
	case FISHERMAN = 5;
	case FLETCHER = 6;
	case LEATHERWORKER = 7;
	case LIBRARIAN = 8;
	case MASON = 9;
	case SHEPHERD = 10;
	case TOOLSMITH = 11;
	case WEAPONSMITH = 12;
	case NITWIT = 13;
	case UNEMPLOYED = 14;
}