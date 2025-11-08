<?php

namespace pmmp\ExampleScriptPlugin;

use pocketmine\event\Listener;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\plugin\PluginBase;

/**
 * @main pmmp\ExampleScriptPlugin\Main
 * @api 5.37.0
 * @version 1.0.0
 * @name ExampleScriptPlugin
 */
class Main extends PluginBase{
	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents(new ExampleListener($this->getLogger()), $this);
	}
}

class ExampleListener implements Listener{

	public function __construct(
		private \Logger $logger
	){}

	public function onWorldLoad(WorldLoadEvent $event) : void{
		$this->logger->info("Script plugin detected world " . $event->getWorld()->getDisplayName() . " being loaded!");
	}
}

