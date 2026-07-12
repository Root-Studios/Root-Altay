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

namespace pocketmine\item;

use pocketmine\utils\RegistryTrait;

/**
 * @method static ArmorTrimPattern COAST()
 * @method static ArmorTrimPattern BOLT()
 * @method static ArmorTrimPattern DUNE()
 * @method static ArmorTrimPattern EYE()
 * @method static ArmorTrimPattern FLOW()
 * @method static ArmorTrimPattern HOST()
 * @method static ArmorTrimPattern RAISER()
 * @method static ArmorTrimPattern RIB()
 * @method static ArmorTrimPattern SENTRY()
 * @method static ArmorTrimPattern SHAPER()
 * @method static ArmorTrimPattern SILENCE()
 * @method static ArmorTrimPattern SNOUT()
 * @method static ArmorTrimPattern SPIRE()
 * @method static ArmorTrimPattern TIDE()
 * @method static ArmorTrimPattern VEX()
 * @method static ArmorTrimPattern WARD()
 * @method static ArmorTrimPattern WAYFINDER()
 * @method static ArmorTrimPattern WILD()
 */
final class VanillaArmorTrimPatterns{
	use RegistryTrait;
	private function __construct(){}

	protected static function register(string $name, ArmorTrimPattern $pattern) : void{ self::_registryRegister($name, $pattern); }
	/** @return array<string, ArmorTrimPattern> */
	public static function getAll() : array{ return self::_registryGetAll(); }

	protected static function setup() : void{
		self::register("bolt", new ArmorTrimPattern(VanillaItems::BOLT_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("coast", new ArmorTrimPattern(VanillaItems::COAST_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("dune", new ArmorTrimPattern(VanillaItems::DUNE_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("eye", new ArmorTrimPattern(VanillaItems::EYE_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("flow", new ArmorTrimPattern(VanillaItems::FLOW_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("host", new ArmorTrimPattern(VanillaItems::HOST_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("raiser", new ArmorTrimPattern(VanillaItems::RAISER_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("rib", new ArmorTrimPattern(VanillaItems::RIB_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("sentry", new ArmorTrimPattern(VanillaItems::SENTRY_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("shaper", new ArmorTrimPattern(VanillaItems::SHAPER_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("silence", new ArmorTrimPattern(VanillaItems::SILENCE_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("snout", new ArmorTrimPattern(VanillaItems::SNOUT_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("spire", new ArmorTrimPattern(VanillaItems::SPIRE_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("tide", new ArmorTrimPattern(VanillaItems::TIDE_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("vex", new ArmorTrimPattern(VanillaItems::VEX_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("ward", new ArmorTrimPattern(VanillaItems::WARD_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("wayfinder", new ArmorTrimPattern(VanillaItems::WAYFINDER_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register("wild", new ArmorTrimPattern(VanillaItems::WILD_ARMOR_TRIM_SMITHING_TEMPLATE()));
	}
}
