<?php


namespace pocketmine\item;

use pocketmine\item\Armor;

class NetheriteLeggings extends Armor {
	public function __construct(int $meta = 0){
		parent::__construct(ItemIds::NETHERITE_LEGGINGS, $meta, "Netherite Leggings");
	}

	public function getDefensePoints() : int{
		return 6;
	}

	public function getMaxDurability() : int{
		return 556;
	}

	public function getArmorSlot() : int{
		return 2;
	}
}
