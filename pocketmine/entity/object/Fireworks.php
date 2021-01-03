<?php

declare(strict_types = 1);

namespace pocketmine\entity\object;

use pocketmine\entity\Entity;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\ActorEventPacket;

class Fireworks extends Entity {

	public const NETWORK_ID = Entity::FIREWORKS_ROCKET;

	public const DATA_FIREWORK_ITEM = 16;

	public $width = 0.25;
	public $height = 0.25;

	public function __construct(Level $level, CompoundTag $nbt, ?CompoundTag $fireworks = null){
		parent::__construct($level, $nbt);

		if($fireworks !== null && $fireworks instanceof CompoundTag){
        	$this->propertyManager->setCompoundTag(self::DATA_FIREWORK_ITEM, $fireworks);
        }
	}

	public function entityBaseTick(int $tickDiff = 1): bool {
		if($this->closed) {
			return false;
		}
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if(!$this->isFlaggedForDespawn()) {
			$this->broadcastEntityEvent(ActorEventPacket::FIREWORK_PARTICLES);
			$this->flagForDespawn();
			$hasUpdate = true;
		}
		return $hasUpdate;
	}
}