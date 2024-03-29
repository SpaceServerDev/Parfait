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

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\TranslationContainer;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use function count;

class TimeCommand extends VanillaCommand{

	public function __construct(string $name){
		parent::__construct(
			$name,
			"%pocketmine.command.time.description",
			"%pocketmine.command.time.usage"
		);
		$this->setPermission("pocketmine.command.time.add;pocketmine.command.time.set;pocketmine.command.time.start;pocketmine.command.time.stop");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		if (count($args) < 1) {
			throw new InvalidCommandSyntaxException();
		}

		if ($args[0] === "start") {
			if (!$sender->hasPermission("pocketmine.command.time.start")) {
				$sender->sendMessage($sender->getServer()->getLanguage()->translateString(TextFormat::RED . "%commands.generic.permission"));

				return true;
			}
			if (!isset($args[1]) or $args[1] === "") {
				$sender->getLevel()->startTime();
				Command::broadcastCommandMessage($sender, "Restarted the time world : " . $sender->getLevel()->getFolderName());
			} else {
				$level = $sender->getServer()->getLevelByName($args[1]);
				if (!isset($level)) {
					$sender->sendMessage("存在しないワールドです");
					return true;
				}
				$level->startTime();
				Command::broadcastCommandMessage($sender, "Restarted the time world : " . $args[1]);
			}
			return true;
		} elseif ($args[0] === "stop") {
			if (!$sender->hasPermission("pocketmine.command.time.stop")) {
				$sender->sendMessage($sender->getServer()->getLanguage()->translateString(TextFormat::RED . "%commands.generic.permission"));

				return true;
			}
			if (!isset($args[1]) or $args[1] === "") {
				$sender->getLevel()->stopTime();
				Command::broadcastCommandMessage($sender, "Stopped the time world : " . $sender->getLevel()->getFolderName());
			} else {
				$level = $sender->getServer()->getLevelByName($args[1]);
				if (!isset($level)) {
					$sender->sendMessage("存在しないワールドです");
					return true;
				}
				$level->stopTime();
				Command::broadcastCommandMessage($sender, "Stopped the time world : " . $args[1]);
			}
			return true;
		} elseif ($args[0] === "query") {
			if (!$sender->hasPermission("pocketmine.command.time.query")) {
				$sender->sendMessage($sender->getServer()->getLanguage()->translateString(TextFormat::RED . "%commands.generic.permission"));

				return true;
			}
			if ($sender instanceof Player) {
				$level = $sender->getLevel();
			} else {
				$level = $sender->getServer()->getDefaultLevel();
			}
			$sender->sendMessage($sender->getServer()->getLanguage()->translateString("commands.time.query", [$level->getTime()]));
			return true;
		}


		if (count($args) < 2) {
			throw new InvalidCommandSyntaxException();
		}

		if ($args[0] === "set") {
			if (!$sender->hasPermission("pocketmine.command.time.set")) {
				$sender->sendMessage($sender->getServer()->getLanguage()->translateString(TextFormat::RED . "%commands.generic.permission"));

				return true;
			}

			if ($args[1] === "day") {
				$args[1] = 25000;
			} elseif ($args[1] === "night") {
				$args[1] = 37000;
			}
			if (!is_numeric($args[1])) {
				$sender->sendMessage("/time set (day/night/数値)");
				return true;
			}
			if (!isset($args[2]) or $args[2] === "") {
				$sender->getLevel()->setTime((int)$args[1]);
				Command::broadcastCommandMessage($sender, "Time set " . $args[1] . " world : " . $sender->getLevel()->getFolderName());
			} else {
				$level = $sender->getServer()->getLevelByName($args[2]);
				if (!isset($level)) {
					$sender->sendMessage("存在しないワールドです");
					return true;
				}
				$level->setTime((int)$args[1]);
				Command::broadcastCommandMessage($sender, "Time set " . $args[1] . " world : " . $args[2]);
			}
		}
	}
}
