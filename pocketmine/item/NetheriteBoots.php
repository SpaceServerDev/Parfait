<?php


namespace pocketmine\item;


use pocketmine\item\Armor;

class NetheriteBoots extends Armor{
	public function __construct(int $meta = 0){
		parent::__construct(ItemIds::NETHERITE_BOOTS, $meta, "netherite Leggings");
	}

	public function getDefensePoints() : int{
		return 6;
	}

	public function getMaxDurability() : int{
		return 482;
	}

	public function getArmorSlot() : int{
		return 2;
	}
}
