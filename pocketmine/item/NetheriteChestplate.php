<?php


namespace pocketmine\item;

use pocketmine\item\Armor;

class NetheriteChestplate extends Armor {

	public function __construct(int $meta = 0){
		parent::__construct(ItemIds::NETHERITE_CHESTPLATE, $meta, "Netherite Chestplate");
	}

	public function getDefensePoints() : int{
		return 8;
	}

	public function getMaxDurability() : int{
		return 593;
	}

	public function getArmorSlot() : int{
		return 1;
	}

}