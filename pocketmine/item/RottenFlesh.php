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

use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use function lcg_value;

class RottenFlesh extends Food{

	public function __construct(int $meta = 0){
		parent::__construct(self::ROTTEN_FLESH, $meta, "Rotten Flesh");
	}

	public function getFoodRestore() : int{
		return 4;
	}

	public function getSaturationRestore() : float{
		return 0.8;
	}

	public function getAdditionalEffects() : array{
		if(lcg_value() <= 0.8){
			return [
				new EffectInstance(Effect::getEffect(Effect::NAUSEA), 300),
				new EffectInstance(Effect::getEffect(Effect::STRENGTH), 600)
			];
		}

		return [];
	}
}
