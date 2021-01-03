<?php


namespace pocketmine\item;


use pocketmine\item\Armor;

class NetheriteHelmet extends Armor {

	public function __construct(int $meta = 0){
		parent::__construct(ItemIds::NETHERITE_HELMET, $meta, "Netherite Helmet");
	}

	public function getDefensePoints() : int{
		return 3;
	}

	public function getMaxDurability() : int{
		return 408;
	}

	public function getArmorSlot() : int{
		return 0;
	}
}